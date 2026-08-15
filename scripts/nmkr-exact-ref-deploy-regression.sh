#!/usr/bin/env bash
set -Eeuo pipefail

root=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
deploy=$root/scripts/nmkr-exact-ref-deploy.sh
tmp=$(mktemp -d "${TMPDIR:-/tmp}/nmkr-deploy-regression.XXXXXXXX")
trap 'rm -rf -- "$tmp"' EXIT
export GIT_AUTHOR_NAME='Synthetic Test' GIT_AUTHOR_EMAIL='test@example.invalid'
export GIT_COMMITTER_NAME=$GIT_AUTHOR_NAME GIT_COMMITTER_EMAIL=$GIT_AUTHOR_EMAIL

pass=0
check() { if "$@"; then pass=$((pass + 1)); else printf 'Deploy regression failed at assertion %d.\n' "$((pass + 1))" >&2; exit 1; fi; }
test_must_fail() { if "$@"; then return 1; else return 0; fi; }

source_repo=$tmp/source
remote=$tmp/private-remote-sentinel.git
mkdir "$source_repo"
git -C "$source_repo" init -q -b main
mkdir -p "$source_repo"/{includes,css,js,vendor/freemius/wordpress-sdk,tools}
printf '<?php // synthetic\n' >"$source_repo/nmkr-connect.php"
printf '{"name":"synthetic/nmkr-connect"}\n' >"$source_repo/composer.json"
printf '{"packages":[]}\n' >"$source_repo/composer.lock"
printf 'placeholder\n' >"$source_repo/includes/readme"
printf 'placeholder\n' >"$source_repo/css/readme"
printf 'placeholder\n' >"$source_repo/js/readme"
cat >"$source_repo/tools/tracked-executable" <<'EOF'
#!/usr/bin/env sh
exit 0
EOF
chmod 0755 "$source_repo/tools/tracked-executable"
git -C "$source_repo" add .
git -C "$source_repo" commit -qm 'synthetic deployment fixture'
sha=$(git -C "$source_repo" rev-parse HEAD)
git init -q --bare "$remote"
git -C "$source_repo" remote add origin "$remote"
git -C "$source_repo" push -q origin main
git -C "$source_repo" push -q origin HEAD:refs/pull/123/head

bin=$tmp/bin
mkdir "$bin"
cat >"$bin/composer-sentinel" <<'EOF'
#!/usr/bin/env bash
set -eu
printf 'composer\n' >>"$TEST_STATE/composer-calls"
if [[ "${TEST_BLOCK_COMPOSER:-false}" == true ]]; then
  : >"$TEST_STATE/composer-blocked"
  read -r _ <"$TEST_STATE/composer-release"
fi
[[ "${TEST_BECOME_ACTIVE_AFTER_BUILD:-false}" != true ]] || : >"$TEST_STATE/sync-active"
printf 'PRIVATE_SUBPROCESS_DIAGNOSTIC_SENTINEL\n' >&2
[[ "${TEST_COMPOSER_FAIL:-false}" != true ]] || exit 9
mkdir -p vendor/freemius/wordpress-sdk
printf '<?php // generated synthetic autoloader\n' >vendor/autoload.php
if [[ "${TEST_MISSING_BUILD:-false}" != true ]]; then
  printf '<?php // generated synthetic SDK entry\n' >vendor/freemius/wordpress-sdk/start.php
fi
if [[ -n "${TEST_PREP_HIDDEN_FLAG:-}" ]]; then
  git update-index "--${TEST_PREP_HIDDEN_FLAG}" nmkr-connect.php
  printf 'hidden preparation change\n' >>nmkr-connect.php
