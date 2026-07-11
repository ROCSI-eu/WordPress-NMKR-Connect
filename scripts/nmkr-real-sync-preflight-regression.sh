#!/usr/bin/env bash
set -Eeuo pipefail

ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

pass() { printf 'PASS: %s\n' "$1"; }
fail() { printf 'FAIL: %s\n' "$1" >&2; exit 1; }
run_expect_fail() {
  local label="$1"; shift
  if "$@" >/dev/null 2>&1; then fail "$label unexpectedly passed"; fi
  pass "$label"
}
make_repo() {
  local dir="$1"
  mkdir -p "$dir/scripts"
  cp "$ROOT/scripts/nmkr-real-sync-preflight.sh" "$dir/scripts/"
  cat > "$dir/scripts/nmkr-wpcli-db-state.sh" <<'EOF'
#!/usr/bin/env bash
exit 0
EOF
  chmod +x "$dir/scripts/"*.sh
  git -C "$dir" init -q
  git -C "$dir" config user.email public@example.invalid
  git -C "$dir" config user.name PublicTest
  git -C "$dir" add scripts
  git -C "$dir" commit -q -m init
}
base_env() {
  env -u CI \
  -u GITHUB_ACTIONS \
  -u GITLAB_CI \
  -u CIRCLECI \
  -u BUILDKITE \
  -u TF_BUILD \
  RUN_REAL_SYNC=true \
  NMKR_REAL_SYNC_CONFIRM=I_UNDERSTAND_THIS_MUTATES_DEV \
  PW_SAVE_ARTIFACTS=false \
  NMKR_REAL_SYNC_BACKUP_CONFIRM=I_CONFIRMED_A_RECENT_DEV_BACKUP \
  WP_CLI_BIN=true \
  WP_BASE_URL=https://example.invalid \
  NMKR_REAL_SYNC_ALLOWED_ORIGIN=https://example.invalid \
  WP_ADMIN_USER=admin \
  NMKR_REAL_SYNC_BACKUP_CONFIRMED_AT=2099-01-01T00:00:00Z \
  "$@"
}

# Private-state path tests: invalid locations fail before creating runs or chmodding.
SRC1="$TMP/src1"; WP1="$TMP/wp1"; mkdir -p "$WP1"; make_repo "$SRC1"

CI_PARENT="$TMP/ci-parent"; mkdir -p "$CI_PARENT"; ci_parent_perm_before="$(stat -c %a "$CI_PARENT")"; CI_OUT="$TMP/ci-refusal.out"
if env CI=true RUN_REAL_SYNC=true NMKR_REAL_SYNC_CONFIRM=I_UNDERSTAND_THIS_MUTATES_DEV PW_SAVE_ARTIFACTS=false NMKR_REAL_SYNC_BACKUP_CONFIRM=I_CONFIRMED_A_RECENT_DEV_BACKUP WP_PATH="$WP1" NMKR_PHASE2_LOG_DIR="$CI_PARENT/ci-state" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh" >"$CI_OUT" 2>&1; then
  fail "CI refusal unexpectedly passed"
fi
grep -q 'failed gate: ci-refusal' "$CI_OUT" || fail "CI refusal did not fail at ci-refusal"
[[ ! -e "$CI_PARENT/ci-state" && ! -e "$CI_PARENT/ci-state/runs" ]] || fail "CI refusal created private state"
[[ "$(stat -c %a "$CI_PARENT")" == "$ci_parent_perm_before" ]] || fail "CI refusal changed parent permissions"
pass "CI refusal before private-state mutation"

