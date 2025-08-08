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
 * @return int The current progress percentage
 */
function nmkr_update_sync_progress($steps_completed, $total_steps, $current_item = '') {
    // Calculate percentage internally based on steps
    $percent = $total_steps > 0
        ? (int) round($steps_completed / $total_steps * 100)
        : 0;
    
    // Ensure progress never goes backward
    $current_progress = get_transient('nmkr_sync_progress');
    $current_progress = ($current_progress !== false) ? $current_progress : 0;
    $percent = max($current_progress, $percent);
    
    // Ensure progress is between 0 and 100
    $percent = max(0, min(100, $percent));
    
    // Update WordPress transients with progress information (better cross-process visibility)
    set_transient('nmkr_sync_progress', $percent, 3600);
    set_transient('nmkr_sync_current_item', $current_item, 3600);
    set_transient('nmkr_sync_current_count', $steps_completed, 3600);
    set_transient('nmkr_sync_total_items', $total_steps, 3600);
    
    // Record the time of this progress update
    set_transient('nmkr_last_progress_update_time', time(), 3600);
    set_transient('nmkr_last_progress_value', $percent, 3600);
    
    update_option('nmkr_sync_progress', $percent);
    update_option('nmkr_sync_current_item', $current_item);
    update_option('nmkr_sync_current_count', $steps_completed);
    
    // Update sync heartbeat to indicate active progress updates
    nmkr_update_sync_heartbeat();
    
    // Log the progress update
    nmkr_log_data_sync('Progress: ' . $percent . '% (' . $steps_completed . '/' . $total_steps . ')');
    
    nmkr_log_ui_status('TRANSIENT WRITE: Progress ' . $percent . '% written to transient by process ' . getmypid(), 'debug');
    
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
 * @return array|false The final sync status or false on error
 */
function nmkr_sync_data_complete($success = true, $error_message = '') {
    // Get the current sync data
    $sync_data = nmkr_get_sync_data();
    
    // Get the sync stats ID
    $sync_stats_id = isset($sync_data['sync_stats_id']) ? $sync_data['sync_stats_id'] : null;
    
    // Update sync stats based on success/failure
    if ($sync_stats_id) {
        $status_update = array(
            'status' => $success ? 'completed' : 'failed',
            'end_time' => nmkr_get_timestamp()
        );
        
        if (!$success && !empty($error_message)) {
            $status_update['error_message'] = $error_message;
        }
        
        nmkr_update_sync_stats($sync_stats_id, $status_update);
    }
    
    // Remove near completion flag if it exists
    delete_option('nmkr_sync_near_completion');
    
    // Update sync progress to indicate completion or failure
    if ($success) {
        // Set progress to 100% using consistent completion model
        nmkr_update_sync_progress(100, 100, '✅ Synchronization Completed');
        update_option('nmkr_sync_status', 'completed');
        set_transient('nmkr_sync_status', 'completed', 3600);
        
        // Log completion success
        nmkr_log_data_sync('Sync process completed successfully');
        
        // Log UI status update for successful completion
        nmkr_log_ui_status('UI: Sync completed - displaying 100% progress bar and success message', 'info');
        
        // Set last sync time
        $current_time = nmkr_get_timestamp();
        update_option('nmkr_last_sync_time', $current_time);
        
        // Log UI status update for statistics panel refresh
        nmkr_log_ui_status('UI: Updated last sync time to ' . $current_time, 'info');
    } else {
        // Failed - reset to 0% for consistent failure indication
        nmkr_update_sync_progress(0, 100, '❌ Synchronization Failed: ' . $error_message);
        update_option('nmkr_sync_error', $error_message);
        update_option('nmkr_sync_status', 'failed');
        set_transient('nmkr_sync_error', $error_message, 3600);
        set_transient('nmkr_sync_status', 'failed', 3600);
        
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
    
    // Clear scheduled cron jobs to prevent additional processing
    wp_clear_scheduled_hook('nmkr_process_batch_hook');
    
    // Update sync data with final status
    $status = array(
        'status' => $success ? 'completed' : 'failed',
        'completed' => true,
        'end_time' => nmkr_get_timestamp(),
        'error_message' => $error_message,
    );
    
    $sync_data = array_merge($sync_data, $status);
    nmkr_save_sync_data($sync_data);
    
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