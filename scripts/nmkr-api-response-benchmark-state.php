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
function nmkr_benchmark_table_state($table) {
    global $wpdb;
    $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table;
    if ($wpdb->last_error || !$exists) return array('exists' => $exists, 'count' => 0, 'max_id' => 0, 'digest' => null);
    $rows = $wpdb->get_results("SELECT * FROM `{$table}` ORDER BY id ASC", ARRAY_A);
    if ($wpdb->last_error || !is_array($rows)) return new WP_Error('nmkr_benchmark_state_ambiguous', 'State inspection was ambiguous.');
    $state = array('exists' => true, 'count' => count($rows), 'max_id' => empty($rows) ? 0 : (int) end($rows)['id'], 'digest' => nmkr_benchmark_digest($rows));
    if (substr($table, -15) === 'nmkr_sync_stats') {
        $terminal = array(); $active = 0;
        foreach ($rows as $row) { $status = isset($row['status']) ? (string) $row['status'] : ''; if (in_array($status, array('completed','failed','stopped'), true)) $terminal[] = $row; else $active++; }
        $state['active_count'] = $active; $state['terminal_count'] = count($terminal); $state['terminal_digest'] = nmkr_benchmark_digest($terminal);
    }
    if (substr($table, -17) === 'nmkr_sync_metrics') { $latest = empty($rows) ? null : end($rows); $state['latest_digest'] = nmkr_benchmark_digest($latest); $state['latest_timestamp_digest'] = nmkr_benchmark_digest(is_array($latest) ? array($latest['last_sync_time'] ?? null, $latest['created_at'] ?? null) : null); }
    return $state;
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
    $option_names = array('nmkr_last_sync_time','nmkr_sync_data','nmkr_sync_active','nmkr_sync_finalization_resume','nmkr_sync_recovery','nmkr_sync_worker','nmkr_sync_heartbeat','nmkr_connect_options');
    $transient_names = array('nmkr_current_sync_stats_live','nmkr_current_sync_stats_summary','nmkr_active_sync_metrics','nmkr_sync_performance_metrics','nmkr_sync_worker_started_at','nmkr_sync_worker_lock','nmkr_sync_finalization_lock','nmkr_sync_heartbeat');
    $options = array(); foreach ($option_names as $name) $options[$name] = array('present' => get_option($name, '__nmkr_missing__') !== '__nmkr_missing__', 'digest' => nmkr_benchmark_digest(get_option($name, '__nmkr_missing__')));
    $transients = array(); foreach ($transient_names as $name) { $value = get_transient($name); $transients[$name] = array('present' => $value !== false, 'digest' => nmkr_benchmark_digest($value)); }
    $cron = _get_cron_array(); if (!is_array($cron)) return new WP_Error('nmkr_benchmark_state_ambiguous', 'State inspection was ambiguous.');
    $nmkr_cron = array(); foreach ($cron as $timestamp => $hooks) foreach ((array) $hooks as $hook => $events) if (strpos($hook, 'nmkr_') === 0) $nmkr_cron[$timestamp][$hook] = $events;
    $active = false; foreach (array('nmkr_sync_data','nmkr_sync_active','nmkr_sync_finalization_resume','nmkr_sync_recovery','nmkr_sync_worker') as $name) if (get_option($name, false) !== false) $active = true;
    foreach ($transients as $item) if ($item['present']) $active = true;
    return array('tables'=>$tables,'options'=>$options,'transients'=>$transients,'cron_digest'=>nmkr_benchmark_digest($nmkr_cron),'active'=>$active,'source_sha'=>$source_sha,'deployed_sha'=>$deployed_sha,'source_clean'=>(bool)$source_clean,'deployed_clean'=>(bool)$deployed_clean);
}
function nmkr_benchmark_state_equal($before, $after) { return is_array($before) && is_array($after) && hash_equals(nmkr_benchmark_digest($before), nmkr_benchmark_digest($after)); }