PRE_ENV_PARENT="$TMP/pre-env-parent"; mkdir -p "$PRE_ENV_PARENT"; pre_env_parent_perm_before="$(stat -c %a "$PRE_ENV_PARENT")"
PRE_SENTINEL="$TMP/pre-env-sentinel"; PRE_ENV_FILE="$TMP/pre-source.env"; PRE_OUT="$TMP/pre-source-ci.out"
cat > "$PRE_ENV_FILE" <<EOF
CI=false
GITHUB_ACTIONS=false
touch "$PRE_SENTINEL"
EOF
if env CI=true NMKR_PHASE2_ENV_FILE="$PRE_ENV_FILE" NMKR_PHASE2_LOG_DIR="$PRE_ENV_PARENT/state" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh" >"$PRE_OUT" 2>&1; then
  fail "pre-source CI refusal unexpectedly passed"
fi
grep -q 'failed gate: ci-refusal' "$PRE_OUT" || fail "pre-source CI refusal did not fail at ci-refusal"
[[ ! -e "$PRE_SENTINEL" ]] || fail "pre-source CI refusal sourced env file"
[[ ! -e "$PRE_ENV_PARENT/state" && ! -e "$PRE_ENV_PARENT/state/runs" ]] || fail "pre-source CI refusal created private state"
[[ "$(stat -c %a "$PRE_ENV_PARENT")" == "$pre_env_parent_perm_before" ]] || fail "pre-source CI refusal changed parent permissions"
pass "pre-source CI refusal does not source env file"

POST_ENV_PARENT="$TMP/post-env-parent"; mkdir -p "$POST_ENV_PARENT"; post_env_parent_perm_before="$(stat -c %a "$POST_ENV_PARENT")"
POST_SENTINEL="$TMP/post-env-sentinel"; POST_ENV_FILE="$TMP/post-source.env"; POST_OUT="$TMP/post-source-ci.out"
cat > "$POST_ENV_FILE" <<EOF
CI=true
touch "$POST_SENTINEL"
EOF
if env -u CI -u GITHUB_ACTIONS -u GITLAB_CI -u CIRCLECI -u BUILDKITE -u TF_BUILD NMKR_PHASE2_ENV_FILE="$POST_ENV_FILE" NMKR_PHASE2_LOG_DIR="$POST_ENV_PARENT/state" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh" >"$POST_OUT" 2>&1; then
  fail "post-source CI refusal unexpectedly passed"
fi
grep -q 'failed gate: ci-refusal' "$POST_OUT" || fail "post-source CI refusal did not fail at ci-refusal"
[[ -e "$POST_SENTINEL" ]] || fail "post-source CI refusal did not source env file"
[[ ! -e "$POST_ENV_PARENT/state" && ! -e "$POST_ENV_PARENT/state/runs" ]] || fail "post-source CI refusal created private state"
[[ "$(stat -c %a "$POST_ENV_PARENT")" == "$post_env_parent_perm_before" ]] || fail "post-source CI refusal changed parent permissions"
pass "post-source CI refusal rejects env-introduced marker"

NESTED_PARENT="$TMP/nested-parent"; mkdir -m 700 "$NESTED_PARENT"; nested_parent_perm_before="$(stat -c %a "$NESTED_PARENT")"
NESTED_STATE="$NESTED_PARENT/one/two/three"; NESTED_OUT="$TMP/nested-valid.out"
if base_env WP_PATH="$WP1" NMKR_PHASE2_LOG_DIR="$NESTED_STATE" NMKR_DEPLOYED_PLUGIN_PATH="$SRC1" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh" >"$NESTED_OUT" 2>&1; then
  fail "nested first-time private state unexpectedly passed full preflight"
fi
grep -q 'failed gate: deployment-integrity' "$NESTED_OUT" || { cat "$NESTED_OUT"; fail "nested first-time private state did not reach later deployment guard"; }
for component in "$NESTED_PARENT/one" "$NESTED_PARENT/one/two" "$NESTED_PARENT/one/two/three" "$NESTED_PARENT/one/two/three/runs"; do
  [[ -d "$component" && ! -L "$component" ]] || fail "nested component missing or symlinked"
  [[ "$(stat -c %a "$component")" == "700" ]] || fail "nested component mode was not 0700"