fi
EOF
cat >"$bin/wp-sentinel" <<'EOF'
#!/usr/bin/env bash
set -eu
printf 'PRIVATE_WP_DIAGNOSTIC_SENTINEL\n' >&2
while [[ "${1:-}" == --path=* ]]; do shift; done
case "${1:-} ${2:-}" in
  'core is-installed') exit 0 ;;
  'eval '*)
    if [[ "${2:-}" == *'require_once ABSPATH'* ]]; then
      if [[ "${TEST_FINAL_DIRTY:-false}" == true && -e "$TEST_STATE/activated" && ! -e "$TEST_STATE/dirtied" ]]; then
        printf 'dirty\n' >>"$NMKR_DEPLOYED_PLUGIN_PATH/nmkr-connect.php"; : >"$TEST_STATE/dirtied"
      fi
      if [[ -n "${TEST_BOUND_PATH:-}" ]]; then printf '%s' "$TEST_BOUND_PATH"; else printf '%s/nmkr-connect.php' "$(realpath -e "$NMKR_DEPLOYED_PLUGIN_PATH")"; fi
    else
      [[ "${TEST_SYNC_ACTIVE:-false}" != true && ! -e "$TEST_STATE/sync-active" ]] || exit 25
      printf synthetic-idle-digest
    fi
    ;;
  'plugin activate')
    if [[ "${TEST_ACTIVATE_FAIL_ONCE:-false}" == true && ! -e "$TEST_STATE/activation-failed" ]]; then
      : >"$TEST_STATE/activation-failed"; exit 8
    fi
    : >"$TEST_STATE/active"
    : >"$TEST_STATE/activated"
    if [[ "${TEST_BLOCK_ACTIVATION:-false}" == true && ! -e "$TEST_STATE/activation-blocked" ]]; then
      : >"$TEST_STATE/activation-blocked"
      read -r _ <"$TEST_STATE/activation-release"
      : >"$TEST_STATE/activation-released"
    fi
    if [[ "${TEST_BLOCK_ROLLBACK_ACTIVATION:-false}" == true && -e "$TEST_STATE/activation-failed" && ! -e "$TEST_STATE/rollback-activation-blocked" ]]; then
      : >"$TEST_STATE/rollback-activation-blocked"
      read -r _ <"$TEST_STATE/rollback-activation-release"
      : >"$TEST_STATE/rollback-activation-released"
    fi
    if [[ -n "${TEST_FINAL_HIDDEN_FLAG:-}" && -d "$NMKR_DEPLOYED_PLUGIN_PATH/.git" && ! -e "$TEST_STATE/hidden-final" ]]; then
      git -C "$NMKR_DEPLOYED_PLUGIN_PATH" update-index "--${TEST_FINAL_HIDDEN_FLAG}" nmkr-connect.php
      printf 'hidden activated change\n' >>"$NMKR_DEPLOYED_PLUGIN_PATH/nmkr-connect.php"
      : >"$TEST_STATE/hidden-final"
    fi
    ;;
  'plugin is-active')
    [[ -e "$TEST_STATE/active" ]] || exit 1
    if [[ "${TEST_FINAL_DIRTY:-false}" == true && -d "$NMKR_DEPLOYED_PLUGIN_PATH/.git" && ! -e "$TEST_STATE/dirtied" ]]; then
      printf 'dirty\n' >>"$NMKR_DEPLOYED_PLUGIN_PATH/nmkr-connect.php"
      : >"$TEST_STATE/dirtied"
    fi
    ;;
  *) exit 2 ;;
