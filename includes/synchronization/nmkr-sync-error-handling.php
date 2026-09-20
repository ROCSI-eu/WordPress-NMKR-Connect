<?php
/**
 * NMKR Connect - Error Handling Functions
 *
 * This file contains functions for handling errors and cleaning up
 * during synchronization processes with NMKR API.
 *
 * @package NMKR Connect
 * @since 0.25.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Generic function to clear NMKR sync related cron jobs and data
 * 
 * @param string $context Optional context for logging (e.g., 'sync_start', 'max_errors', 'manual_cleanup')
 * @param bool $clear_data Whether to clear sync data (default: true)
 * @param bool $force Whether to forcibly clear all data regardless of state (default: false)
 * @return array Result of the cleanup operation
 */
/**
 * Broad legacy cleanup implementation. Call only from the ownerless
 * coordinator while the owner advisory lock is held.
 */
function nmkr_clear_sync_jobs_ownerless($context = 'manual_cleanup', $clear_data = true, $force = false) {
    $result = array(
        'success' => true,
        'message' => '',
        'cleared_jobs' => array(),
        'cleared_data' => array()
    );

    // Get the current sync data to see if we have an active sync stats record
    $sync_data = nmkr_get_sync_data();
    $sync_stats_id = ($sync_data && isset($sync_data['sync_stats_id'])) ? $sync_data['sync_stats_id'] : null;
    if ($sync_stats_id && function_exists('nmkr_clear_sync_finalization_resume')) {
        nmkr_clear_sync_finalization_resume($sync_stats_id);
    }
    
    // Get current sync stats directly from database if we have an ID
    if ($sync_stats_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'nmkr_sync_stats';
        $sync_stats = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $sync_stats_id),
            ARRAY_A
        );
    }

    // First, stop any running cron jobs
    // Clear the main batch processing hook - try multiple times to ensure it's cleared
    $cleared = false;
    for ($i = 0; $i < 3; $i++) {
        $attempt_cleared = wp_clear_scheduled_hook('nmkr_process_batch_hook');
        if ($attempt_cleared !== false) {
            $cleared = true;
            break;
        }
        // Small delay before retrying
        usleep(100000); // 100ms
    }
    
    if ($cleared) {
        $result['cleared_jobs'][] = 'nmkr_process_batch_hook';
    }

    // Clear any other NMKR-related cron jobs
    $nmkr_hooks = array(
        'nmkr_sync_cron_hook',
        'nmkr_install_sync_cron_hook'
    );

    foreach ($nmkr_hooks as $hook) {
        $cleared = wp_clear_scheduled_hook($hook);
        if ($cleared !== false) {
            $result['cleared_jobs'][] = $hook;
        }
    }
    
    // Force stop any running batches by cleaning up options and transients
    $options_to_clean = array(
        'nmkr_sync_progress',
        'nmkr_sync_current_item',
        'nmkr_sync_total_items',
        'nmkr_sync_current_count',
        'nmkr_sync_error',
        'nmkr_sync_status',
        'nmkr_sync_start_time'
    );
    
    foreach ($options_to_clean as $option) {
        delete_option($option);
        $result['cleared_data'][] = $option;
    }
    
    // Reset progress indicators
    nmkr_update_sync_progress(0, 100, '');
    update_option('nmkr_sync_status', 'idle');

    // Clear additional transients
    $transients_to_clean = array(
        'nmkr_sync_in_progress',
        'nmkr_last_sync_error',
        'nmkr_current_sync_stats',
        'nmkr_sync_batch_state',
        'nmkr_api_connection_status',
        'nmkr_current_sync_stats_live'
    );
    
    foreach ($transients_to_clean as $transient) {
        delete_transient($transient);
        $result['cleared_data'][] = $transient;
    }

    // Clean up sync heartbeat and last-progress markers on manual/normal stop
    if (function_exists('nmkr_cleanup_sync_heartbeat')) {
        nmkr_cleanup_sync_heartbeat();
    }
    delete_option('nmkr_last_progress_update_time');
    delete_option('nmkr_last_progress_value');

    // If we have an active sync stats record, mark it as cancelled or stopped
    if ($sync_stats_id) {
        // If force is true or the status is stuck in processing, force it to 'stopped'
        $current_status = isset($sync_stats) ? $sync_stats['status'] : null;
        $end_time = isset($sync_stats['end_time']) ? $sync_stats['end_time'] : null;
        $active_statuses = array('initializing', 'processing_projects', 'processing_tokens', 'in_progress', 'running');
        $has_end_time = !empty($end_time);
        $is_terminal = function_exists('nmkr_is_sync_terminal_status')
            ? nmkr_is_sync_terminal_status($current_status)
            : in_array($current_status, array('completed', 'success', 'failed', 'error', 'stopped', 'cancelled', 'aborted'), true);
        $is_active_or_incomplete = in_array($current_status, $active_statuses, true) || (!$has_end_time && !$is_terminal);

        if ($is_active_or_incomplete) {
            if ($force || $current_status === 'processing_tokens' || $current_status === 'processing_projects') {
                nmkr_update_sync_stats($sync_stats_id, [
                    'status' => 'stopped',
                    'end_time' => nmkr_get_timestamp(),
                    'error_message' => "Sync stopped forcibly: {$context}"
                ]);
                
                $result['status_update'] = "Updated sync status from '{$current_status}' to 'stopped'";
            } else {
                nmkr_update_sync_stats($sync_stats_id, [
                    'status' => 'cancelled',
                    'end_time' => nmkr_get_timestamp(),
                    'error_message' => "Sync cancelled manually: {$context}"
                ]);
            }
        } else {
            $result['status_update'] = "Preserved immutable historical sync stats row '{$sync_stats_id}' with status '{$current_status}'";
            nmkr_log_data_sync(
                'Cleanup preserved immutable historical sync stats row',
                'info',
                array(
                    'context' => $context,
                    'sync_stats_id' => $sync_stats_id,
                    'status' => $current_status,
                    'end_time' => $end_time
                )
            );
        }
    }

    // Clear sync data if requested
    if ($clear_data) {
        nmkr_clear_sync_data();
        $result['cleared_data'][] = 'nmkr_sync_data';
    }

    // Log the cleanup operation
    nmkr_log_data_sync('Cron cleanup performed', 'info', array(
        'context' => $context,
        'force' => $force,
        'cleared_jobs' => $result['cleared_jobs'],
        'cleared_data' => $result['cleared_data'],
        'sync_stats_id' => $sync_stats_id
    ));

    $result['message'] = sprintf(
        'Cleared %d cron jobs and %d data items in context: %s',
        count($result['cleared_jobs']),
        count($result['cleared_data']),
        $context
    );

    return $result;
}

