<?php
/** Private, read-only state capture for the M3-06 benchmark (not Phase 16A). */
function nmkr_benchmark_canonical($value) {
    if (is_object($value)) $value = (array) $value;
    if (is_array($value)) {
        if (array_keys($value) !== range(0, count($value) - 1)) ksort($value, SORT_STRING);
        foreach ($value as $key => $item) $value[$key] = nmkr_benchmark_canonical($item);
    }
    return $value;
}
function nmkr_benchmark_digest($value) { return hash('sha256', serialize(nmkr_benchmark_canonical($value))); }
function nmkr_benchmark_terminal_statuses() {
    return function_exists('nmkr_sync_terminal_statuses')
        ? nmkr_sync_terminal_statuses()
        : array('completed', 'success', 'failed', 'error', 'stopped', 'cancelled', 'aborted');
}
function nmkr_benchmark_sync_data_is_terminal($value) {
    if (!is_array($value) || empty($value)) return false;
    foreach (array('status', 'sync_status', 'state') as $key) {
        if (isset($value[$key]) && in_array(strtolower(trim((string) $value[$key])), nmkr_benchmark_terminal_statuses(), true)) return true;
    }
    return false;
}
function nmkr_benchmark_table_state($table) {
    global $wpdb;
    $discovered = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
    if ($wpdb->last_error) return new WP_Error('nmkr_benchmark_state_ambiguous', 'State inspection was ambiguous.');
    $exists = $discovered === $table;
    if (!$exists) return array('exists' => false, 'count' => 0, 'max_id' => 0, 'digest' => null);
    $rows = $wpdb->get_results("SELECT * FROM `{$table}` ORDER BY id ASC", ARRAY_A);
    if ($wpdb->last_error || !is_array($rows)) return new WP_Error('nmkr_benchmark_state_ambiguous', 'State inspection was ambiguous.');
    $state = array('exists' => true, 'count' => count($rows), 'max_id' => empty($rows) ? 0 : (int) end($rows)['id'], 'digest' => nmkr_benchmark_digest($rows));
    if (substr($table, -15) === 'nmkr_sync_stats') {
        $terminal = array(); $active = 0;
        foreach ($rows as $row) { $status = isset($row['status']) ? strtolower(trim((string) $row['status'])) : ''; if (in_array($status, nmkr_benchmark_terminal_statuses(), true)) $terminal[] = $row; else $active++; }
        $state['active_count'] = $active; $state['terminal_count'] = count($terminal); $state['terminal_digest'] = nmkr_benchmark_digest($terminal);
    }
    if (substr($table, -17) === 'nmkr_sync_metrics') { $latest = empty($rows) ? null : end($rows); $state['latest_digest'] = nmkr_benchmark_digest($latest); $state['latest_timestamp_digest'] = nmkr_benchmark_digest(is_array($latest) ? array($latest['last_sync_time'] ?? null, $latest['created_at'] ?? null) : null); }
    return $state;
}
function nmkr_benchmark_transient_state($name) {
    global $wpdb;
    $value_name = '_transient_' . $name;
    $timeout_name = '_transient_timeout_' . $name;
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT option_name, option_value FROM `{$wpdb->options}` WHERE option_name IN (%s, %s)",
            $value_name,
            $timeout_name
        ),
        ARRAY_A
    );
    if ($wpdb->last_error || !is_array($rows)) return new WP_Error('nmkr_benchmark_state_ambiguous', 'State inspection was ambiguous.');
    $records = array();
    foreach ($rows as $row) $records[(string) $row['option_name']] = (string) $row['option_value'];
    $present = array_key_exists($value_name, $records);
    $timeout_present = array_key_exists($timeout_name, $records);
    $timeout = $timeout_present && ctype_digit($records[$timeout_name]) ? (int) $records[$timeout_name] : null;
    // Inspect raw option rows: get_transient() can delete expired records while reading them.
    $active = $present && (!$timeout_present || $timeout === null || $timeout > time());
    return array('present' => $present, 'active' => $active, 'digest' => nmkr_benchmark_digest($records));
}
function nmkr_benchmark_state_snapshot($source_sha, $deployed_sha, $source_clean, $deployed_clean) {
    global $wpdb;
    if (!function_exists('_get_cron_array') || (function_exists('wp_using_ext_object_cache') && wp_using_ext_object_cache())) return new WP_Error('nmkr_benchmark_state_ambiguous', 'State inspection was ambiguous.');
    $tables = array();
    foreach (array('projects','tokens','token_details','sync_stats','sync_metrics') as $suffix) {
        $state = nmkr_benchmark_table_state($wpdb->prefix . 'nmkr_' . $suffix);
        if (is_wp_error($state)) return $state;
        $tables[$suffix] = $state;
    }
    $option_names = array('nmkr_last_sync_time','nmkr_sync_data','nmkr_sync_active','nmkr_sync_finalization_resume','nmkr_sync_recovery','nmkr_sync_worker','nmkr_sync_heartbeat','nmkr_sync_owner','nmkr_connect_options');
    $transient_names = array('nmkr_current_sync_stats_live','nmkr_current_sync_stats_summary','nmkr_active_sync_metrics','nmkr_sync_performance_metrics','nmkr_sync_worker_started_at','nmkr_sync_worker_lock','nmkr_sync_finalization_lock','nmkr_sync_heartbeat');
    $options = array(); foreach ($option_names as $name) $options[$name] = array('present' => get_option($name, '__nmkr_missing__') !== '__nmkr_missing__', 'digest' => nmkr_benchmark_digest(get_option($name, '__nmkr_missing__')));
    $transients = array(); foreach ($transient_names as $name) { $state = nmkr_benchmark_transient_state($name); if (is_wp_error($state)) return $state; $transients[$name] = $state; }
    $cron = _get_cron_array(); if (!is_array($cron)) return new WP_Error('nmkr_benchmark_state_ambiguous', 'State inspection was ambiguous.');
    $nmkr_cron = array(); foreach ($cron as $timestamp => $hooks) foreach ((array) $hooks as $hook => $events) if (strpos($hook, 'nmkr_') === 0) $nmkr_cron[$timestamp][$hook] = $events;
    $active = false; foreach (array('nmkr_sync_active','nmkr_sync_finalization_resume','nmkr_sync_recovery','nmkr_sync_worker','nmkr_sync_owner') as $name) if (get_option($name, false) !== false) $active = true;
    $sync_data = get_option('nmkr_sync_data', false);
    if ($sync_data !== false && !nmkr_benchmark_sync_data_is_terminal($sync_data)) $active = true;
    foreach ($transients as $item) if ($item['active']) $active = true;
    if (!empty($tables['sync_stats']['active_count'])) $active = true;
    return array('tables'=>$tables,'options'=>$options,'transients'=>$transients,'cron_digest'=>nmkr_benchmark_digest($nmkr_cron),'active'=>$active,'source_sha'=>$source_sha,'deployed_sha'=>$deployed_sha,'source_clean'=>(bool)$source_clean,'deployed_clean'=>(bool)$deployed_clean);
}
function nmkr_benchmark_state_equal($before, $after) { return is_array($before) && is_array($after) && hash_equals(nmkr_benchmark_digest($before), nmkr_benchmark_digest($after)); }
