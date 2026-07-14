#!/usr/bin/env bash
set -Eeuo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
if REPO_ROOT_FROM_GIT="$(git -C "$SCRIPT_DIR" rev-parse --show-toplevel 2>/dev/null)"; then
  REPO_ROOT="$REPO_ROOT_FROM_GIT"
else
  REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
fi
FINAL_MIN_REMAINING_SECONDS=120
REQUIRED_HEAD="${NMKR_PHASE16A_EXPECTED_HEAD:-}"

ci_active() { [[ -n "${1:-}" && "${1:-}" != "0" && "${1:-}" != "false" ]]; }
summary() { printf '\nPhase 16A controlled real-sync summary\n  result: %s\n' "$1"; [[ -n "${2:-}" ]] && printf '  failed gate: %s\n' "$2"; [[ -n "${RUN_DIR:-}" ]] && printf '  private diagnostic path: %s\n' "$RUN_DIR"; }
fail() { summary FAIL "$1"; exit 1; }
require_tool() { command -v "$1" >/dev/null 2>&1 || fail required-tools; }
wp_cli() { local bin="${WP_CLI_BIN:-wp}"; "$bin" --path="$WP_PATH" "$@"; }
json_get() { python3 -c 'import json,sys; print(json.load(open(sys.argv[1]))[sys.argv[2]])' "$1" "$2"; }
inside_or_equal() { python3 - "$1" "$2" <<'PY'
import os,sys
a=os.path.realpath(sys.argv[1]); b=os.path.realpath(sys.argv[2])
raise SystemExit(0 if a == b or a.startswith(b.rstrip(os.sep) + os.sep) else 1)
PY
}

validate_private_state() {
  python3 - "${NMKR_PHASE2_LOG_DIR:-}" "$REPO_ROOT" "${WP_PATH:-}" <<'PY'
import os, stat, sys
state, repo, wp = sys.argv[1:4]
uid = os.getuid()
def bad(): raise SystemExit(1)
def inside(a,b):
    a=os.path.realpath(a); b=os.path.realpath(b)
    return a == b or a.startswith(b.rstrip(os.sep) + os.sep)
if not state or not os.path.isabs(state) or not wp or not os.path.isabs(wp): bad()
if any(part in ('.','..') for part in state.split(os.sep)): bad()
if any(inside(state, root) or inside(root, state) for root in (repo, wp)): bad()
probe = state
missing=[]
while not os.path.exists(probe):
    missing.append(os.path.basename(probe)); parent=os.path.dirname(probe)
    if parent == probe: bad()
    probe=parent
cur = os.sep
for part in [p for p in state.split(os.sep) if p]:
    cur=os.path.join(cur, part)
    if os.path.exists(cur):
        if cur in ('/tmp','/var/tmp','/private/tmp','/'):
            continue
        st=os.stat(cur)
        if os.path.islink(cur) or st.st_uid != uid or (stat.S_IMODE(st.st_mode) & 0o077): bad()
    else:
        break
os.makedirs(os.path.join(state, 'runs'), mode=0o700, exist_ok=True)
for p in (state, os.path.join(state, 'runs')):
    st=os.stat(p)
    if os.path.islink(p) or st.st_uid != uid or (stat.S_IMODE(st.st_mode) & 0o077): bad()
PY
}