done
[[ "$(stat -c %a "$NESTED_PARENT")" == "$nested_parent_perm_before" ]] || fail "nested parent permissions changed"
pass "nested first-time private state reaches later guard"

NESTED_REPO_STATE="$SRC1/nested/one/two"
run_expect_fail "nested repository private state" base_env WP_PATH="$WP1" NMKR_PHASE2_LOG_DIR="$NESTED_REPO_STATE" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh"
[[ ! -e "$SRC1/nested" ]] || fail "nested repository private state created components"

NESTED_WP_STATE="$WP1/nested/one/two"
run_expect_fail "nested WordPress private state" base_env WP_PATH="$WP1" NMKR_PHASE2_LOG_DIR="$NESTED_WP_STATE" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh"
[[ ! -e "$WP1/nested" ]] || fail "nested WordPress private state created components"

LINK_PARENT="$TMP/link-parent"; LINK_TARGET="$TMP/link-target"; mkdir -m 700 "$LINK_PARENT" "$LINK_TARGET"; ln -s "$LINK_TARGET" "$LINK_PARENT/link"; link_target_perm_before="$(stat -c %a "$LINK_TARGET")"
run_expect_fail "symlinked intermediate private state" base_env WP_PATH="$WP1" NMKR_PHASE2_LOG_DIR="$LINK_PARENT/link/one/two" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh"
[[ ! -e "$LINK_TARGET/one" ]] || fail "symlinked intermediate target modified"
[[ "$(stat -c %a "$LINK_TARGET")" == "$link_target_perm_before" ]] || fail "symlinked intermediate target permissions changed"

UNSAFE_PARENT="$TMP/unsafe-parent"; mkdir -m 700 "$UNSAFE_PARENT"; mkdir -m 755 "$UNSAFE_PARENT/unsafe"; unsafe_perm_before="$(stat -c %a "$UNSAFE_PARENT/unsafe")"
run_expect_fail "unsafe intermediate private state" base_env WP_PATH="$WP1" NMKR_PHASE2_LOG_DIR="$UNSAFE_PARENT/unsafe/one/two" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh"
[[ ! -e "$UNSAFE_PARENT/unsafe/one" ]] || fail "unsafe intermediate private state created components"
[[ "$(stat -c %a "$UNSAFE_PARENT/unsafe")" == "$unsafe_perm_before" ]] || fail "unsafe intermediate permissions changed"

perm_before="$(stat -c %a "$SRC1")"
run_expect_fail "repository root as private state" base_env WP_PATH="$WP1" NMKR_PHASE2_LOG_DIR="$SRC1" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh"
[[ ! -e "$SRC1/runs" ]] || fail "invalid repository-root state created runs"
[[ "$(stat -c %a "$SRC1")" == "$perm_before" ]] || fail "invalid repository-root state permissions changed"

run_expect_fail "repository subdirectory as private state" base_env WP_PATH="$WP1" NMKR_PHASE2_LOG_DIR="$SRC1/scripts" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh"
[[ ! -e "$SRC1/scripts/runs" ]] || fail "invalid repository-subdirectory state created runs"

run_expect_fail "WP_PATH as private state" base_env WP_PATH="$WP1" NMKR_PHASE2_LOG_DIR="$WP1" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh"
[[ ! -e "$WP1/runs" ]] || fail "invalid WP_PATH state created runs"
mkdir -p "$WP1/state"
run_expect_fail "WordPress subdirectory as private state" base_env WP_PATH="$WP1" NMKR_PHASE2_LOG_DIR="$WP1/state" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh"
[[ ! -e "$WP1/state/runs" ]] || fail "invalid WP subdirectory state created runs"

