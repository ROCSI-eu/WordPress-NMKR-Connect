#!/usr/bin/env bash
set -Eeuo pipefail

root=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
deploy=$root/scripts/nmkr-exact-ref-deploy.sh
tmp=$(mktemp -d "${TMPDIR:-/tmp}/nmkr-deploy-regression.XXXXXXXX")
trap 'rm -rf -- "$tmp"' EXIT
export GIT_AUTHOR_NAME='Synthetic Test' GIT_AUTHOR_EMAIL='test@example.invalid'
export GIT_COMMITTER_NAME=$GIT_AUTHOR_NAME GIT_COMMITTER_EMAIL=$GIT_AUTHOR_EMAIL

pass=0
check() { if "$@"; then pass=$((pass + 1)); else printf 'Deploy regression failed.\n' >&2; exit 1; fi; }
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
printf 'PRIVATE_SUBPROCESS_DIAGNOSTIC_SENTINEL\n' >&2
[[ "${TEST_COMPOSER_FAIL:-false}" != true ]] || exit 9
mkdir -p vendor/freemius/wordpress-sdk
printf '<?php // generated synthetic autoloader\n' >vendor/autoload.php
if [[ "${TEST_MISSING_BUILD:-false}" != true ]]; then
  printf '<?php // generated synthetic SDK entry\n' >vendor/freemius/wordpress-sdk/start.php
fi
EOF
cat >"$bin/wp-sentinel" <<'EOF'
#!/usr/bin/env bash
set -eu
printf 'PRIVATE_WP_DIAGNOSTIC_SENTINEL\n' >&2
while [[ "${1:-}" == --path=* ]]; do shift; done
case "${1:-} ${2:-}" in
  'eval '*) [[ "${TEST_SYNC_ACTIVE:-false}" != true ]] && printf NMKR_IDLE ;;
  'plugin activate')
    if [[ "${TEST_ACTIVATE_FAIL_ONCE:-false}" == true && ! -e "$TEST_STATE/activation-failed" ]]; then
      : >"$TEST_STATE/activation-failed"; exit 8
    fi
    : >"$TEST_STATE/active"
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
chmod +x "$bin/composer-sentinel" "$bin/wp-sentinel"

new_case() {
  case_root=$(mktemp -d "$tmp/case.XXXXXXXX")
  export TEST_STATE=$case_root/state
  export NMKR_DEPLOY_REMOTE=$remote
  export NMKR_DEPLOYED_PLUGIN_PATH=$case_root/plugins/nmkr-connect
  export NMKR_DEPLOY_BACKUP_ROOT=$case_root/private-backup-sentinel
  export WP_PATH=$case_root/private-wordpress-sentinel
  export WP_CLI_BIN=$bin/wp-sentinel COMPOSER_BIN=$bin/composer-sentinel
  unset TEST_SYNC_ACTIVE TEST_COMPOSER_FAIL TEST_MISSING_BUILD TEST_ACTIVATE_FAIL_ONCE TEST_FINAL_DIRTY
  mkdir -p "$TEST_STATE" "$NMKR_DEPLOYED_PLUGIN_PATH" "$NMKR_DEPLOY_BACKUP_ROOT" "$WP_PATH"
  printf 'original\n' >"$NMKR_DEPLOYED_PLUGIN_PATH/original-marker"
  output=$case_root/output
}
run_deploy() { bash "$deploy" --ref "$1" --expected-sha "$2" >"$output" 2>&1; }
unchanged() { [[ -f "$NMKR_DEPLOYED_PLUGIN_PATH/original-marker" ]]; }
one_backup() { [[ $(find "$NMKR_DEPLOY_BACKUP_ROOT" -maxdepth 1 -type f -name '*.tar' | wc -l) -eq 1 ]]; }
no_leaks() {
  ! rg -q 'private-remote-sentinel|private-backup-sentinel|private-wordpress-sentinel|PRIVATE_(WP|SUBPROCESS)_DIAGNOSTIC_SENTINEL' "$output"
}

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
export TEST_COMPOSER_FAIL=true
check test_must_fail run_deploy refs/heads/main "$sha"
check unchanged
check test "$(find "$NMKR_DEPLOY_BACKUP_ROOT" -name '*.tar' | wc -l)" -eq 0
check no_leaks

new_case
export TEST_MISSING_BUILD=true
check test_must_fail run_deploy refs/heads/main "$sha"
check unchanged

new_case
export TEST_ACTIVATE_FAIL_ONCE=true
check test_must_fail run_deploy refs/heads/main "$sha"
check unchanged
check one_backup
check test -e "$TEST_STATE/active"
check no_leaks

new_case
export TEST_FINAL_DIRTY=true
check test_must_fail run_deploy refs/heads/main "$sha"
check unchanged
check one_backup
check no_leaks

printf 'Exact-ref deployment regression: PASS (%d assertions)\n' "$pass"
