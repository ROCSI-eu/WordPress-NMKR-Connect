#!/usr/bin/env bash
set -Eeuo pipefail
REAL_ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
TMP="$(mktemp -d)"; trap 'rm -rf "$TMP"' EXIT
fail(){ echo "FAIL: $1" >&2; exit 1; }
pass(){ echo "PASS: $1"; }
run_fail(){ local name="$1"; shift; local out="$TMP/${name// /_}.out"; if "$@" >"$out" 2>&1; then fail "$name unexpectedly passed"; fi; pass "$name"; }

build_clean_source_fixture(){
  local src="$1" dest="$2"
  rm -rf "$dest"; mkdir -p "$dest"
  git -C "$src" ls-files -z | python3 -c '
import os, shutil, sys
src, dest = sys.argv[1:3]
for raw in [p for p in sys.stdin.buffer.read().split(b"\0") if p]:
    rel = raw.decode("utf-8")
    if rel.startswith(("vendor/", "node_modules/")):
        continue
    source = os.path.join(src, rel)
    target = os.path.join(dest, rel)
    os.makedirs(os.path.dirname(target), exist_ok=True)
    if os.path.islink(source):
        os.symlink(os.readlink(source), target)
    else:
        shutil.copy2(source, target)
' "$src" "$dest"
  git -C "$dest" init -q
  git -C "$dest" add -A
  git -C "$dest" -c user.email=phase16a@example.invalid -c user.name=Phase16A commit -q -m "phase16a clean source fixture"
  [[ -z "$(git -C "$dest" status --porcelain=v1 --untracked-files=all)" ]] || fail "clean source fixture dirty"
}

DIRTY_INPUT="$TMP/dirty-input"; CLEAN_FROM_DIRTY="$TMP/clean-from-dirty"
git clone -q "$REAL_ROOT" "$DIRTY_INPUT"
printf '\nphase16a tracked fixture change\n' >>"$DIRTY_INPUT/README.md"
printf 'untracked sentinel\n' >"$DIRTY_INPUT/untracked-phase16a-sentinel.txt"
build_clean_source_fixture "$DIRTY_INPUT" "$CLEAN_FROM_DIRTY"
rg -q 'phase16a tracked fixture change' "$CLEAN_FROM_DIRTY/README.md" || fail "tracked dirty input was not represented in clean fixture"
[[ ! -e "$CLEAN_FROM_DIRTY/untracked-phase16a-sentinel.txt" ]] || fail "untracked dirty input sentinel copied"
[[ -z "$(git -C "$CLEAN_FROM_DIRTY" status --porcelain=v1 --untracked-files=all)" ]] || fail "clean dirty-input fixture not clean"
pass "clean source fixture captures tracked WIP and excludes untracked sentinels"

ROOT="$TMP/clean-source"
build_clean_source_fixture "$REAL_ROOT" "$ROOT"
! rg -n 'driver-default|phase16a-driver\.mjs" >/dev/null' "$ROOT/scripts/nmkr-real-sync-phase16a.sh" >/dev/null || fail "premature private driver invocation remains"
pass "controller contains no premature driver execution"

WP="$TMP/wp"; STATE="$TMP/state"; ACTIVE_PLUGIN="$WP/wp-content/plugins/nmkr-connect"
mkdir -p "$WP/wp-content/plugins" "$STATE"; chmod 700 "$TMP" "$STATE"
git clone -q "$ROOT" "$ACTIVE_PLUGIN"
git -C "$ACTIVE_PLUGIN" checkout -q "$(git -C "$ROOT" rev-parse HEAD)"
reset_active_plugin(){ git -C "$ACTIVE_PLUGIN" reset --hard -q "$(git -C "$ROOT" rev-parse HEAD)"; git -C "$ACTIVE_PLUGIN" clean -fdq; }
make_receipt(){ local path="$1" ttl="${2:-300}" created_offset="${3:-0}"; local head; head="$(git -C "$ROOT" rev-parse HEAD)"; python3 - "$path" "$head" "$ttl" "$created_offset" <<'PY'
import json,os,sys,time
p,head,ttl,created_offset=sys.argv[1:5]; now=int(time.time()); created=now+int(created_offset); ttl=int(ttl)
d={'receipt_version':1,'purpose':'nmkr-real-sync-preflight','created_at_epoch':created,'expires_at_epoch':created+ttl,'source_commit':head,'deployed_commit':head,'origin_sha256':'95bd8950c21c1294c6c0408521381453bde62be1df02df429f79791abb319f37','plugin_active':True,'admin_capability_ok':True,'db_state_clean':True,'api_key_present':True,'runtime_state_clean':True,'cron_state_clean':True,'object_cache_state_clean':True,'profile_guard_passed':True,'backup_confirmed':True,'backup_confirmed_at_epoch':now,'max_duration_seconds':300,'poll_timeout_seconds':10,'receipt_ttl_seconds':ttl}
json.dump(d,open(p,'w')); os.chmod(p,0o600)
PY
}
state_json(){ local history="$1" metrics="$2" maxh="$3" maxm="$4" digest="$5" sync_class="${6:-absent}"; cat <<JSON
{"schema_version":1,"required_tables_present":{"projects":true,"tokens":true,"token_details":true,"sync_stats":true,"sync_metrics":true},"project_count":1,"project_max_id":1,"token_count":1,"token_max_id":1,"token_detail_count":1,"token_detail_max_id":1,"sync_history_total_count":$history,"active_history_count":0,"terminal_history_count":$history,"max_history_id":$maxh,"metrics_total_count":$metrics,"max_metrics_id":$maxm,"duplicate_project_uid_count":0,"duplicate_token_uid_count":0,"duplicate_token_detail_uid_count":0,"invalid_relationship_count":0,"impossible_counter_count":0,"option_active_marker_count":0,"transient_active_marker_count":0,"stale_recovery_marker_count":0,"heartbeat_worker_evidence_count":0,"sync_data_classification":"$sync_class","blocked_cron_hook_counts":{"nmkr_execute_sync_background":0,"nmkr_process_batch_hook":0,"nmkr_sync_cron_hook":0,"nmkr_install_sync_cron_hook":0},"blocked_sync_cron_count":0,"cron_inspectable":true,"light_profile_guard":true,"api_key_present":true,"last_sync_time_matches_latest_metrics":true,"terminal_history_digest":"$digest","latest_history_id":$maxh,"latest_history_status_classification":"completed","latest_history_completed":true,"latest_history_end_time_valid":true,"snapshot_epoch":1}
JSON
}
FAKE_WP="$TMP/fake-wp"; cat >"$FAKE_WP" <<'SH'
#!/usr/bin/env bash
set -Eeuo pipefail
cmd=""; for a in "$@"; do [[ "$a" != --path=* ]] && { cmd="$a"; break; }; done
if [[ "$cmd" == core ]]; then exit 0; fi
if [[ "$cmd" == plugin ]]; then [[ "${NMKR_FAKE_PLUGIN_INACTIVE:-false}" == true ]] && exit 1 || exit 0; fi
if [[ "$cmd" == option ]]; then echo "${NMKR_FAKE_WP_ORIGIN:-https://example.invalid}"; exit 0; fi
if [[ "$cmd" == eval ]]; then [[ "${NMKR_FAKE_CAPABILITY:-ok}" == ok ]] && exit 0 || exit 1; fi
if [[ "$cmd" == eval-file ]]; then
  if [[ -n "${NMKR_PHASE16A_HISTORY_MAX_ID:-}" ]]; then cat "$NMKR_FAKE_POST_STATE"; else cat "$NMKR_FAKE_PRE_STATE"; fi
  exit 0