REAL_STATE="$TMP/real-state"; mkdir -m 700 "$REAL_STATE"; ln -s "$REAL_STATE" "$TMP/state-link"
run_expect_fail "symlinked state directory" base_env WP_PATH="$WP1" NMKR_PHASE2_LOG_DIR="$TMP/state-link" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh"
[[ ! -e "$REAL_STATE/runs" ]] || fail "symlinked state target was modified"
RUNS_TARGET="$TMP/runs-target"; STATE2="$TMP/state2"; mkdir -m 700 "$RUNS_TARGET" "$STATE2"; ln -s "$RUNS_TARGET" "$STATE2/runs"; perm_runs_before="$(stat -c %a "$RUNS_TARGET")"
run_expect_fail "symlinked runs path" base_env WP_PATH="$WP1" NMKR_PHASE2_LOG_DIR="$STATE2" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh"
[[ "$(stat -c %a "$RUNS_TARGET")" == "$perm_runs_before" ]] || fail "symlinked runs target permissions changed"

# Deployment path tests.
VALID_STATE="$TMP/valid-state"; DEPLOY_SRC_SUB="$SRC1/scripts"
run_expect_fail "source subdirectory deployed path" base_env WP_PATH="$WP1" NMKR_PHASE2_LOG_DIR="$VALID_STATE" NMKR_DEPLOYED_PLUGIN_PATH="$DEPLOY_SRC_SUB" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh"
run_expect_fail "source root deployed path" base_env WP_PATH="$WP1" NMKR_PHASE2_LOG_DIR="$TMP/valid-state2" NMKR_DEPLOYED_PLUGIN_PATH="$SRC1" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh"
PARENT_DEPLOY="$TMP/parent-deploy"; mkdir -p "$PARENT_DEPLOY/plugin"; git -C "$PARENT_DEPLOY" init -q; git -C "$PARENT_DEPLOY" config user.email public@example.invalid; git -C "$PARENT_DEPLOY" config user.name PublicTest; touch "$PARENT_DEPLOY/file"; git -C "$PARENT_DEPLOY" add file; git -C "$PARENT_DEPLOY" commit -q -m init
run_expect_fail "deployed Git top-level parent" base_env WP_PATH="$WP1" NMKR_PHASE2_LOG_DIR="$TMP/valid-state3" NMKR_DEPLOYED_PLUGIN_PATH="$PARENT_DEPLOY/plugin" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh"

[[ -z "$(git -C "$SRC1" -c core.fileMode=true status --porcelain=v1 --untracked-files=all)" ]] || fail "source fixture dirty before structural checkout test"
STRUCT_CLONE="$TMP/deployed-structural"; git clone -q "$SRC1" "$STRUCT_CLONE"
STRUCT_OUT="$TMP/structural.out"
if base_env WP_PATH="$WP1" NMKR_PHASE2_LOG_DIR="$TMP/valid-state-structural" NMKR_DEPLOYED_PLUGIN_PATH="$STRUCT_CLONE" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh" >"$STRUCT_OUT" 2>&1; then fail "structural clean checkout unexpectedly passed full preflight"; fi
grep -q 'failed gate: origin-guard' "$STRUCT_OUT" || { cat "$STRUCT_OUT"; fail "valid separate deployed checkout did not pass structural checks before later guard"; }
pass "valid separate deployed Git checkout passed structural checks"

RAW_ORIGIN="https://phase15-origin.invalid"
ORIGIN_STATE="$TMP/origin-state"
WPCLI_MOCK="$TMP/wpcli-origin-mock"
cat > "$WPCLI_MOCK" <<'EOF'
#!/usr/bin/env bash
if [[ "$1" == --path=* ]]; then shift; fi
if [[ "$1 $2 $3" == "option get home" || "$1 $2 $3" == "option get siteurl" ]]; then
  printf 'https://different-origin.invalid\n'
  exit 0
