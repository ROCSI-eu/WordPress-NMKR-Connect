#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
TMP="$(mktemp -d)"; trap 'rm -rf "$TMP"' EXIT
fail(){ echo "FAIL: $1" >&2; exit 1; }
pass(){ echo "PASS: $1"; }
run_fail(){ local name="$1"; shift; local out="$TMP/${name// /_}.out"; if "$@" >"$out" 2>&1; then fail "$name unexpectedly passed"; fi; grep -E 'Phase 16A|failed gate|FAIL' "$out" >/dev/null || fail "$name did not emit sanitized failure"; pass "$name"; }

! rg -n 'driver-default|phase16a-driver\.mjs" >/dev/null' "$ROOT/scripts/nmkr-real-sync-phase16a.sh" >/dev/null || fail "premature private driver invocation remains"
pass "controller contains no premature driver execution"

WP="$TMP/wp"; STATE="$TMP/state"; mkdir -p "$WP" "$STATE"; chmod 700 "$TMP" "$STATE"
make_receipt(){ local path="$1" exp="${2:-600}"; local head; head="$(git -C "$ROOT" rev-parse HEAD)"; python3 - "$path" "$head" "$exp" <<'PY'
import json,os,sys,time
p,head,exp=sys.argv[1:4]; now=int(time.time())
d={'receipt_version':1,'purpose':'nmkr-real-sync-preflight','created_at_epoch':now,'expires_at_epoch':now+int(exp),'source_commit':head,'deployed_commit':head,'origin_sha256':'0'*64,'plugin_active':True,'admin_capability_ok':True,'db_state_clean':True,'api_key_present':True,'runtime_state_clean':True,'cron_state_clean':True,'object_cache_state_clean':True,'profile_guard_passed':True,'backup_confirmed':True,'backup_confirmed_at_epoch':now,'max_duration_seconds':300,'poll_timeout_seconds':10,'receipt_ttl_seconds':300}
json.dump(d,open(p,'w')); os.chmod(p,0o600)
PY
}
state_json(){ local history="$1" metrics="$2" maxh="$3" maxm="$4" digest="$5"; cat <<JSON
{"schema_version":1,"required_tables_present":{"projects":true,"tokens":true,"token_details":true,"sync_stats":true,"sync_metrics":true},"project_count":1,"project_max_id":1,"token_count":1,"token_max_id":1,"token_detail_count":1,"token_detail_max_id":1,"sync_history_total_count":$history,"active_history_count":0,"terminal_history_count":$history,"max_history_id":$maxh,"metrics_total_count":$metrics,"max_metrics_id":$maxm,"duplicate_project_uid_count":0,"duplicate_token_uid_count":0,"duplicate_token_detail_uid_count":0,"invalid_relationship_count":0,"impossible_counter_count":0,"option_active_marker_count":0,"transient_active_marker_count":0,"stale_recovery_marker_count":0,"heartbeat_worker_evidence_count":0,"sync_data_classification":"absent","blocked_cron_hook_counts":{"nmkr_execute_sync_background":0,"nmkr_process_batch_hook":0,"nmkr_sync_cron_hook":0,"nmkr_install_sync_cron_hook":0},"blocked_sync_cron_count":0,"cron_inspectable":true,"light_profile_guard":true,"api_key_present":true,"last_sync_time_matches_latest_metrics":true,"terminal_history_digest":"$digest","latest_history_id":$maxh,"latest_history_status_classification":"completed","latest_history_completed":true,"latest_history_end_time_valid":true,"snapshot_epoch":1}
JSON
}
FAKE_WP="$TMP/fake-wp"; cat >"$FAKE_WP" <<'SH'
#!/usr/bin/env bash
set -Eeuo pipefail
cmd=""; for a in "$@"; do [[ "$a" != --path=* ]] && { cmd="$a"; break; }; done
if [[ "$cmd" == core ]]; then exit 0; fi
if [[ "$cmd" == plugin ]]; then [[ "${NMKR_FAKE_PLUGIN_INACTIVE:-false}" == true ]] && exit 1 || exit 0; fi
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
base_env(){ local state_dir="$1"; shift; env -u CI -u GITHUB_ACTIONS -u GITLAB_CI -u CIRCLECI -u BUILDKITE -u TF_BUILD RUN_REAL_SYNC=true PW_SAVE_ARTIFACTS=false NMKR_REAL_SYNC_CONFIRM=I_UNDERSTAND_THIS_MUTATES_DEV NMKR_PHASE16A_CONFIRM=I_AUTHORIZE_EXACTLY_ONE_START WP_PATH="$WP" WP_CLI_BIN="$FAKE_WP" NMKR_PHASE2_LOG_DIR="$state_dir" NMKR_DEPLOYED_PLUGIN_PATH="$ROOT" NMKR_PHASE16A_PUBLIC_REGRESSION=true NMKR_PHASE16A_TEST_DRIVER_BIN="$FAKE_DRIVER" "$@"; }
run_refusal(){ local name="$1"; shift; local d="$TMP/$name"; mkdir -m700 "$d"; : >"$TMP/$name.driver"; run_fail "$name" env -u RUN_REAL_SYNC -u CI -u GITHUB_ACTIONS -u GITLAB_CI -u CIRCLECI -u BUILDKITE -u TF_BUILD NMKR_FAKE_DRIVER_LOG="$TMP/$name.driver" "$@"; [[ ! -s "$TMP/$name.driver" ]] || fail "$name invoked driver"; pass "$name driver zero-times"; }
run_refusal "default RUN_REAL_SYNC=false" WP_PATH="$WP" NMKR_PHASE2_LOG_DIR="$STATE" bash "$ROOT/scripts/nmkr-real-sync-phase16a.sh"
run_refusal "CI refusal before env" CI=true RUN_REAL_SYNC=true WP_PATH="$WP" NMKR_PHASE2_ENV_FILE="$TMP/nope" NMKR_PHASE2_LOG_DIR="$STATE" bash "$ROOT/scripts/nmkr-real-sync-phase16a.sh"
run_refusal "PW_SAVE_ARTIFACTS true" RUN_REAL_SYNC=true PW_SAVE_ARTIFACTS=true WP_PATH="$WP" NMKR_PHASE2_LOG_DIR="$STATE" NMKR_REAL_SYNC_CONFIRM=I_UNDERSTAND_THIS_MUTATES_DEV NMKR_PHASE16A_CONFIRM=I_AUTHORIZE_EXACTLY_ONE_START bash "$ROOT/scripts/nmkr-real-sync-phase16a.sh"
D="$TMP/auth"; mkdir -m700 "$D"; R="$D/real-sync-preflight.receipt.json"; C="$D/real-sync-preflight.receipt.consumed.json"; make_receipt "$R"; PRE="$TMP/pre.json"; POST="$TMP/post.json"; state_json 1 1 1 1 abc >"$PRE"; state_json 2 2 2 2 abc >"$POST"; LOG="$TMP/driver.log"
base_env "$D" NMKR_PHASE16A_RECEIPT="$R" NMKR_FAKE_PRE_STATE="$PRE" NMKR_FAKE_POST_STATE="$POST" NMKR_FAKE_DRIVER_LOG="$LOG" NMKR_FAKE_CONSUMED="$C" bash "$ROOT/scripts/nmkr-real-sync-phase16a.sh" >"$TMP/auth.out" 2>&1 || { cat "$TMP/auth.out"; fail "authorized synthetic path failed"; }
[[ "$(wc -l <"$LOG")" -eq 2 ]] || fail "driver invoked more than once"
grep -q '^before:unconsumed$' "$LOG" || fail "driver started after premature consumption sentinel failed"
grep -q '^after:consumed$' "$LOG" || fail "receipt not consumed during final authorization"
[[ -f "$C" && ! -f "$R" ]] || fail "receipt consumption was not atomic"
pass "authorized synthetic path invokes driver exactly once after initial validation and consumes during final authorization"
for case in "consumed destination collision" "expired receipt" "insufficient remaining lifetime after dashboard bootstrap" "stale backup"; do
  D="$TMP/${case// /_}"; mkdir -m700 "$D"; R="$D/real-sync-preflight.receipt.json"; make_receipt "$R"
  case "$case" in
    "consumed destination collision") touch "$D/real-sync-preflight.receipt.consumed.json";;
    "expired receipt") make_receipt "$R" -1;;
    "insufficient remaining lifetime after dashboard bootstrap") make_receipt "$R" 60;;
    "stale backup") python3 - "$R" -c 'import json,sys,time; d=json.load(open(sys.argv[1])); d["backup_confirmed_at_epoch"]=int(time.time())-900000; json.dump(d,open(sys.argv[1],"w"))';;
  esac
  : >"$TMP/$case.driver"; run_fail "$case" base_env "$D" NMKR_PHASE16A_RECEIPT="$R" NMKR_FAKE_DRIVER_LOG="$TMP/$case.driver" bash "$ROOT/scripts/nmkr-real-sync-phase16a.sh"
  if [[ "$case" == "insufficient remaining lifetime after dashboard bootstrap" || "$case" == "stale backup" ]]; then
    [[ -s "$TMP/$case.driver" ]] || fail "$case did not reach driver/final authorization"
  else
    [[ ! -s "$TMP/$case.driver" ]] || fail "$case invoked driver"
  fi