/**
 * Public compatibility wrapper. A direct owner makes broad cleanup unsafe;
 * ownerless legacy cleanup is serialized with Start admission.
 */
function nmkr_clear_sync_jobs($context = 'manual_cleanup', $clear_data = true, $force = false) {
    $result = nmkr_coordinate_sync_cleanup('generic', function () use ($context, $clear_data, $force) {
        return nmkr_clear_sync_jobs_ownerless($context, $clear_data, $force);
    });
    if (is_wp_error($result)) {
        return array(
            'success' => false,
            'message' => $result->get_error_message(),
            'error_code' => $result->get_error_code(),
            'cleared_jobs' => array(),
            'cleared_data' => array(),
        );
    }
    return is_array($result) ? $result : array(
        'success' => false,
        'message' => __('Synchronization cleanup could not be verified.', 'connector-for-nmkr'),
        'error_code' => 'sync_cleanup_owner_changed',
        'cleared_jobs' => array(),
        'cleared_data' => array(),
    );
}

/** Complete legacy Stop path; caller must hold the proven-ownerless lock. */
function nmkr_stop_ownerless_sync($force = false) {
    global $wpdb;
    $sync_data = nmkr_get_sync_data();
    $sync_stats_id = is_array($sync_data) ? (int) ($sync_data['sync_stats_id'] ?? 0) : 0;
    $table_name = $wpdb->prefix . 'nmkr_sync_stats';
    $history = false;
    if ($sync_stats_id > 0) {
        $history = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $sync_stats_id), ARRAY_A);
    } else {
        $active_statuses = array('initializing', 'processing_projects', 'processing_tokens', 'in_progress', 'running');
        $placeholders = implode(', ', array_fill(0, count($active_statuses), '%s'));
        $history = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE status IN ($placeholders) ORDER BY id DESC LIMIT 1", $active_statuses), ARRAY_A);
        $sync_stats_id = is_array($history) ? (int) ($history['id'] ?? 0) : 0;
    }
    if (is_array($history)) {
        $status = (string) ($history['status'] ?? '');
        $terminal = function_exists('nmkr_is_sync_terminal_status') && nmkr_is_sync_terminal_status($status);
        if (!$terminal) {
            $target = $force ? 'stopped' : 'cancelled';
            if (!nmkr_update_sync_stats($sync_stats_id, array(
                'status' => $target,
                'end_time' => nmkr_get_timestamp(),
                'error_message' => $force ? 'Synchronization stopped forcibly by administrator.' : 'Synchronization cancelled by administrator.',
            ))) {
                return new WP_Error('sync_stop_history_cleanup_failed', __('Synchronization history could not be stopped safely.', 'connector-for-nmkr'));
            }
            $verified = $wpdb->get_row($wpdb->prepare("SELECT status, end_time FROM $table_name WHERE id = %d", $sync_stats_id), ARRAY_A);
            if (!is_array($verified) || ($verified['status'] ?? '') !== $target || empty($verified['end_time'])) {
                return new WP_Error('sync_stop_history_cleanup_failed', __('Synchronization history cleanup could not be verified.', 'connector-for-nmkr'));
            }
        }
    }
    $cleanup = nmkr_clear_sync_jobs_ownerless('manual_stop', true, $force);
    if (empty($cleanup['success'])) {
        return new WP_Error('sync_stop_cleanup_failed', __('Synchronization cleanup failed.', 'connector-for-nmkr'));
    }
    update_option('nmkr_sync_user_stopped', true);
    set_transient('nmkr_sync_user_stopped', true, NMKR_SYNC_TRANSIENT_TTL);
    update_option('nmkr_sync_stop_requested', time());
    update_option('nmkr_sync_in_progress', false);
    delete_transient('nmkr_sync_in_progress');
    nmkr_update_sync_progress(0, 100, '⛔ Synchronization Stopped', true);
    update_option('nmkr_sync_current_item', '');
    if (function_exists('nmkr_cleanup_sync_heartbeat')) { nmkr_cleanup_sync_heartbeat(); }
    foreach (array('nmkr_current_sync_stats_live', 'nmkr_active_sync_metrics', 'nmkr_sync_performance_metrics', 'nmkr_api_connection_status') as $key) { delete_transient($key); }
    $cleanup['sync_stats_id'] = $sync_stats_id;
    $cleanup['force_applied'] = (bool) $force;
    return $cleanup;
}

