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
  local case_id before_runs after_runs new_run output
  case_id="$(mktemp "$tmp_dir/case.XXXXXX")"
  before_runs="$case_id.before"
  after_runs="$case_id.after"
  output="$case_id.output"
  find "$tmp_dir/private/runs" -mindepth 1 -maxdepth 1 -type d -printf '%f\n' | sort >"$before_runs"
  if "${base[@]}" "$@" bash "$runner" >"$output" 2>&1; then echo 'Expected safe failure.' >&2; exit 1; fi
  find "$tmp_dir/private/runs" -mindepth 1 -maxdepth 1 -type d -printf '%f\n' | sort >"$after_runs"
  new_run="$(comm -13 "$before_runs" "$after_runs")"
  if [[ -n "$new_run" ]]; then
    [[ "$(printf '%s\n' "$new_run" | wc -l)" == 1 ]] || { echo 'Expected one Phase 2 run directory.' >&2; exit 1; }
    grep -F -- "$expected" "$tmp_dir/private/runs/$new_run/preflight.log" >/dev/null
  else
    # Unsafe roots are rejected before a private run directory can be created.
    [[ "$expected" == 'ERROR: Phase 2 private root is unsafe.' ]] || { echo 'Expected a preflight log for this case.' >&2; exit 1; }
    grep -F -- "$expected" "$output" >/dev/null
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
