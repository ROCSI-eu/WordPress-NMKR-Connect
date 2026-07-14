#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
TMP="$(mktemp -d)"; trap 'rm -rf "$TMP"' EXIT
fail(){ echo "FAIL: $1" >&2; exit 1; }
pass(){ echo "PASS: $1"; }
run_fail(){ local name="$1"; shift; local out="$TMP/${name// /_}.out"; if "$@" >"$out" 2>&1; then fail "$name unexpectedly passed"; fi; grep -E 'Phase 16A|failed gate|FAIL' "$out" >/dev/null || fail "$name did not emit sanitized failure"; pass "$name"; }
mkrepo(){ local d="$1"; mkdir -p "$d"; git -C "$d" init -q; git -C "$d" config user.email a@invalid.test; git -C "$d" config user.name test; echo x >"$d/file"; git -C "$d" add file; git -C "$d" commit -qm init; }
SRC="$TMP/src"; WP="$TMP/wp"; STATE="$TMP/state"; mkrepo "$SRC"; mkdir -p "$WP" "$STATE"; chmod 700 "$TMP" "$STATE"
base_env(){ env -u CI -u GITHUB_ACTIONS -u GITLAB_CI -u CIRCLECI -u BUILDKITE -u TF_BUILD WP_PATH="$WP" NMKR_PHASE2_LOG_DIR="$STATE" NMKR_PHASE16A_CONFIRM=I_AUTHORIZE_EXACTLY_ONE_START NMKR_REAL_SYNC_CONFIRM=I_UNDERSTAND_THIS_MUTATES_DEV PW_SAVE_ARTIFACTS=false "$@"; }
run_fail "default RUN_REAL_SYNC=false" env -u RUN_REAL_SYNC WP_PATH="$WP" NMKR_PHASE2_LOG_DIR="$STATE" bash "$ROOT/scripts/nmkr-real-sync-phase16a.sh"
run_fail "CI refusal before env" env CI=true RUN_REAL_SYNC=true WP_PATH="$WP" NMKR_PHASE2_ENV_FILE="$TMP/does-not-exist" NMKR_PHASE2_LOG_DIR="$STATE" bash "$ROOT/scripts/nmkr-real-sync-phase16a.sh"
run_fail "PW_SAVE_ARTIFACTS true" base_env RUN_REAL_SYNC=true PW_SAVE_ARTIFACTS=true bash "$ROOT/scripts/nmkr-real-sync-phase16a.sh"
touch "$SRC/.env.tests"; run_fail "unsafe env-file path" base_env RUN_REAL_SYNC=true NMKR_PHASE2_ENV_FILE="$SRC/.env.tests" bash "$ROOT/scripts/nmkr-real-sync-phase16a.sh"
run_fail "unsafe private-state path" env -u CI RUN_REAL_SYNC=true WP_PATH="$WP" NMKR_PHASE2_LOG_DIR="$SRC/state" NMKR_PHASE16A_CONFIRM=I_AUTHORIZE_EXACTLY_ONE_START NMKR_REAL_SYNC_CONFIRM=I_UNDERSTAND_THIS_MUTATES_DEV PW_SAVE_ARTIFACTS=false bash "$ROOT/scripts/nmkr-real-sync-phase16a.sh"
LOCKSTATE="$TMP/lockstate"; mkdir -m700 "$LOCKSTATE" "$LOCKSTATE/phase16a-controller.lock"; run_fail "duplicate Phase 16 controller lock" base_env RUN_REAL_SYNC=true NMKR_PHASE2_LOG_DIR="$LOCKSTATE" bash "$ROOT/scripts/nmkr-real-sync-phase16a.sh"
make_receipt(){ local path="$1"; local exp="${2:-600}"; local src_head; src_head="$(git -C "$ROOT" rev-parse HEAD)"; python3 - "$path" "$src_head" "$exp" <<'PY'
import json,sys,time,os
p,head,exp=sys.argv[1:4]; now=int(time.time())
d={'receipt_version':1,'purpose':'nmkr-real-sync-preflight','created_at_epoch':now,'expires_at_epoch':now+int(exp),'source_commit':head,'deployed_commit':head,'origin_sha256':'0'*64,'plugin_active':True,'admin_capability_ok':True,'db_state_clean':True,'api_key_present':True,'runtime_state_clean':True,'cron_state_clean':True,'object_cache_state_clean':True,'profile_guard_passed':True,'backup_confirmed':True,'backup_confirmed_at_epoch':now,'max_duration_seconds':300,'poll_timeout_seconds':10,'receipt_ttl_seconds':300}
json.dump(d,open(p,'w')); os.chmod(p,0o600)
PY
}
for case in "missing receipt" "malformed JSON" "wrong schema" "wrong version" "wrong purpose" "expired receipt" "insufficient remaining receipt lifetime" "wrong source commit" "wrong deployed commit" "wrong origin digest" "timeout values outside bounds" "stale backup timestamp" "consumed destination already exists"; do
  D="$TMP/${case// /_}"; mkdir -m700 "$D"; R="$D/real-sync-preflight.receipt.json"; make_receipt "$R"
  case "$case" in
    "missing receipt") rm "$R";; "malformed JSON") echo '{' >"$R"; chmod 600 "$R";; "wrong schema") python3 - "$R" -c 'import json,sys; d=json.load(open(sys.argv[1])); d["extra"]=1; json.dump(d,open(sys.argv[1],"w"))';;
    "wrong version") python3 - "$R" -c 'import json,sys; d=json.load(open(sys.argv[1])); d["receipt_version"]=2; json.dump(d,open(sys.argv[1],"w"))';; "wrong purpose") python3 - "$R" -c 'import json,sys; d=json.load(open(sys.argv[1])); d["purpose"]="x"; json.dump(d,open(sys.argv[1],"w"))';;
    "expired receipt") make_receipt "$R" -1;; "insufficient remaining receipt lifetime") make_receipt "$R" 60;; "wrong source commit") python3 - "$R" -c 'import json,sys; d=json.load(open(sys.argv[1])); d["source_commit"]="1"*40; json.dump(d,open(sys.argv[1],"w"))';;
    "wrong deployed commit") python3 - "$R" -c 'import json,sys; d=json.load(open(sys.argv[1])); d["deployed_commit"]="1"*40; json.dump(d,open(sys.argv[1],"w"))';; "wrong origin digest") python3 - "$R" -c 'import json,sys; d=json.load(open(sys.argv[1])); d["origin_sha256"]="bad"; json.dump(d,open(sys.argv[1],"w"))';;
    "timeout values outside bounds") python3 - "$R" -c 'import json,sys; d=json.load(open(sys.argv[1])); d["poll_timeout_seconds"]=999; json.dump(d,open(sys.argv[1],"w"))';; "stale backup timestamp") python3 - "$R" -c 'import json,sys,time; d=json.load(open(sys.argv[1])); d["backup_confirmed_at_epoch"]=int(time.time())-90000; json.dump(d,open(sys.argv[1],"w"))';;
    "consumed destination already exists") touch "$D/real-sync-preflight.receipt.consumed.json";;
  esac
  run_fail "$case" base_env RUN_REAL_SYNC=true NMKR_PHASE2_LOG_DIR="$D" NMKR_PHASE16A_RECEIPT="$R" bash "$ROOT/scripts/nmkr-real-sync-phase16a.sh"
