#!/usr/bin/env bash
set -Eeuo pipefail
umask 077
SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(git -C "$SCRIPT_DIR" rev-parse --show-toplevel 2>/dev/null || true)"
CONFIRM='I_AUTHORIZE_PRIVATE_READ_ONLY_NMKR_API_BENCHMARK'
truthy(){ [[ -n "${1:-}" && "${1,,}" != 0 && "${1,,}" != false && "${1,,}" != no ]]; }
fail(){ printf 'benchmark controller: FAIL (%s)\n' "$1"; exit 1; }
truthy "${CI:-}" && fail public-ci
[[ "${NMKR_API_BENCHMARK_CONFIRM:-}" == "$CONFIRM" ]] || fail authorization
SRC_SHA="${NMKR_API_BENCHMARK_SOURCE_SHA:-}"; DEP_SHA="${NMKR_API_BENCHMARK_DEPLOYED_SHA:-}"
[[ "$SRC_SHA" =~ ^[0-9a-f]{40}$ && "$DEP_SHA" =~ ^[0-9a-f]{40}$ ]] || fail identity
[[ -n "$REPO_ROOT" && -n "${NMKR_DEPLOYED_PLUGIN_PATH:-}" && -n "${WP_PATH:-}" && -n "${NMKR_API_BENCHMARK_RESULT_DIR:-}" ]] || fail configuration
python3 - "$NMKR_API_BENCHMARK_RESULT_DIR" "$REPO_ROOT" "$WP_PATH" 2>/dev/null <<'PY' || fail private-directory
import os,stat,sys
p,repo,wp=map(os.path.realpath,sys.argv[1:])
if not os.path.isabs(p) or p in (repo,wp) or p.startswith(repo+os.sep) or p.startswith(wp+os.sep): raise SystemExit(1)
st=os.lstat(p)
if not stat.S_ISDIR(st.st_mode) or stat.S_ISLNK(st.st_mode) or st.st_uid!=os.getuid() or stat.S_IMODE(st.st_mode)&0o077: raise SystemExit(1)
PY
DEPLOYED="$(python3 - "$NMKR_DEPLOYED_PLUGIN_PATH" "$WP_PATH" "${NMKR_PLUGIN_SLUG:-nmkr-connect/nmkr-connect.php}" 2>/dev/null <<'PY'
import os,sys
p,wp,slug=sys.argv[1:]; p=os.path.realpath(p); active=os.path.realpath(os.path.join(wp,'wp-content','plugins',slug.split('/')[0]))
if p!=active or not os.path.isdir(p): raise SystemExit(1)
print(p)
PY
)" || fail plugin-binding
check_git(){ [[ "$(git -C "$REPO_ROOT" rev-parse HEAD)" == "$SRC_SHA" && "$(git -C "$DEPLOYED" rev-parse HEAD)" == "$DEP_SHA" && "$SRC_SHA" == "$DEP_SHA" ]] || return 1; [[ -z "$(git -C "$REPO_ROOT" status --porcelain=v1 --untracked-files=all)" && -z "$(git -C "$DEPLOYED" status --porcelain=v1 --untracked-files=all)" ]]; }
check_git || fail identity
WP_BIN="${WP_CLI_BIN:-wp}"; "$WP_BIN" --path="$WP_PATH" plugin is-active "${NMKR_PLUGIN_SLUG:-nmkr-connect/nmkr-connect.php}" >/dev/null 2>&1 || fail plugin-binding
LOCK="$NMKR_API_BENCHMARK_RESULT_DIR/.nmkr-api-benchmark.lock"; RUN_DIR="$NMKR_API_BENCHMARK_RESULT_DIR/run-$(date -u +%Y%m%dT%H%M%SZ)-$$"
( set -o noclobber; : >"$LOCK" ) 2>/dev/null || fail lock
chmod 600 "$LOCK" 2>/dev/null || { rm -f -- "$LOCK" 2>/dev/null || true; fail lock; }
OWN_LOCK=1
cleanup(){ if [[ "${OWN_LOCK:-0}" == 1 ]]; then OWN_LOCK=0; rm -f -- "$LOCK" 2>/dev/null || true; fi; }
trap cleanup EXIT
terminate(){ trap - HUP INT TERM; if [[ -n "${CHILD_PID:-}" ]]; then kill -TERM "$CHILD_PID" 2>/dev/null || true; wait "$CHILD_PID" 2>/dev/null || true; fi; cleanup; exit 130; }
trap terminate HUP INT TERM
mkdir -m 700 "$RUN_DIR" 2>/dev/null || fail run-directory
RESULT="$RUN_DIR/result.json"; DIAG="$RUN_DIR/diagnostic.txt"
{ : >"$RESULT" && : >"$DIAG" && chmod 600 "$RESULT" "$DIAG"; } 2>/dev/null || fail run-directory
printf 'benchmark controller: preflight PASS\n'
set +e
NMKR_API_BENCHMARK_CONTROLLER=1 "$WP_BIN" --path="$WP_PATH" eval-file "$DEPLOYED/scripts/nmkr-api-response-benchmark.php" >"$RESULT" 2>"$DIAG" &
CHILD_PID=$!
wait "$CHILD_PID"; STATUS=$?; CHILD_PID=
set -e
check_git || fail final-integrity
python3 - "$RESULT" <<'PY' || fail result-schema
import json,sys
x=json.load(open(sys.argv[1])); required={'schema_version','profile','endpoint_summaries','combined_summary','http_attempts','failed_http_attempts','measured_logical_failures','state_equal','limits_ok','pass'}
if set(x)!=required or x['schema_version']!=1 or x['profile']!='m3-06' or set(x['endpoint_summaries'])!={'projects','token_list','token_detail'}: raise SystemExit(1)
PY
(( STATUS == 0 )) || fail benchmark-result
printf 'benchmark controller: final-integrity PASS\nbenchmark controller: result PASS\n'
