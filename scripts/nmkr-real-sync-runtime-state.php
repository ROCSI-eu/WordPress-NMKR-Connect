<?php
if (!defined('ABSPATH')) {
    fwrite(STDERR, "WordPress is not loaded.\n");
    exit(1);
}

$active_statuses = array('initializing', 'finalizing', 'processing', 'processing_projects', 'processing_tokens', 'in_progress', 'running', 'pending');

$is_truthy = static function ($value): bool {
    if (is_bool($value)) {
        return $value;
    }
    if (is_int($value) || is_float($value)) {
        return ((float) $value) > 0;
    }
    if (is_string($value)) {
        $normalized = strtolower(trim($value));
        return in_array($normalized, array('1', 'true', 'yes', 'on', 'running', 'in_progress', 'processing', 'processing_projects', 'processing_tokens', 'initializing', 'finalizing', 'pending'), true);
    }
    return !empty($value);
};

$is_progress_active = static function ($value): bool {
    if (is_string($value)) {
        $value = trim($value);
    }
    if (is_numeric($value)) {
        $progress = (float) $value;
        return $progress > 0 && $progress < 100;
    }
    return false;
};

$is_active_status = static function ($value) use ($active_statuses): bool {
    if (!is_scalar($value)) {
        return false;
    }
    return in_array(strtolower(trim((string) $value)), $active_statuses, true);
};

$sync_data_active = static function ($value) use ($is_active_status): bool {
    if (!is_array($value)) {
        return false;
    }
    foreach (array('status', 'sync_status', 'state') as $key) {
        if (array_key_exists($key, $value) && $is_active_status($value[$key])) {
            return true;
        }
    }
    if (array_key_exists('completed', $value) && $value['completed'] === false) {
        return true;
    }
    foreach (array('in_progress', 'is_running', 'running', 'pending', 'active') as $key) {
        if (array_key_exists($key, $value) && $value[$key] === true) {
            return true;
        }
    }
    return false;
};

$option_active_marker_count = 0;
$in_progress_active = $is_truthy(get_option('nmkr_sync_in_progress', false));
$progress_active = $is_progress_active(get_option('nmkr_sync_progress', 0));
$status_active = $is_active_status(get_option('nmkr_sync_status', ''));
$sync_data_is_active = $sync_data_active(get_option('nmkr_sync_data', array()));
$durable_sync_active = $in_progress_active || $status_active || $sync_data_is_active;

if ($in_progress_active) {
    $option_active_marker_count++;
}
if ($progress_active) {
    $option_active_marker_count++;
}
if ($status_active) {
    $option_active_marker_count++;
}
if ($sync_data_is_active) {
    $option_active_marker_count++;
}
if ($durable_sync_active && $is_truthy(get_option('nmkr_sync_near_completion', false))) {
    $option_active_marker_count++;
}
$heartbeat = get_option('nmkr_sync_heartbeat', 0);
if ($durable_sync_active && is_numeric($heartbeat) && (float) $heartbeat > 0) {
    $option_active_marker_count++;
}

$external_object_cache = (bool) wp_using_ext_object_cache();
$runtime_transient_checks_performed = false;
$external_cache_active_marker_count = 0;
if ($external_object_cache) {
    $runtime_transient_checks_performed = true;
    if ($is_truthy(get_transient('nmkr_sync_in_progress'))) {
        $external_cache_active_marker_count++;
    }
    if ($is_progress_active(get_transient('nmkr_sync_progress'))) {
        $external_cache_active_marker_count++;
    }
    if ($is_active_status(get_transient('nmkr_sync_status'))) {
        $external_cache_active_marker_count++;
    }
    if ($is_truthy(get_transient('nmkr_stale_recovery_running'))) {
        $external_cache_active_marker_count++;
    }
}

$pending_sync_cron_count = 0;
$cron_state_inspectable = true;
$cron = get_option('cron', array());
$blocked_hooks = array('nmkr_execute_sync_background', 'nmkr_process_batch_hook', 'nmkr_sync_cron_hook', 'nmkr_install_sync_cron_hook');
if (!is_array($cron)) {
    $cron_state_inspectable = false;
} elseif (!isset($cron['version']) || (int) $cron['version'] !== 2) {
    $cron_state_inspectable = false;
} else {
    foreach ($cron as $timestamp => $timestamp_events) {
        if ($timestamp === 'version') {
            continue;
        }
        if (!is_scalar($timestamp) || !ctype_digit((string) $timestamp) || !is_array($timestamp_events)) {
            $cron_state_inspectable = false;
            break;
        }
        foreach ($timestamp_events as $hook => $events) {
            if (!is_string($hook) || !is_array($events)) {
                $cron_state_inspectable = false;
                break 2;
            }
            if (in_array($hook, $blocked_hooks, true)) {
                $pending_sync_cron_count += count($events);
            }
        }
    }
}

$options = get_option('nmkr_connect_options', array());
$profile_guard_passed = false;
if (is_array($options)) {
    $profile = isset($options['sync_profile']) ? (string) $options['sync_profile'] : '';
    $batch_size = $options['sync_batch_size'] ?? null;
    $batch_delay = $options['sync_batch_delay'] ?? null;
    $profile_guard_passed = $profile === 'light'
        && is_numeric($batch_size) && (int) $batch_size >= 1 && (int) $batch_size <= 3
        && is_numeric($batch_delay) && (int) $batch_delay >= 3 && (int) $batch_delay <= 10;
}

$admin_capability_ok = false;
$admin_identifier = getenv('WP_ADMIN_USER');
if (is_string($admin_identifier) && trim($admin_identifier) !== '') {
    $user = get_user_by('login', trim($admin_identifier));
    if (!$user && is_email(trim($admin_identifier))) {
        $user = get_user_by('email', trim($admin_identifier));
    }
    if ($user instanceof WP_User) {
        $admin_capability_ok = user_can($user, 'nmkr_manage_sync');
    }
}

$result = array(
    'external_object_cache' => $external_object_cache,
    'runtime_transient_checks_performed' => $runtime_transient_checks_performed,
    'option_active_marker_count' => $option_active_marker_count,
    'external_cache_active_marker_count' => $external_cache_active_marker_count,
    'sync_data_active' => $sync_data_is_active,
    'pending_sync_cron_count' => $pending_sync_cron_count,
    'cron_state_inspectable' => $cron_state_inspectable,
    'profile_guard_passed' => $profile_guard_passed,
    'admin_capability_ok' => $admin_capability_ok,
);

echo wp_json_encode($result, JSON_UNESCAPED_SLASHES) . "\n";