fi
exit 0
SH
chmod +x "$FAKE_WP"
FAKE_DRIVER="$TMP/fake-driver.mjs"; cat >"$FAKE_DRIVER" <<'JS'
#!/usr/bin/env node
import { execFileSync } from 'node:child_process';
import { existsSync, appendFileSync, readFileSync } from 'node:fs';
const log=process.env.NMKR_FAKE_DRIVER_LOG;
appendFileSync(log, `before:${existsSync(process.env.NMKR_FAKE_CONSUMED) ? 'consumed' : 'unconsumed'}\n`);
const out=execFileSync('bash',[process.env.NMKR_PHASE16A_FINAL_AUTH_SCRIPT,'--final-authorize',process.env.NMKR_PHASE16A_FINAL_AUTH_RUN_DIR],{encoding:'utf8'});
if(!JSON.parse(out.trim().split(/\n/).pop()).ok) process.exit(2);
appendFileSync(log, `after:${existsSync(process.env.NMKR_FAKE_CONSUMED) ? 'consumed' : 'unconsumed'}\n`);
console.log(JSON.stringify({startCount:1,pollCount:2,maxProgress:100,nonterminalObserved:true,validLiveMetricsObserved:true,terminalClassification:'terminal-observed',transportRetryCount:0,activeHistoryIdObserved:2,elapsedSeconds:4}));
JS
chmod +x "$FAKE_DRIVER"
base_env(){ local state_dir="$1"; shift; env -u CI -u GITHUB_ACTIONS -u GITLAB_CI -u CIRCLECI -u BUILDKITE -u TF_BUILD RUN_REAL_SYNC=true PW_SAVE_ARTIFACTS=false NMKR_REAL_SYNC_CONFIRM=I_UNDERSTAND_THIS_MUTATES_DEV NMKR_PHASE16A_CONFIRM=I_AUTHORIZE_EXACTLY_ONE_START WP_PATH="$WP" WP_CLI_BIN="$FAKE_WP" WP_BASE_URL="${NMKR_TEST_WP_BASE_URL:-https://example.invalid}" NMKR_REAL_SYNC_ALLOWED_ORIGIN="${NMKR_TEST_ALLOWED_ORIGIN:-https://example.invalid}" WP_ADMIN_USER="admin@example.invalid" NMKR_PHASE2_LOG_DIR="$state_dir" NMKR_PHASE16A_PUBLIC_REGRESSION=true NMKR_PHASE16A_TEST_DRIVER_BIN="$FAKE_DRIVER" "$@"; }
run_refusal(){ local name="$1"; shift; local d="$TMP/$name"; mkdir -m700 "$d"; : >"$TMP/$name.driver"; run_fail "$name" env -u RUN_REAL_SYNC -u CI -u GITHUB_ACTIONS -u GITLAB_CI -u CIRCLECI -u BUILDKITE -u TF_BUILD NMKR_FAKE_DRIVER_LOG="$TMP/$name.driver" "$@"; [[ ! -s "$TMP/$name.driver" ]] || fail "$name invoked driver"; pass "$name driver zero-times"; }
run_refusal "default RUN_REAL_SYNC=false" WP_PATH="$WP" NMKR_PHASE2_LOG_DIR="$STATE" bash "$ROOT/scripts/nmkr-real-sync-phase16a.sh"
run_refusal "CI refusal before env" CI=true RUN_REAL_SYNC=true WP_PATH="$WP" NMKR_PHASE2_ENV_FILE="$TMP/nope" NMKR_PHASE2_LOG_DIR="$STATE" bash "$ROOT/scripts/nmkr-real-sync-phase16a.sh"
run_refusal "PW_SAVE_ARTIFACTS true" RUN_REAL_SYNC=true PW_SAVE_ARTIFACTS=true WP_PATH="$WP" NMKR_PHASE2_LOG_DIR="$STATE" NMKR_REAL_SYNC_CONFIRM=I_UNDERSTAND_THIS_MUTATES_DEV NMKR_PHASE16A_CONFIRM=I_AUTHORIZE_EXACTLY_ONE_START bash "$ROOT/scripts/nmkr-real-sync-phase16a.sh"
D="$TMP/auth"; mkdir -m700 "$D"; mkdir -p "$D/runs/phase15"; chmod 700 "$D/runs" "$D/runs/phase15"; R="$D/runs/phase15/real-sync-preflight.receipt.json"; C="$D/runs/phase15/real-sync-preflight.receipt.consumed.json"; make_receipt "$R"; PRE="$TMP/pre.json"; POST="$TMP/post.json"; state_json 1 1 1 1 abc >"$PRE"; state_json 2 2 2 2 abc >"$POST"; LOG="$TMP/driver.log"
base_env "$D" NMKR_PHASE16A_RECEIPT="$R" NMKR_FAKE_PRE_STATE="$PRE" NMKR_FAKE_POST_STATE="$POST" NMKR_FAKE_DRIVER_LOG="$LOG" NMKR_FAKE_CONSUMED="$C" bash "$ROOT/scripts/nmkr-real-sync-phase16a.sh" >"$TMP/auth.out" 2>&1 || { cat "$TMP/auth.out"; fail "authorized synthetic path failed"; }
[[ "$(wc -l <"$LOG")" -eq 2 ]] || fail "driver invoked more than once"
grep -q '^before:unconsumed$' "$LOG" || fail "driver started after premature consumption sentinel failed"
grep -q '^after:consumed$' "$LOG" || fail "receipt not consumed during final authorization"
[[ -f "$C" && ! -f "$R" ]] || fail "receipt consumption was not atomic"
pass "authorized synthetic path invokes driver exactly once after initial validation and consumes during final authorization"

D="$TMP/active_override"; mkdir -m700 "$D"; mkdir -p "$D/runs/phase15"; chmod 700 "$D/runs" "$D/runs/phase15"
R="$D/runs/phase15/real-sync-preflight.receipt.json"; C="$D/runs/phase15/real-sync-preflight.receipt.consumed.json"; make_receipt "$R"
PRE="$TMP/active_override.pre.json"; POST="$TMP/active_override.post.json"; LOG="$TMP/active_override.driver"
state_json 1 1 1 1 abc >"$PRE"; state_json 2 2 2 2 abc >"$POST"
base_env "$D" NMKR_DEPLOYED_PLUGIN_PATH="$ACTIVE_PLUGIN" NMKR_PHASE16A_RECEIPT="$R" NMKR_FAKE_PRE_STATE="$PRE" NMKR_FAKE_POST_STATE="$POST" NMKR_FAKE_DRIVER_LOG="$LOG" NMKR_FAKE_CONSUMED="$C" bash "$ROOT/scripts/nmkr-real-sync-phase16a.sh" >/dev/null 2>&1 || fail "explicit active deployed override unexpectedly failed"
pass "explicit deployed override resolving to active plugin checkout accepted"

