<?php
/* Public-safe regression for run-owned synchronization health classification. */
define('ABSPATH', __DIR__ . '/');
define('NMKR_SYNC_TRANSIENT_TTL', 3600);
define('HOUR_IN_SECONDS', 3600);

$GLOBALS['options'] = array();
$GLOBALS['transients'] = array();
$GLOBALS['cron'] = array();
$GLOBALS['owner'] = false;
$GLOBALS['sync_data'] = false;
$GLOBALS['logs'] = array();

function __($value) { return $value; }
function get_option($name, $default = false) { return array_key_exists($name, $GLOBALS['options']) ? $GLOBALS['options'][$name] : $default; }
function get_transient($name) { return array_key_exists($name, $GLOBALS['transients']) ? $GLOBALS['transients'][$name] : false; }
function wp_next_scheduled($hook, $args = array()) {
    $key = $hook . ':' . json_encode(array_values($args));
    return $GLOBALS['cron'][$key] ?? false;
}
function nmkr_get_sync_owner() { return $GLOBALS['owner']; }
function nmkr_get_sync_data() { return $GLOBALS['sync_data']; }
function nmkr_is_valid_sync_run_id($run_id) {
    return is_string($run_id) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $run_id) === 1;
}
function nmkr_sync_finalization_resume_key($sync_stats_id) { return 'nmkr_sync_finalization_resume_' . (int) $sync_stats_id; }
function nmkr_is_valid_sync_finalization_record($record, $run_id = '', $sync_stats_id = 0) {
    if (!is_array($record) || !nmkr_is_valid_sync_run_id((string) ($record['run_id'] ?? ''))
        || (int) ($record['sync_stats_id'] ?? 0) <= 0 || !is_int($record['attempt'] ?? null)
        || !in_array(($record['outcome'] ?? ''), array('completed', 'failed', 'stopped'), true)
        || (string) ($record['run_id'] ?? '') !== (string) $run_id
        || (int) ($record['sync_stats_id'] ?? 0) !== (int) $sync_stats_id) {
        return false;
    }
    foreach (array('items_processed', 'items_successful', 'items_failed', 'items_skipped', 'token_details_synced') as $counter) {
        if (!array_key_exists($counter, $record) || !is_numeric($record[$counter])) return false;
    }
    return true;
}
function nmkr_sync_finalization_resume_event_scheduled($sync_stats_id) {
    return wp_next_scheduled('nmkr_resume_sync_finalization', array((int) $sync_stats_id)) !== false;
}
function nmkr_get_heartbeat_age() {
    $heartbeat = (int) get_option('nmkr_sync_heartbeat', 0);
    return $heartbeat > 0 ? time() - $heartbeat : -1;
}
function nmkr_log_ui_status($message, $level = 'info') { $GLOBALS['logs'][] = array($level, $message); }

require dirname(__DIR__) . '/includes/synchronization/nmkr-sync-error-handling.php';

function check($condition, $message) {
    if (!$condition) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
    echo "PASS: {$message}\n";
}
function reset_health_fixture() {
    $GLOBALS['options'] = array(
        'nmkr_connect_options' => array(
            'sync_profile' => 'balanced',
            'sync_batch_size' => 5,
            'sync_batch_delay' => 2,
            'sync_initial_interval' => 1000,
        ),
    );
    $GLOBALS['transients'] = array();
    $GLOBALS['cron'] = array();
    $GLOBALS['owner'] = false;
    $GLOBALS['sync_data'] = false;
    $GLOBALS['logs'] = array();
}
function finalization_resume_record($run_id, $sync_stats_id) {
    $GLOBALS['options'][nmkr_sync_finalization_resume_key($sync_stats_id)] = array(
        'run_id' => $run_id,
        'sync_stats_id' => $sync_stats_id,
        'attempt' => 0,
        'outcome' => 'stopped',
        'items_processed' => 10,
        'items_successful' => 9,
        'items_failed' => 1,
        'items_skipped' => 0,
        'token_details_synced' => 8,
    );
}
function direct_owner($run_id, $state, $stats_id, $age) {
    $stamp = gmdate('c', time() - $age);
    return array(
        'run_id' => $run_id,
        'mode' => 'direct',
        'state' => $state,
        'sync_stats_id' => $stats_id,
        'created_at' => $stamp,
        'updated_at' => $stamp,
        'heartbeat_at' => $stamp,
    );
}

