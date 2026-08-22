import fs from 'node:fs';

const fail = () => { throw new Error('synthetic-state-invalid'); };
const read = path => JSON.parse(fs.readFileSync(path, 'utf8'));
const equal = (a, b) => JSON.stringify(a) === JSON.stringify(b);

try {
  if (process.argv.length !== 4) fail();
  const before = read(process.argv[2]);
  const after = read(process.argv[3]);
  if (before.schema_version !== 2 || after.schema_version !== 2) fail();
  if (after.project_count !== 24 || after.token_count !== 2400 || after.token_detail_count !== 2400) fail();
  if (!equal(after.chain_classifications, { cardano_only: 8, solana_only: 8, dual_chain: 8 })) fail();
  for (const key of ['duplicate_project_count', 'duplicate_token_count', 'orphan_token_count', 'orphan_detail_count', 'active_history_count']) {
    if (after[key] !== 0) fail();
  }
  if (after.history_count !== before.history_count + 1 || after.metrics_count !== before.metrics_count + 1) fail();
  if (after.history_max_id <= before.history_max_id || after.latest_history.id !== after.history_max_id) fail();
  if (!equal(after.latest_history, { id: after.history_max_id, status: 'completed', items_processed: 2400, items_successful: 2400, items_failed: 0, ended: 1, error_free: 1 })) fail();
  const metrics = after.latest_metrics;
  if (after.metrics_max_id <= before.metrics_max_id || metrics.id !== after.metrics_max_id) fail();
  if (metrics.total_projects !== 24 || metrics.total_tokens !== 2400 || metrics.api_requests !== 2497 || metrics.timestamped !== 1) fail();
  if (!(metrics.total_sync_duration > 0 && metrics.total_api_time > 0 && metrics.average_response_time > 0 && metrics.memory_usage >= 0)) fail();
  for (const key of ['owner_present', 'active_marker', 'worker_lock', 'heartbeat', 'live_metrics']) if (after[key]) fail();
  if (!equal(after.cron, { nmkr_execute_sync_background: 0, nmkr_process_batch_hook: 0, nmkr_resume_sync_finalization: 0 })) fail();
  if (!equal(after.provider_counters, { projects: 1, token_lists: 96, details: 2400, violations: 0, external: 0, total: 2497 })) fail();
} catch {
  process.stderr.write('Synthetic final state: FAIL\n');
  process.exitCode = 1;
}