validate_receipt() {
  local phase="$1" receipt="$2" consumed="$3" deployed="$4" fingerprint_out="$5"
  python3 - "$phase" "$receipt" "$consumed" "$REPO_ROOT" "$deployed" "$fingerprint_out" "$FINAL_MIN_REMAINING_SECONDS" "$REQUIRED_HEAD" <<'PY'
import hashlib, json, os, stat, subprocess, sys, time
phase, receipt, consumed, src, deployed, fingerprint_out, min_remaining, required_head = sys.argv[1:9]
min_remaining = int(min_remaining)
required = {
 'receipt_version': int, 'purpose': str, 'created_at_epoch': int, 'expires_at_epoch': int,
 'source_commit': str, 'deployed_commit': str, 'origin_sha256': str,
 'plugin_active': bool, 'admin_capability_ok': bool, 'db_state_clean': bool,
 'api_key_present': bool, 'runtime_state_clean': bool, 'cron_state_clean': bool,
 'object_cache_state_clean': bool, 'profile_guard_passed': bool, 'backup_confirmed': bool,
 'backup_confirmed_at_epoch': int, 'max_duration_seconds': int,
 'poll_timeout_seconds': int, 'receipt_ttl_seconds': int,
}
def bad(): raise SystemExit(1)
def clean(repo):
    return subprocess.check_output(['git','-C',repo,'status','--porcelain=v1','--untracked-files=all'], text=True).strip() == ''
def head(repo):
    return subprocess.check_output(['git','-C',repo,'rev-parse','HEAD'], text=True).strip()
def parent_private(path):
    uid=os.getuid(); cur=os.path.dirname(os.path.realpath(path)); root=os.path.parse if False else None
    while cur and cur != os.path.dirname(cur):
        if cur in ('/tmp','/var/tmp','/private/tmp','/'):
            break
        st=os.stat(cur)
        if os.path.islink(cur) or st.st_uid != uid or (stat.S_IMODE(st.st_mode) & 0o077): bad()
        cur=os.path.dirname(cur)
try: st=os.lstat(receipt)
except OSError: bad()
if not stat.S_ISREG(st.st_mode) or stat.S_ISLNK(st.st_mode) or st.st_uid != os.getuid() or stat.S_IMODE(st.st_mode) != 0o600: bad()
parent_private(receipt)
if os.path.exists(consumed): bad()
raw=open(receipt,'rb').read()
try: data=json.loads(raw.decode('utf-8'))
except Exception: bad()
if set(data) != set(required): bad()
for key, typ in required.items():
    if type(data[key]) is not typ: bad()
if data['receipt_version'] != 1 or data['purpose'] != 'nmkr-real-sync-preflight': bad()
now=int(time.time())
if data['created_at_epoch'] > now + 300 or data['created_at_epoch'] <= 0: bad()
if data['expires_at_epoch'] <= now: bad()
if phase == 'final' and data['expires_at_epoch'] - now < min_remaining: bad()
if now - data['backup_confirmed_at_epoch'] > 86400 or data['backup_confirmed_at_epoch'] > now + 300: bad()
if not (60 <= data['max_duration_seconds'] <= 7200 and 5 <= data['poll_timeout_seconds'] <= 120 and 120 <= data['receipt_ttl_seconds'] <= 3600): bad()
for key in ['plugin_active','admin_capability_ok','db_state_clean','api_key_present','runtime_state_clean','cron_state_clean','object_cache_state_clean','profile_guard_passed','backup_confirmed']:
    if data[key] is not True: bad()
if not isinstance(data['origin_sha256'], str) or len(data['origin_sha256']) != 64 or any(c not in '0123456789abcdef' for c in data['origin_sha256']): bad()
src_head=head(src); dep_head=head(deployed)
if src_head != data['source_commit'] or dep_head != data['deployed_commit'] or src_head != dep_head: bad()
if required_head and src_head != required_head: bad()
if not clean(src) or not clean(deployed): bad()
fingerprint=hashlib.sha256(raw).hexdigest()
if fingerprint_out:
    if phase == 'initial': open(fingerprint_out,'w').write(fingerprint+'\n')
    else:
        if open(fingerprint_out).read().strip() != fingerprint: bad()
print(json.dumps({'ok': True, 'fingerprint': fingerprint, 'source_commit': src_head, 'deployed_commit': dep_head, 'max_duration_seconds': data['max_duration_seconds'], 'poll_timeout_seconds': data['poll_timeout_seconds']}, separators=(',',':')))
PY
}

capture_state() {
  local out="$1" max_id="${2:-}"
  if [[ -n "$max_id" ]]; then
    NMKR_PHASE16A_HISTORY_MAX_ID="$max_id" wp_cli eval-file "$REPO_ROOT/scripts/nmkr-real-sync-phase16a-state.php" >"$out" 2>>"$DIAGNOSTIC_FILE"
  else
    wp_cli eval-file "$REPO_ROOT/scripts/nmkr-real-sync-phase16a-state.php" >"$out" 2>>"$DIAGNOSTIC_FILE"
  fi
  chmod 600 "$out"
}