$run = '11111111-1111-4111-8111-111111111111';

reset_health_fixture();
$GLOBALS['owner'] = direct_owner($run, 'running', 41, 10);
$GLOBALS['transients']['nmkr_sync_progress'] = 25;
$GLOBALS['options']['nmkr_last_progress_update_time'] = time() - 10;
$before = array($GLOBALS['options'], $GLOBALS['transients'], $GLOBALS['cron'], $GLOBALS['owner'], $GLOBALS['sync_data']);
$health = nmkr_check_sync_health();
$after = array($GLOBALS['options'], $GLOBALS['transients'], $GLOBALS['cron'], $GLOBALS['owner'], $GLOBALS['sync_data']);
check($health['in_progress'] && !$health['is_stalled'] && $health['has_running_jobs']
    && $health['owner_state'] === 'running' && $health['lifecycle_source'] === 'direct_owner',
    'healthy direct run ignores absent legacy batch cron');
check($before === $after, 'read-only direct health classification does not mutate lifecycle state');

reset_health_fixture();
$GLOBALS['owner'] = direct_owner($run, 'running', 42, 400);
$GLOBALS['transients']['nmkr_sync_progress'] = 30;
$GLOBALS['options']['nmkr_last_progress_update_time'] = time() - 400;
$health = nmkr_check_sync_health();
check($health['is_stalled'] && strpos($health['stall_reason'], 'heartbeat and progress') !== false,
    'stale direct run requires both run-owned heartbeat and progress freshness to expire');

reset_health_fixture();
$GLOBALS['owner'] = direct_owner($run, 'queued', 0, 400);
$GLOBALS['cron']['nmkr_execute_sync_background:' . json_encode(array($run))] = time() + 30;
$health = nmkr_check_sync_health();
check(!$health['is_stalled'] && $health['has_running_jobs'] && $health['owner_state'] === 'queued',
    'queued direct run recognizes its exact background worker event');

reset_health_fixture();
$GLOBALS['owner'] = direct_owner($run, 'queued', 0, 400);
$GLOBALS['cron']['nmkr_execute_sync_background:' . json_encode(array($run))] = time() - 400;
$health = nmkr_check_sync_health();
check($health['is_stalled'] && !$health['has_running_jobs'] && $health['owner_state'] === 'queued',
    'overdue queued worker event expires with stale owner freshness');

reset_health_fixture();
$GLOBALS['owner'] = direct_owner($run, 'queued', 0, 10);
$GLOBALS['cron']['nmkr_execute_sync_background:' . json_encode(array($run))] = time() - 400;
$health = nmkr_check_sync_health();
check(!$health['is_stalled'] && $health['has_running_jobs'] && $health['owner_state'] === 'queued',
    'fresh queued owner remains healthy when its worker event is overdue');

reset_health_fixture();
$GLOBALS['owner'] = direct_owner($run, 'finalizing', 43, 400);
$GLOBALS['sync_data'] = array('run_id' => $run, 'sync_stats_id' => 43, 'status' => 'finalizing');
finalization_resume_record($run, 43);
$GLOBALS['cron']['nmkr_resume_sync_finalization:' . json_encode(array(43))] = time() + 30;
$health = nmkr_check_sync_health();
check(!$health['is_stalled'] && $health['finalization_pending'] && $health['has_running_jobs'],
    'finalizing direct run uses exact resume evidence rather than legacy batch cron');

reset_health_fixture();
$GLOBALS['owner'] = direct_owner($run, 'finalizing', 45, 400);
$GLOBALS['sync_data'] = array('run_id' => $run, 'sync_stats_id' => 45, 'status' => 'processing_tokens');
finalization_resume_record($run, 45);
$GLOBALS['cron']['nmkr_resume_sync_finalization:' . json_encode(array(45))] = time() + 30;
$health = nmkr_check_sync_health();
check(!$health['is_stalled'] && $health['finalization_pending'] && $health['has_running_jobs'],
    'exact durable finalization handoff is healthy before status publication');

