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
function nmkr_benchmark_truthy($value) { return !empty($value); }
function nmkr_benchmark_progress_active($value) { return is_numeric($value) && (float) $value > 0 && (float) $value < 100; }
function nmkr_benchmark_status_active($value) {
    if (!is_scalar($value)) return !empty($value);
    $status = strtolower(trim((string) $value));
    if ($status === '' || in_array($status, nmkr_benchmark_terminal_statuses(), true)) return false;
    return true; // Canonical active statuses and unknown nonterminal values both fail closed.
}
function nmkr_benchmark_table_state($table) {
    global $wpdb;
    $discovered = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
    if ($wpdb->last_error) return new WP_Error('nmkr_benchmark_state_ambiguous', 'State inspection was ambiguous.');
    $exists = $discovered === $table;
    if (!$exists) return array('exists' => false, 'count' => 0, 'max_id' => 0, 'digest' => null);
    $digest = hash_init('sha256'); $terminal_digest = hash_init('sha256');
    $count = 0; $max_id = 0; $active = 0; $terminal_count = 0; $latest = null;
    do {
        // Keyset pagination bounds peak memory even for large prepared datasets.
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM `{$table}` WHERE id > %d ORDER BY id ASC LIMIT 250", $max_id), ARRAY_A);
        if ($wpdb->last_error || !is_array($rows)) return new WP_Error('nmkr_benchmark_state_ambiguous', 'State inspection was ambiguous.');
        foreach ($rows as $row) {
            if (!isset($row['id']) || (int) $row['id'] <= $max_id) return new WP_Error('nmkr_benchmark_state_ambiguous', 'State inspection was ambiguous.');
            $max_id = (int) $row['id']; $count++; $latest = $row;
            hash_update($digest, serialize(nmkr_benchmark_canonical($row)) . "\n");
            if (substr($table, -15) === 'nmkr_sync_stats') {
                $status = isset($row['status']) ? strtolower(trim((string) $row['status'])) : '';
                if (in_array($status, nmkr_benchmark_terminal_statuses(), true)) { $terminal_count++; hash_update($terminal_digest, serialize(nmkr_benchmark_canonical($row)) . "\n"); } else $active++;
            }
        }
    } while (count($rows) === 250);
    $state = array('exists' => true, 'count' => $count, 'max_id' => $max_id, 'digest' => hash_final($digest));
    if (substr($table, -15) === 'nmkr_sync_stats') {
        $state['active_count'] = $active; $state['terminal_count'] = $terminal_count; $state['terminal_digest'] = hash_final($terminal_digest);
    }
    if (substr($table, -17) === 'nmkr_sync_metrics') { $state['latest_digest'] = nmkr_benchmark_digest($latest); $state['latest_timestamp_digest'] = nmkr_benchmark_digest(is_array($latest) ? array($latest['last_sync_time'] ?? null, $latest['created_at'] ?? null) : null); }
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
    return array('present' => $present, 'active' => $active, 'value' => $present ? maybe_unserialize($records[$value_name]) : null, 'digest' => nmkr_benchmark_digest($records));
}
function nmkr_benchmark_option_records($names) {
    global $wpdb;
    if (empty($names)) return array();
    $placeholders = implode(', ', array_fill(0, count($names), '%s'));
    $rows = $wpdb->get_results(
        $wpdb->prepare("SELECT option_name, option_value FROM `{$wpdb->options}` WHERE option_name IN ({$placeholders})", ...$names),
        ARRAY_A
    );
    if ($wpdb->last_error || !is_array($rows)) return new WP_Error('nmkr_benchmark_state_ambiguous', 'State inspection was ambiguous.');
    $records = array();
    foreach ($rows as $row) $records[(string) $row['option_name']] = maybe_unserialize($row['option_value']);
    return $records;
}
function nmkr_benchmark_state_snapshot($source_sha, $deployed_sha, $source_clean, $deployed_clean) {
    global $wpdb;
    if (function_exists('wp_using_ext_object_cache') && wp_using_ext_object_cache()) return new WP_Error('nmkr_benchmark_state_ambiguous', 'State inspection was ambiguous.');
    $tables = array();
    foreach (array('projects','tokens','token_details','sync_stats','sync_metrics') as $suffix) {
        $state = nmkr_benchmark_table_state($wpdb->prefix . 'nmkr_' . $suffix);
        if (is_wp_error($state)) return $state;
        $tables[$suffix] = $state;
    }
    $option_names = array('nmkr_last_sync_time','nmkr_sync_data','nmkr_sync_in_progress','nmkr_sync_progress','nmkr_sync_status','nmkr_stale_recovery_running','nmkr_sync_near_completion','nmkr_sync_worker_started_at','nmkr_sync_worker_lock','nmkr_sync_heartbeat','nmkr_sync_owner','nmkr_connect_options');
    $transient_names = array('nmkr_sync_in_progress','nmkr_sync_progress','nmkr_sync_status','nmkr_stale_recovery_running','nmkr_current_sync_stats_live','nmkr_current_sync_stats_summary','nmkr_active_sync_metrics','nmkr_sync_performance_metrics','nmkr_sync_worker_started_at','nmkr_sync_worker_lock','nmkr_sync_finalization_lock','nmkr_sync_heartbeat');
    // Read authoritative rows on every snapshot; request-local option and cron caches can be stale.
    $option_records = nmkr_benchmark_option_records(array_merge($option_names, array('cron')));
    if (is_wp_error($option_records)) return $option_records;
    $sync_data = $option_records['nmkr_sync_data'] ?? null;
    $sync_stats_id = is_array($sync_data) ? (int) ($sync_data['sync_stats_id'] ?? 0) : 0;
    if ($sync_stats_id > 0) {
        $resume_name = 'nmkr_sync_finalization_resume_' . $sync_stats_id;
        $resume_records = nmkr_benchmark_option_records(array($resume_name));
        if (is_wp_error($resume_records)) return $resume_records;
        $option_names[] = $resume_name;
        $option_records = array_merge($option_records, $resume_records);
    }
    $options = array(); foreach ($option_names as $name) { $present = array_key_exists($name, $option_records); $options[$name] = array('present' => $present, 'digest' => nmkr_benchmark_digest($present ? $option_records[$name] : null)); }
    $transients = array(); foreach ($transient_names as $name) { $state = nmkr_benchmark_transient_state($name); if (is_wp_error($state)) return $state; $transients[$name] = $state; }
    $cron = $option_records['cron'] ?? array('version' => 2);
    if (!is_array($cron) || !isset($cron['version']) || (int) $cron['version'] !== 2) return new WP_Error('nmkr_benchmark_state_ambiguous', 'State inspection was ambiguous.');
    $nmkr_cron = array();
    foreach ($cron as $timestamp => $hooks) {
        if ($timestamp === 'version') continue;
        if (!ctype_digit((string) $timestamp) || !is_array($hooks)) return new WP_Error('nmkr_benchmark_state_ambiguous', 'State inspection was ambiguous.');
        foreach ($hooks as $hook => $events) {
            if (!is_string($hook) || !is_array($events)) return new WP_Error('nmkr_benchmark_state_ambiguous', 'State inspection was ambiguous.');
            foreach ($events as $event) if (!is_array($event) || (isset($event['args']) && !is_array($event['args']))) return new WP_Error('nmkr_benchmark_state_ambiguous', 'State inspection was ambiguous.');
            if (strpos($hook, 'nmkr_') === 0) $nmkr_cron[$timestamp][$hook] = $events;
        }
    }
    $active = false;
    $lifecycle_hooks = array('nmkr_execute_sync_background', 'nmkr_process_batch_hook', 'nmkr_sync_cron_hook', 'nmkr_install_sync_cron_hook', 'nmkr_resume_sync_finalization');
    foreach ($nmkr_cron as $hooks) {
        foreach ($lifecycle_hooks as $hook) if (!empty($hooks[$hook])) $active = true;
    }
    foreach (array('nmkr_sync_owner', 'nmkr_sync_worker_lock', 'nmkr_sync_heartbeat', 'nmkr_sync_worker_started_at', 'nmkr_stale_recovery_running') as $name) if (nmkr_benchmark_truthy($option_records[$name] ?? null)) $active = true;
    $durable_active = nmkr_benchmark_truthy($option_records['nmkr_sync_in_progress'] ?? null) || nmkr_benchmark_status_active($option_records['nmkr_sync_status'] ?? '') || (array_key_exists('nmkr_sync_data', $option_records) && !nmkr_benchmark_sync_data_is_terminal($option_records['nmkr_sync_data']));
    if ($durable_active || nmkr_benchmark_progress_active($option_records['nmkr_sync_progress'] ?? 0)) $active = true;
    if ($durable_active && nmkr_benchmark_truthy($option_records['nmkr_sync_near_completion'] ?? null)) $active = true;
    if (isset($resume_name) && array_key_exists($resume_name, $option_records)) $active = true;
    if (array_key_exists('nmkr_sync_data', $option_records) && !nmkr_benchmark_sync_data_is_terminal($option_records['nmkr_sync_data'])) $active = true;
    foreach ($transients as $name => $item) {
        if (!$item['active']) continue;
        if ($name === 'nmkr_sync_progress') { if (nmkr_benchmark_progress_active($item['value'])) $active = true; continue; }
        if ($name === 'nmkr_sync_status') { if (nmkr_benchmark_status_active($item['value'])) $active = true; continue; }
        if (nmkr_benchmark_truthy($item['value'])) $active = true;
    }
    if (!empty($tables['sync_stats']['active_count'])) $active = true;
    return array('tables'=>$tables,'options'=>$options,'transients'=>$transients,'cron_digest'=>nmkr_benchmark_digest($nmkr_cron),'active'=>$active,'source_sha'=>$source_sha,'deployed_sha'=>$deployed_sha,'source_clean'=>(bool)$source_clean,'deployed_clean'=>(bool)$deployed_clean);
}
function nmkr_benchmark_state_equal($before, $after) { return is_array($before) && is_array($after) && hash_equals(nmkr_benchmark_digest($before), nmkr_benchmark_digest($after)); }