deployment_refusal(){
  local name="$1"; shift
  D="$TMP/deploy_${name// /_}"; mkdir -m700 "$D"; mkdir -p "$D/runs/phase15"; chmod 700 "$D/runs" "$D/runs/phase15"
  R="$D/runs/phase15/real-sync-preflight.receipt.json"; C="$D/runs/phase15/real-sync-preflight.receipt.consumed.json"; make_receipt "$R"
  PRE="$TMP/deploy_${name// /_}.pre.json"; POST="$TMP/deploy_${name// /_}.post.json"; LOG="$TMP/deploy_${name// /_}.driver"
  state_json 1 1 1 1 abc >"$PRE"; state_json 2 2 2 2 abc >"$POST"; : >"$LOG"
  run_fail "deployment $name" base_env "$D" "$@" NMKR_PHASE16A_RECEIPT="$R" NMKR_FAKE_PRE_STATE="$PRE" NMKR_FAKE_POST_STATE="$POST" NMKR_FAKE_DRIVER_LOG="$LOG" NMKR_FAKE_CONSUMED="$C" bash "$ROOT/scripts/nmkr-real-sync-phase16a.sh"
  [[ ! -s "$LOG" ]] || fail "deployment $name invoked driver"
  [[ -f "$R" && ! -e "$C" ]] || fail "deployment $name consumed receipt"
}

reset_active_plugin
printf 'stale\n' >>"$ACTIVE_PLUGIN/phase16a-stale.txt"; git -C "$ACTIVE_PLUGIN" add phase16a-stale.txt; git -C "$ACTIVE_PLUGIN" -c user.email=a@b.invalid -c user.name=a commit -q -m "phase16a stale deployed fixture"
deployment_refusal "stale deployed HEAD"
reset_active_plugin
printf 'dirty\n' >>"$ACTIVE_PLUGIN/phase16a-dirty.txt"
deployment_refusal "dirty deployed checkout"
reset_active_plugin
deployment_refusal "override source checkout" NMKR_DEPLOYED_PLUGIN_PATH="$ROOT"
OTHER="$TMP/other-deploy"; git clone -q "$ROOT" "$OTHER"; git -C "$OTHER" checkout -q "$(git -C "$ROOT" rev-parse HEAD)"
deployment_refusal "override other checkout" NMKR_DEPLOYED_PLUGIN_PATH="$OTHER"
WP_PARENT="$TMP/wp-parent"; mkdir -p "$WP_PARENT/wp-content/plugins/nmkr-connect"; git -C "$WP_PARENT/wp-content/plugins" init -q; printf 'parent\n' >"$WP_PARENT/wp-content/plugins/nmkr-connect/fixture.txt"; git -C "$WP_PARENT/wp-content/plugins" add nmkr-connect/fixture.txt; git -C "$WP_PARENT/wp-content/plugins" -c user.email=a@b.invalid -c user.name=a commit -q -m parent
deployment_refusal "active plugin git top-level parent" WP_PATH="$WP_PARENT"
pass "active deployed checkout gate rejects stale dirty wrong override and parent worktree cases"

mutate_receipt(){ python3 - "$1" "$2" "$3" <<'PY'
import json,sys
p,k,v=sys.argv[1:4]
d=json.load(open(p)); d[k]=int(v); json.dump(d,open(p,'w'))
PY
}
set_receipt_ttl(){ python3 - "$1" "$2" <<'PY'
import json,sys
p,ttl=sys.argv[1:3]
d=json.load(open(p)); d['receipt_ttl_seconds']=int(ttl); d['expires_at_epoch']=d['created_at_epoch']+int(ttl); json.dump(d,open(p,'w'))
PY
}
for spec in "max_duration_seconds 300 accept" "max_duration_seconds 3600 accept" "poll_timeout_seconds 10 accept" "poll_timeout_seconds 60 accept" "receipt_ttl_seconds 60 accept" "receipt_ttl_seconds 300 accept"; do
  set -- $spec; field="$1"; value="$2"
  D="$TMP/bounds_${field}_${value}"; mkdir -m700 "$D"; mkdir -p "$D/runs/phase15"; chmod 700 "$D/runs" "$D/runs/phase15"
  R="$D/runs/phase15/real-sync-preflight.receipt.json"; C="$D/runs/phase15/real-sync-preflight.receipt.consumed.json"; make_receipt "$R"; if [[ "$field" == receipt_ttl_seconds ]]; then set_receipt_ttl "$R" "$value"; else mutate_receipt "$R" "$field" "$value"; fi
  PRE="$TMP/bounds_${field}_${value}.pre.json"; POST="$TMP/bounds_${field}_${value}.post.json"; LOG="$TMP/bounds_${field}_${value}.driver"
  state_json 1 1 1 1 abc >"$PRE"; state_json 2 2 2 2 abc >"$POST"
  base_env "$D" NMKR_PHASE16A_RECEIPT="$R" NMKR_FAKE_PRE_STATE="$PRE" NMKR_FAKE_POST_STATE="$POST" NMKR_FAKE_DRIVER_LOG="$LOG" NMKR_FAKE_CONSUMED="$C" bash "$ROOT/scripts/nmkr-real-sync-phase16a.sh" >/dev/null 2>&1 || fail "receipt bound $field=$value unexpectedly failed"
done
pass "Phase 15 receipt timeout boundary values accepted"
for spec in "max_duration_seconds 299" "max_duration_seconds 3601" "poll_timeout_seconds 9" "poll_timeout_seconds 61" "receipt_ttl_seconds 59" "receipt_ttl_seconds 301"; do
  set -- $spec; field="$1"; value="$2"
  D="$TMP/bounds_bad_${field}_${value}"; mkdir -m700 "$D"; mkdir -p "$D/runs/phase15"; chmod 700 "$D/runs" "$D/runs/phase15"
  R="$D/runs/phase15/real-sync-preflight.receipt.json"; C="$D/runs/phase15/real-sync-preflight.receipt.consumed.json"; make_receipt "$R"; mutate_receipt "$R" "$field" "$value"; LOG="$TMP/bounds_bad_${field}_${value}.driver"; : >"$LOG"
  run_fail "receipt bound $field=$value" base_env "$D" NMKR_PHASE16A_RECEIPT="$R" NMKR_FAKE_DRIVER_LOG="$LOG" NMKR_FAKE_CONSUMED="$C" bash "$ROOT/scripts/nmkr-real-sync-phase16a.sh"
  [[ ! -s "$LOG" ]] || fail "receipt bound $field=$value invoked driver"
  [[ -f "$R" && ! -e "$C" ]] || fail "receipt bound $field=$value consumed receipt"
done
pass "Phase 15 receipt timeout out-of-range values rejected before driver"
for case in "expiry plus one" "expiry minus one" "old creation future expiry" "unrelated future expiry" "expiry equals creation" "expiry before creation"; do
  D="$TMP/timing_${case// /_}"; mkdir -m700 "$D"; mkdir -p "$D/runs/phase15"; chmod 700 "$D/runs" "$D/runs/phase15"
  R="$D/runs/phase15/real-sync-preflight.receipt.json"; C="$D/runs/phase15/real-sync-preflight.receipt.consumed.json"; make_receipt "$R"; LOG="$TMP/timing_${case// /_}.driver"; : >"$LOG"
  python3 - "$R" "$case" <<'PY'
import json,sys,time
p,case=sys.argv[1:3]
d=json.load(open(p))
if case == 'expiry plus one':
    d['expires_at_epoch'] = d['created_at_epoch'] + d['receipt_ttl_seconds'] + 1
elif case == 'expiry minus one':
    d['expires_at_epoch'] = d['created_at_epoch'] + d['receipt_ttl_seconds'] - 1
elif case == 'old creation future expiry':
    d['created_at_epoch'] = int(time.time()) - 1000
    d['expires_at_epoch'] = int(time.time()) + 200
elif case == 'unrelated future expiry':
    d['expires_at_epoch'] = int(time.time()) + 123
elif case == 'expiry equals creation':
    d['expires_at_epoch'] = d['created_at_epoch']
elif case == 'expiry before creation':
    d['expires_at_epoch'] = d['created_at_epoch'] - 1