esac
EOF
cat >"$bin/mv" <<'EOF'
#!/usr/bin/env bash
set -eu
args=("$@"); count=${#args[@]}; src=${args[$((count-2))]}; dst=${args[$((count-1))]}
if [[ "$src" == "$NMKR_DEPLOYED_PLUGIN_PATH" && "${TEST_FAIL_FIRST_MOVE:-false}" == true ]]; then exit 7; fi
if [[ "$src" == */.nmkr-candidate.* && "${TEST_FAIL_SECOND_MOVE:-false}" == true ]]; then exit 7; fi
if [[ "$src" == */.nmkr-previous.* && "${TEST_FAIL_RESTORE_MOVE:-false}" == true ]]; then exit 7; fi
printf '%s\n%s\n' "$src" "$dst" >>"$TEST_STATE/move-paths"
exec /bin/mv "$@"
EOF
cat >"$bin/rm" <<'EOF'
#!/usr/bin/env bash
set -eu
target=${!#}
if [[ "$target" == */.nmkr-previous.* && "${TEST_BLOCK_PREVIOUS_DELETE:-false}" == true ]]; then
  : >"$TEST_STATE/previous-delete-blocked"
  read -r _ <"$TEST_STATE/previous-delete-release"
  : >"$TEST_STATE/previous-delete-released"
fi
exec /bin/rm "$@"
EOF
chmod +x "$bin/composer-sentinel" "$bin/wp-sentinel" "$bin/mv" "$bin/rm"

new_case() {
  case_root=$(mktemp -d "$tmp/case.XXXXXXXX")
  export TEST_STATE=$case_root/state
  export NMKR_DEPLOY_REMOTE=$remote
  export NMKR_DEPLOYED_PLUGIN_PATH=$case_root/plugins/nmkr-connect
  export NMKR_DEPLOY_BACKUP_ROOT=$case_root/private-backup-sentinel
  export WP_PATH=$case_root/private-wordpress-sentinel
  export WP_CLI_BIN=$bin/wp-sentinel COMPOSER_BIN=$bin/composer-sentinel
  export PATH=$bin:$ORIGINAL_PATH
  unset TEST_SYNC_ACTIVE TEST_BECOME_ACTIVE_AFTER_BUILD TEST_COMPOSER_FAIL TEST_MISSING_BUILD TEST_ACTIVATE_FAIL_ONCE TEST_FINAL_DIRTY TEST_BOUND_PATH TEST_FAIL_FIRST_MOVE TEST_FAIL_SECOND_MOVE TEST_FAIL_RESTORE_MOVE TEST_BLOCK_COMPOSER TEST_BLOCK_ACTIVATION TEST_BLOCK_ROLLBACK_ACTIVATION TEST_BLOCK_PREVIOUS_DELETE TEST_PREP_HIDDEN_FLAG TEST_FINAL_HIDDEN_FLAG
  mkdir -p "$TEST_STATE" "$NMKR_DEPLOYED_PLUGIN_PATH" "$NMKR_DEPLOY_BACKUP_ROOT" "$WP_PATH"
  printf 'original\n' >"$NMKR_DEPLOYED_PLUGIN_PATH/original-marker"
  printf '<?php // original synthetic\n' >"$NMKR_DEPLOYED_PLUGIN_PATH/nmkr-connect.php"
  : >"$TEST_STATE/active"
  output=$case_root/output
}
run_deploy() { bash "$deploy" --ref "$1" --expected-sha "$2" >"$output" 2>&1; }
start_deploy() {
  setsid bash "$deploy" --ref "$1" --expected-sha "$2" >"$output" 2>&1 &
  deploy_pid=$!
}
wait_for_marker() {
  local marker=$1 count=0
  while [[ ! -e "$marker" && $count -lt 100 ]]; do sleep 0.02; count=$((count + 1)); done
  [[ -e "$marker" ]]
}
signal_deploy() {
  local signal=$1 status=0
  kill -s "$signal" -- "-$deploy_pid"
  wait "$deploy_pid" || status=$?
  [[ $status -ne 0 ]]
}
unchanged() { [[ -f "$NMKR_DEPLOYED_PLUGIN_PATH/original-marker" ]]; }
one_backup() { [[ $(find "$NMKR_DEPLOY_BACKUP_ROOT" -maxdepth 1 -type f -name '*.tar' | wc -l) -eq 1 ]]; }
no_leaks() {
  ! grep -E -q -- 'private-remote-sentinel|private-backup-sentinel|private-wordpress-sentinel|PRIVATE_(WP|SUBPROCESS)_DIAGNOSTIC_SENTINEL' "$output"
}
no_success() { ! grep -E -q -- '^Exact-ref deployment succeeded\.$' "$output"; }
ORIGINAL_PATH=$PATH

new_case
check run_deploy refs/pull/123/head "$sha"
check test "$(git -C "$NMKR_DEPLOYED_PLUGIN_PATH" rev-parse HEAD)" = "$sha"
check test -z "$(git -C "$NMKR_DEPLOYED_PLUGIN_PATH" status --porcelain --untracked-files=no)"
check test "$(stat -c %a "$NMKR_DEPLOYED_PLUGIN_PATH/tools/tracked-executable")" = 755
check one_backup
check no_leaks

new_case
check run_deploy refs/heads/main "${sha^^}"
check test -e "$NMKR_DEPLOYED_PLUGIN_PATH/vendor/autoload.php"
check no_leaks

new_case
check test_must_fail run_deploy refs/heads/main invalid
check test_must_fail run_deploy refs/tags/main "$sha"
check unchanged

new_case
wrong=0000000000000000000000000000000000000000
check test_must_fail run_deploy refs/heads/main "$wrong"
check unchanged
check no_leaks

new_case
export TEST_SYNC_ACTIVE=true
check test_must_fail run_deploy refs/heads/main "$sha"
check unchanged
check test ! -e "$TEST_STATE/composer-calls"

new_case
export TEST_BECOME_ACTIVE_AFTER_BUILD=true
check test_must_fail run_deploy refs/heads/main "$sha"
check unchanged
check one_backup

new_case
export TEST_BOUND_PATH=$case_root/plugins/other/nmkr-connect.php
check test_must_fail run_deploy refs/heads/main "$sha"
check unchanged
check one_backup

new_case
export TEST_FAIL_FIRST_MOVE=true
check test_must_fail run_deploy refs/heads/main "$sha"
check unchanged

new_case
export TEST_FAIL_SECOND_MOVE=true
check test_must_fail run_deploy refs/heads/main "$sha"
check unchanged
check one_backup

new_case
export TEST_ACTIVATE_FAIL_ONCE=true
check test_must_fail run_deploy refs/heads/main "$sha"
check unchanged
check test -e "$TEST_STATE/active"

new_case
export TEST_FAIL_SECOND_MOVE=true TEST_FAIL_RESTORE_MOVE=true
check test_must_fail run_deploy refs/heads/main "$sha"
check test ! -e "$NMKR_DEPLOYED_PLUGIN_PATH"
check test "$(find "${NMKR_DEPLOYED_PLUGIN_PATH%/*}" -maxdepth 1 -type d -name '.nmkr-previous.*' | wc -l)" -eq 1

new_case
check run_deploy refs/heads/main "$sha"
check awk -v parent="${NMKR_DEPLOYED_PLUGIN_PATH%/*}" 'index($0,parent "/.nmkr-")==1 || $0==parent "/nmkr-connect" {next} {exit 1}' "$TEST_STATE/move-paths"

new_case
export TEST_COMPOSER_FAIL=true
check test_must_fail run_deploy refs/heads/main "$sha"
check unchanged
check test "$(find "$NMKR_DEPLOY_BACKUP_ROOT" -name '*.tar' | wc -l)" -eq 0
check no_leaks

new_case
export TEST_BLOCK_COMPOSER=true
mkfifo "$TEST_STATE/composer-release"
start_deploy refs/heads/main "$sha"
check wait_for_marker "$TEST_STATE/composer-blocked"
check signal_deploy TERM
check unchanged
check test "$(find "${NMKR_DEPLOYED_PLUGIN_PATH%/*}" -maxdepth 1 -type d -name '.nmkr-previous.*' | wc -l)" -eq 0
check no_leaks

new_case
export TEST_BLOCK_ACTIVATION=true
mkfifo "$TEST_STATE/activation-release"
start_deploy refs/heads/main "$sha"
check wait_for_marker "$TEST_STATE/activation-blocked"
check signal_deploy TERM
check unchanged
check test "$(find "${NMKR_DEPLOYED_PLUGIN_PATH%/*}" -maxdepth 1 -type d -name '.nmkr-previous.*' | wc -l)" -eq 0
check no_leaks

for deployment_signal in INT TERM; do
  new_case
  export TEST_ACTIVATE_FAIL_ONCE=true TEST_BLOCK_ROLLBACK_ACTIVATION=true
  mkfifo "$TEST_STATE/rollback-activation-release"
  start_deploy refs/heads/main "$sha"
  check wait_for_marker "$TEST_STATE/rollback-activation-blocked"
  check kill -s "$deployment_signal" -- "-$deploy_pid"
  printf 'release\n' >"$TEST_STATE/rollback-activation-release"
  rollback_status=0; wait "$deploy_pid" || rollback_status=$?
  check test "$rollback_status" -ne 0
  check unchanged
  check test -e "$TEST_STATE/rollback-activation-released"
  check test "$(find "${NMKR_DEPLOYED_PLUGIN_PATH%/*}" -maxdepth 1 -type d -name '.nmkr-previous.*' | wc -l)" -eq 0
  check grep -E -q -- '^Exact-ref deployment failed; rollback succeeded\.$' "$output"
  check no_success
  check no_leaks
done

for deployment_signal in INT TERM; do
  new_case
  export TEST_BLOCK_PREVIOUS_DELETE=true
  mkfifo "$TEST_STATE/previous-delete-release"
  start_deploy refs/heads/main "$sha"
  check wait_for_marker "$TEST_STATE/previous-delete-blocked"
  check kill -s "$deployment_signal" -- "-$deploy_pid"
  printf 'release\n' >"$TEST_STATE/previous-delete-release"
  cleanup_status=0; wait "$deploy_pid" || cleanup_status=$?
  check test "$cleanup_status" -eq 0
  check test "$(git -C "$NMKR_DEPLOYED_PLUGIN_PATH" rev-parse HEAD)" = "$sha"
  check test -z "$(git -C "$NMKR_DEPLOYED_PLUGIN_PATH" -c core.fileMode=true status --porcelain --untracked-files=no)"
  check test ! -e "$NMKR_DEPLOYED_PLUGIN_PATH/original-marker"
  check test -e "$TEST_STATE/previous-delete-released"
  check test "$(find "${NMKR_DEPLOYED_PLUGIN_PATH%/*}" -maxdepth 1 -type d -name '.nmkr-previous.*' | wc -l)" -eq 0
  check grep -E -q -- '^Exact-ref deployment succeeded\.$' "$output"
  check no_leaks
done

for hidden_flag in assume-unchanged skip-worktree; do
  new_case
  export TEST_PREP_HIDDEN_FLAG=$hidden_flag
  check test_must_fail run_deploy refs/heads/main "$sha"
  check unchanged
  check test "$(find "$NMKR_DEPLOY_BACKUP_ROOT" -name '*.tar' | wc -l)" -eq 0
done

new_case
export TEST_FINAL_HIDDEN_FLAG=assume-unchanged
check test_must_fail run_deploy refs/heads/main "$sha"
check unchanged
check one_backup
check no_success
check no_leaks

new_case
export TEST_MISSING_BUILD=true
check test_must_fail run_deploy refs/heads/main "$sha"
check unchanged

new_case
export TEST_FINAL_DIRTY=true
check test_must_fail run_deploy refs/heads/main "$sha"
check unchanged
check one_backup
check no_leaks

printf 'Exact-ref deployment regression: PASS (%d assertions)\n' "$pass"