/**
 * Enhanced function to forcibly stop a running synchronization process
 * This is used when a regular stop fails or when a sync process appears to be stuck
 *
 * @param string $context The context for the stop (e.g., 'manual_stop', 'force_stop', 'stuck_detected')
 * @return array Result of the stop operation
 */
/** Legacy force cleanup; requires the ownerless advisory-lock boundary. */
function nmkr_force_stop_sync_ownerless($context = 'force_stop') {
    global $wpdb;
    
    // Log UI status update for force stop
    nmkr_log_ui_status('UI: Force stopping synchronization - ' . $context, 'warning');
    
    // Set stage to indicate force stopping (consistent 0→100 reset)
    nmkr_update_sync_progress(0, 100, '⏹️ Forcibly Cleaning Up All Resources');
    
    // Log UI status update for force stopping
    nmkr_log_ui_status('UI: Changed status to "⏹️ Stopping Synchronization - Forcibly Cleaning Up All Resources"', 'info');
    
    // Always mark sync as not in progress to avoid stuck state
    update_option('nmkr_sync_in_progress', false);
    delete_transient('nmkr_sync_in_progress');
    
    // Immediately clear all cron events - do this multiple times to ensure they're cleared
    for ($i = 0; $i < 3; $i++) {
        wp_clear_scheduled_hook('nmkr_process_batch_hook');
        wp_clear_scheduled_hook('nmkr_sync_cron_hook');
        wp_clear_scheduled_hook('nmkr_install_sync_cron_hook');
        usleep(100000); // 100ms delay between attempts
    }
    
    // Find any active sync records in the database and mark them as failed
    $table_name = $wpdb->prefix . 'nmkr_sync_stats';
    $active_syncs = $wpdb->get_results(
        "SELECT * FROM $table_name WHERE status IN ('initializing', 'processing_projects', 'processing_tokens') ORDER BY id DESC",
        ARRAY_A
    );
    
    if ($active_syncs) {
        foreach ($active_syncs as $sync) {
            nmkr_update_sync_stats($sync['id'], [
                'status' => 'failed',
                'end_time' => nmkr_get_timestamp(),
                'error_message' => 'Sync process forcibly terminated: ' . $context
            ]);
        }
    }
    
    // Run a cleanup with force parameter
    $cleanup_result = nmkr_clear_sync_jobs_ownerless('force_stop', true, true);
    
    // Reset all sync state options for a clean state
    $options_to_reset = array(
        'nmkr_sync_progress' => 0,
        'nmkr_sync_status' => 'idle',
        'nmkr_sync_current_item' => '',
        'nmkr_sync_error' => '',
        'nmkr_sync_in_progress' => false,
        'nmkr_sync_start_time' => 0
    );
    
    foreach ($options_to_reset as $option => $value) {
        update_option($option, $value);
    }
    
    // Clean up sync heartbeat when forcibly stopping
    nmkr_cleanup_sync_heartbeat();
    
    // Log UI status update for final force stop state
    nmkr_log_ui_status('UI: Changed status to "Sync stopped forcibly", reset progress bar to 0%', 'info');
    nmkr_log_ui_status('UI: Restoring "Start Synchronization" button, hiding "Stop Synchronization" button', 'info');
    
    // Clear all related transients
    $transients_to_clear = array(
        'nmkr_sync_in_progress',
        'nmkr_last_sync_error',
        'nmkr_current_sync_stats',
        'nmkr_sync_batch_state',
        'nmkr_api_connection_status',
        'nmkr_current_sync_stats_live'
    );
    
    foreach ($transients_to_clear as $transient) {
        delete_transient($transient);
    }
    
    // Block any future sync stage updates
    update_option('nmkr_sync_stop_requested', time());
    
    // Clear sync data
    nmkr_clear_sync_data();
    
    // Log the result
    nmkr_log_data_sync(
        'Force stop completed',
        'info',
        array(
            'cleared_jobs' => $cleanup_result['cleared_jobs'],
            'cleared_data' => $cleanup_result['cleared_data'],
            'active_syncs_terminated' => count($active_syncs)
        )
    );
    
    return array(
        'success' => true,
        'message' => 'Synchronization forcibly stopped',
        'cleared_jobs' => $cleanup_result['cleared_jobs'],
        'cleared_data' => $cleanup_result['cleared_data'],
        'active_syncs_terminated' => count($active_syncs)
    );
}

