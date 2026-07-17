<?php
/** Phase 16A read-only aggregate state snapshot. */
if (!defined('ABSPATH')) {
    fwrite(STDERR, "WordPress must be loaded.\n");
    exit(1);
}

global $wpdb;

function nmkr16_sql_ident($name) {
    return '`' . str_replace('`', '``', $name) . '`';
}
function nmkr16_table_exists($table) {
    global $wpdb;
    return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
}
function nmkr16_count_sql($sql) {
    global $wpdb;
    $value = $wpdb->get_var($sql);
    return is_numeric($value) ? (int) $value : 0;
}
function nmkr16_max_id($table) {
    if (!nmkr16_table_exists($table)) {
        return 0;
    }
    return nmkr16_count_sql('SELECT COALESCE(MAX(id),0) FROM ' . nmkr16_sql_ident($table));
}
function nmkr16_has_column($table, $column) {
    global $wpdb;
    if (!nmkr16_table_exists($table)) {
        return false;
    }
    return (bool) $wpdb->get_var($wpdb->prepare('SHOW COLUMNS FROM ' . nmkr16_sql_ident($table) . ' LIKE %s', $column));
}
function nmkr16_truthy($value) {
    if (is_bool($value)) {
        return $value;
    }
    if (is_int($value) || is_float($value)) {
        return ((float) $value) > 0;
    }
    if (is_string($value)) {
        return in_array(strtolower(trim($value)), array('1', 'true', 'yes', 'on', 'running', 'in_progress', 'processing', 'processing_projects', 'processing_tokens', 'initializing', 'pending'), true);
    }
    return !empty($value);
}
function nmkr16_progress_active($value) {
    return is_numeric($value) && (float) $value > 0 && (float) $value < 100;
}
function nmkr16_active_statuses() {
    return array('initializing', 'finalizing', 'processing', 'processing_projects', 'processing_tokens', 'in_progress', 'running', 'pending', 'active', 'started');
}
function nmkr16_terminal_statuses() {
    return function_exists('nmkr_sync_terminal_statuses')
        ? nmkr_sync_terminal_statuses()
        : array('completed', 'success', 'failed', 'error', 'stopped', 'cancelled', 'aborted');
}
function nmkr16_sql_list($values) {
    return "'" . implode("','", array_map('esc_sql', $values)) . "'";
}
function nmkr16_is_active_status($value) {
    return is_scalar($value) && in_array(strtolower(trim((string) $value)), nmkr16_active_statuses(), true);
}
function nmkr16_sync_data_classification($value) {
    if (!is_array($value) || empty($value)) {
        return 'absent';
    }
    foreach (array('status', 'sync_status', 'state') as $key) {
        if (array_key_exists($key, $value) && nmkr16_is_active_status($value[$key])) {
            return 'active';
        }
        if (array_key_exists($key, $value) && in_array(strtolower(trim((string) $value[$key])), nmkr16_terminal_statuses(), true)) {
            return 'terminal';
        }
    }
    foreach (array('in_progress', 'is_running', 'running', 'pending', 'active') as $key) {
        if (array_key_exists($key, $value) && $value[$key] === true) {
            return 'active';
        }
    }
    if (array_key_exists('completed', $value) && $value['completed'] === false) {
        return 'active';
    }
    return 'unknown';
}
function nmkr16_terminal_digest($table) {
    global $wpdb;
    if (!nmkr16_table_exists($table)) {
        return hash('sha256', 'missing');
    }
    $limit = getenv('NMKR_PHASE16A_HISTORY_MAX_ID');
    $limit_sql = '';
    if ($limit !== false) {
        if (!is_string($limit) || !preg_match('/^(0|[1-9][0-9]*)$/', $limit)) {
            exit(1);
        }
        $limit_sql = ' AND id <= ' . (int) $limit;
    }
    $rows = $wpdb->get_results('SELECT id,sync_type,start_time,end_time,status,items_processed,items_successful,items_failed,updated_at FROM ' . nmkr16_sql_ident($table) . ' WHERE BINARY status IN (' . nmkr16_sql_list(nmkr16_terminal_statuses()) . ')' . $limit_sql . ' ORDER BY id ASC', ARRAY_A);
    $ctx = hash_init('sha256');
    foreach ((array) $rows as $row) {
        hash_update($ctx, hash('sha256', wp_json_encode($row)) . "\n");
    }
    return hash_final($ctx);
}
function nmkr16_option_active_count() {
    $count = 0;
    $in_progress_active = nmkr16_truthy(get_option('nmkr_sync_in_progress', false));
    $progress_active = nmkr16_progress_active(get_option('nmkr_sync_progress', 0));
    $status_active = nmkr16_is_active_status(get_option('nmkr_sync_status', ''));
    $sync_data_active = nmkr16_sync_data_classification(get_option('nmkr_sync_data', array())) === 'active';
    $durable_sync_active = $in_progress_active || $status_active || $sync_data_active;
    if ($in_progress_active) { $count++; }
    if ($progress_active) { $count++; }
    if ($status_active) { $count++; }
    if ($sync_data_active) { $count++; }
    if ($durable_sync_active && nmkr16_truthy(get_option('nmkr_sync_near_completion', false))) { $count++; }
    return $count;
}
function nmkr16_transient_active_count() {
    if (!wp_using_ext_object_cache()) {
        return 0;
    }
    $count = 0;
    if (nmkr16_truthy(get_transient('nmkr_sync_in_progress'))) { $count++; }
    if (nmkr16_progress_active(get_transient('nmkr_sync_progress'))) { $count++; }
    if (nmkr16_is_active_status(get_transient('nmkr_sync_status'))) { $count++; }
    if (nmkr16_truthy(get_transient('nmkr_stale_recovery_running'))) { $count++; }
    return $count;
}
function nmkr16_stale_recovery_count() {
    $count = 0;
    if (nmkr16_truthy(get_option('nmkr_stale_recovery_running', false))) { $count++; }
    if (wp_using_ext_object_cache() && nmkr16_truthy(get_transient('nmkr_stale_recovery_running'))) { $count++; }
    return $count;
}
function nmkr16_heartbeat_worker_count() {
    $count = 0;
    foreach (array('nmkr_sync_heartbeat', 'nmkr_sync_worker_started_at', 'nmkr_sync_worker_lock') as $key) {
        if (nmkr16_truthy(get_option($key, false))) { $count++; }
    }
    return $count;
}
function nmkr16_cron_state() {
    $blocked = array('nmkr_execute_sync_background', 'nmkr_process_batch_hook', 'nmkr_sync_cron_hook', 'nmkr_install_sync_cron_hook');
    $counts = array_fill_keys($blocked, 0);
    $inspectable = true;
    $cron = get_option('cron', array());
    if (!is_array($cron) || !isset($cron['version']) || (int) $cron['version'] !== 2) {
        return array($counts, false, 0);
    }
    foreach ($cron as $timestamp => $events) {
        if ($timestamp === 'version') { continue; }
        if (!is_scalar($timestamp) || !ctype_digit((string) $timestamp) || !is_array($events)) { $inspectable = false; break; }
        foreach ($events as $hook => $hook_events) {
            if (!is_string($hook) || !is_array($hook_events)) { $inspectable = false; break 2; }
            if (array_key_exists($hook, $counts)) { $counts[$hook] += count($hook_events); }
        }
    }
    return array($counts, $inspectable, array_sum($counts));
}
function nmkr16_light_profile_ok() {
    $options = get_option('nmkr_connect_options', array());
    if (!is_array($options)) { return false; }
    $profile = isset($options['sync_profile']) ? (string) $options['sync_profile'] : '';
    $batch_size = isset($options['sync_batch_size']) ? $options['sync_batch_size'] : null;
    $batch_delay = isset($options['sync_batch_delay']) ? $options['sync_batch_delay'] : null;
    return $profile === 'light' && is_numeric($batch_size) && (int) $batch_size >= 1 && (int) $batch_size <= 3 && is_numeric($batch_delay) && (int) $batch_delay >= 3 && (int) $batch_delay <= 10;
}
function nmkr16_api_key_present() {
    $options = get_option('nmkr_connect_options', array());
    return is_array($options) && isset($options['api_key']) && is_string($options['api_key']) && trim($options['api_key']) !== '';
}

