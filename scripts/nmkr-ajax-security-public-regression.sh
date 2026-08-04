#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
RUNNER="$ROOT/scripts/nmkr-ajax-security-test-runner.sh"
fail(){ echo 'AJAX security harness regression: FAIL' >&2; exit 1; }

# Keep these source checks narrow: they prevent a future edit from silently restoring
# Playwright's repository-local defaults or cleanup-before-reap signal ordering.
for name in PLAYWRIGHT_HTML_REPORT PLAYWRIGHT_TEST_OUTPUT_DIR NMKR_AUTH_STATE_ROOT; do
  grep -Fq -- "export ${name}=\"\$RUN_DIR/" "$RUNNER" || fail
done
grep -Fq -- 'mkdir -m 700 -- "$NMKR_AUTH_STATE_ROOT" || fail auth-state' "$RUNNER" || fail
grep -Fq -- '[[ -d "$NMKR_AUTH_STATE_ROOT" && ! -L "$NMKR_AUTH_STATE_ROOT" ]] || fail auth-state' "$RUNNER" || fail
grep -Fq -- '[[ "$(stat -c '\''%a'\'' "$NMKR_AUTH_STATE_ROOT" 2>/dev/null)" == 700 ]] || fail auth-state' "$RUNNER" || fail
auth_prepare_line="$(grep -nF -- 'mkdir -m 700 -- "$NMKR_AUTH_STATE_ROOT"' "$RUNNER" | cut -d: -f1)"
playwright_line="$(grep -nF -- 'run negative npm --prefix "$ROOT" run test:e2e:ajax-security' "$RUNNER" | cut -d: -f1)"
[[ "$auth_prepare_line" =~ ^[0-9]+$ && "$playwright_line" =~ ^[0-9]+$ && "$auth_prepare_line" -lt "$playwright_line" ]] || fail
grep -Eq -- 'reap_active; .*rm -rf' "$RUNNER" || fail
grep -Fq -- 'kill -TERM -- "-$ACTIVE_PGID"' "$RUNNER" || fail

private="$(mktemp -d)"; chmod 700 "$private"
trap 'rm -rf -- "$private"' EXIT
export PLAYWRIGHT_HTML_REPORT="$private/report" PLAYWRIGHT_TEST_OUTPUT_DIR="$private/results" NMKR_AUTH_STATE_ROOT="$private/auth"
setsid bash -c 'mkdir -p "$PLAYWRIGHT_HTML_REPORT" "$PLAYWRIGHT_TEST_OUTPUT_DIR" "$NMKR_AUTH_STATE_ROOT"; bash -c '\''trap "echo stopped >\"$NMKR_AUTH_STATE_ROOT/stopped\"; exit 0" TERM; while :; do sleep 1; done'\'' & echo $! >"$NMKR_AUTH_STATE_ROOT/child"; wait' >/dev/null 2>&1 &
leader=$!
for _ in {1..50}; do [[ -s "$private/auth/child" ]] && break; sleep .02; done
[[ -s "$private/auth/child" ]] || fail
child="$(cat "$private/auth/child")"
kill -TERM -- "-$leader" 2>/dev/null || fail
wait "$leader" 2>/dev/null || true
for _ in {1..50}; do [[ -f "$private/auth/stopped" ]] && break; sleep .02; done
[[ -f "$private/auth/stopped" ]] || fail
for path in "$PLAYWRIGHT_HTML_REPORT" "$PLAYWRIGHT_TEST_OUTPUT_DIR" "$NMKR_AUTH_STATE_ROOT"; do
  [[ "$path" == "$private"/* ]] || fail
done
rm -rf -- "$private"; trap - EXIT
[[ ! -e "$private" ]] || fail
echo 'AJAX security harness regression: PASS'
