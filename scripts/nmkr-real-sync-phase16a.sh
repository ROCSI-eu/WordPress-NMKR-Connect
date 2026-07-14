#!/usr/bin/env bash
set -Eeuo pipefail
SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"; REPO_ROOT="$(git -C "$SCRIPT_DIR" rev-parse --show-toplevel 2>/dev/null || cd "$SCRIPT_DIR/.." && pwd)"
ci_active(){ [[ -n "${1:-}" && "${1:-}" != 0 && "${1:-}" != false ]]; }
summary(){ printf '\nPhase 16A controlled real-sync summary\n  result: %s\n' "$1"; [[ -n "${2:-}" ]]&&printf '  failed gate: %s\n' "$2"; [[ -n "${RUN_DIR:-}" ]]&&printf '  private diagnostic path: %s\n' "$RUN_DIR"; }
fail(){ summary FAIL "$1"; exit 1; }
for n in CI GITHUB_ACTIONS GITLAB_CI CIRCLECI BUILDKITE TF_BUILD; do ci_active "${!n:-}" && fail ci-refusal; done
inside(){ python3 - "$1" "$2" <<'PY'
import os,sys
a=os.path.realpath(sys.argv[1]); b=os.path.realpath(sys.argv[2]); raise SystemExit(0 if a==b or a.startswith(b.rstrip(os.sep)+os.sep) else 1)
PY
}
[[ "${RUN_REAL_SYNC:-false}" == true ]] || fail confirmations
[[ "${PW_SAVE_ARTIFACTS:-false}" == false ]] || fail confirmations
[[ "${NMKR_REAL_SYNC_CONFIRM:-}" == I_UNDERSTAND_THIS_MUTATES_DEV ]] || fail confirmations
[[ "${NMKR_PHASE16A_CONFIRM:-}" == I_AUTHORIZE_EXACTLY_ONE_START ]] || fail confirmations
[[ -n "${WP_PATH:-}" && "$WP_PATH" = /* ]] || fail env-file
if [[ -n "${NMKR_PHASE2_ENV_FILE:-}" ]]; then [[ "$NMKR_PHASE2_ENV_FILE" = /* && -f "$NMKR_PHASE2_ENV_FILE" && ! -L "$NMKR_PHASE2_ENV_FILE" ]] || fail env-file; inside "$NMKR_PHASE2_ENV_FILE" "$REPO_ROOT" && fail env-file; inside "$NMKR_PHASE2_ENV_FILE" "$WP_PATH" && fail env-file; set -a; source "$NMKR_PHASE2_ENV_FILE" >/dev/null 2>/dev/null || fail env-file; set +a; fi
[[ -n "${NMKR_PHASE2_LOG_DIR:-}" && "$NMKR_PHASE2_LOG_DIR" = /* ]] || fail private-state
python3 - "${NMKR_PHASE2_LOG_DIR}" "$REPO_ROOT" "$WP_PATH" <<'PY' || fail private-state
import os,stat,sys
state,repo,wp=sys.argv[1:4]; uid=os.getuid();
def bad(): raise SystemExit(1)
def inside(a,b): a=os.path.realpath(a); b=os.path.realpath(b); return a==b or a.startswith(b.rstrip(os.sep)+os.sep)
if any(inside(state,r) or inside(r,state) for r in (repo,wp)): bad()
parent=state
missing=[]
while not os.path.exists(parent): missing.append(os.path.basename(parent)); parent=os.path.dirname(parent)
st=os.stat(parent)
if os.path.islink(parent) or st.st_uid!=uid or (stat.S_IMODE(st.st_mode)&0o077): bad()
os.makedirs(state+'/runs',mode=0o700,exist_ok=True)
for p in (state,state+'/runs'):
 st=os.stat(p); 
 if os.path.islink(p) or st.st_uid!=uid or (stat.S_IMODE(st.st_mode)&0o077): bad()
PY
RUN_DIR="$NMKR_PHASE2_LOG_DIR/runs/phase16a-$(date -u +%Y%m%dT%H%M%SZ)-$$"; mkdir -m 700 "$RUN_DIR" || fail private-state
LOCK="$NMKR_PHASE2_LOG_DIR/phase16a-controller.lock"; OWN_LOCK=0; cleanup(){ if [[ "$OWN_LOCK" == 1 && -d "$LOCK" ]]; then rmdir "$LOCK" 2>/dev/null || true; fi; }; trap 'printf '''{"result":"interrupted"}\n''' >"$RUN_DIR/result.json"; cleanup; exit 130' INT TERM; trap cleanup EXIT
mkdir -m 700 "$LOCK" || fail controller-lock; OWN_LOCK=1
RECEIPT="${NMKR_PHASE16A_RECEIPT:-$NMKR_PHASE2_LOG_DIR/real-sync-preflight.receipt.json}"; CONSUMED="${RECEIPT%.json}.consumed.json"
node "$REPO_ROOT/scripts/nmkr-real-sync-phase16a-driver.mjs" >/dev/null 2>&1 && fail driver-default || true
python3 - "$RECEIPT" "$CONSUMED" "$REPO_ROOT" "${NMKR_DEPLOYED_PLUGIN_PATH:-$REPO_ROOT}" <<'PY' || fail receipt
import json,os,stat,sys,time,hashlib,subprocess
r,c,src,dep=sys.argv[1:5]; req={'receipt_version','purpose','created_at_epoch','expires_at_epoch','source_commit','deployed_commit','origin_sha256','plugin_active','admin_capability_ok','db_state_clean','api_key_present','runtime_state_clean','cron_state_clean','object_cache_state_clean','profile_guard_passed','backup_confirmed','backup_confirmed_at_epoch','max_duration_seconds','poll_timeout_seconds','receipt_ttl_seconds'}
st=os.lstat(r)
if not stat.S_ISREG(st.st_mode) or stat.S_ISLNK(st.st_mode) or st.st_uid!=os.getuid() or stat.S_IMODE(st.st_mode)!=0o600: raise SystemExit(1)
d=json.load(open(r))
if set(d)!=req or d['receipt_version']!=1 or d['purpose']!='nmkr-real-sync-preflight': raise SystemExit(1)
now=int(time.time())
if d['expires_at_epoch']<=now or d['expires_at_epoch']-now<120: raise SystemExit(1)
for k in ['plugin_active','admin_capability_ok','db_state_clean','api_key_present','runtime_state_clean','cron_state_clean','object_cache_state_clean','profile_guard_passed','backup_confirmed']:
 if d[k] is not True: raise SystemExit(1)
if now-int(d['backup_confirmed_at_epoch'])>86400: raise SystemExit(1)
if not (60<=d['max_duration_seconds']<=7200 and 5<=d['poll_timeout_seconds']<=120 and 120<=d['receipt_ttl_seconds']<=3600): raise SystemExit(1)
head=subprocess.check_output(['git','-C',src,'rev-parse','HEAD'],text=True).strip()
if head!=d['source_commit']: raise SystemExit(1)
if subprocess.check_output(['git','-C',src,'status','--porcelain=v1','--untracked-files=all'],text=True).strip(): raise SystemExit(1)
if os.path.exists(c): raise SystemExit(1)
os.rename(r,c)
os.chmod(c,0o600)
PY
wp="${WP_CLI_BIN:-wp}"; "$wp" --path="$WP_PATH" eval-file "$REPO_ROOT/scripts/nmkr-real-sync-phase16a-state.php" --skip-plugins --skip-themes --skip-packages >"$RUN_DIR/pre.json" 2>/dev/null || fail pre-state
node "$REPO_ROOT/scripts/nmkr-real-sync-phase16a-driver.mjs" >"$RUN_DIR/driver.json" 2>"$RUN_DIR/driver.err" || fail driver
NMKR_PHASE16A_HISTORY_MAX_ID="$(python3 -c 'import json,sys; print(json.load(open(sys.argv[1]))["max_history_id"])' "$RUN_DIR/pre.json")" "$wp" --path="$WP_PATH" eval-file "$REPO_ROOT/scripts/nmkr-real-sync-phase16a-state.php" --skip-plugins --skip-themes --skip-packages >"$RUN_DIR/post.json" 2>/dev/null || fail post-state
python3 - "$RUN_DIR/pre.json" "$RUN_DIR/post.json" "$RUN_DIR/driver.json" <<'PY' || fail final-state
import json,sys
pre,post,drv=[json.load(open(p)) for p in sys.argv[1:4]]
if drv.get('startCount')!=1: raise SystemExit(1)
if post['sync_history_total_count']-pre['sync_history_total_count']!=1: raise SystemExit(1)
if post['metrics_total_count']-pre['metrics_total_count']!=1: raise SystemExit(1)
for k in ['active_history_count','option_active_marker_count','transient_active_marker_count','blocked_sync_cron_count','duplicate_project_uid_count','duplicate_token_uid_count','duplicate_token_detail_uid_count','invalid_relationship_count','impossible_counter_count']:
 if post[k]!=0: raise SystemExit(1)
if post['terminal_history_digest_before']!=pre['terminal_history_digest_before']: raise SystemExit(1)
if not post.get('latest_history_completed'): raise SystemExit(1)
if post['project_count']<pre['project_count'] or post['token_count']<pre['token_count'] or post['token_detail_count']<pre['token_detail_count']: raise SystemExit(1)
if post.get('sync_data_classification')=='active_or_unknown': raise SystemExit(1)
if not post['last_sync_time_matches_latest_metrics']: raise SystemExit(1)
PY
bash "$REPO_ROOT/scripts/nmkr-wpcli-db-state.sh" >/dev/null || fail db-state
summary PASS