done
node --input-type=module <<'NODE' >"$TMP/driver-state.out"
import {runExactlyOnceSync, actionFromRequestLike, routeDecisionForAction} from './scripts/nmkr-real-sync-phase16a-driver.mjs';
const secret='nonce-fixture-value'; let starts=[], polls=[];
let result=await runExactlyOnceSync({nonce:secret,receipt:{maxDurationSeconds:20,pollTimeoutSeconds:5},sleep:async()=>{},transport:{start:async x=>{starts.push(x.nonce);return {status:200,json:{success:true}}},poll:async x=>{polls.push(x.nonce); return polls.length===1?{status:200,json:{data:{progress:10,in_progress:true,metrics:{api_requests:1}}}}:{status:200,json:{data:{progress:100,completed:true}}}}}});
if(!result.ok||starts.length!==1||starts[0]!==secret||polls.some(n=>n!==secret)) throw Error('nonce propagation failed');
if(JSON.stringify(result).includes(secret)) throw Error('nonce leaked in sanitized output');
if(actionFromRequestLike({url:'https://x/wp-admin/admin-ajax.php',method:'POST',postData:'action=nmkr_start_sync'})!=='nmkr_start_sync') throw Error('POST action parse failed');
if(routeDecisionForAction('nmkr_check_api_status','prepare')!=='synthetic-ok') throw Error('api status route failed');
if(routeDecisionForAction('heartbeat','frozen')!=='block'||routeDecisionForAction('nmkr_get_sync_statistics','frozen')!=='block') throw Error('frozen blocking failed');
if(routeDecisionForAction('nmkr_start_sync','after-start',true)!=='block') throw Error('second Start not blocked');
result=await runExactlyOnceSync({nonce:'n',receipt:{maxDurationSeconds:20,pollTimeoutSeconds:5},sleep:async()=>{},transport:{start:async()=>({status:200,json:{success:true}}),poll:async()=>({status:200,json:{data:{progress:10,in_progress:false}}})}}); if(!result.timeout) throw Error('explicit not in progress without terminal should not succeed');
result=await runExactlyOnceSync({nonce:'n',receipt:{maxDurationSeconds:20,pollTimeoutSeconds:5},sleep:async()=>{},transport:{start:async()=>({timeout:true}),poll:async()=>{throw Error('no poll')}}}); if(!result.ambiguous||result.sanitized.startCount!==1) throw Error('ambiguous retry failed');
console.log('driver route and nonce synthetic checks passed');
NODE
pass "driver nonce, POST routing, frozen blocking, second Start, and ambiguous/no-terminal regressions"
find "$TMP" \( -name 'playwright-report' -o -name 'test-results' -o -name '*.webm' -o -name '*.zip' -o -name '*.png' \) -print -quit | grep -q . && fail "Playwright artifacts created"
if rg -n 'nonce-fixture-value|private-token-fixture|cookie-fixture' "$TMP" >/dev/null 2>&1; then fail "fixture secret value appeared in output"; fi
npx playwright test --list --reporter=list 2>/dev/null | rg 'nmkr-real-sync-phase16a-driver' && fail "private driver discovered by Playwright"
pass "no external network, no browser artifacts, no nonce leak, and no private driver discovery"
