#!/usr/bin/env bash
set -Eeuo pipefail
runner="$(cd "$(dirname "$0")" && pwd)/nmkr-synthetic-run.sh"; tmp="$(mktemp -d)"; trap 'rm -rf "$tmp"' EXIT
run_fail(){ local out; if out="$(env -i PATH="$PATH" HOME="$HOME" "$@" bash "$runner" 2>&1)"; then echo "FAIL: accepted unsafe invocation" >&2; exit 1; fi; [[ "$out" != *password* && "$out" != *credential* && "$out" != *"$tmp"* ]] || { echo 'FAIL: unsafe output' >&2; exit 1; }; }
run_fail
run_fail CI=true RUN_NMKR_SYNTHETIC=true
run_fail RUN_NMKR_SYNTHETIC=true NMKR_SYNTHETIC_CONFIRM=I_AUTHORIZE_DISPOSABLE_SYNTHETIC_SYNC
! grep -En 'docker|iptables|nft|wp core download' "$runner" >/dev/null || { echo 'FAIL: prohibited provisioning' >&2; exit 1; }
assertor="$(dirname "$runner")/nmkr-synthetic-state-assert.mjs"
cat >"$tmp/before.json" <<'JSON'
{"schema_version":2,"history_count":4,"history_max_id":4,"metrics_count":4,"metrics_max_id":4}
JSON
cat >"$tmp/after.json" <<'JSON'
{"schema_version":2,"project_count":24,"token_count":2400,"token_detail_count":2400,"chain_classifications":{"cardano_only":8,"solana_only":8,"dual_chain":8},"duplicate_project_count":0,"duplicate_token_count":0,"orphan_token_count":0,"orphan_detail_count":0,"active_history_count":0,"history_count":5,"history_max_id":5,"latest_history":{"id":5,"status":"completed","items_processed":2400,"items_successful":2400,"items_failed":0,"ended":1,"error_free":1},"metrics_count":5,"metrics_max_id":5,"latest_metrics":{"id":5,"total_projects":24,"total_tokens":2400,"total_sync_duration":360,"total_api_time":312,"average_response_time":0.125,"api_requests":2497,"memory_usage":32,"timestamped":1},"owner_present":false,"active_marker":false,"worker_lock":false,"heartbeat":false,"live_metrics":false,"cron":{"nmkr_execute_sync_background":0,"nmkr_process_batch_hook":0,"nmkr_resume_sync_finalization":0},"provider_counters":{"projects":1,"token_lists":96,"details":2400,"violations":0,"external":0,"total":2497}}
JSON
node "$assertor" "$tmp/before.json" "$tmp/after.json"
sed -i 's/"token_count":2400/"token_count":2399/' "$tmp/after.json"
if node "$assertor" "$tmp/before.json" "$tmp/after.json" >/dev/null 2>&1; then echo 'FAIL: invalid final state accepted' >&2; exit 1; fi
grep -Fq 'terminal_outcome' "$(dirname "$runner")/nmkr-synthetic-driver.mjs" || { echo 'FAIL: canonical terminal outcome missing' >&2; exit 1; }
grep -Fq 'NMKR_SYNTHETIC_DEPLOYED_PLUGIN_PATH' "$runner" || { echo 'FAIL: deployed integrity gate missing' >&2; exit 1; }
grep -Fq 'NMKR_SYNTHETIC_WORKER_LOG' "$(dirname "$runner")/nmkr-synthetic-driver.mjs" || { echo 'FAIL: private worker diagnostics missing' >&2; exit 1; }
grep -Fq 'NMKR_SYNTHETIC_DIAGNOSTIC_CLASSIFIER' "$runner" || { echo 'FAIL: worker diagnostic classification missing' >&2; exit 1; }
grep -Fq 'NMKR_SYNTHETIC_SERVER_LOG' "$runner" || { echo 'FAIL: private server diagnostics missing' >&2; exit 1; }
echo 'Synthetic controller regression: PASS'