/**
 * Force stop is intentionally no more permissive than normal Stop for direct
 * runs: an executing worker remains owned until cooperative stop exists.
 */
function nmkr_force_stop_sync($context = 'force_stop') {
    $result = nmkr_coordinate_sync_cleanup('cancel', function () use ($context) {
        return nmkr_force_stop_sync_ownerless($context);
    });
    if ($result === true) {
        return array('success' => true, 'message' => __('Queued synchronization cancelled.', 'connector-for-nmkr'), 'cleared_jobs' => array(), 'cleared_data' => array());
    }
    if (is_wp_error($result)) {
        return array('success' => false, 'message' => $result->get_error_message(), 'error_code' => $result->get_error_code());
    }
    return is_array($result) ? $result : array('success' => false, 'message' => __('Synchronization ownership changed before cleanup.', 'connector-for-nmkr'), 'error_code' => 'sync_cleanup_owner_changed');
}

/**
 * Function to check if a sync process appears to be stalled
 * This is used for monitoring sync health
 * 
 * @return array Status information about potential stalled sync
 */
function nmkr_get_sync_health_profile_settings() {
    $options = get_option('nmkr_connect_options', array());
    $batch_size = isset($options['sync_batch_size']) ? max(1, (int) $options['sync_batch_size']) : 5;
    $batch_delay = isset($options['sync_batch_delay']) ? max(0, (int) $options['sync_batch_delay']) : 2;
    $polling_interval = isset($options['sync_initial_interval']) ? max(0, (int) $options['sync_initial_interval']) : 1000;
    $delay_factor = max(1, $batch_delay);
    $ttl = defined('NMKR_SYNC_TRANSIENT_TTL') ? max(1, (int) NMKR_SYNC_TRANSIENT_TTL) : HOUR_IN_SECONDS;

    return array(
        'batch_size' => $batch_size,
        'batch_delay' => $batch_delay,
        'polling_interval' => $polling_interval,
        'no_progress_timeout' => max(60, min(300, $batch_size * $delay_factor * 10)),
        'no_jobs_timeout' => max(60, min(180, $batch_size * $delay_factor * 5)),
        'long_running_timeout' => max(600, min(1800, $batch_size * $delay_factor * 60)),
        'direct_stale_timeout' => min($ttl, 300),
    );
}

