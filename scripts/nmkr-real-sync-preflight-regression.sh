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
  env RUN_REAL_SYNC=true \
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
perm_before="$(stat -c %a "$SRC1")"
run_expect_fail "repository root as private state" base_env WP_PATH="$WP1" NMKR_PHASE2_LOG_DIR="$SRC1" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh"
[[ ! -e "$SRC1/runs" ]] || fail "invalid repository-root state created runs"
[[ "$(stat -c %a "$SRC1")" == "$perm_before" ]] || fail "invalid repository-root state permissions changed"

mkdir -p "$SRC1/private-state"
run_expect_fail "repository subdirectory as private state" base_env WP_PATH="$WP1" NMKR_PHASE2_LOG_DIR="$SRC1/private-state" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh"
[[ ! -e "$SRC1/private-state/runs" ]] || fail "invalid repository-subdirectory state created runs"

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

STRUCT_CLONE="$TMP/deployed-structural"; git clone -q "$SRC1" "$STRUCT_CLONE"
STRUCT_OUT="$TMP/structural.out"
if base_env WP_PATH="$WP1" NMKR_PHASE2_LOG_DIR="$TMP/valid-state-structural" NMKR_DEPLOYED_PLUGIN_PATH="$STRUCT_CLONE" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh" >"$STRUCT_OUT" 2>&1; then fail "structural clean checkout unexpectedly passed full preflight"; fi
grep -q 'failed gate: origin-guard' "$STRUCT_OUT" || { cat "$STRUCT_OUT"; fail "valid separate deployed checkout did not pass structural checks before later guard"; }
pass "valid separate deployed Git checkout passed structural checks"

MISMATCH="$TMP/deployed-mismatch"; git clone -q "$SRC1" "$MISMATCH"; echo mismatch > "$MISMATCH/mismatch.txt"; git -C "$MISMATCH" add mismatch.txt; git -C "$MISMATCH" commit -q -m mismatch
run_expect_fail "source/deployed commit mismatch" base_env WP_PATH="$WP1" NMKR_PHASE2_LOG_DIR="$TMP/valid-state-mismatch" NMKR_DEPLOYED_PLUGIN_PATH="$MISMATCH" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh"

CLONE="$TMP/deployed-clone"; git clone -q "$SRC1" "$CLONE"; chmod +x "$CLONE/scripts/nmkr-wpcli-db-state.sh"; git -C "$CLONE" config core.fileMode false; chmod -x "$CLONE/scripts/nmkr-real-sync-preflight.sh"
run_expect_fail "mode-only deployed change with fileMode false" base_env WP_PATH="$WP1" NMKR_PHASE2_LOG_DIR="$TMP/valid-state4" NMKR_DEPLOYED_PLUGIN_PATH="$CLONE" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh"

git -C "$SRC1" config core.fileMode false; chmod -x "$SRC1/scripts/nmkr-real-sync-preflight.sh"
run_expect_fail "mode-only source change with fileMode false" base_env WP_PATH="$WP1" NMKR_PHASE2_LOG_DIR="$TMP/valid-state5" NMKR_DEPLOYED_PLUGIN_PATH="$CLONE" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh"
chmod +x "$SRC1/scripts/nmkr-real-sync-preflight.sh"

git clone -q "$SRC1" "$TMP/deployed-clean"; touch "$TMP/deployed-clean/untracked.txt"
run_expect_fail "untracked deployed file" base_env WP_PATH="$WP1" NMKR_PHASE2_LOG_DIR="$TMP/valid-state6" NMKR_DEPLOYED_PLUGIN_PATH="$TMP/deployed-clean" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh"

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
