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
for command_name in git tar flock mktemp "$WP_CLI_BIN" "$COMPOSER_BIN"; do
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
displaced=
cleanup() {
  [[ -z "$displaced" || ! -e "$displaced" ]] || rm -rf -- "$displaced"
  rm -rf -- "$work"
}
trap cleanup EXIT

wp_quiet() { "$WP_CLI_BIN" --path="$WP_PATH" "$@" >/dev/null 2>&1; }
sync_is_idle() {
  local answer
  answer=$("$WP_CLI_BIN" --path="$WP_PATH" eval '
    $truthy = static function ($value) { return !empty($value); };
    $owner = get_option("nmkr_sync_owner", false);
    $data = get_option("nmkr_sync_data", false);
    $active_data = is_array($data) && in_array(($data["status"] ?? ""), array("initializing", "queued", "running", "stop_requested", "finalizing"), true);
    if (!$truthy(get_option("nmkr_sync_in_progress", false)) && !$truthy(get_transient("nmkr_sync_in_progress")) && empty($owner) && !$active_data) { echo "NMKR_IDLE"; }
  ' 2>/dev/null) || return 1
  [[ "$answer" == NMKR_IDLE ]]
}

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
[[ "$(git -C "$repo" rev-parse HEAD 2>/dev/null)" == "$expected_sha" ]] || fail
[[ -z "$(git -C "$repo" status --porcelain --untracked-files=no 2>/dev/null)" ]] || fail

sync_is_idle || fail
stamp=$(date -u +%Y%m%dT%H%M%SZ)-$$
backup="$backup_root/nmkr-connect-$stamp.tar"
tar -C "$live_parent" -cpf "$backup" -- "${live##*/}" >/dev/null 2>&1 || fail
displaced=$work/previous-plugin
if ! mv -- "$live" "$displaced" >/dev/null 2>&1 || ! mv -- "$repo" "$live" >/dev/null 2>&1; then
  rm -rf -- "$live"
  mv -- "$displaced" "$live" >/dev/null 2>&1 || true
  displaced=
  printf 'Exact-ref deployment failed; rollback attempted.\n'
  exit 1
fi

rollback() {
  rm -rf -- "$live"
  if mv -- "$displaced" "$live" >/dev/null 2>&1 && wp_quiet plugin activate nmkr-connect && wp_quiet plugin is-active nmkr-connect; then
    displaced=
    printf 'Exact-ref deployment failed; rollback succeeded.\n'
  else
    printf 'Exact-ref deployment failed; rollback failed.\n'
  fi
  exit 1
}

wp_quiet plugin activate nmkr-connect || rollback
wp_quiet plugin is-active nmkr-connect || rollback
[[ "$(git -C "$live" rev-parse HEAD 2>/dev/null)" == "$expected_sha" ]] || rollback
[[ -z "$(git -C "$live" status --porcelain --untracked-files=no 2>/dev/null)" ]] || rollback
for item in "${required[@]}"; do [[ -e "$live/$item" ]] || rollback; done

rm -rf -- "$displaced"; displaced=
printf 'Exact-ref deployment succeeded.\n'
