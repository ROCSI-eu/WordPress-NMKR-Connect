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
const base={schema_version:5,non_synthetic_state:{projects:{count:2,fingerprint:'projects-stable'},tokens:{count:3,fingerprint:'tokens-stable'},token_details:{count:3,fingerprint:'details-stable'}},project_count:0,token_count:0,token_detail_count:0,chain_classifications:{cardano_only:0,solana_only:0,dual_chain:0},token_attribution:{cardano_only:0,solana_only:0,dual_chain:0},duplicate_project_count:0,duplicate_token_count:0,orphan_token_count:0,orphan_detail_count:0,history_count:4,metrics_count:4,terminal_history_fingerprint:'stable',exact_history_count:0,exact_history:{},exact_metrics_count:0,exact_metrics:{},active_history_count:0,owner_present:false,option_active_marker_count:0,transient_active_marker_count:0,stale_recovery_marker_count:0,worker_evidence_count:0,live_metrics:false,finalization_resume_marker_count:0,sync_data_classification:'absent',cron_inspectable:true,cron:{nmkr_execute_sync_background:0,nmkr_process_batch_hook:0,nmkr_sync_cron_hook:0,nmkr_install_sync_cron_hook:0,nmkr_resume_sync_finalization:0},provider_counters:null};
const warm={...base,project_count:24,token_count:2400,token_detail_count:2400,chain_classifications:{cardano_only:8,solana_only:8,dual_chain:8},token_attribution:{cardano_only:800,solana_only:800,dual_chain:800}};
const after={...warm,history_count:5,metrics_count:5,exact_history_count:1,exact_history:{status:'completed',items_processed:2400,items_successful:2400,items_failed:0,ended:true,error_free:true},exact_metrics_count:1,exact_metrics:{total_projects:24,total_tokens:2400,total_sync_duration:360,total_api_time:312,average_response_time:.125,api_requests:2497,memory_usage:32,timestamp_match:true},sync_data_classification:'terminal',provider_counters:{projects:1,token_lists:96,details:2400,violations:0,external:0,total:2497}};
for(const [n,v] of Object.entries({cold:base,warm,bad:{...base,project_count:1},after}))fs.writeFileSync(`${d}/${n}.json`,JSON.stringify(v));
JS
node "$assertor" --preflight cold "$tmp/cold.json"
node "$assertor" --preflight warm "$tmp/warm.json"
! node "$assertor" --preflight cold "$tmp/bad.json" >/dev/null 2>&1 || { echo 'FAIL: partial cold state accepted' >&2; exit 1; }
node "$assertor" cold "$tmp/cold.json" "$tmp/after.json"
node "$assertor" warm "$tmp/warm.json" "$tmp/after.json"
node - "$tmp/after.json" "$tmp/wrong-attribution.json" <<'JS'
const fs=require('fs'),source=JSON.parse(fs.readFileSync(process.argv[2],'utf8'));source.token_attribution={cardano_only:799,solana_only:801,dual_chain:800};fs.writeFileSync(process.argv[3],JSON.stringify(source));
JS
! node "$assertor" cold "$tmp/cold.json" "$tmp/wrong-attribution.json" >/dev/null 2>&1 || { echo 'FAIL: wrong token attribution accepted' >&2; exit 1; }
node - "$tmp/after.json" "$tmp/non-synthetic-changed.json" <<'JS'
const fs=require('fs'),source=JSON.parse(fs.readFileSync(process.argv[2],'utf8'));source.non_synthetic_state.projects.fingerprint='changed';fs.writeFileSync(process.argv[3],JSON.stringify(source));
JS
! node "$assertor" cold "$tmp/cold.json" "$tmp/non-synthetic-changed.json" >/dev/null 2>&1 || { echo 'FAIL: non-synthetic mutation accepted' >&2; exit 1; }
sed -i 's/"exact_history_count":1/"exact_history_count":0/' "$tmp/after.json"
! node "$assertor" cold "$tmp/cold.json" "$tmp/after.json" >/dev/null 2>&1 || { echo 'FAIL: unbound history accepted' >&2; exit 1; }
grep -Fq 'terminal_outcome' "$(dirname "$runner")/nmkr-synthetic-driver.mjs" || { echo 'FAIL: canonical terminal outcome missing' >&2; exit 1; }
driver="$(dirname "$runner")/nmkr-synthetic-driver.mjs"
grep -Fq 'globalThis.nmkrSyncProgress?.nonce' "$driver" && grep -Fq "document.querySelector('#nmkr-sync-nonce')?.value" "$driver" || { echo 'FAIL: canonical nonce sources missing' >&2; exit 1; }
! grep -Eq 'nmkrSyncData|nmkr_sync_ajax' "$driver" || { echo 'FAIL: unsupported nonce fallback present' >&2; exit 1; }
DRIVER="$driver" node --input-type=module <<'JS'
const { canonicalNonce } = await import(`file://${process.env.DRIVER}`);
const expectFailure = (name, left, right) => { try { canonicalNonce(left, right); } catch (error) { if (error.message === name) return; } throw new Error(`nonce regression: ${name}`); };
if (canonicalNonce('abc123def4', 'abc123def4') !== 'abc123def4') throw new Error('canonical nonce rejected');
expectFailure('nonce-missing', '', 'abc123def4');
expectFailure('nonce-malformed', 'unsafe value', 'unsafe value');
expectFailure('nonce-mismatch', 'abc123def4', 'abc123def5');
JS
grep -Fq "form.get('action') !== 'nmkr_check_api_status'" "$(dirname "$runner")/nmkr-synthetic-driver.mjs" || { echo 'FAIL: dashboard status probes not isolated' >&2; exit 1; }
grep -Fq 'NMKR_SYNTHETIC_DEPLOYED_PLUGIN_PATH' "$runner" || { echo 'FAIL: deployed integrity gate missing' >&2; exit 1; }
grep -Fq 'NMKR_SYNTHETIC_WORKER_LOG' "$(dirname "$runner")/nmkr-synthetic-driver.mjs" || { echo 'FAIL: private worker diagnostics missing' >&2; exit 1; }
grep -Fq 'NMKR_SYNTHETIC_DIAGNOSTIC_CLASSIFIER' "$runner" || { echo 'FAIL: worker diagnostic classification missing' >&2; exit 1; }
grep -Fq 'duplicate-start-accepted' "$(dirname "$runner")/nmkr-synthetic-driver.mjs" || { echo 'FAIL: duplicate Start gate missing' >&2; exit 1; }
grep -Fq 'NMKR_SYNTHETIC_RUN_RECEIPT' "$runner" || { echo 'FAIL: run receipt missing' >&2; exit 1; }
grep -Fq 'debug-delta.log' "$runner" || { echo 'FAIL: debug-log delta missing' >&2; exit 1; }
grep -Fq '2>"$debug_baseline_diagnostic"' "$runner" || { echo 'FAIL: debug-baseline stderr reaches the console' >&2; exit 1; }
grep -Fq 'debug_baseline_diagnostic_status" == 0 || "$debug_baseline_diagnostic_status" == 3' "$runner" || { echo 'FAIL: debug-baseline diagnostics are not enforced' >&2; exit 1; }
php_gate="$(cat "$runner")"
[[ "$php_gate" == *'lstat($p)'* && "$php_gate" == *'is_link($p)'* && "$php_gate" == *'@chmod($p,0600)'* ]] || { echo 'FAIL: debug-log path gate missing' >&2; exit 1; }
[[ "${php_gate%%'@chmod($p,0600)'*}" == *'is_link($p)'* ]] || { echo 'FAIL: debug-log symlink checked after chmod' >&2; exit 1; }
grep -Fq '"$debug_source" != "$debug_delta_parent/$(basename -- "$debug_delta")"' "$runner" || { echo 'FAIL: debug source/delta collision accepted' >&2; exit 1; }
grep -Fq 'NMKR_SYNTHETIC_SERVER_LOG' "$runner" || { echo 'FAIL: private server diagnostics missing' >&2; exit 1; }
[[ "$(grep -Fc ' --complete-file "$NMKR_SYNTHETIC_SERVER_LOG" 60' "$runner")" == 1 ]] || { echo 'FAIL: server log complete-file classification missing' >&2; exit 1; }
[[ "$(grep -Fc ' --complete-file "$debug_delta" 60' "$runner")" == 1 ]] || { echo 'FAIL: debug delta complete-file classification missing' >&2; exit 1; }
[[ "$(grep -Fc -- '--complete-file' "$runner")" == 2 ]] || { echo 'FAIL: complete-file mode escaped controller-owned logs' >&2; exit 1; }
[[ "$(grep -Fc 'NMKR_SYNTHETIC_DIAGNOSTIC_CLASSIFIER" "$NMKR_SYNTHETIC_WORKER_LOG" 60' "$runner")" == 1 ]] || { echo 'FAIL: worker log default classification changed' >&2; exit 1; }
[[ "$(grep -Fc 'capture_state "$NMKR_SYNTHETIC_RUN_DIR/' "$runner")" == 2 ]] || { echo 'FAIL: state helpers are not captured' >&2; exit 1; }
grep -Fq 'eval-file "$NMKR_SYNTHETIC_STATE_HELPER" >"$output" 2>"$diagnostic"' "$runner" || { echo 'FAIL: state-helper stderr reaches the console' >&2; exit 1; }
grep -Fq 'before-state.stderr" || exit 46' "$runner" || { echo 'FAIL: preflight diagnostics are not enforced' >&2; exit 1; }
grep -Fq 'after-state.stderr" || exit 46' "$runner" || { echo 'FAIL: final diagnostics are not enforced' >&2; exit 1; }
classifier="$(dirname "$runner")/nmkr-debug-log-classifier.py"
printf '' >"$tmp/clean.log"
printf 'PHP Warning: synthetic first-party warning in /private/synthetic/plugin/file.php on line 1\n' >"$tmp/first-party.log"
printf 'PHP Warning: synthetic dependency warning in /private/synthetic/plugin/vendor/package/file.php on line 1\n' >"$tmp/vendor.log"
classify(){ set +e; python3 "$classifier" "$1" 60 >/dev/null 2>&1; local status=$?; set -e; printf '%s' "$status"; }
[[ "$(classify "$tmp/clean.log")" == 0 ]] || { echo 'FAIL: clean state-helper diagnostics rejected' >&2; exit 1; }
[[ "$(classify "$tmp/vendor.log")" == 3 ]] || { echo 'FAIL: accepted dependency diagnostics rejected' >&2; exit 1; }
[[ "$(classify "$tmp/first-party.log")" == 1 ]] || { echo 'FAIL: first-party state-helper diagnostic accepted' >&2; exit 1; }
echo 'Synthetic controller regression: PASS'