fi
exit 0
EOF
chmod +x "$WPCLI_MOCK"
ORIGIN_OUT="$TMP/origin-failure.out"
if base_env WP_CLI_BIN="$WPCLI_MOCK" WP_PATH="$WP1" NMKR_PHASE2_LOG_DIR="$ORIGIN_STATE" NMKR_DEPLOYED_PLUGIN_PATH="$STRUCT_CLONE" WP_BASE_URL="$RAW_ORIGIN" NMKR_REAL_SYNC_ALLOWED_ORIGIN="$RAW_ORIGIN" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh" >"$ORIGIN_OUT" 2>&1; then
  fail "origin failure unexpectedly passed"
fi
grep -q 'failed gate: origin-guard' "$ORIGIN_OUT" || fail "origin failure did not reach origin-guard"
! grep -Fq "$RAW_ORIGIN" "$ORIGIN_OUT" || fail "raw origin appeared in captured output"
if [[ -d "$ORIGIN_STATE" ]]; then
  if find "$ORIGIN_STATE" -type f -print0 | xargs -0 grep -Fq "$RAW_ORIGIN" 2>/dev/null; then
    fail "raw origin persisted in private run directory"
  fi
  [[ -z "$(find "$ORIGIN_STATE" -name origin.json -print -quit)" ]] || fail "origin.json persisted after origin failure"
fi
pass "origin failure does not persist raw origin"

MISMATCH="$TMP/deployed-mismatch"; git clone -q "$SRC1" "$MISMATCH"; git -C "$MISMATCH" config user.email public@example.invalid; git -C "$MISMATCH" config user.name PublicTest; echo mismatch > "$MISMATCH/mismatch.txt"; git -C "$MISMATCH" add mismatch.txt; git -C "$MISMATCH" commit -q -m mismatch
run_expect_fail "source/deployed commit mismatch" base_env WP_PATH="$WP1" NMKR_PHASE2_LOG_DIR="$TMP/valid-state-mismatch" NMKR_DEPLOYED_PLUGIN_PATH="$MISMATCH" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh"

CLONE="$TMP/deployed-clone"; git clone -q "$SRC1" "$CLONE"; chmod +x "$CLONE/scripts/nmkr-wpcli-db-state.sh"; git -C "$CLONE" config core.fileMode false; chmod -x "$CLONE/scripts/nmkr-real-sync-preflight.sh"
run_expect_fail "mode-only deployed change with fileMode false" base_env WP_PATH="$WP1" NMKR_PHASE2_LOG_DIR="$TMP/valid-state4" NMKR_DEPLOYED_PLUGIN_PATH="$CLONE" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh"

git -C "$SRC1" config core.fileMode false; chmod -x "$SRC1/scripts/nmkr-real-sync-preflight.sh"
run_expect_fail "mode-only source change with fileMode false" base_env WP_PATH="$WP1" NMKR_PHASE2_LOG_DIR="$TMP/valid-state5" NMKR_DEPLOYED_PLUGIN_PATH="$CLONE" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh"
chmod +x "$SRC1/scripts/nmkr-real-sync-preflight.sh"

git clone -q "$SRC1" "$TMP/deployed-clean"; touch "$TMP/deployed-clean/untracked.txt"
run_expect_fail "untracked deployed file" base_env WP_PATH="$WP1" NMKR_PHASE2_LOG_DIR="$TMP/valid-state6" NMKR_DEPLOYED_PLUGIN_PATH="$TMP/deployed-clean" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh"


# Backup timestamp parser must require exact canonical UTC shape before parsing.
python3 - <<'PY' || fail "backup timestamp parser regression cases failed"
import datetime, re, sys
def check(value):
    if not re.fullmatch(r'[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z', value):
        raise ValueError('shape')
    dt = datetime.datetime.strptime(value, '%Y-%m-%dT%H:%M:%SZ').replace(tzinfo=datetime.timezone.utc)
    if dt.strftime('%Y-%m-%dT%H:%M:%SZ') != value:
        raise ValueError('roundtrip')
    now = datetime.datetime.now(datetime.timezone.utc)
    if dt < now - datetime.timedelta(hours=24) or dt > now + datetime.timedelta(minutes=5):
        raise ValueError('age')
    return int(dt.timestamp())