reset_health_fixture();
$GLOBALS['owner'] = direct_owner($run, 'finalizing', 47, 400);
$GLOBALS['sync_data'] = array('run_id' => $run, 'sync_stats_id' => 47, 'status' => 'finalizing');
finalization_resume_record($run, 47);
$health = nmkr_check_sync_health();
check($health['is_stalled'] && $health['finalization_pending'] && !$health['has_running_jobs'],
    'stale finalization resume option without an exact callback is not executable evidence');

reset_health_fixture();
$GLOBALS['owner'] = direct_owner($run, 'finalizing', 46, 400);
$GLOBALS['sync_data'] = array('run_id' => '22222222-2222-4222-8222-222222222222', 'sync_stats_id' => 46, 'status' => 'processing_tokens');
finalization_resume_record('22222222-2222-4222-8222-222222222222', 46);
$GLOBALS['cron']['nmkr_resume_sync_finalization:' . json_encode(array(46))] = time() + 30;
$health = nmkr_check_sync_health();
check($health['is_stalled'] && !$health['finalization_pending'],
    'finalization handoff evidence cannot cross the exact run boundary');

reset_health_fixture();
$GLOBALS['owner'] = direct_owner($run, 'finalizing', 48, 400);
$GLOBALS['sync_data'] = array('run_id' => $run, 'sync_stats_id' => 48, 'status' => 'processing_tokens');
$GLOBALS['options'][nmkr_sync_finalization_resume_key(48)] = array_merge(
    array('run_id' => $run, 'sync_stats_id' => 49, 'attempt' => 0, 'outcome' => 'stopped'),
    array_fill_keys(array('items_processed', 'items_successful', 'items_failed', 'items_skipped', 'token_details_synced'), 0)
);
$GLOBALS['cron']['nmkr_resume_sync_finalization:' . json_encode(array(48))] = time() + 30;
$health = nmkr_check_sync_health();
check($health['is_stalled'] && !$health['finalization_pending'] && !$health['has_running_jobs'],
    'scheduled callback cannot substitute for an exact durable finalization record');

reset_health_fixture();
$GLOBALS['owner'] = direct_owner($run, 'stop_requested', 44, 400);
$health = nmkr_check_sync_health();
check($health['in_progress'] && !$health['is_stalled'] && $health['owner_state'] === 'stop_requested',
    'stop_requested direct owner remains active without legacy stall classification');

reset_health_fixture();
$GLOBALS['transients']['nmkr_sync_progress'] = 35;
$GLOBALS['options']['nmkr_sync_start_time'] = time() - 400;
$GLOBALS['options']['nmkr_last_progress_update_time'] = time() - 400;
$GLOBALS['options']['nmkr_last_progress_value'] = 35;
$health = nmkr_check_sync_health();
check($health['lifecycle_source'] === 'legacy_ownerless' && $health['is_stalled'] && !$health['has_running_jobs'],
    'ownerless legacy state retains bounded batch-era stall classification');

reset_health_fixture();
$GLOBALS['options']['nmkr_connect_options']['sync_batch_size'] = 3;
$GLOBALS['options']['nmkr_connect_options']['sync_batch_delay'] = 3;
$health = nmkr_check_sync_health();
check($health['profile_settings']['batch_size'] === 3 && $health['profile_settings']['batch_delay'] === 3,
    'health settings consume canonical light-profile keys');
$GLOBALS['options']['nmkr_connect_options']['sync_batch_size'] = 10;
$GLOBALS['options']['nmkr_connect_options']['sync_batch_delay'] = 1;
$health = nmkr_check_sync_health();
check($health['profile_settings']['batch_size'] === 10 && $health['profile_settings']['batch_delay'] === 1,
    'health settings consume canonical aggressive-profile keys');

echo "Sync health regression passed.\n";
