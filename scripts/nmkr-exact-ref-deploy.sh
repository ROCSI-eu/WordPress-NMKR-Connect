#!/usr/bin/env bash
set -Eeuo pipefail
set +x
exec 2>/dev/null

fail() {
  printf 'Exact-ref deployment failed.\n'
  exit 1
}

ref=
expected_sha=
seen_ref=false
seen_sha=false
while (( $# )); do
  case "$1" in
    --ref)
      $seen_ref && fail
      (( $# >= 2 )) || fail
      ref=$2; seen_ref=true; shift 2
      ;;
    --expected-sha)
      $seen_sha && fail
      (( $# >= 2 )) || fail
      expected_sha=$2; seen_sha=true; shift 2
      ;;
    *) fail ;;
  esac
done
$seen_ref && $seen_sha || fail
[[ "$expected_sha" =~ ^[0-9A-Fa-f]{40}$ ]] || fail
expected_sha=${expected_sha,,}
if [[ "$ref" =~ ^refs/pull/([1-9][0-9]*)/head$ ]]; then
  :
elif [[ "$ref" =~ ^refs/heads/[A-Za-z0-9][A-Za-z0-9._-]*(/[A-Za-z0-9][A-Za-z0-9._-]*)*$ ]] &&
     [[ "$ref" != *'..'* && "$ref" != *'.lock' && "$ref" != *'.' ]]; then
  :
else
  fail
fi

for name in NMKR_DEPLOY_REMOTE NMKR_DEPLOYED_PLUGIN_PATH NMKR_DEPLOY_BACKUP_ROOT WP_PATH; do
  [[ -n "${!name:-}" ]] || fail
done
WP_CLI_BIN=${WP_CLI_BIN:-wp}
COMPOSER_BIN=${COMPOSER_BIN:-composer}
for command_name in git tar flock mktemp cp mv realpath "$WP_CLI_BIN" "$COMPOSER_BIN"; do
  command -v "$command_name" >/dev/null 2>&1 || fail
done

live=$NMKR_DEPLOYED_PLUGIN_PATH
backup_root=$NMKR_DEPLOY_BACKUP_ROOT
[[ "$live" == /* && "$backup_root" == /* && "$WP_PATH" == /* ]] || fail
[[ "$live" != / && "$backup_root" != / ]] || fail
[[ ! -L "$live" && ! -L "$backup_root" && -d "$live" && -d "$backup_root" ]] || fail
live_parent=${live%/*}; [[ -n "$live_parent" ]] || live_parent=/
[[ -d "$live_parent" && -w "$live_parent" && -w "$backup_root" ]] || fail
case "$backup_root/" in "$live/"*) fail ;; esac

exec 9>"$backup_root/.nmkr-exact-ref-deploy.lock" || fail
flock -n 9 || fail
work=$(mktemp -d "${TMPDIR:-/tmp}/nmkr-exact-ref.XXXXXXXX") || fail
staged=
displaced=
original_displaced=false
candidate_installed=false
preserve_displaced=false
restoration_in_progress=false
backup_temp=
pending_signal=
signal_handling=false
cleanup() {
  [[ -z "$staged" || ! -e "$staged" ]] || rm -rf -- "$staged"
  [[ -z "$backup_temp" || ! -e "$backup_temp" ]] || rm -f -- "$backup_temp"
  rm -rf -- "$work"
}
trap cleanup EXIT

wp_quiet() { "$WP_CLI_BIN" --path="$WP_PATH" "$@" >/dev/null 2>&1; }
sync_is_idle() {
  NMKR_PLUGIN_SLUG=nmkr-connect/nmkr-connect.php WP_PATH="$WP_PATH" WP_CLI_BIN="$WP_CLI_BIN" \
    "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/nmkr-wpcli-ajax-security-state.sh" idle >/dev/null 2>&1
}
active_plugin_is_bound() {
  local deployed_root expected_file active_file
  [[ ! -L "$live" ]] || return 1
  deployed_root=$(realpath -e -- "$live" 2>/dev/null) || return 1
  [[ -d "$deployed_root" ]] || return 1
  expected_file=$deployed_root/nmkr-connect.php
  [[ -f "$expected_file" && ! -L "$expected_file" ]] || return 1
  [[ "$(realpath -e -- "$expected_file" 2>/dev/null)" == "$expected_file" ]] || return 1
  active_file=$(NMKR_PLUGIN_SLUG=nmkr-connect/nmkr-connect.php "$WP_CLI_BIN" --path="$WP_PATH" eval 'require_once ABSPATH . "wp-admin/includes/plugin.php"; $slug = getenv("NMKR_PLUGIN_SLUG"); if ($slug !== "nmkr-connect/nmkr-connect.php" || !is_plugin_active($slug)) { exit(1); } $file = realpath(WP_PLUGIN_DIR . "/" . $slug); if ($file === false || !is_file($file)) { exit(1); } echo $file;' 2>/dev/null) || return 1
  [[ "$active_file" == "$expected_file" ]]
}
git_head_and_clean() {
  local candidate=$1 head status index_entry
  head=$(git -C "$candidate" rev-parse HEAD 2>/dev/null) || return 1
  [[ "$head" == "$expected_sha" ]] || return 1
  status=$(git -C "$candidate" -c core.fileMode=true status --porcelain --untracked-files=no 2>/dev/null) || return 1
  [[ -z "$status" ]] || return 1
  git -C "$candidate" ls-files -v -z 2>/dev/null |
    while IFS= read -r -d '' index_entry; do
      [[ "${index_entry:0:1}" != S && "${index_entry:0:1}" != [a-z] ]] || exit 1
    done
}
candidate_is_valid() {
  local candidate=$1 item file
  git_head_and_clean "$candidate" || return 1
  for item in "${required[@]}"; do [[ -e "$candidate/$item" ]] || return 1; done
  while IFS= read -r -d '' file; do
    [[ -x "$candidate/$file" ]] || return 1
  done < <(git -C "$candidate" ls-files -z --stage | awk -v RS='\0' -F '[ \t]+' '$1 == "100755" { sub(/^[^\t]*\t/, ""); printf "%s%c", $0, 0 }')
}

defer_signal() {
  [[ -n "$pending_signal" ]] || pending_signal=$1
}
run_protected() {
  local child status=0
  if $signal_handling; then
    trap '' INT TERM
    "$@"
    return
  fi
  pending_signal=
  trap 'defer_signal INT' INT
  trap 'defer_signal TERM' TERM
  ( trap '' INT TERM; "$@" ) &
  child=$!
  while true; do
    if wait "$child"; then
      status=0
      break
    else
      status=$?
      kill -0 "$child" 2>/dev/null || break
    fi
  done
  trap 'handle_signal INT' INT
  trap 'handle_signal TERM' TERM
  if [[ -n "$pending_signal" ]]; then
    local replay=$pending_signal
    pending_signal=
    handle_signal "$replay"
  fi
  return "$status"
}
restoration_transaction() {
  local can_replace_live=false
  [[ -n "$displaced" && -d "$displaced" && ! -L "$displaced" ]] || return 1
  if [[ ! -e "$live" ]]; then
    can_replace_live=true
  elif $candidate_installed || [[ -n "$staged" && ! -e "$staged" ]]; then
    # The candidate sibling has completed its rename. The displaced sibling is
    # therefore the recoverable previous tree even if the following flag write
    # was interrupted.
    rm -rf -- "$live" || return 1
    can_replace_live=true
  fi
  if $can_replace_live && [[ ! -e "$live" ]]; then
    if mv -- "$displaced" "$live" >/dev/null 2>&1; then
      wp_quiet plugin activate nmkr-connect/nmkr-connect.php && active_plugin_is_bound
      return
    fi
  fi
  return 1
}
restore_previous() {
  local restored=false
  restoration_in_progress=true
  if run_protected restoration_transaction; then restored=true; fi
  if $restored; then
    candidate_installed=false
    original_displaced=false; restoration_in_progress=false; displaced=
  else
    preserve_displaced=true
  fi
  $restored
}
handle_signal() {
  local signal=$1 status=143
  [[ "$signal" == INT ]] && status=130
  $signal_handling && return
  signal_handling=true
  trap '' INT TERM
  if [[ -n "$displaced" && -e "$displaced" ]]; then
    if restore_previous; then
      printf 'Exact-ref deployment interrupted; rollback succeeded.\n'
    else
      preserve_displaced=true
      printf 'Exact-ref deployment interrupted; rollback failed.\n'
    fi
  else
    printf 'Exact-ref deployment interrupted.\n'
  fi
  exit "$status"
}
trap 'handle_signal INT' INT
trap 'handle_signal TERM' TERM

sync_is_idle || fail
repo=$work/repository
mkdir "$repo"
git -C "$repo" init -q >/dev/null 2>&1 || fail
git -C "$repo" -c protocol.file.allow=always fetch -q --no-tags --depth=1 "$NMKR_DEPLOY_REMOTE" "+$ref:refs/nmkr-selected" >/dev/null 2>&1 || fail
resolved=$(git -C "$repo" rev-parse --verify 'refs/nmkr-selected^{commit}' 2>/dev/null) || fail
[[ "${resolved,,}" == "$expected_sha" ]] || fail
git -C "$repo" -c advice.detachedHead=false checkout -q --detach "$expected_sha" >/dev/null 2>&1 || fail

(cd "$repo" && "$COMPOSER_BIN" install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader) >/dev/null 2>&1 || fail
required=(nmkr-connect.php composer.json composer.lock includes css js vendor/autoload.php vendor/freemius/wordpress-sdk/start.php)
for item in "${required[@]}"; do [[ -e "$repo/$item" ]] || fail; done

find "$repo" -type d -exec chmod 0755 {} + >/dev/null 2>&1 || fail
find "$repo" -type f -exec chmod 0644 {} + >/dev/null 2>&1 || fail
while IFS= read -r -d '' file; do chmod 0755 "$repo/$file" || fail; done < <(git -C "$repo" ls-files -z --stage | awk -v RS='\0' -F '[ \t]+' '$1 == "100755" { sub(/^[^\t]*\t/, ""); printf "%s%c", $0, 0 }')
candidate_is_valid "$repo" || fail

stamp=$(date -u +%Y%m%dT%H%M%SZ)-$$
backup="$backup_root/nmkr-connect-$stamp.tar"
backup_temp=$(mktemp "$backup_root/.nmkr-connect-$stamp.XXXXXXXX.tmp") || fail
if ! tar -C "$live_parent" -cpf "$backup_temp" -- "${live##*/}" >/dev/null 2>&1; then
  rm -f -- "$backup_temp"
  backup_temp=
  fail
fi
mv -- "$backup_temp" "$backup" >/dev/null 2>&1 || fail
backup_temp=
staged=$(mktemp -d "$live_parent/.nmkr-candidate.XXXXXXXX") || fail
cp -a -- "$repo/." "$staged/" >/dev/null 2>&1 || fail
candidate_is_valid "$staged" || fail
displaced=$(mktemp -d "$live_parent/.nmkr-previous.XXXXXXXX") || fail
rmdir -- "$displaced" >/dev/null 2>&1 || fail

active_plugin_is_bound || fail
sync_is_idle || fail
if ! mv -- "$live" "$displaced" >/dev/null 2>&1; then
  printf 'Exact-ref deployment failed.\n'
  exit 1
fi
original_displaced=true
if ! mv -- "$staged" "$live" >/dev/null 2>&1; then
  if mv -- "$displaced" "$live" >/dev/null 2>&1; then
    original_displaced=false; displaced=
    printf 'Exact-ref deployment failed; rollback succeeded.\n'
  else
    preserve_displaced=true
    printf 'Exact-ref deployment failed; rollback failed.\n'
  fi
  exit 1
fi
candidate_installed=true; staged=

rollback() {
  if ! restore_previous; then
    preserve_displaced=true
    printf 'Exact-ref deployment failed; rollback failed.\n'
    exit 1
  fi
  printf 'Exact-ref deployment failed; rollback succeeded.\n'
  exit 1
}

wp_quiet plugin activate nmkr-connect/nmkr-connect.php || rollback
active_plugin_is_bound || rollback
candidate_is_valid "$live" || rollback
active_plugin_is_bound || rollback

if run_protected rm -rf -- "$displaced"; then
  displaced=; original_displaced=false
else
  preserve_displaced=true
  fail
fi
printf 'Exact-ref deployment succeeded.\n'