final_authorize() {
  RUN_DIR="$1"; DIAGNOSTIC_FILE="$RUN_DIR/phase16a.log"
  source "$RUN_DIR/controller.env"
  [[ -d "$LOCK" ]] || fail controller-lock
  validate_receipt final "$RECEIPT" "$CONSUMED" "$DEPLOYED_REAL" "$RUN_DIR/receipt.sha256" >"$RUN_DIR/final-receipt.json" || fail receipt
  wp_cli core is-installed >/dev/null 2>>"$DIAGNOSTIC_FILE" || fail wordpress-ready
  wp_cli plugin is-active "${NMKR_PLUGIN_SLUG:-nmkr-connect/nmkr-connect.php}" >/dev/null 2>>"$DIAGNOSTIC_FILE" || fail plugin-active
  if [[ -n "${WP_ADMIN_USER:-}" ]]; then wp_cli eval 'exit(current_user_can("nmkr_manage_sync") ? 0 : 1);' >/dev/null 2>>"$DIAGNOSTIC_FILE" || true; fi
  capture_state "$RUN_DIR/pre.json"
  python3 - "$RUN_DIR/pre.json" <<'PY' || fail pre-state
import json,sys
d=json.load(open(sys.argv[1]))
if not all(d['required_tables_present'].values()): raise SystemExit(1)
for k in ['active_history_count','option_active_marker_count','transient_active_marker_count','stale_recovery_marker_count','heartbeat_worker_evidence_count','blocked_sync_cron_count','duplicate_project_uid_count','duplicate_token_uid_count','duplicate_token_detail_uid_count','invalid_relationship_count','impossible_counter_count']:
    if d.get(k) != 0: raise SystemExit(1)
if not d['cron_inspectable'] or not d['light_profile_guard'] or not d['api_key_present']: raise SystemExit(1)
if d['sync_data_classification'] == 'active': raise SystemExit(1)
PY
  [[ ! -e "$CONSUMED" ]] || fail receipt
  mv "$RECEIPT" "$CONSUMED" || fail receipt
  chmod 600 "$CONSUMED"
  printf '{"ok":true}\n'
}

if [[ "${1:-}" == "--final-authorize" ]]; then final_authorize "$2"; exit 0; fi

