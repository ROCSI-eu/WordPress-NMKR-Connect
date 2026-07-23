#!/usr/bin/env bash
set -Eeuo pipefail
# Synthetic public-safe preflight cases: no usable host and no credentials.
ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
runner="$ROOT/scripts/nmkr-phase2-test-runner.sh"
tmp_dir="$(mktemp -d)"; trap 'rm -rf "$tmp_dir"' EXIT
mkdir -p "$tmp_dir/wp" "$tmp_dir/private/runs"
base=(env WP_BASE_URL=http://invalid.test WP_ADMIN_USER=placeholder WP_ADMIN_PASSWORD=placeholder WP_CLI_BIN=true WP_PATH="$tmp_dir/wp" NMKR_PHASE2_LOG_DIR="$tmp_dir/private")
expect_fail() {
  local expected="$1"; shift
  local before after
  before="$(find "$tmp_dir/private/runs" -name preflight.log -print 2>/dev/null | wc -l)"
  if "${base[@]}" "$@" bash "$runner" >/dev/null 2>&1; then echo 'Expected safe failure.' >&2; exit 1; fi
  after="$(find "$tmp_dir/private/runs" -name preflight.log -print 2>/dev/null | wc -l)"
  if [[ "$after" -gt "$before" ]]; then
    find "$tmp_dir/private/runs" -name preflight.log -print0 | xargs -0 cat | rg -F -- "$expected" >/dev/null
  else
    [[ "$expected" == 'ERROR: Phase 2 private root is unsafe.' ]]
  fi
}
expect_fail 'Unknown Phase 2 profile.' NMKR_PHASE2_PROFILE=unknown
expect_fail 'RUN_REAL_SYNC must be true or false.' RUN_REAL_SYNC=maybe
expect_fail 'NMKR_RETAIN_AUTH_STATE must be true or false.' NMKR_RETAIN_AUTH_STATE=maybe
expect_fail 'ERROR: Phase 2 private root is unsafe.' NMKR_PHASE2_LOG_DIR="$ROOT"
ln -s "$ROOT" "$tmp_dir/escape"; expect_fail 'ERROR: Phase 2 private root is unsafe.' NMKR_PHASE2_LOG_DIR="$tmp_dir/escape"
# A caller-selected profile cannot be changed or blanked by a sourced file.
printf 'NMKR_PHASE2_PROFILE=\nNMKR_DEPLOY_COMMAND="touch %s/marker"\nNMKR_PHASE2_SKIP_DEPLOY=false\n' "$tmp_dir" >"$tmp_dir/env"
expect_fail 'Phase 2 profile conflict.' NMKR_PHASE2_PROFILE=existing-readonly NMKR_PHASE2_ENV_FILE="$tmp_dir/env"
test ! -e "$tmp_dir/marker"
printf 'NMKR_PHASE2_PROFILE=general\n' >"$tmp_dir/env"; expect_fail 'Phase 2 profile conflict.' NMKR_PHASE2_PROFILE=existing-readonly NMKR_PHASE2_ENV_FILE="$tmp_dir/env"
printf 'NMKR_PHASE2_SKIP_DEPLOY=true\n' >"$tmp_dir/env"; expect_fail 'Expected source SHA is invalid.' NMKR_PHASE2_PROFILE=existing-readonly NMKR_PHASE2_ENV_FILE="$tmp_dir/env" NMKR_PHASE2_EXPECTED_SOURCE_SHA=invalid
printf 'NMKR_PHASE2_PROFILE=existing-readonly\n' >"$tmp_dir/env"; expect_fail 'Expected source SHA is invalid.' NMKR_PHASE2_ENV_FILE="$tmp_dir/env" NMKR_PHASE2_EXPECTED_SOURCE_SHA=invalid
# Direct Playwright path validation must fail closed without touching an exact-name sentinel.
mkdir -p "$tmp_dir/unrelated"; printf sentinel >"$tmp_dir/unrelated/auth-state.json"
if NMKR_AUTH_STATE_ROOT="$tmp_dir/unrelated" NMKR_AUTH_STATE_PATH="$tmp_dir/unrelated/auth-state.json" node "$ROOT/scripts/nmkr-playwright.js" --list >/dev/null 2>&1; then exit 1; fi
test "$(cat "$tmp_dir/unrelated/auth-state.json")" = sentinel
# Wrapper publishes one approved state path to config and all workers (discovery does no login).
NMKR_AUTH_STATE_ROOT= NMKR_AUTH_STATE_DIR= NMKR_AUTH_STATE_PATH= NMKR_AUTH_STATE_OWNER_TOKEN= node "$ROOT/scripts/nmkr-playwright.js" --list --reporter=list >/dev/null
echo 'PASS: Phase 2 profile and auth-state validation regressions.'