json.dump(d,open(p,'w'))
PY
  run_fail "receipt timing $case" base_env "$D" NMKR_PHASE16A_RECEIPT="$R" NMKR_FAKE_DRIVER_LOG="$LOG" NMKR_FAKE_CONSUMED="$C" bash "$ROOT/scripts/nmkr-real-sync-phase16a.sh"
  [[ ! -s "$LOG" ]] || fail "receipt timing $case invoked driver"
  [[ -f "$R" && ! -e "$C" ]] || fail "receipt timing $case consumed receipt"
done
pass "receipt creation expiry TTL mismatches rejected before driver"

for sync_class in absent terminal active unknown; do
  D="$TMP/post-sync-$sync_class"; mkdir -m700 "$D"; mkdir -p "$D/runs/phase15"; chmod 700 "$D/runs" "$D/runs/phase15"
  R="$D/runs/phase15/real-sync-preflight.receipt.json"; C="$D/runs/phase15/real-sync-preflight.receipt.consumed.json"; make_receipt "$R"
  PRE="$TMP/post-sync-$sync_class.pre.json"; POST="$TMP/post-sync-$sync_class.post.json"; LOG="$TMP/post-sync-$sync_class.driver"
  state_json 1 1 1 1 abc absent >"$PRE"; state_json 2 2 2 2 abc "$sync_class" >"$POST"
  if [[ "$sync_class" == absent || "$sync_class" == terminal ]]; then
    base_env "$D" NMKR_PHASE16A_RECEIPT="$R" NMKR_FAKE_PRE_STATE="$PRE" NMKR_FAKE_POST_STATE="$POST" NMKR_FAKE_DRIVER_LOG="$LOG" NMKR_FAKE_CONSUMED="$C" bash "$ROOT/scripts/nmkr-real-sync-phase16a.sh" >"$TMP/post-sync-$sync_class.out" 2>&1 || { cat "$TMP/post-sync-$sync_class.out"; fail "post-run $sync_class state unexpectedly failed"; }
    grep -q '^after:consumed$' "$LOG" || fail "post-run $sync_class did not consume receipt"
    pass "post-run sync_data_classification=$sync_class accepted"
  else
    if base_env "$D" NMKR_PHASE16A_RECEIPT="$R" NMKR_FAKE_PRE_STATE="$PRE" NMKR_FAKE_POST_STATE="$POST" NMKR_FAKE_DRIVER_LOG="$LOG" NMKR_FAKE_CONSUMED="$C" bash "$ROOT/scripts/nmkr-real-sync-phase16a.sh" >"$TMP/post-sync-$sync_class.out" 2>&1; then
      fail "post-run $sync_class state unexpectedly passed"
    fi
    [[ "$(wc -l <"$LOG")" -eq 2 ]] || fail "post-run $sync_class invoked driver more than once or skipped authorized Start"
    grep -q '^after:consumed$' "$LOG" || fail "post-run $sync_class did not consume before final failure"
    [[ -f "$C" ]] || fail "post-run $sync_class did not preserve consumed receipt"
    pass "post-run sync_data_classification=$sync_class rejected after one authorized synthetic Start"
  fi
done
for case in "allowed origin mismatch" "wordpress home mismatch" "wordpress siteurl mismatch" "http origin" "explicit port" "path query fragment" "missing administrator identifier" "capability failure"; do
  D="$TMP/${case// /_}"; mkdir -m700 "$D"; mkdir -p "$D/runs/phase15"; chmod 700 "$D/runs" "$D/runs/phase15"; R="$D/runs/phase15/real-sync-preflight.receipt.json"; make_receipt "$R"; PRE="$TMP/${case// /_}.pre.json"; POST="$TMP/${case// /_}.post.json"; state_json 1 1 1 1 abc >"$PRE"; state_json 2 2 2 2 abc >"$POST"; : >"$TMP/$case.driver"
  extra=(WP_ADMIN_USER="admin@example.invalid")
  case "$case" in
    "allowed origin mismatch") extra=(NMKR_REAL_SYNC_ALLOWED_ORIGIN="https://other.invalid");;
    "wordpress home mismatch"|"wordpress siteurl mismatch") extra=(NMKR_FAKE_WP_ORIGIN="https://other.invalid");;
    "http origin") extra=(NMKR_REAL_SYNC_ALLOWED_ORIGIN="http://example.invalid" WP_BASE_URL="http://example.invalid" NMKR_FAKE_WP_ORIGIN="http://example.invalid");;
    "explicit port") extra=(NMKR_REAL_SYNC_ALLOWED_ORIGIN="https://example.invalid:443" WP_BASE_URL="https://example.invalid:443" NMKR_FAKE_WP_ORIGIN="https://example.invalid:443");;
    "path query fragment") extra=(NMKR_REAL_SYNC_ALLOWED_ORIGIN="https://example.invalid/path?x=1#f" WP_BASE_URL="https://example.invalid/path?x=1#f" NMKR_FAKE_WP_ORIGIN="https://example.invalid/path?x=1#f");;
    "missing administrator identifier") extra=(WP_ADMIN_USER="");;
    "capability failure") extra=(NMKR_FAKE_CAPABILITY="denied");;
  esac
  run_fail "$case" base_env "$D" "${extra[@]}" NMKR_PHASE16A_RECEIPT="$R" NMKR_FAKE_PRE_STATE="$PRE" NMKR_FAKE_POST_STATE="$POST" NMKR_FAKE_DRIVER_LOG="$TMP/$case.driver" NMKR_FAKE_CONSUMED="$D/runs/phase15/real-sync-preflight.receipt.consumed.json" bash "$ROOT/scripts/nmkr-real-sync-phase16a.sh"
  pass "$case synthetic guard"
done
D="$TMP/origin_normalization"; mkdir -m700 "$D"; mkdir -p "$D/runs/phase15"; chmod 700 "$D/runs" "$D/runs/phase15"; R="$D/runs/phase15/real-sync-preflight.receipt.json"; C="$D/runs/phase15/real-sync-preflight.receipt.consumed.json"; make_receipt "$R"; PRE="$TMP/origin_norm.pre.json"; POST="$TMP/origin_norm.post.json"; state_json 1 1 1 1 abc >"$PRE"; state_json 2 2 2 2 abc >"$POST"; LOG="$TMP/origin_norm.driver"
base_env "$D" NMKR_REAL_SYNC_ALLOWED_ORIGIN="https://EXAMPLE.INVALID." WP_BASE_URL="https://example.invalid" NMKR_FAKE_WP_ORIGIN="https://example.invalid." NMKR_PHASE16A_RECEIPT="$R" NMKR_FAKE_PRE_STATE="$PRE" NMKR_FAKE_POST_STATE="$POST" NMKR_FAKE_DRIVER_LOG="$LOG" NMKR_FAKE_CONSUMED="$C" bash "$ROOT/scripts/nmkr-real-sync-phase16a.sh" >/dev/null 2>&1 || fail "origin normalization unexpectedly failed"
pass "origin hostname case and trailing-dot normalization"
for case in "consumed destination collision" "expired receipt" "insufficient remaining lifetime after dashboard bootstrap" "stale backup"; do
  D="$TMP/${case// /_}"; mkdir -m700 "$D"; mkdir -p "$D/runs/phase15"; chmod 700 "$D/runs" "$D/runs/phase15"; R="$D/runs/phase15/real-sync-preflight.receipt.json"; make_receipt "$R"
  case "$case" in
    "consumed destination collision") touch "$D/runs/phase15/real-sync-preflight.receipt.consumed.json";;
    "expired receipt") make_receipt "$R" -1;;
    "insufficient remaining lifetime after dashboard bootstrap") make_receipt "$R" 60 -40;;
    "stale backup") python3 - "$R" -c 'import json,sys,time; d=json.load(open(sys.argv[1])); d["backup_confirmed_at_epoch"]=int(time.time())-900000; json.dump(d,open(sys.argv[1],"w"))';;
  esac
  : >"$TMP/$case.driver"; run_fail "$case" base_env "$D" NMKR_PHASE16A_RECEIPT="$R" NMKR_FAKE_DRIVER_LOG="$TMP/$case.driver" bash "$ROOT/scripts/nmkr-real-sync-phase16a.sh"
  if [[ "$case" == "insufficient remaining lifetime after dashboard bootstrap" || "$case" == "stale backup" ]]; then
    [[ -s "$TMP/$case.driver" ]] || fail "$case did not reach driver/final authorization"
  else
    [[ ! -s "$TMP/$case.driver" ]] || fail "$case invoked driver"
  fi
