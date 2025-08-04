<?php
/**
 * NMKR Connect - Error Handling Functions
 *
 * This file contains functions for handling errors and cleaning up
 * during synchronization processes with NMKR API.
 *
 * @package NMKR Connect
 * @since 1.0.0
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
function nmkr_clear_sync_jobs($context = 'manual_cleanup', $clear_data = true, $force = false) {
    $result = array(
        'success' => true,
        'message' => '',
        'cleared_jobs' => array(),
        'cleared_data' => array()
    );

    // Get the current sync data to see if we have an active sync stats record
    $sync_data = nmkr_get_sync_data();
    $sync_stats_id = ($sync_data && isset($sync_data['sync_stats_id'])) ? $sync_data['sync_stats_id'] : null;
    
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
        'nmkr_api_connection_status' // Clear API connection status cache to ensure fresh check after sync
    );
    
    foreach ($transients_to_clean as $transient) {
        delete_transient($transient);
        $result['cleared_data'][] = $transient;
    }

    // If we have an active sync stats record, mark it as cancelled or stopped
    if ($sync_stats_id) {
        // If force is true or the status is stuck in processing, force it to 'stopped'
        $current_status = isset($sync_stats) ? $sync_stats['status'] : null;
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
 * Enhanced function to forcibly stop a running synchronization process
 * This is used when a regular stop fails or when a sync process appears to be stuck
 *
 * @param string $context The context for the stop (e.g., 'manual_stop', 'force_stop', 'stuck_detected')
 * @return array Result of the stop operation
 */
function nmkr_force_stop_sync($context = 'force_stop') {
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
    $cleanup_result = nmkr_clear_sync_jobs('force_stop', true, true);
    
    // Kill any WP-Cron lock
    delete_transient('doing_cron');
    
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
 * Function to check if a sync process appears to be stalled
 * This is used for monitoring sync health
 * 
 * @return array Status information about potential stalled sync
 */
function nmkr_check_sync_health() {
    // Get sync progress data
    $progress = get_option('nmkr_sync_progress', 0);
    $start_time = get_option('nmkr_sync_start_time', 0);
    $current_time = time();
    $time_elapsed = $start_time > 0 ? $current_time - $start_time : 0;
    
    // Get the last progress update
    $last_progress_update = get_option('nmkr_last_progress_update_time', 0);
    $last_progress_value = get_option('nmkr_last_progress_value', 0);
    $time_since_update = $last_progress_update > 0 ? $current_time - $last_progress_update : 0;
    
    // Check if cron jobs are running
    $has_running_jobs = wp_next_scheduled('nmkr_process_batch_hook') ? true : false;
    
    // Get current sync profile settings
    $options = get_option('nmkr_connect_options', array());
    $batch_size = isset($options['batch_size']) ? intval($options['batch_size']) : 10;
    $batch_delay = isset($options['batch_delay']) ? intval($options['batch_delay']) : 1;
    $polling_interval = isset($options['sync_initial_interval']) ? intval($options['sync_initial_interval']) : 2000;
    
    // Calculate timeouts based on sync profile settings
    // For smaller batch sizes and longer delays, use shorter timeouts for stall detection
    $no_progress_timeout = max(60, min(300, $batch_size * $batch_delay * 10)); // Between 1-5 minutes
    $no_jobs_timeout = max(60, min(180, $batch_size * $batch_delay * 5)); // Between 1-3 minutes
    $long_running_timeout = max(600, min(1800, $batch_size * $batch_delay * 60)); // Between 10-30 minutes
    
    // For smaller batch sizes (lighter profiles), reduce the progress threshold
    $progress_threshold = ($batch_size <= 5) ? 30 : 50; // Lighter profiles should make more progress faster
    
    // Determine if sync appears stalled
    $is_stalled = false;
    $stall_reason = '';
    
    // Check if sync is actively running (progress > 0 and < 100, no error)
    $error = get_option('nmkr_sync_error', '');
    
    if ($progress > 0 && $progress < 100 && empty($error)) {
        // Check for signs of stalled sync
        if ($time_since_update > $no_progress_timeout && $progress === $last_progress_value) {
            $is_stalled = true;
            $stall_reason = "No progress update for {$time_since_update} seconds (timeout: {$no_progress_timeout}s)";
            
            // Log UI status update for stalled sync detection - no progress
            nmkr_log_ui_status('UI: Sync health check detected stalled sync - no progress for ' . $time_since_update . ' seconds', 'warning');
        } else if ($time_elapsed > $long_running_timeout && $progress < $progress_threshold) {
            $is_stalled = true;
            $stall_reason = "Sync running for {$time_elapsed} seconds with only {$progress}% progress (timeout: {$long_running_timeout}s)";
            
            // Log UI status update for stalled sync detection - too slow progress
            nmkr_log_ui_status('UI: Sync health check detected stalled sync - running for ' . $time_elapsed . ' seconds with only ' . $progress . '% progress', 'warning');
        } else if (!$has_running_jobs && $time_since_update > $no_jobs_timeout) {
            $is_stalled = true;
            $stall_reason = "No active cron jobs for {$time_since_update} seconds (timeout: {$no_jobs_timeout}s)";
            
            // Log UI status update for stalled sync detection - no jobs
            nmkr_log_ui_status('UI: Sync health check detected stalled sync - no active cron jobs for ' . $time_since_update . ' seconds', 'warning');
        }
    }
    
    // Log UI status update for health check result
    if ($is_stalled) {
        nmkr_log_ui_status('UI: Sync health check result: STALLED - ' . $stall_reason, 'warning');
    } else if ($progress > 0 && $progress < 100 && empty($error)) {
        nmkr_log_ui_status('UI: Sync health check result: HEALTHY - Sync progressing normally', 'debug');
    } else {
        nmkr_log_ui_status('UI: Sync health check result: INACTIVE - No active sync or in completed/error state', 'debug');
    }
    
    return array(
        'in_progress' => ($progress > 0 && $progress < 100 && empty($error)),
        'progress' => $progress,
        'time_elapsed' => $time_elapsed,
        'time_since_update' => $time_since_update,
        'has_running_jobs' => $has_running_jobs,
        'is_stalled' => $is_stalled,
        'stall_reason' => $stall_reason,
        'heartbeat' => get_option('nmkr_sync_heartbeat', 0),
        'heartbeat_age' => nmkr_get_heartbeat_age(),
        'profile_settings' => array(
            'batch_size' => $batch_size,
            'batch_delay' => $batch_delay,
            'no_progress_timeout' => $no_progress_timeout,
            'no_jobs_timeout' => $no_jobs_timeout,
            'long_running_timeout' => $long_running_timeout
        )
    );
} 