$prefix = $wpdb->prefix;
$projects = $prefix . 'nmkr_projects';
$tokens = $prefix . 'nmkr_tokens';
$details = $prefix . 'nmkr_token_details';
$history = $prefix . 'nmkr_sync_stats';
$metrics = $prefix . 'nmkr_sync_metrics';
$present = array(
    'projects' => nmkr16_table_exists($projects),
    'tokens' => nmkr16_table_exists($tokens),
    'token_details' => nmkr16_table_exists($details),
    'sync_stats' => nmkr16_table_exists($history),
    'sync_metrics' => nmkr16_table_exists($metrics),
);
$active_sql = nmkr16_sql_list(nmkr16_active_statuses());
$terminal_sql = nmkr16_sql_list(nmkr16_terminal_statuses());
$history_total = $present['sync_stats'] ? nmkr16_count_sql('SELECT COUNT(*) FROM ' . nmkr16_sql_ident($history)) : 0;
$active_history = $present['sync_stats'] ? nmkr16_count_sql('SELECT COUNT(*) FROM ' . nmkr16_sql_ident($history) . ' WHERE BINARY status IN (' . $active_sql . ')') : 0;
$terminal_history = $present['sync_stats'] ? nmkr16_count_sql('SELECT COUNT(*) FROM ' . nmkr16_sql_ident($history) . ' WHERE BINARY status IN (' . $terminal_sql . ')') : 0;
$metrics_total = $present['sync_metrics'] ? nmkr16_count_sql('SELECT COUNT(*) FROM ' . nmkr16_sql_ident($metrics)) : 0;
$latest_metric_time = $present['sync_metrics'] ? (string) $wpdb->get_var('SELECT last_sync_time FROM ' . nmkr16_sql_ident($metrics) . ' ORDER BY last_sync_time DESC, id DESC LIMIT 1') : '';
$last_sync_time = (string) get_option('nmkr_last_sync_time', '');
list($blocked_cron_hook_counts, $cron_inspectable, $blocked_sync_cron_count) = nmkr16_cron_state();
$latest_status = $present['sync_stats'] ? (string) $wpdb->get_var('SELECT status FROM ' . nmkr16_sql_ident($history) . ' ORDER BY id DESC LIMIT 1') : '';
$latest_end_valid = $present['sync_stats'] ? (bool) $wpdb->get_var('SELECT COUNT(*) FROM ' . nmkr16_sql_ident($history) . ' WHERE id = (SELECT MAX(id) FROM ' . nmkr16_sql_ident($history) . ') AND end_time IS NOT NULL AND end_time <> ""') : false;
$duplicate_project = ($present['projects'] && nmkr16_has_column($projects, 'project_uid')) ? nmkr16_count_sql('SELECT COUNT(*) FROM (SELECT project_uid FROM ' . nmkr16_sql_ident($projects) . ' WHERE project_uid IS NOT NULL AND project_uid <> "" GROUP BY project_uid HAVING COUNT(*) > 1) d') : 0;
$duplicate_token = ($present['tokens'] && nmkr16_has_column($tokens, 'token_uid')) ? nmkr16_count_sql('SELECT COUNT(*) FROM (SELECT token_uid FROM ' . nmkr16_sql_ident($tokens) . ' WHERE token_uid IS NOT NULL AND token_uid <> "" GROUP BY token_uid HAVING COUNT(*) > 1) d') : 0;
$duplicate_detail = ($present['token_details'] && nmkr16_has_column($details, 'token_uid')) ? nmkr16_count_sql('SELECT COUNT(*) FROM (SELECT token_uid FROM ' . nmkr16_sql_ident($details) . ' WHERE token_uid IS NOT NULL AND token_uid <> "" GROUP BY token_uid HAVING COUNT(*) > 1) d') : 0;
$invalid_relationship = ($present['tokens'] && $present['projects']) ? nmkr16_count_sql('SELECT COUNT(*) FROM ' . nmkr16_sql_ident($tokens) . ' t LEFT JOIN ' . nmkr16_sql_ident($projects) . ' p ON t.project_uid = p.project_uid WHERE t.project_uid IS NOT NULL AND t.project_uid <> "" AND p.project_uid IS NULL') : 0;
$invalid_relationship += ($present['token_details'] && $present['tokens']) ? nmkr16_count_sql('SELECT COUNT(*) FROM ' . nmkr16_sql_ident($details) . ' d LEFT JOIN ' . nmkr16_sql_ident($tokens) . ' t ON d.token_uid = t.token_uid WHERE d.token_uid IS NOT NULL AND d.token_uid <> "" AND t.token_uid IS NULL') : 0;
$impossible = 0;
if ($present['sync_stats']) { $impossible += nmkr16_count_sql('SELECT COUNT(*) FROM ' . nmkr16_sql_ident($history) . ' WHERE start_time IS NULL OR items_processed < 0 OR items_successful < 0 OR items_failed < 0 OR (end_time IS NOT NULL AND end_time < start_time)'); }
if ($present['sync_metrics']) { $impossible += nmkr16_count_sql('SELECT COUNT(*) FROM ' . nmkr16_sql_ident($metrics) . ' WHERE total_projects < 0 OR total_tokens < 0 OR total_sync_duration < 0 OR total_api_time < 0 OR average_response_time < 0 OR api_requests < 0 OR memory_usage < 0 OR last_sync_time IS NULL'); }