done
STATE_HELPER_HARNESS="$TMP/state-helper-harness.php"; cat >"$STATE_HELPER_HARNESS" <<'PHP'
<?php
define('ABSPATH', __DIR__);
define('ARRAY_A', 'ARRAY_A');
function esc_sql($value) { return str_replace("'", "''", (string) $value); }
function wp_json_encode($value, $flags = 0) { return json_encode($value, $flags); }
function get_option($key, $default = false) {
    $case = getenv('NMKR_PHASE16A_FAKE_OPTION_CASE');
    if ($case === 'near-terminal-residue' && $key === 'nmkr_sync_near_completion') { return true; }
    if ($case === 'near-in-progress' && ($key === 'nmkr_sync_in_progress' || $key === 'nmkr_sync_near_completion')) { return true; }
    if ($case === 'near-active-status' && $key === 'nmkr_sync_status') { return 'processing'; }
    if ($case === 'near-active-status' && $key === 'nmkr_sync_near_completion') { return true; }
    if ($case === 'near-active-sync-data' && $key === 'nmkr_sync_data') { return array('status' => 'processing'); }
    if ($case === 'near-active-sync-data' && $key === 'nmkr_sync_near_completion') { return true; }
    if ($case === 'near-progress-only' && $key === 'nmkr_sync_progress') { return 50; }
    if ($case === 'near-progress-only' && $key === 'nmkr_sync_near_completion') { return true; }
    if ($case === 'near-terminal-status-data' && $key === 'nmkr_sync_status') { return 'completed'; }
    if ($case === 'near-terminal-status-data' && $key === 'nmkr_sync_data') { return array('status' => 'completed'); }
    if ($case === 'near-terminal-status-data' && $key === 'nmkr_sync_near_completion') { return true; }
    $fixture_path = getenv('NMKR_PHASE16A_FAKE_OPTIONS_JSON_FILE');
    $fixture_raw = $fixture_path ? file_get_contents($fixture_path) : (getenv('NMKR_PHASE16A_FAKE_OPTIONS_JSON') ?: '{}');
    $fixture = json_decode($fixture_raw, true);
    if (is_array($fixture) && array_key_exists($key, $fixture)) { return $fixture[$key]; }
    if ($key === 'nmkr_connect_options') { return array('sync_profile' => 'light', 'sync_batch_size' => 1, 'sync_batch_delay' => 3, 'api_key' => 'synthetic'); }
    if ($key === 'cron') { return array('version' => 2); }
    return $default;
}
function wp_using_ext_object_cache() { return false; }
function get_transient($key) { return false; }
class NMKR16FakeWpdb {
    public $prefix = 'wp_';
    private $history;
    public function __construct() { $this->history = json_decode(getenv('NMKR_PHASE16A_FAKE_HISTORY_JSON') ?: '[]', true); }
    public function prepare($sql, $value) { return str_replace('%s', "'" . str_replace("'", "''", $value) . "'", $sql); }
    private function tableFrom($sql) { return preg_match('/FROM `([^`]+)`/', $sql, $m) ? $m[1] : ''; }
    private function terminal($row) { return in_array(strtolower($row['status']), array('completed','success','failed','error','stopped','cancelled','aborted'), true); }
    private function active($row) { return in_array(strtolower($row['status']), array('initializing','processing','processing_projects','processing_tokens','in_progress','running','pending','active','started'), true); }
    private function boundedRows($sql) {
        $rows = array_values(array_filter($this->history, function($row) { return $this->terminal($row); }));
        if (preg_match('/id <= ([0-9]+)/', $sql, $m)) { $limit = (int) $m[1]; $rows = array_values(array_filter($rows, function($row) use ($limit) { return (int) $row['id'] <= $limit; })); }
        usort($rows, function($a, $b) { return (int) $a['id'] <=> (int) $b['id']; });
        return $rows;
    }
    public function get_results($sql, $format = null) { return $this->boundedRows($sql); }
    public function get_var($sql) {
        if (strpos($sql, 'SHOW TABLES LIKE') === 0 && preg_match("/'([^']+)'/", $sql, $m)) { return $m[1]; }
        if (strpos($sql, 'SHOW COLUMNS FROM') === 0) { return null; }
        $table = $this->tableFrom($sql);
        if (strpos($sql, 'MAX(id)') !== false) { return $table === 'wp_nmkr_sync_stats' && $this->history ? max(array_map(function($row) { return (int) $row['id']; }, $this->history)) : 0; }
        if (strpos($sql, 'ORDER BY id DESC LIMIT 1') !== false) {
            if (!$this->history) { return ''; }
            $rows = $this->history; usort($rows, function($a, $b) { return (int) $b['id'] <=> (int) $a['id']; });
            return $rows[0]['status'];
        }
        if (strpos($sql, 'end_time IS NOT NULL') !== false && strpos($sql, 'SELECT MAX(id)') !== false) {
            if (!$this->history) { return 0; }
            $max = max(array_map(function($row) { return (int) $row['id']; }, $this->history));
            foreach ($this->history as $row) { if ((int) $row['id'] === $max) { return empty($row['end_time']) ? 0 : 1; } }
            return 0;
        }
        if (strpos($sql, 'COUNT(*)') !== false) {
            if ($table !== 'wp_nmkr_sync_stats') { return 0; }
            if (strpos($sql, 'BINARY status IN') !== false && strpos($sql, "'processing'") !== false) { return count(array_filter($this->history, function($row) { return $this->active($row); })); }
            if (strpos($sql, 'BINARY status IN') !== false) { return count($this->boundedRows($sql)); }
            return count($this->history);
        }
        return '';
    }
}
$wpdb = new NMKR16FakeWpdb();
require getenv('NMKR_PHASE16A_STATE_HELPER');
PHP
state_helper_snapshot(){
  local rows="$1" limit="${2-__unset__}" out="$3" options="${4:-{}}"
  local optfile; optfile="$TMP/options-${name:-snapshot}-$RANDOM.json"
  printf '%s' "$options" >"$optfile"
  if [[ "$limit" == __unset__ ]]; then
    env -u NMKR_PHASE16A_HISTORY_MAX_ID NMKR_PHASE16A_FAKE_HISTORY_JSON="$rows" NMKR_PHASE16A_FAKE_OPTIONS_JSON_FILE="$optfile" NMKR_PHASE16A_STATE_HELPER="$ROOT/scripts/nmkr-real-sync-phase16a-state.php" php "$STATE_HELPER_HARNESS" >"$out"
  else
    NMKR_PHASE16A_HISTORY_MAX_ID="$limit" NMKR_PHASE16A_FAKE_HISTORY_JSON="$rows" NMKR_PHASE16A_FAKE_OPTIONS_JSON_FILE="$optfile" NMKR_PHASE16A_STATE_HELPER="$ROOT/scripts/nmkr-real-sync-phase16a-state.php" php "$STATE_HELPER_HARNESS" >"$out"
  fi
}
state_json_get(){ python3 -c 'import json,sys; print(json.load(open(sys.argv[1]))[sys.argv[2]])' "$1" "$2"; }
EMPTY_ROWS='[]'
ONE_ROW='[{"id":1,"sync_type":"manual","start_time":"2026-01-01 00:00:00","end_time":"2026-01-01 00:01:00","status":"completed","items_processed":1,"items_successful":1,"items_failed":0,"updated_at":"2026-01-01 00:01:00"}]'
TWO_ROWS='[{"id":1,"sync_type":"manual","start_time":"2026-01-01 00:00:00","end_time":"2026-01-01 00:01:00","status":"completed","items_processed":1,"items_successful":1,"items_failed":0,"updated_at":"2026-01-01 00:01:00"},{"id":2,"sync_type":"manual","start_time":"2026-01-02 00:00:00","end_time":"2026-01-02 00:01:00","status":"completed","items_processed":2,"items_successful":2,"items_failed":0,"updated_at":"2026-01-02 00:01:00"}]'
THREE_ROWS='[{"id":1,"sync_type":"manual","start_time":"2026-01-01 00:00:00","end_time":"2026-01-01 00:01:00","status":"completed","items_processed":1,"items_successful":1,"items_failed":0,"updated_at":"2026-01-01 00:01:00"},{"id":2,"sync_type":"manual","start_time":"2026-01-02 00:00:00","end_time":"2026-01-02 00:01:00","status":"completed","items_processed":2,"items_successful":2,"items_failed":0,"updated_at":"2026-01-02 00:01:00"},{"id":3,"sync_type":"manual","start_time":"2026-01-03 00:00:00","end_time":"2026-01-03 00:01:00","status":"completed","items_processed":3,"items_successful":3,"items_failed":0,"updated_at":"2026-01-03 00:01:00"}]'
MUTATED_THREE_ROWS='[{"id":1,"sync_type":"manual","start_time":"2026-01-01 00:00:00","end_time":"2026-01-01 00:01:00","status":"completed","items_processed":1,"items_successful":1,"items_failed":0,"updated_at":"2026-01-01 00:01:00"},{"id":2,"sync_type":"manual","start_time":"2026-01-02 00:00:00","end_time":"2026-01-02 00:01:00","status":"completed","items_processed":99,"items_successful":2,"items_failed":0,"updated_at":"2026-01-02 00:01:00"},{"id":3,"sync_type":"manual","start_time":"2026-01-03 00:00:00","end_time":"2026-01-03 00:01:00","status":"completed","items_processed":3,"items_successful":3,"items_failed":0,"updated_at":"2026-01-03 00:01:00"}]'
state_helper_snapshot "$EMPTY_ROWS" __unset__ "$TMP/state-empty-pre.json"
state_helper_snapshot "$ONE_ROW" 0 "$TMP/state-one-bounded-zero.json"
state_helper_snapshot "$ONE_ROW" __unset__ "$TMP/state-one-unbounded.json"
[[ "$(state_json_get "$TMP/state-empty-pre.json" terminal_history_digest)" == "$(state_json_get "$TMP/state-one-bounded-zero.json" terminal_history_digest)" ]] || fail "zero history boundary did not preserve empty digest"
[[ "$(state_json_get "$TMP/state-empty-pre.json" terminal_history_digest)" != "$(state_json_get "$TMP/state-one-unbounded.json" terminal_history_digest)" ]] || fail "unbounded digest did not include new history row"
[[ "$(state_json_get "$TMP/state-one-bounded-zero.json" sync_history_total_count)" == 1 && "$(state_json_get "$TMP/state-one-bounded-zero.json" max_history_id)" == 1 && "$(state_json_get "$TMP/state-one-bounded-zero.json" latest_history_id)" == 1 ]] || fail "bounded zero snapshot hid post-run history counts"
pass "state helper honors explicit zero history boundary"
state_helper_snapshot "$TWO_ROWS" __unset__ "$TMP/state-two-pre.json"
state_helper_snapshot "$THREE_ROWS" 2 "$TMP/state-three-bounded-two.json"
state_helper_snapshot "$MUTATED_THREE_ROWS" 2 "$TMP/state-three-mutated-bounded-two.json"
[[ "$(state_json_get "$TMP/state-two-pre.json" terminal_history_digest)" == "$(state_json_get "$TMP/state-three-bounded-two.json" terminal_history_digest)" ]] || fail "positive history boundary included new row"
[[ "$(state_json_get "$TMP/state-two-pre.json" terminal_history_digest)" != "$(state_json_get "$TMP/state-three-mutated-bounded-two.json" terminal_history_digest)" ]] || fail "positive boundary did not detect pre-existing row change"
pass "state helper honors positive history boundary and detects bounded row changes"
state_helper_snapshot "$THREE_ROWS" __unset__ "$TMP/state-three-unset.json"
[[ "$(state_json_get "$TMP/state-three-unset.json" terminal_history_digest)" != "$(state_json_get "$TMP/state-three-bounded-two.json" terminal_history_digest)" ]] || fail "unset history boundary did not include all rows"
pass "state helper unset history boundary remains unbounded"
for invalid_limit in -1 1.5 1e3 abc; do
  if NMKR_PHASE16A_HISTORY_MAX_ID="$invalid_limit" NMKR_PHASE16A_FAKE_HISTORY_JSON="$ONE_ROW" NMKR_PHASE16A_STATE_HELPER="$ROOT/scripts/nmkr-real-sync-phase16a-state.php" php "$STATE_HELPER_HARNESS" >"$TMP/state-invalid-$invalid_limit.out" 2>&1; then
    fail "invalid history boundary $invalid_limit unexpectedly passed"
  fi
  ! rg -n 'manual|completed|items_processed|2026-01' "$TMP/state-invalid-$invalid_limit.out" >/dev/null || fail "invalid history boundary leaked row content"