done
D="$TMP/modes"; mkdir -m700 "$D"; R="$D/real-sync-preflight.receipt.json"; make_receipt "$R"; chmod 644 "$R"; run_fail "wrong mode" base_env RUN_REAL_SYNC=true NMKR_PHASE2_LOG_DIR="$D" NMKR_PHASE16A_RECEIPT="$R" bash "$ROOT/scripts/nmkr-real-sync-phase16a.sh"
D="$TMP/symlink"; mkdir -m700 "$D"; echo '{}' >"$D/target"; ln -s target "$D/real-sync-preflight.receipt.json"; run_fail "symlink receipt" base_env RUN_REAL_SYNC=true NMKR_PHASE2_LOG_DIR="$D" NMKR_PHASE16A_RECEIPT="$D/real-sync-preflight.receipt.json" bash "$ROOT/scripts/nmkr-real-sync-phase16a.sh"
node --input-type=module <<'NODE' >"$TMP/driver.out"
import {runExactlyOnceSync,routeDecision} from './scripts/nmkr-real-sync-phase16a-driver.mjs';
let starts=0,polls=0,sleeps=[];
let r=await runExactlyOnceSync({receipt:{maxDurationSeconds:20,pollTimeoutSeconds:5},sleep:async ms=>sleeps.push(ms),transport:{start:async()=>{starts++;return {status:200,json:{success:true}}},poll:async()=>{polls++; return polls===1?{status:200,json:{data:{progress:25,in_progress:true,metrics:{api_requests:1}}}}:{status:200,json:{data:{progress:100,completed:true}}}}}});
if(!r.ok||starts!==1||r.sanitized.startCount!==1||r.sanitized.pollCount!==2) throw Error('positive synthetic state machine failed');
starts=0; r=await runExactlyOnceSync({receipt:{maxDurationSeconds:20,pollTimeoutSeconds:5},sleep:async()=>{},transport:{start:async()=>{starts++;return {status:403,json:{success:false}}},poll:async()=>{throw Error('poll forbidden')}}}); if(starts!==1||!r.fatal) throw Error('unsuccessful Start count failed');
starts=0; r=await runExactlyOnceSync({receipt:{maxDurationSeconds:20,pollTimeoutSeconds:5},sleep:async()=>{},transport:{start:async()=>{starts++;return {timeout:true}},poll:async()=>{throw Error('poll forbidden')}}}); if(starts!==1||!r.ambiguous) throw Error('ambiguous Start retry failed');
starts=0; polls=0; sleeps=[]; let t=0; r=await runExactlyOnceSync({receipt:{maxDurationSeconds:20,pollTimeoutSeconds:5},now:()=>t,sleep:async ms=>{sleeps.push(ms);t+=ms},transport:{start:async()=>{starts++;return {status:200,json:{success:true}}},poll:async()=>{polls++; return polls<3?{status:503}:{status:200,json:{data:{progress:100,completed:true}}}}}}); if(!r.ok||sleeps[0]!==3000||sleeps[1]!==6000) throw Error('backoff failed');
r=await runExactlyOnceSync({receipt:{maxDurationSeconds:20,pollTimeoutSeconds:5},sleep:async()=>{},transport:{start:async()=>({status:200,json:{success:true}}),poll:async()=>({status:200,json:{data:{progress:101}}})}}); if(!r.fatal) throw Error('malformed progress failed');
let cleanup=0; try{await runExactlyOnceSync({receipt:{maxDurationSeconds:20,pollTimeoutSeconds:5},transport:{start:async()=>({timeout:true}),poll:async()=>({})},onCleanup:()=>cleanup++});}catch{} if(cleanup!==0) throw Error('cleanup sentinel tripped');
if(routeDecision('nmkr_check_api_status','prepare')!=='synthetic-ok'||routeDecision('nmkr_start_sync','prepare')!=='block'||routeDecision('heartbeat','frozen')!=='block') throw Error('route decisions failed');
console.log('driver synthetic regressions passed');
NODE
pass "positive synthetic state machine sends Start exactly once"
pass "unsuccessful and ambiguous Start never retry"
pass "retriable polling failures back off without overlap sentinel"
pass "progress decrease/malformed progress fail"
pass "interruption cleanup sentinels remain zero"
find "$TMP" \( -name 'playwright-report' -o -name 'test-results' -o -name '*.webm' -o -name '*.zip' -o -name '*.png' \) -print -quit | grep -q . && fail "Playwright artifacts created"
if rg -n "super-secret-fixture|private-token-fixture|cookie-fixture|nonce-fixture" "$TMP" >/dev/null 2>&1; then fail "fixture secret value appeared in generated output"; fi
npx playwright test --list --reporter=list 2>/dev/null | rg 'nmkr-real-sync-phase16a-driver' && fail "private driver discovered by Playwright"
pass "no Playwright artifacts and private driver absent from discovery"
