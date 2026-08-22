#!/usr/bin/env bash
set -Eeuo pipefail
runner="$(cd "$(dirname "$0")" && pwd)/nmkr-synthetic-run.sh"; tmp="$(mktemp -d)"; trap 'rm -rf "$tmp"' EXIT
run_fail(){ local out; if out="$(env -i PATH="$PATH" HOME="$HOME" "$@" bash "$runner" 2>&1)"; then echo "FAIL: accepted unsafe invocation" >&2; exit 1; fi; [[ "$out" != *password* && "$out" != *credential* && "$out" != *"$tmp"* ]] || { echo 'FAIL: unsafe output' >&2; exit 1; }; }
run_fail
run_fail CI=true RUN_NMKR_SYNTHETIC=true
run_fail RUN_NMKR_SYNTHETIC=true NMKR_SYNTHETIC_CONFIRM=I_AUTHORIZE_DISPOSABLE_SYNTHETIC_SYNC
! grep -En 'docker|iptables|nft|wp core download' "$runner" >/dev/null || { echo 'FAIL: prohibited provisioning' >&2; exit 1; }
assertor="$(dirname "$runner")/nmkr-synthetic-state-assert.mjs"
node - "$tmp" <<'JS'
const fs=require('fs'),d=process.argv[2];
const base={schema_version:3,project_count:0,token_count:0,token_detail_count:0,chain_classifications:{cardano_only:0,solana_only:0,dual_chain:0},duplicate_project_count:0,duplicate_token_count:0,orphan_token_count:0,orphan_detail_count:0,history_count:4,metrics_count:4,terminal_history_fingerprint:'stable',exact_history_count:0,exact_history:{},exact_metrics_count:0,exact_metrics:{},active_history_count:0,owner_present:false,option_active_marker_count:0,transient_active_marker_count:0,stale_recovery_marker_count:0,worker_evidence_count:0,live_metrics:false,finalization_resume_marker_count:0,sync_data_classification:'absent',cron_inspectable:true,cron:{nmkr_execute_sync_background:0,nmkr_process_batch_hook:0,nmkr_sync_cron_hook:0,nmkr_install_sync_cron_hook:0,nmkr_resume_sync_finalization:0},provider_counters:null};
const warm={...base,project_count:24,token_count:2400,token_detail_count:2400,chain_classifications:{cardano_only:8,solana_only:8,dual_chain:8}};
const after={...warm,history_count:5,metrics_count:5,exact_history_count:1,exact_history:{status:'completed',items_processed:2400,items_successful:2400,items_failed:0,ended:true,error_free:true},exact_metrics_count:1,exact_metrics:{total_projects:24,total_tokens:2400,total_sync_duration:360,total_api_time:312,average_response_time:.125,api_requests:2497,memory_usage:32,timestamp_match:true},sync_data_classification:'terminal',provider_counters:{projects:1,token_lists:96,details:2400,violations:0,external:0,total:2497}};
for(const [n,v] of Object.entries({cold:base,warm,bad:{...base,project_count:1},after}))fs.writeFileSync(`${d}/${n}.json`,JSON.stringify(v));
JS
node "$assertor" --preflight cold "$tmp/cold.json"
node "$assertor" --preflight warm "$tmp/warm.json"
! node "$assertor" --preflight cold "$tmp/bad.json" >/dev/null 2>&1 || { echo 'FAIL: partial cold state accepted' >&2; exit 1; }
node "$assertor" cold "$tmp/cold.json" "$tmp/after.json"
node "$assertor" warm "$tmp/warm.json" "$tmp/after.json"
sed -i 's/"exact_history_count":1/"exact_history_count":0/' "$tmp/after.json"
! node "$assertor" cold "$tmp/cold.json" "$tmp/after.json" >/dev/null 2>&1 || { echo 'FAIL: unbound history accepted' >&2; exit 1; }
grep -Fq 'terminal_outcome' "$(dirname "$runner")/nmkr-synthetic-driver.mjs" || { echo 'FAIL: canonical terminal outcome missing' >&2; exit 1; }
grep -Fq "form.get('action') !== 'nmkr_check_api_status'" "$(dirname "$runner")/nmkr-synthetic-driver.mjs" || { echo 'FAIL: dashboard status probes not isolated' >&2; exit 1; }
grep -Fq 'NMKR_SYNTHETIC_DEPLOYED_PLUGIN_PATH' "$runner" || { echo 'FAIL: deployed integrity gate missing' >&2; exit 1; }
grep -Fq 'NMKR_SYNTHETIC_WORKER_LOG' "$(dirname "$runner")/nmkr-synthetic-driver.mjs" || { echo 'FAIL: private worker diagnostics missing' >&2; exit 1; }
grep -Fq 'NMKR_SYNTHETIC_DIAGNOSTIC_CLASSIFIER' "$runner" || { echo 'FAIL: worker diagnostic classification missing' >&2; exit 1; }
grep -Fq 'duplicate-start-accepted' "$(dirname "$runner")/nmkr-synthetic-driver.mjs" || { echo 'FAIL: duplicate Start gate missing' >&2; exit 1; }
grep -Fq 'NMKR_SYNTHETIC_RUN_RECEIPT' "$runner" || { echo 'FAIL: run receipt missing' >&2; exit 1; }
grep -Fq 'debug-delta.log' "$runner" || { echo 'FAIL: debug-log delta missing' >&2; exit 1; }
grep -Fq 'NMKR_SYNTHETIC_SERVER_LOG' "$runner" || { echo 'FAIL: private server diagnostics missing' >&2; exit 1; }
echo 'Synthetic controller regression: PASS'