done
pass "state helper invalid history boundaries fail closed without row leaks"
option_count_case(){
  local name="$1" options="$2" expected="$3" out
  out="$TMP/option-$name.json"
  NMKR_PHASE16A_FAKE_OPTION_CASE="$name" state_helper_snapshot "$EMPTY_ROWS" __unset__ "$out" "$options"
  local actual; actual="$(state_json_get "$out" option_active_marker_count)"
  [[ "$actual" == "$expected" ]] || fail "option active marker case $name expected $expected got $actual"
}
option_count_case "near-terminal-residue" '{"nmkr_sync_near_completion":true}' 0
option_count_case "near-in-progress" '{"nmkr_sync_in_progress":true,"nmkr_sync_near_completion":true}' 2
option_count_case "near-active-status" '{"nmkr_sync_status":"processing","nmkr_sync_near_completion":true}' 2
option_count_case "near-active-sync-data" '{"nmkr_sync_data":{"status":"processing"},"nmkr_sync_near_completion":true}' 2
option_count_case "near-progress-only" '{"nmkr_sync_progress":50,"nmkr_sync_near_completion":true}' 1
option_count_case "near-terminal-status-data" '{"nmkr_sync_status":"completed","nmkr_sync_data":{"status":"completed"},"nmkr_sync_near_completion":true}' 0
pass "state helper gates near_completion on durable active sync state"
ACTIVE_EMPTY_END='[{"id":4,"sync_type":"manual","start_time":"2026-01-04 00:00:00","end_time":"","status":"processing","items_processed":0,"items_successful":0,"items_failed":0,"updated_at":"2026-01-04 00:00:00"}]'
ACTIVE_POPULATED_END='[{"id":5,"sync_type":"manual","start_time":"2026-01-05 00:00:00","end_time":"2026-01-05 00:01:00","status":"processing","items_processed":0,"items_successful":0,"items_failed":0,"updated_at":"2026-01-05 00:01:00"}]'
TERMINAL_POPULATED_END='[{"id":6,"sync_type":"manual","start_time":"2026-01-06 00:00:00","end_time":"2026-01-06 00:01:00","status":"completed","items_processed":1,"items_successful":1,"items_failed":0,"updated_at":"2026-01-06 00:01:00"}]'
MIXED_ACTIVE_POPULATED='[{"id":6,"sync_type":"manual","start_time":"2026-01-06 00:00:00","end_time":"2026-01-06 00:01:00","status":"completed","items_processed":1,"items_successful":1,"items_failed":0,"updated_at":"2026-01-06 00:01:00"},{"id":7,"sync_type":"manual","start_time":"2026-01-07 00:00:00","end_time":"2026-01-07 00:01:00","status":"processing","items_processed":0,"items_successful":0,"items_failed":0,"updated_at":"2026-01-07 00:01:00"}]'
UNKNOWN_HISTORY='[{"id":8,"sync_type":"manual","start_time":"2026-01-08 00:00:00","end_time":"2026-01-08 00:01:00","status":"mystery","items_processed":0,"items_successful":0,"items_failed":0,"updated_at":"2026-01-08 00:01:00"}]'
state_helper_snapshot "$ACTIVE_EMPTY_END" __unset__ "$TMP/history-active-empty.json"
state_helper_snapshot "$ACTIVE_POPULATED_END" __unset__ "$TMP/history-active-populated.json"
state_helper_snapshot "$TERMINAL_POPULATED_END" __unset__ "$TMP/history-terminal-populated.json"
state_helper_snapshot "$MIXED_ACTIVE_POPULATED" __unset__ "$TMP/history-mixed-active.json"
state_helper_snapshot "$UNKNOWN_HISTORY" __unset__ "$TMP/history-unknown.json"
[[ "$(state_json_get "$TMP/history-active-empty.json" active_history_count)" == 1 ]] || fail "active empty end_time not counted"
[[ "$(state_json_get "$TMP/history-active-populated.json" active_history_count)" == 1 ]] || fail "active populated end_time not counted"
[[ "$(state_json_get "$TMP/history-terminal-populated.json" active_history_count)" == 0 ]] || fail "terminal row counted active"
[[ "$(state_json_get "$TMP/history-mixed-active.json" active_history_count)" == 1 ]] || fail "mixed active populated end_time not counted"
[[ "$(state_json_get "$TMP/history-unknown.json" active_history_count)" == 0 ]] || fail "unknown status counted active"
pass "state helper counts active history regardless of end_time without counting terminal or unknown statuses"
state_json_active_history(){ local history="$1" metrics="$2" maxh="$3" maxm="$4" digest="$5"; cat <<JSON
{"schema_version":1,"required_tables_present":{"projects":true,"tokens":true,"token_details":true,"sync_stats":true,"sync_metrics":true},"project_count":1,"project_max_id":1,"token_count":1,"token_max_id":1,"token_detail_count":1,"token_detail_max_id":1,"sync_history_total_count":$history,"active_history_count":1,"terminal_history_count":$history,"max_history_id":$maxh,"metrics_total_count":$metrics,"max_metrics_id":$maxm,"duplicate_project_uid_count":0,"duplicate_token_uid_count":0,"duplicate_token_detail_uid_count":0,"invalid_relationship_count":0,"impossible_counter_count":0,"option_active_marker_count":0,"transient_active_marker_count":0,"stale_recovery_marker_count":0,"heartbeat_worker_evidence_count":0,"sync_data_classification":"absent","blocked_cron_hook_counts":{"nmkr_execute_sync_background":0,"nmkr_process_batch_hook":0,"nmkr_sync_cron_hook":0,"nmkr_install_sync_cron_hook":0},"blocked_sync_cron_count":0,"cron_inspectable":true,"light_profile_guard":true,"api_key_present":true,"last_sync_time_matches_latest_metrics":true,"terminal_history_digest":"$digest","latest_history_id":$maxh,"latest_history_status_classification":"active","latest_history_completed":false,"latest_history_end_time_valid":true,"snapshot_epoch":1}
JSON
}
D="$TMP/active_history_authorization"; mkdir -m700 "$D"; mkdir -p "$D/runs/phase15"; chmod 700 "$D/runs" "$D/runs/phase15"; R="$D/runs/phase15/real-sync-preflight.receipt.json"; C="$D/runs/phase15/real-sync-preflight.receipt.consumed.json"; make_receipt "$R"
PRE="$TMP/active-history-auth.pre.json"; POST="$TMP/active-history-auth.post.json"; LOG="$TMP/active-history-auth.driver"; state_json_active_history 1 1 1 1 abc >"$PRE"; state_json 2 2 2 2 abc >"$POST"; : >"$LOG"
run_fail "active history with populated end_time blocks authorization" base_env "$D" NMKR_PHASE16A_RECEIPT="$R" NMKR_FAKE_PRE_STATE="$PRE" NMKR_FAKE_POST_STATE="$POST" NMKR_FAKE_DRIVER_LOG="$LOG" NMKR_FAKE_CONSUMED="$C" bash "$ROOT/scripts/nmkr-real-sync-phase16a.sh"
grep -q "^before:unconsumed$" "$LOG" || fail "active history authorization did not reach final pre-state guard"
! grep -q "^after:consumed$" "$LOG" || fail "active history authorization consumed receipt"
[[ -f "$R" && ! -e "$C" ]] || fail "active history authorization consumed receipt artifact"
pass "controller rejects active history snapshot before receipt consumption and synthetic Start"
node --input-type=module <<'NODE' >"$TMP/driver-state.out"
import {runExactlyOnceSync, actionFromRequestLike, routeDecisionForAction, frozenRouteDecisionForUrl, buildStartForm, buildProgressForm} from './scripts/nmkr-real-sync-phase16a-driver.mjs';
const secret='nonce-fixture-value'; let starts=[], polls=[];
const fakeClock = () => {
  let fakeNow = 0;
  return { now: () => fakeNow, sleep: async (ms) => { fakeNow += ms; } };
};
const sf=buildStartForm(secret), pf=buildProgressForm(secret);
if(sf.action!=='nmkr_start_sync'||sf.nonce!==secret||Object.hasOwn(sf,'nmkr_sync_nonce')) throw Error('Start form shape failed');
if(pf.action!=='nmkr_sync_progress'||pf.nonce!==secret||Object.hasOwn(pf,'nmkr_sync_nonce')) throw Error('Progress form shape failed');
let result=await runExactlyOnceSync({nonce:secret,receipt:{maxDurationSeconds:20,pollTimeoutSeconds:5},sleep:async()=>{},transport:{start:async x=>{starts.push(x.nonce);return {status:200,json:{success:true}}},poll:async x=>{polls.push(x.nonce); return polls.length===1?{status:200,json:{data:{progress:10,in_progress:true,metrics:{api_requests:1}}}}:{status:200,json:{data:{progress:100,completed:true}}}}}});
if(!result.ok||starts.length!==1||starts[0]!==secret||polls.some(n=>n!==secret)) throw Error('nonce propagation failed');
if(JSON.stringify(result).includes(secret)) throw Error('nonce leaked in sanitized output');
if(actionFromRequestLike({url:'https://x/wp-admin/admin-ajax.php',method:'POST',postData:'action=nmkr_start_sync'})!=='nmkr_start_sync') throw Error('POST action parse failed');
if(routeDecisionForAction('nmkr_check_api_status','prepare')!=='synthetic-ok') throw Error('api status route failed');
if(routeDecisionForAction('heartbeat','frozen')!=='block'||routeDecisionForAction('nmkr_get_sync_statistics','frozen')!=='block') throw Error('frozen blocking failed');
if(routeDecisionForAction('nmkr_start_sync','after-start',true)!=='block') throw Error('second Start not blocked');
const base='https://example.invalid';
for (const path of ['/', '/?foo=bar', '/wp-json/', '/wp-json/example/v1/test', '/example-pretty-permalink/', '/index.php?rest_route=/example', '/wp-admin/admin.php?page=nmkr-connect-dashboard', '/wp-admin/admin-ajax.php', '/wp-content/plugins/nmkr-connect/example.js', '/wp-includes/css/example.css']) {
  if (frozenRouteDecisionForUrl(new URL(path, base).toString(), base) !== 'block') throw Error(`same-origin frozen URL was not blocked: ${path}`);
}
if (frozenRouteDecisionForUrl('https://example.invalid.attacker.invalid/wp-admin/admin-ajax.php', base) !== 'allow') throw Error('hostname substring matched as origin');
if (frozenRouteDecisionForUrl('https://user@example.invalid/wp-json/', base) !== 'block') throw Error('userinfo URL did not fail closed');
if (frozenRouteDecisionForUrl('https://[malformed', base) !== 'block') throw Error('malformed HTTP-like URL did not fail closed');
for (const url of ['about:blank', 'data:text/plain,ok', 'blob:https://example.invalid/token']) {
  if (frozenRouteDecisionForUrl(url, base) !== 'allow') throw Error(`non-HTTP browser URL misclassified: ${url}`);
}
let clock=fakeClock();
result=await runExactlyOnceSync({nonce:'n',receipt:{maxDurationSeconds:20,pollTimeoutSeconds:5},now:clock.now,sleep:clock.sleep,transport:{start:async()=>({status:200,json:{success:true}}),poll:async()=>({status:200,json:{data:{progress:10,in_progress:false}}})}}); if(!result.timeout) throw Error('explicit not in progress without terminal should not succeed');
result=await runExactlyOnceSync({nonce:'n',receipt:{maxDurationSeconds:20,pollTimeoutSeconds:5},sleep:async()=>{},transport:{start:async()=>({timeout:true}),poll:async()=>{throw Error('no poll')}}}); if(!result.ambiguous||result.sanitized.startCount!==1) throw Error('ambiguous retry failed');