now = datetime.datetime.now(datetime.timezone.utc).replace(microsecond=0)
canonical = now.strftime('%Y-%m-%dT%H:%M:%SZ')
check(canonical)
invalids = [
    f'{now.year}-7-{now.day:02d}T{now.hour:02d}:{now.minute:02d}:{now.second:02d}Z',
    f'{now.year}-{now.month:02d}-3T{now.hour:02d}:{now.minute:02d}:{now.second:02d}Z',
    f'{now.year}-{now.month:02d}-{now.day:02d}T7:{now.minute:02d}:{now.second:02d}Z',
    f'{now.year}-{now.month:02d}-{now.day:02d}T{now.hour:02d}:5:{now.second:02d}Z',
    f'{now.year}-{now.month:02d}-{now.day:02d}T{now.hour:02d}:{now.minute:02d}:4Z',
    canonical[:-1] + '.123Z',
    canonical[:-1] + '+00:00',
    canonical[:-1] + 'z',
    ' ' + canonical,
    canonical + ' ',
    f'{now.year}-02-30T{now.hour:02d}:{now.minute:02d}:{now.second:02d}Z',
]
for value in invalids:
    try:
        check(value)
    except Exception:
        continue
    raise SystemExit(f'invalid timestamp accepted: {value!r}')
PY
pass "backup timestamp parser rejects non-canonical forms"

# Cron/runtime static and harness tests.
! grep -q '_get_cron_array' "$ROOT/scripts/nmkr-real-sync-runtime-state.php" || fail "runtime helper calls _get_cron_array"
cat > "$TMP/runtime-harness.php" <<'PHP'
<?php
define('ABSPATH', __DIR__);
class WP_User {}
$GLOBALS['cron_value'] = array('version' => 2);
function get_option($name, $default = false) { if ($name === 'cron') return $GLOBALS['cron_value']; if ($name === 'nmkr_connect_options') return array('sync_profile'=>'light','sync_batch_size'=>1,'sync_batch_delay'=>3); return $default; }
function get_transient($name) { return false; }
function wp_using_ext_object_cache() { return false; }
function is_email($value) { return strpos($value, '@') !== false; }
function get_user_by($field, $value) { return new WP_User(); }
function user_can($user, $cap) { return true; }
function wp_json_encode($value, $flags = 0) { return json_encode($value, $flags); }
$case = $argv[1];
if ($case === 'blocked') $GLOBALS['cron_value'] = array('version'=>2, time()=>array('nmkr_sync_cron_hook'=>array('k'=>array('args'=>array()))));
if ($case === 'malformed') $GLOBALS['cron_value'] = array(time()=>array());
include $argv[2];
PHP
valid_json="$(WP_ADMIN_USER=admin php "$TMP/runtime-harness.php" valid "$ROOT/scripts/nmkr-real-sync-runtime-state.php")"
python3 -c 'import json,sys; d=json.loads(sys.argv[1]); assert d["cron_state_inspectable"] is True and d["pending_sync_cron_count"] == 0' "$valid_json" || fail "valid cron not inspectable"
blocked_json="$(WP_ADMIN_USER=admin php "$TMP/runtime-harness.php" blocked "$ROOT/scripts/nmkr-real-sync-runtime-state.php")"
python3 -c 'import json,sys; d=json.loads(sys.argv[1]); assert d["cron_state_inspectable"] is True and d["pending_sync_cron_count"] == 1' "$blocked_json" || fail "blocked cron aggregate count wrong"
malformed_json="$(WP_ADMIN_USER=admin php "$TMP/runtime-harness.php" malformed "$ROOT/scripts/nmkr-real-sync-runtime-state.php")"
python3 -c 'import json,sys; d=json.loads(sys.argv[1]); assert d["cron_state_inspectable"] is False' "$malformed_json" || fail "malformed cron did not fail inspectability"
pass "cron helper regression cases"