$output = array(
    'schema_version' => 1,
    'required_tables_present' => $present,
    'project_count' => $present['projects'] ? nmkr16_count_sql('SELECT COUNT(*) FROM ' . nmkr16_sql_ident($projects)) : 0,
    'project_max_id' => nmkr16_max_id($projects),
    'token_count' => $present['tokens'] ? nmkr16_count_sql('SELECT COUNT(*) FROM ' . nmkr16_sql_ident($tokens)) : 0,
    'token_max_id' => nmkr16_max_id($tokens),
    'token_detail_count' => $present['token_details'] ? nmkr16_count_sql('SELECT COUNT(*) FROM ' . nmkr16_sql_ident($details)) : 0,
    'token_detail_max_id' => nmkr16_max_id($details),
    'sync_history_total_count' => $history_total,
    'active_history_count' => $active_history,
    'terminal_history_count' => $terminal_history,
    'max_history_id' => nmkr16_max_id($history),
    'metrics_total_count' => $metrics_total,
    'max_metrics_id' => nmkr16_max_id($metrics),
    'duplicate_project_uid_count' => $duplicate_project,
    'duplicate_token_uid_count' => $duplicate_token,
    'duplicate_token_detail_uid_count' => $duplicate_detail,
    'invalid_relationship_count' => $invalid_relationship,
    'impossible_counter_count' => $impossible,
    'option_active_marker_count' => nmkr16_option_active_count(),
    'transient_active_marker_count' => nmkr16_transient_active_count(),
    'stale_recovery_marker_count' => nmkr16_stale_recovery_count(),
    'heartbeat_worker_evidence_count' => nmkr16_heartbeat_worker_count(),
    'sync_data_classification' => nmkr16_sync_data_classification(get_option('nmkr_sync_data', array())),
    'blocked_cron_hook_counts' => $blocked_cron_hook_counts,
    'blocked_sync_cron_count' => $blocked_sync_cron_count,
    'cron_inspectable' => $cron_inspectable,
    'light_profile_guard' => nmkr16_light_profile_ok(),
    'api_key_present' => nmkr16_api_key_present(),
    'last_sync_time_matches_latest_metrics' => ($last_sync_time === '' && $latest_metric_time === '') || ($last_sync_time !== '' && $last_sync_time === $latest_metric_time),
    'terminal_history_digest' => nmkr16_terminal_digest($history),
    'latest_history_id' => nmkr16_max_id($history),
    'latest_history_status_classification' => in_array(strtolower($latest_status), nmkr16_terminal_statuses(), true) ? strtolower($latest_status) : (nmkr16_is_active_status($latest_status) ? 'active' : 'unknown'),
    'latest_history_completed' => strtolower($latest_status) === 'completed' || strtolower($latest_status) === 'success',
    'latest_history_end_time_valid' => $latest_end_valid,
    'snapshot_epoch' => time(),
);

echo wp_json_encode($output, JSON_UNESCAPED_SLASHES) . "\n";