/** Return a bounded age for numeric or ISO-8601 lifecycle timestamps. */
function nmkr_sync_health_timestamp_age($timestamp, $current_time) {
    if (is_numeric($timestamp)) {
        $epoch = (int) $timestamp;
    } elseif (is_string($timestamp) && $timestamp !== '') {
        $parsed = strtotime($timestamp);
        $epoch = $parsed === false ? 0 : (int) $parsed;
    } else {
        $epoch = 0;
    }
    return $epoch > 0 ? max(0, (int) $current_time - $epoch) : -1;
}

/**
 * Classify an exact direct owner without mutating synchronization state.
 *
 * @return array|false False when there is no valid active direct owner.
 */
function nmkr_classify_direct_sync_health($owner, $sync_data, $last_progress_update, $current_time, $profile_settings) {
    if (!is_array($owner) || ($owner['mode'] ?? '') !== 'direct') {
        return false;
    }

    $run_id = (string) ($owner['run_id'] ?? '');
    $state = (string) ($owner['state'] ?? '');
    if (!nmkr_is_valid_sync_run_id($run_id)
        || !in_array($state, array('queued', 'running', 'stop_requested', 'finalizing'), true)) {
        return false;
    }

    $sync_stats_id = (int) ($owner['sync_stats_id'] ?? 0);
    $grace = max(1, (int) ($profile_settings['direct_stale_timeout'] ?? 300));
    $heartbeat_timestamp = $owner['heartbeat_at'] ?? ($owner['updated_at'] ?? ($owner['created_at'] ?? ''));
    $worker_heartbeat_age = nmkr_sync_health_timestamp_age($heartbeat_timestamp, $current_time);
    $progress_age = $last_progress_update > 0 ? max(0, $current_time - (int) $last_progress_update) : -1;
    $owner_fresh = $worker_heartbeat_age >= 0 && $worker_heartbeat_age < $grace;
    $progress_fresh = $progress_age >= 0 && $progress_age < $grace;
    $has_running_jobs = true;
    $is_stalled = false;
    $stall_reason = '';
    $finalization_pending = false;
    $finalization_scheduled = false;

    if ($state === 'queued') {
        $worker_scheduled = wp_next_scheduled('nmkr_execute_sync_background', array($run_id)) !== false;
        $has_running_jobs = $worker_scheduled || $owner_fresh;
        if (!$worker_scheduled && !$owner_fresh) {
            $is_stalled = true;
            $stall_reason = sprintf(
                'Direct synchronization has remained queued without executable worker evidence for at least %d seconds.',
                $grace
            );
        }
    } elseif ($state === 'running') {
        if (!$owner_fresh && !$progress_fresh) {
            $is_stalled = true;
            $stall_reason = sprintf(
                'Direct synchronization heartbeat and progress are both stale for at least %d seconds.',
                $grace
            );
        }
    } elseif ($state === 'finalizing') {
        $exact_finalization = is_array($sync_data)
            && (string) ($sync_data['run_id'] ?? '') === $run_id
            && (int) ($sync_data['sync_stats_id'] ?? 0) === $sync_stats_id;
        $finalization_pending = $exact_finalization && $sync_stats_id > 0
            && function_exists('nmkr_sync_finalization_resume_pending')
            && nmkr_sync_finalization_resume_pending($sync_stats_id);
        $finalization_scheduled = $finalization_pending
            && function_exists('nmkr_sync_finalization_resume_event_scheduled')
            && nmkr_sync_finalization_resume_event_scheduled($sync_stats_id);
        // The durable resume record is written before sync_data publishes its
        // finalizing status. Treat it as executable only while its exact
        // callback remains scheduled.
        $exact_finalizing = $exact_finalization
            && (($sync_data['status'] ?? '') === 'finalizing' || $finalization_scheduled);
        $has_running_jobs = $exact_finalizing && ($finalization_scheduled || $owner_fresh);
        if (!$exact_finalizing) {
            $is_stalled = true;
            $stall_reason = 'Direct synchronization finalization state does not match its exact owner.';
        } elseif (!$finalization_scheduled && !$owner_fresh) {
            $is_stalled = true;
            $stall_reason = sprintf(
                'Direct synchronization finalization has no fresh owner or resume evidence for at least %d seconds.',
                $grace
            );
        }
    }

    return array(
        'in_progress' => true,
        'owner_state' => $state,
        'run_id' => $run_id,
        'sync_stats_id' => $sync_stats_id,
        'has_running_jobs' => $has_running_jobs,
        'is_stalled' => $is_stalled,
        'stall_reason' => $stall_reason,
        'worker_heartbeat_age' => $worker_heartbeat_age,
        'progress_age' => $progress_age,
        'finalization_pending' => $finalization_pending,
        'lifecycle_source' => 'direct_owner',
    );
}

