<?php
/**
 * NMKR Connect - Progress Tracking Functions
 *
 * This file contains functions related to tracking and updating the progress
 * of NMKR synchronization processes.
 *
 * @package NMKR Connect
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Function to update the sync progress
 *
 * @param int $steps_completed The number of steps completed so far
 * @param int $total_steps The total number of steps to process
 * @param string $current_item Optional item currently being processed
 * @param bool $allow_backward Whether to allow intentional backward progress updates
 * @return int The current progress percentage
 */
function nmkr_update_sync_progress($steps_completed, $total_steps, $current_item = '', $allow_backward = false) {
    // Calculate percentage internally based on steps
    $percent = $total_steps > 0
        ? (int) round($steps_completed / $total_steps * 100)
        : 0;
    
    // Ensure progress never goes backward unless explicitly allowed for intentional resets
    $current_progress = get_transient('nmkr_sync_progress');
    $current_progress = ($current_progress !== false) ? (int) $current_progress : 0;
    if (!$allow_backward) {
        $percent = max($current_progress, $percent);
    }
    
    // Ensure progress is between 0 and 100
    $percent = max(0, min(100, $percent));
    
    // Update WordPress transients with progress information (better cross-process visibility)
    set_transient('nmkr_sync_progress', $percent, NMKR_SYNC_TRANSIENT_TTL);
    set_transient('nmkr_sync_current_item', $current_item, NMKR_SYNC_TRANSIENT_TTL);
    set_transient('nmkr_sync_current_count', $steps_completed, NMKR_SYNC_TRANSIENT_TTL);
    set_transient('nmkr_sync_total_items', $total_steps, NMKR_SYNC_TRANSIENT_TTL);
    
    // Record the time of this progress update (normalize to option for durability)
    update_option('nmkr_last_progress_update_time', time());
    set_transient('nmkr_last_progress_value', $percent, NMKR_SYNC_TRANSIENT_TTL);
    
    update_option('nmkr_sync_progress', $percent);
    update_option('nmkr_sync_current_item', $current_item);
    update_option('nmkr_sync_current_count', $steps_completed);
    
    // Update sync heartbeat to indicate active progress updates
    nmkr_update_sync_heartbeat();
    
    // Log the progress update
    nmkr_log_data_sync('Progress: ' . $percent . '% (' . $steps_completed . '/' . $total_steps . ')');
    
    nmkr_log_ui_status('TRANSIENT WRITE: Progress ' . $percent . '% written to transient by process ' . nmkr_safe_getpid(), 'debug');
    
    // Log UI status update
    $ui_message = sprintf(
        'UI: Progress bar update - %d%% - %s (%d/%d)',
        $percent,
        $current_item,
        $steps_completed,
        $total_steps
    );
    nmkr_log_ui_status($ui_message, 'info');
    
    return $percent;
}

/**
 * Complete the sync process and clean up
 *
 * @param bool $success Whether the sync completed successfully
 * @param string $error_message Optional error message if sync failed
 * @param array $final Authoritative metrics, counters, and optional end time
 * @return array|false The final sync status or false on error
 */