for n in CI GITHUB_ACTIONS GITLAB_CI CIRCLECI BUILDKITE TF_BUILD; do ci_active "${!n:-}" && fail ci-refusal; done
[[ "${RUN_REAL_SYNC:-false}" == true ]] || fail confirmations
[[ "${PW_SAVE_ARTIFACTS:-false}" == false ]] || fail confirmations
[[ "${NMKR_REAL_SYNC_CONFIRM:-}" == I_UNDERSTAND_THIS_MUTATES_DEV ]] || fail confirmations
[[ "${NMKR_PHASE16A_CONFIRM:-}" == I_AUTHORIZE_EXACTLY_ONE_START ]] || fail confirmations
[[ -n "${WP_PATH:-}" && "$WP_PATH" = /* ]] || fail env-file

if [[ -n "${NMKR_PHASE2_ENV_FILE:-}" ]]; then
  [[ "$NMKR_PHASE2_ENV_FILE" = /* && -f "$NMKR_PHASE2_ENV_FILE" && ! -L "$NMKR_PHASE2_ENV_FILE" ]] || fail env-file
  inside_or_equal "$NMKR_PHASE2_ENV_FILE" "$REPO_ROOT" && fail env-file
  inside_or_equal "$NMKR_PHASE2_ENV_FILE" "$WP_PATH" && fail env-file
  WP_PATH_PRE="$(python3 -c 'import os,sys; print(os.path.realpath(sys.argv[1]))' "$WP_PATH")"
  set -a; source "$NMKR_PHASE2_ENV_FILE" >/dev/null 2>/dev/null || fail env-file; set +a
  [[ "$(python3 -c 'import os,sys; print(os.path.realpath(sys.argv[1]))' "$WP_PATH")" == "$WP_PATH_PRE" ]] || fail env-file
fi

for tool in bash git python3 node; do require_tool "$tool"; done
validate_private_state || fail private-state
mkdir -p "$NMKR_PHASE2_LOG_DIR/runs"
RUN_DIR="$NMKR_PHASE2_LOG_DIR/runs/phase16a-$(date -u +%Y%m%dT%H%M%SZ)-$$"; mkdir -m 700 "$RUN_DIR" || fail private-state
DIAGNOSTIC_FILE="$RUN_DIR/phase16a.log"; : >"$DIAGNOSTIC_FILE"; chmod 600 "$DIAGNOSTIC_FILE"
LOCK="$NMKR_PHASE2_LOG_DIR/phase16a-controller.lock"; OWN_LOCK=0
cleanup(){ if [[ "$OWN_LOCK" == 1 && -d "$LOCK" ]]; then rmdir "$LOCK" 2>/dev/null || true; fi; }
trap 'printf '\''{"result":"interrupted"}\n'\'' >"$RUN_DIR/result.json"; cleanup; exit 130' INT TERM
trap cleanup EXIT
mkdir -m 700 "$LOCK" || fail controller-lock; OWN_LOCK=1

RECEIPT="${NMKR_PHASE16A_RECEIPT:-$NMKR_PHASE2_LOG_DIR/real-sync-preflight.receipt.json}"
CONSUMED="${RECEIPT%.json}.consumed.json"
DEPLOYED_REAL="$(python3 -c 'import os,sys; print(os.path.realpath(sys.argv[1]))' "${NMKR_DEPLOYED_PLUGIN_PATH:-$REPO_ROOT}")"
validate_receipt initial "$RECEIPT" "$CONSUMED" "$DEPLOYED_REAL" "$RUN_DIR/receipt.sha256" >"$RUN_DIR/initial-receipt.json" || fail receipt

wp_cli core is-installed >/dev/null 2>>"$DIAGNOSTIC_FILE" || fail wordpress-ready
wp_cli plugin is-active "${NMKR_PLUGIN_SLUG:-nmkr-connect/nmkr-connect.php}" >/dev/null 2>>"$DIAGNOSTIC_FILE" || fail plugin-active

cat >"$RUN_DIR/controller.env" <<ENV
WP_PATH=$(printf '%q' "$WP_PATH")
WP_CLI_BIN=$(printf '%q' "${WP_CLI_BIN:-wp}")
RECEIPT=$(printf '%q' "$RECEIPT")
CONSUMED=$(printf '%q' "$CONSUMED")
DEPLOYED_REAL=$(printf '%q' "$DEPLOYED_REAL")
LOCK=$(printf '%q' "$LOCK")
NMKR_PLUGIN_SLUG=$(printf '%q' "${NMKR_PLUGIN_SLUG:-nmkr-connect/nmkr-connect.php}")
ENV
chmod 600 "$RUN_DIR/controller.env"

DRIVER_BIN="${NMKR_PHASE16A_TEST_DRIVER_BIN:-$REPO_ROOT/scripts/nmkr-real-sync-phase16a-driver.mjs}"
export NMKR_PHASE16A_FINAL_AUTH_RUN_DIR="$RUN_DIR"
export NMKR_PHASE16A_FINAL_AUTH_SCRIPT="$REPO_ROOT/scripts/nmkr-real-sync-phase16a.sh"
export NMKR_REAL_SYNC_MAX_DURATION_SECONDS="$(json_get "$RUN_DIR/initial-receipt.json" max_duration_seconds)"
export NMKR_REAL_SYNC_POLL_TIMEOUT_SECONDS="$(json_get "$RUN_DIR/initial-receipt.json" poll_timeout_seconds)"
node "$DRIVER_BIN" >"$RUN_DIR/driver.json" 2>"$RUN_DIR/driver.err" || fail driver
[[ -f "$RUN_DIR/pre.json" && -f "$CONSUMED" ]] || fail authorization-sequence
PRE_MAX_ID="$(json_get "$RUN_DIR/pre.json" max_history_id)"
capture_state "$RUN_DIR/post.json" "$PRE_MAX_ID" || fail post-state
python3 - "$RUN_DIR/pre.json" "$RUN_DIR/post.json" "$RUN_DIR/driver.json" <<'PY' || fail final-state
import json,sys
pre,post,drv=[json.load(open(p)) for p in sys.argv[1:4]]
if drv.get('startCount') != 1: raise SystemExit(1)
if post['sync_history_total_count'] - pre['sync_history_total_count'] != 1: raise SystemExit(1)
if post['max_history_id'] <= pre['max_history_id']: raise SystemExit(1)
if not post['latest_history_completed'] or not post['latest_history_end_time_valid']: raise SystemExit(1)
if post['metrics_total_count'] - pre['metrics_total_count'] != 1: raise SystemExit(1)
if post['max_metrics_id'] <= pre['max_metrics_id']: raise SystemExit(1)
if post['terminal_history_digest'] != pre['terminal_history_digest']: raise SystemExit(1)
for k in ['active_history_count','option_active_marker_count','transient_active_marker_count','stale_recovery_marker_count','heartbeat_worker_evidence_count','blocked_sync_cron_count','duplicate_project_uid_count','duplicate_token_uid_count','duplicate_token_detail_uid_count','invalid_relationship_count','impossible_counter_count']:
    if post.get(k) != 0: raise SystemExit(1)
if not all(post['required_tables_present'].values()): raise SystemExit(1)
if not post['cron_inspectable'] or not post['light_profile_guard'] or not post['api_key_present']: raise SystemExit(1)
if post['sync_data_classification'] == 'active': raise SystemExit(1)
if not post['last_sync_time_matches_latest_metrics']: raise SystemExit(1)
for k in ['project_count','token_count','token_detail_count']:
    if post[k] < pre[k]: raise SystemExit(1)
PY
if [[ "${NMKR_PHASE16A_PUBLIC_REGRESSION:-false}" != true ]]; then
  NMKR_DB_STATE_ALLOW_ACTIVE_SYNC=false bash "$REPO_ROOT/scripts/nmkr-wpcli-db-state.sh" >/dev/null 2>>"$DIAGNOSTIC_FILE" || fail db-state
fi
validate_receipt final "$CONSUMED" "$CONSUMED.never" "$DEPLOYED_REAL" "" >/dev/null 2>>"$DIAGNOSTIC_FILE" || true
summary PASS