/**
 * Function to check if a sync process appears to be stalled.
 * Direct-run classification is read-only and authoritative from exact owner
 * state plus freshness/finalization evidence. Legacy batch heuristics remain
 * only for ownerless compatibility state.
 *
 * @return array Status information about potential stalled sync.
 */
function nmkr_check_sync_health() {
    $progress_raw = get_transient('nmkr_sync_progress');
    $progress = ($progress_raw !== false) ? (int) $progress_raw : 0;
    $start_time = (int) get_option('nmkr_sync_start_time', 0);
    $current_time = time();
    $time_elapsed = $start_time > 0 ? max(0, $current_time - $start_time) : 0;
    $last_progress_update = (int) get_option('nmkr_last_progress_update_time', 0);
    $last_progress_value = (int) get_option('nmkr_last_progress_value', 0);
    $time_since_update = $last_progress_update > 0 ? max(0, $current_time - $last_progress_update) : 0;
    $error = get_option('nmkr_sync_error', '');
    $profile_settings = nmkr_get_sync_health_profile_settings();
    $owner = nmkr_get_sync_owner();
    $sync_data = nmkr_get_sync_data();
    $direct = nmkr_classify_direct_sync_health(
        $owner,
        $sync_data,
        $last_progress_update,
        $current_time,
        $profile_settings
    );

    $owner_state = 'released';
    $lifecycle_source = 'legacy_ownerless';
    $finalization_pending = false;
    $worker_heartbeat_age = function_exists('nmkr_get_heartbeat_age') ? nmkr_get_heartbeat_age() : -1;

    if (is_array($direct)) {
        $in_progress = true;
        $has_running_jobs = (bool) $direct['has_running_jobs'];
        $is_stalled = (bool) $direct['is_stalled'];
        $stall_reason = (string) $direct['stall_reason'];
        $owner_state = (string) $direct['owner_state'];
        $lifecycle_source = (string) $direct['lifecycle_source'];
        $finalization_pending = (bool) $direct['finalization_pending'];
        $worker_heartbeat_age = (int) $direct['worker_heartbeat_age'];
    } else {
        $has_running_jobs = wp_next_scheduled('nmkr_process_batch_hook') !== false;
        $in_progress = $progress > 0 && $progress < 100 && empty($error);
        $is_stalled = false;
        $stall_reason = '';

        if ($in_progress) {
            if ($time_since_update > $profile_settings['no_progress_timeout'] && $progress === $last_progress_value) {
                $is_stalled = true;
                $stall_reason = "No progress update for {$time_since_update} seconds (timeout: {$profile_settings['no_progress_timeout']}s)";
            } elseif ($time_elapsed > $profile_settings['long_running_timeout']
                && $progress < ($profile_settings['batch_size'] <= 5 ? 30 : 50)) {
                $is_stalled = true;
                $stall_reason = "Sync running for {$time_elapsed} seconds with only {$progress}% progress (timeout: {$profile_settings['long_running_timeout']}s)";
            } elseif (!$has_running_jobs && $time_since_update > $profile_settings['no_jobs_timeout']) {
                $is_stalled = true;
                $stall_reason = "No active legacy batch jobs for {$time_since_update} seconds (timeout: {$profile_settings['no_jobs_timeout']}s)";
            }
        }
    }

    if ($is_stalled) {
        nmkr_log_ui_status('UI: Sync health check result: STALLED - ' . $stall_reason, 'warning');
    } elseif ($in_progress) {
        nmkr_log_ui_status('UI: Sync health check result: HEALTHY - synchronization lifecycle is active', 'debug');
    } else {
        nmkr_log_ui_status('UI: Sync health check result: INACTIVE - No active sync or in completed/error state', 'debug');
    }

    return array(
        'in_progress' => $in_progress,
        'progress' => $progress,
        'time_elapsed' => $time_elapsed,
        'time_since_update' => $time_since_update,
        'has_running_jobs' => $has_running_jobs,
        'is_stalled' => $is_stalled,
        'stall_reason' => $stall_reason,
        'heartbeat' => get_option('nmkr_sync_heartbeat', 0),
        'heartbeat_age' => $worker_heartbeat_age,
        'owner_state' => $owner_state,
        'lifecycle_source' => $lifecycle_source,
        'finalization_pending' => $finalization_pending,
        'profile_settings' => $profile_settings,
    );
}