# HTTP/TLS static coverage for the readiness guard.
grep -q -- '--cacert' "$ROOT/scripts/nmkr-real-sync-preflight.sh" || fail "private CA bundle support missing"
! grep -q 'curl -k' "$ROOT/scripts/nmkr-real-sync-preflight.sh" || fail "insecure curl -k present"
grep -q '%{url_effective}' "$ROOT/scripts/nmkr-real-sync-preflight.sh" || fail "effective URL check missing"
grep -q 'ORIGIN_SHA256' "$ROOT/scripts/nmkr-real-sync-preflight.sh" || fail "same-origin digest check missing"
pass "HTTP readiness guard static checks"

# Dynamic TLS and same-origin readiness primitives without contacting external hosts.
if command -v openssl >/dev/null 2>&1; then
  CERT_DIR="$TMP/cert"; mkdir -p "$CERT_DIR"
  cat > "$CERT_DIR/openssl.cnf" <<'EOF'
[req]
distinguished_name=req_distinguished_name
x509_extensions=v3_req
prompt=no
[req_distinguished_name]
CN=localhost
[v3_req]
subjectAltName=@alt_names
[alt_names]
DNS.1=localhost
EOF
  openssl req -x509 -newkey rsa:2048 -nodes -days 1 -keyout "$CERT_DIR/key.pem" -out "$CERT_DIR/cert.pem" -config "$CERT_DIR/openssl.cnf" >/dev/null 2>&1
  cat > "$CERT_DIR/https_server.py" <<'PY'
import http.server, ssl, sys
class Handler(http.server.BaseHTTPRequestHandler):
    def do_GET(self):
        if self.path == '/redirect':
            self.send_response(302)
            self.send_header('Location', 'https://other.example/wp-login.php')
            self.end_headers()
            return
        self.send_response(200)
        self.end_headers()
        self.wfile.write(b'<form id="loginform"><input id="user_login"></form>')
    def log_message(self, *args): pass
server = http.server.HTTPServer(('127.0.0.1', 0), Handler)
print(server.server_port, flush=True)
context = ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER)
context.load_cert_chain(certfile=sys.argv[1], keyfile=sys.argv[2])
server.socket = context.wrap_socket(server.socket, server_side=True)
server.serve_forever()
PY
  python3 "$CERT_DIR/https_server.py" "$CERT_DIR/cert.pem" "$CERT_DIR/key.pem" >"$CERT_DIR/port" 2>/dev/null &
  server_pid=$!
  for _ in {1..50}; do [[ -s "$CERT_DIR/port" ]] && break; sleep 0.1; done
  port="$(cat "$CERT_DIR/port")"
  for _ in {1..50}; do
    curl -sS --cacert "$CERT_DIR/cert.pem" --max-time 1 "https://localhost:$port/wp-login.php" >/dev/null 2>&1 && break
    sleep 0.1
  done
  if curl -sS --max-time 3 "https://localhost:$port/wp-login.php" >/dev/null 2>&1; then kill "$server_pid"; fail "invalid TLS certificate unexpectedly passed without CA bundle"; fi
  curl -sS --cacert "$CERT_DIR/cert.pem" --max-time 3 "https://localhost:$port/wp-login.php" | grep -q 'id="user_login"' || { kill "$server_pid"; fail "configured CA bundle did not permit valid local TLS response"; }
  kill "$server_pid"
  pass "TLS readiness primitives"
fi
python3 - <<'PY' || fail "cross-origin redirect helper failed"
import hashlib, urllib.parse, sys
expected = hashlib.sha256(b'https://example.invalid').hexdigest()
url = 'https://other.example/wp-login.php'
p = urllib.parse.urlsplit(url)
origin = 'https://' + p.hostname.lower().rstrip('.')
if hashlib.sha256(origin.encode()).hexdigest() == expected:
    raise SystemExit(1)
PY
pass "cross-origin effective URL rejection primitive"