for (const status of [301,400,401,403,409,500,503]) {
  const r=await runExactlyOnceSync({nonce:'n',receipt:{maxDurationSeconds:20,pollTimeoutSeconds:5},sleep:async()=>{},transport:{start:async()=>({status,json:{success:true}}),poll:async()=>{throw Error('poll forbidden')}}});
  if(!r.fatal || r.sanitized.startCount!==1) throw Error(`HTTP ${status} start was not fatal exactly once`);
}
clock=fakeClock();
let lm=await runExactlyOnceSync({nonce:'n',receipt:{maxDurationSeconds:20,pollTimeoutSeconds:5},now:clock.now,sleep:clock.sleep,transport:{start:async()=>({status:200,json:{success:true}}),poll:async()=>({status:200,json:{data:{progress:5,in_progress:true,live_metrics:{api_requests:1}}}})}});
if(!lm.timeout || !lm.sanitized.validLiveMetricsObserved) throw Error('live_metrics evidence failed');
clock=fakeClock();
let legacy=await runExactlyOnceSync({nonce:'n',receipt:{maxDurationSeconds:20,pollTimeoutSeconds:5},now:clock.now,sleep:clock.sleep,transport:{start:async()=>({status:200,json:{success:true}}),poll:async()=>({status:200,json:{data:{progress:5,in_progress:true,metrics:{api_requests:1}}}})}});
if(!legacy.sanitized.validLiveMetricsObserved) throw Error('legacy metrics fallback failed');
async function assertProgressSuccessFalse(json, label) {
  let pollCount=0, sleeps=0;
  const r=await runExactlyOnceSync({nonce:'n',receipt:{maxDurationSeconds:20,pollTimeoutSeconds:5},sleep:async()=>{sleeps++;},transport:{start:async()=>({status:200,json:{success:true}}),poll:async()=>{pollCount++; return {status:200,json};}}});
  if(!r.fatal || r.timeout || r.ambiguous) throw Error(`${label} was not immediate fatal`);
  if(r.sanitized.startCount!==1 || r.sanitized.pollCount!==1 || pollCount!==1 || sleeps!==0) throw Error(`${label} retried or counted incorrectly`);
  if(JSON.stringify(r).includes('Synchronization failed') || JSON.stringify(r).includes('payload-secret')) throw Error(`${label} leaked response content`);
}
await assertProgressSuccessFalse({success:false,data:{message:'Synchronization failed',detail:'payload-secret'}}, 'success false object data');
await assertProgressSuccessFalse({success:false,data:'Synchronization failed payload-secret'}, 'success false string data');
await assertProgressSuccessFalse({success:false}, 'success false no data');
let compatiblePolls=0;
let compatible=await runExactlyOnceSync({nonce:'n',receipt:{maxDurationSeconds:20,pollTimeoutSeconds:5},sleep:async()=>{},transport:{start:async()=>({status:200,json:{success:true}}),poll:async()=>++compatiblePolls===1?{status:200,json:{success:true,data:{progress:10,in_progress:true,live_metrics:{api_requests:1}}}}:{status:200,json:{success:true,data:{progress:100,completed:true}}}}});
if(!compatible.ok || compatible.sanitized.pollCount!==2) throw Error('success true progress compatibility failed');
compatiblePolls=0;
compatible=await runExactlyOnceSync({nonce:'n',receipt:{maxDurationSeconds:20,pollTimeoutSeconds:5},sleep:async()=>{},transport:{start:async()=>({status:200,json:{success:true}}),poll:async()=>++compatiblePolls===1?{status:200,json:{data:{progress:10,in_progress:true,live_metrics:{api_requests:1}}}}:{status:200,json:{data:{progress:100,completed:true}}}}});
if(!compatible.ok || compatible.sanitized.pollCount!==2) throw Error('missing success key progress compatibility failed');
console.log('driver route and nonce synthetic checks passed');
NODE
pass "driver nonce, POST routing, frozen same-origin blocking, progress success false, second Start, and ambiguous/no-terminal regressions"
find "$TMP" \( -path '*/playwright-report' -o -path '*/test-results' -o -path '*/blob-report' -o -path '*/playwright/.cache' -o -name '*.webm' -o -name 'trace.zip' \) -print -quit | grep -q . && fail "Playwright artifacts created"
if find "$TMP" -maxdepth 1 -type f -print0 | xargs -0 --no-run-if-empty rg -n 'nonce-fixture-value|private-token-fixture|cookie-fixture' >/dev/null 2>&1; then fail "fixture secret value appeared in output"; fi
npx playwright test --list --reporter=list 2>/dev/null | rg 'nmkr-real-sync-phase16a-driver' && fail "private driver discovered by Playwright"
pass "no external network, no browser artifacts, no nonce leak, and no private driver discovery"