function nmkr_sync_data_complete($success = true, $error_message = '', $final = array()) {
    // Get the current sync data
    $sync_data = nmkr_get_sync_data();
    
    // Get the sync stats ID
    $sync_stats_id = isset($sync_data['sync_stats_id']) ? $sync_data['sync_stats_id'] : null;
    $receipt_key = '';
    
    // A completed terminal record is the durable idempotency receipt. Never
    // touch history or metrics again when the same run is finalized twice.
    if ($success && !empty($sync_stats_id) && isset($sync_data['status'], $sync_data['sync_stats_id'])
        && $sync_data['status'] === 'completed' && (int) $sync_data['sync_stats_id'] === (int) $sync_stats_id) {
        return $sync_data;
    }

    $end_time = !empty($final['end_time']) ? $final['end_time'] : nmkr_get_timestamp();

    if ($success) {
        $metrics = isset($final['metrics']) && is_array($final['metrics'])
            ? $final['metrics'] : get_transient('nmkr_current_sync_stats_live');
        $receipt_key = $sync_stats_id ? 'nmkr_sync_finalizing_' . (int) $sync_stats_id : '';
        $receipt = $receipt_key ? get_option($receipt_key, array()) : array();

        if (is_array($metrics)) {
            $performance = nmkr_get_sync_stats();
            $metrics['total_projects'] = isset($metrics['total_projects']) ? $metrics['total_projects'] : (isset($sync_data['total_projects']) ? $sync_data['total_projects'] : 0);
            $metrics['total_tokens'] = isset($metrics['total_tokens']) ? $metrics['total_tokens'] : (isset($sync_data['total_tokens']) ? $sync_data['total_tokens'] : 0);
            $metrics['total_sync_duration'] = isset($metrics['total_sync_duration']) ? $metrics['total_sync_duration'] : (isset($performance['total_duration']) ? $performance['total_duration'] : 0);
            $metrics['total_api_time'] = isset($metrics['total_api_time']) ? $metrics['total_api_time'] : 0;
            $metrics['average_response_time'] = isset($metrics['average_response_time']) ? $metrics['average_response_time'] : (isset($performance['average_time']) ? $performance['average_time'] : 0);
            $metrics['api_requests'] = isset($metrics['api_requests']) ? $metrics['api_requests'] : (isset($performance['request_count']) ? $performance['request_count'] : 0);
            $metrics['memory_usage'] = isset($metrics['memory_usage']) ? $metrics['memory_usage'] : (isset($performance['memory_used']) ? $performance['memory_used'] : 0);
        }

        // Metrics validation currently requires this flag to be false. Sync
        // data remains active until every durable write below has succeeded,
        // so polling still cannot expose completion during this interval.
        update_option('nmkr_sync_in_progress', false);
        delete_transient('nmkr_sync_in_progress');

        if (empty($receipt['metrics_id'])) {
            if (empty($metrics) || !is_array($metrics)) {
                return false;
            }
            $metrics['last_sync_time'] = $end_time;
            $metrics_id = nmkr_save_sync_metrics($metrics);
            if (!$metrics_id) {
                return false;
            }
            $receipt = array('metrics_id' => (int) $metrics_id, 'end_time' => $end_time);
            if ($receipt_key) {
                update_option($receipt_key, $receipt, false);
            }
        } else {
            $end_time = $receipt['end_time'];
        }

        update_option('nmkr_last_sync_time', $end_time);
        if (!empty($metrics)) {
            $metrics['last_sync_time'] = $end_time;
            set_transient('nmkr_current_sync_stats_summary', $metrics, NMKR_SYNC_TRANSIENT_TTL);
        }
    }

    // Update only the row owned by this run, and never rewrite a terminal row.
    if ($sync_stats_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'nmkr_sync_stats';
        $history = $wpdb->get_row($wpdb->prepare("SELECT status, end_time FROM $table_name WHERE id = %d", $sync_stats_id), ARRAY_A);
        $status_update = array(
            'status' => $success ? 'completed' : 'failed',
            'end_time' => $end_time
        );
        foreach (array('items_processed', 'items_successful', 'items_failed', 'items_skipped', 'token_details_synced') as $counter) {
            if (isset($final[$counter])) {
                $status_update[$counter] = (int) $final[$counter];
            }
        }
        
        if (!$success && !empty($error_message)) {
            $status_update['error_message'] = $error_message;
        }
        
        if ($history && !in_array($history['status'], array('completed', 'failed', 'aborted', 'stopped'), true)) {
            nmkr_update_sync_stats($sync_stats_id, $status_update);
        }
    }
    
    // Remove near completion flag if it exists
    delete_option('nmkr_sync_near_completion');
    
    // Update sync progress to indicate completion or failure
    if ($success) {
        // Set progress to 100% using consistent completion model
        nmkr_update_sync_progress(100, 100, '✅ Synchronization Completed');
        update_option('nmkr_sync_status', 'completed');
        set_transient('nmkr_sync_status', 'completed', NMKR_SYNC_TRANSIENT_TTL);
        
        // Log completion success
        nmkr_log_data_sync('Sync process completed successfully');
        
        // Log UI status update for successful completion
        nmkr_log_ui_status('UI: Sync completed - displaying 100% progress bar and success message', 'info');
        
        nmkr_log_ui_status('UI: Updated last sync time to ' . $end_time, 'info');
    } else {
        // Failed - reset to 0% for consistent failure indication
        nmkr_update_sync_progress(0, 100, '❌ Synchronization Failed: ' . $error_message, true);
        update_option('nmkr_sync_error', $error_message);
        update_option('nmkr_sync_status', 'failed');
        set_transient('nmkr_sync_error', $error_message, NMKR_SYNC_TRANSIENT_TTL);
        set_transient('nmkr_sync_status', 'failed', NMKR_SYNC_TRANSIENT_TTL);
        
        // Log completion failure
        nmkr_log_data_sync('Sync process failed: ' . $error_message, 'error');
        
        // Log UI status update for failure
        nmkr_log_ui_status('UI: Sync failed - displaying error message: ' . $error_message, 'error');
    }
    
    // Remove sync in progress flag
    update_option('nmkr_sync_in_progress', false);
    delete_transient('nmkr_sync_in_progress');
    
    // Clean up sync heartbeat when sync ends
    nmkr_cleanup_sync_heartbeat();
    
    // Clear scheduled cron jobs to prevent additional processing.
    wp_clear_scheduled_hook('nmkr_process_batch_hook');
    wp_clear_scheduled_hook('nmkr_execute_sync_background');
    wp_clear_scheduled_hook('nmkr_sync_cron_hook');
    
    // Update sync data with final status
    $status = array(
        'status' => $success ? 'completed' : 'failed',
        'completed' => true,
        'sync_stats_id' => $sync_stats_id,
        'end_time' => $end_time,
    );
    if (!$success && !empty($error_message)) {
        $status['error_code'] = 'sync_failed';
    }
    nmkr_save_sync_data($status);

    // Live metrics belong to the backend and are cleared only after their
    // durable row, timestamp, history, and terminal state have been written.
    delete_transient('nmkr_current_sync_stats_live');
    delete_transient('nmkr_active_sync_metrics');
    delete_transient('nmkr_sync_performance_metrics');
    if (!empty($receipt_key)) {
        delete_option($receipt_key);
    }
    
    // Log final status
    if ($success) {
        nmkr_log_data_sync('Sync complete - all tokens and projects synchronized');
        nmkr_log_ui_status('UI: Restoring "Start Synchronization" button, hiding "Stop Synchronization" button', 'info');
    } else {
        nmkr_log_data_sync('Sync failed - ' . $error_message, 'error');
        nmkr_log_ui_status('UI: Restoring "Start Synchronization" button, hiding "Stop Synchronization" button after error', 'info');
    }
    
    return $status;
}    