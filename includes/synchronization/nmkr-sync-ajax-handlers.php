<?php
/**
 * NMKR Connect - AJAX Handler Functions
 *
 * This file contains AJAX handlers for the NMKR Connect synchronization processes.
 *
 * @package NMKR Connect
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Include AJAX functions
// require_once plugin_dir_path(dirname(__FILE__)) . 'ajax/nmkr-ajax-functions.php';

// Register AJAX handlers
add_action('wp_ajax_nmkr_start_sync', 'nmkr_start_sync_handler');
add_action('wp_ajax_nmkr_sync_progress', 'nmkr_sync_progress_handler');
add_action('wp_ajax_nmkr_cleanup_sync_jobs', 'nmkr_cleanup_sync_jobs_handler');
add_action('wp_ajax_nmkr_stop_sync', 'nmkr_stop_sync_handler');
add_action('wp_ajax_nmkr_restart_sync_batch', 'nmkr_restart_sync_batch_handler');
add_action('wp_ajax_nmkr_force_stop_sync', 'nmkr_force_stop_sync_handler');
add_action('wp_ajax_nmkr_check_sync_health', 'nmkr_check_sync_health_handler');
// The API status handler is defined in ajax/nmkr-ajax-functions.php

// Hook for background sync execution
add_action('nmkr_execute_sync_background', 'nmkr_execute_sync_background_job');

/**
 * AJAX handler for starting the synchronization process
 */
function nmkr_start_sync_handler() {
    // Verify nonce for security
    check_ajax_referer('nmkr_sync_nonce', 'nonce');
    
    try {
        // Log UI status update for starting sync
        nmkr_log_ui_status('UI: User clicked Start Synchronization button - initializing sync process', 'info');
        
        update_option('nmkr_sync_in_progress', true);
        set_transient('nmkr_sync_in_progress', true, HOUR_IN_SECONDS);
        
        // Schedule the sync to run in the background via WP-Cron
        wp_schedule_single_event(time(), 'nmkr_execute_sync_background');
        
        // Let the client know we queued the job successfully
        wp_send_json_success();
        return;
        
    } catch (Exception $e) {
        // Handle any unexpected errors in AJAX handler itself
        $error_msg = 'Critical error in sync AJAX handler: ' . $e->getMessage();
        nmkr_log_data_sync($error_msg, 'error', array(
            'exception' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ));
        
        nmkr_log_ui_status('UI: Critical AJAX handler error - displaying generic error message', 'error');
        
        wp_send_json_error(array(
            'message' => 'A critical error occurred while starting synchronization',
            'error_code' => 'ajax_handler_failure',
            'technical_details' => $e->getMessage()
        ));
    }
}

/**
 * AJAX handler for cleanup jobs
 */
function nmkr_cleanup_sync_jobs_handler() {
    check_ajax_referer('nmkr_sync_nonce', 'nonce');
    
    $context = isset($_POST['context']) ? sanitize_text_field($_POST['context']) : 'manual_cleanup';
    $clear_data = isset($_POST['clear_data']) ? (bool) $_POST['clear_data'] : true;
    
    $result = nmkr_clear_sync_jobs($context, $clear_data);
    
    if ($result['success']) {
        wp_send_json_success(array(
            'message' => 'Successfully cleaned up sync jobs',
            'cleared_jobs' => $result['cleared_jobs'],
            'cleared_data' => $result['cleared_data']
        ));
    } else {
        wp_send_json_error(array(
            'message' => 'Failed to clean up sync jobs: ' . $result['message']
        ));
    }
    
    wp_die();
}

/**
 * AJAX handler for getting sync progress
 */
function nmkr_sync_progress_handler() {
    try {
        // ** ENHANCED ERROR HANDLING: Parameter Validation **
        $is_recovery = isset($_POST['recovery']) && $_POST['recovery'];
        
        try {
            // Clear WordPress option cache to ensure fresh reads for real-time progress
            wp_cache_delete('nmkr_sync_progress', 'options');
            wp_cache_delete('nmkr_sync_current_item', 'options');
            wp_cache_delete('nmkr_sync_current_count', 'options');
            wp_cache_delete('nmkr_sync_error', 'options');
            wp_cache_delete('nmkr_last_progress_update_time', 'options');
            
            $progress = get_option('nmkr_sync_progress', 0);
            $current_item = get_option('nmkr_sync_current_item', '');
            $current_count = get_option('nmkr_sync_current_count', 0);
            $error = get_option('nmkr_sync_error', '');
            $last_update_time = get_option('nmkr_last_progress_update_time', 0);
            
            if ($progress === 0 && $current_count > 0) {
                global $wpdb;
                $db_progress = $wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name = 'nmkr_sync_progress'");
                if ($db_progress !== null && (int)$db_progress > 0) {
                    $progress = (int)$db_progress;
                    nmkr_log_ui_status('UI: Used direct DB read for progress due to cache issue - Progress: ' . $progress . '%', 'debug');
                }
            }
            
            // Use consistent 100-based denominator instead of potentially stale cache
            $total_items = 100;
            
            // Get performance stats from transient (lightweight read)
            $current_stats = get_transient('nmkr_current_sync_stats_live');
            if ($current_stats === false || $current_stats === null) {
                $current_stats = [
                    'average_time'   => 0,
                    'request_count'  => 0,
                    'memory_used'    => 0,
                    'total_duration' => 0,
                    'total_api_time' => 0,
                ];
            }
        } catch (Exception $e) {
            nmkr_log_data_sync('Progress handler error: Failed to retrieve sync options - ' . $e->getMessage(), 'error');
            wp_send_json_error(array(
                'message' => 'Failed to retrieve synchronization status from database',
                'error_code' => 'option_retrieval_failed',
                'technical_details' => $e->getMessage()
            ));
            return;
        }
        
        // ** ENHANCED ERROR HANDLING: Check for Critical Errors **
        if (!empty($error)) {
            nmkr_log_data_sync('Progress handler: Critical sync error detected - ' . $error, 'warning');
            wp_send_json_error(array(
                'message' => 'Synchronization encountered a critical error: ' . $error,
                'error_code' => 'sync_critical_error',
                'technical_details' => $error,
                'progress' => $progress
            ));
            return;
        }
    
    // Initialize project and token variables to prevent undefined variable errors
    $total_projects = 0;
    $completed_projects = 0;
    $total_tokens = 0;
    $completed_tokens = 0;
    $batch_info = [];
    
    // Check if we have running cron jobs for sync
    $has_running_jobs = false;
    if (wp_next_scheduled('nmkr_process_batch_hook')) {
        $has_running_jobs = true;
    }

    // Get the current batch processing status
    $sync_data = nmkr_get_sync_data();
    
    
    // Normal progress reporting for active sync (throttled)
    static $progress_poll_count = 0;
    $progress_poll_count++;
    
    if (!empty($error)) {
        // Log UI status update for error state (always log errors)
        nmkr_log_ui_status('UI: Reporting sync error state to frontend: ' . $error, 'warning');
    } else if ($progress == 100) {
        // Log UI status update for completed state (only once per completion)
        static $completion_logged = false;
        if (!$completion_logged) {
            nmkr_log_ui_status('UI: Reporting completed sync state to frontend - 100% complete', 'debug');
            $completion_logged = true;
        }
    } else if ($progress < 100) {
        // Log UI status update for normal progress reporting (throttled)
        if (!nmkr_should_throttle_logs() || $progress_poll_count % 10 === 0) {
            $log_message = sprintf(
                'UI: Reporting sync progress to frontend - Progress: %.1f%%, Item: %s, Count: %d/%d (Cache cleared: %s)',
                $progress,
                $current_item,
                $current_count,
                $total_items,
                'yes'
            );
            nmkr_log_ui_status($log_message, 'debug');
        }
    }
    
    // Get sync profile settings to calculate adaptive timeouts
    $options = get_option('nmkr_connect_options', array());
    $batch_size = isset($options['batch_size']) ? intval($options['batch_size']) : 10;
    $batch_delay = isset($options['batch_delay']) ? intval($options['batch_delay']) : 1;
    
    // Calculate timeouts based on sync profile settings
    // For smaller batch sizes and longer delays, use shorter timeouts
    $no_update_timeout = max(60, min(300, $batch_size * $batch_delay * 10)); // Between 1-5 minutes
    $no_jobs_timeout = max(60, min(180, $batch_size * $batch_delay * 5)); // Between 1-3 minutes
    $min_progress_timeout = max(30, min(120, $batch_size * $batch_delay * 3)); // Between 30s-2 minutes
    
            // Verify if process is actually running or has stalled
        if (($is_recovery || isset($_POST['check_stalled'])) && 
            $progress > 0 && $progress < 100 && empty($error)) {
        
        // Check when the sync started
        $sync_start_time = get_option('nmkr_sync_start_time', 0);
        $current_time = time();
        $time_since_start = $current_time - $sync_start_time;
        
        // Check if the process has been running too long with the same progress
        $last_progress_update = get_option('nmkr_last_progress_update_time', 0);
        $last_progress_value = get_option('nmkr_last_progress_value', 0);
        $time_since_progress_update = $current_time - $last_progress_update;
        
        // If no sync data but stage indicates a sync should be running, it might be stalled
        if (!$sync_data && $progress > 0 && $progress < 100) {
            // Log the inconsistency
            nmkr_log_data_sync('Recovery check detected inconsistent state - no sync data but active progress', 
                'warning', array(
                    'progress' => $progress,
                    'has_running_jobs' => $has_running_jobs,
                    'time_since_start' => $time_since_start,
                    'batch_size' => $batch_size,
                    'batch_delay' => $batch_delay,
                    'calculated_timeout' => $no_jobs_timeout
                )
            );
            
            // Log UI status update for stalled sync detection
            nmkr_log_ui_status('UI: Detected potentially stalled sync - no sync data but active stage', 'warning');
            
            // Update last progress check to avoid repeated error states
            update_option('nmkr_last_progress_update_time', $current_time);
            update_option('nmkr_last_progress_value', $progress);
            
            // If there are no scheduled jobs and it's been more than the calculated timeout since start, mark as stalled
            if (!$has_running_jobs && $time_since_start > $no_jobs_timeout) {
                update_option('nmkr_sync_error', 'Sync process stalled - no running jobs detected');
                $error = 'Sync process stalled - no running jobs detected';
                
                // Log UI status update for stalled sync error
                nmkr_log_ui_status('UI: Updating status to "Error occurred" - Sync process stalled', 'error');
                
                // Update sync stats with error status
                global $wpdb;
                $table_name = $wpdb->prefix . 'nmkr_sync_stats';
                $active_sync = $wpdb->get_row(
                    "SELECT * FROM $table_name WHERE status IN ('initializing', 'processing_projects', 'processing_tokens') ORDER BY id DESC LIMIT 1",
                    ARRAY_A
                );
                
                if ($active_sync) {
                    nmkr_update_sync_stats($active_sync['id'], [
                        'status' => 'failed',
                        'end_time' => nmkr_get_timestamp(),
                        'error_message' => 'Sync process stalled - no running jobs detected'
                    ]);
                }
                
                // Clear the in-progress flag
                update_option('nmkr_sync_in_progress', false);
                delete_transient('nmkr_sync_in_progress');
            }
        }
        // Check for stuck progress - same progress for more than the calculated timeout
        else if ($sync_data && $progress > 0 && $progress < 100 && 
                 $last_progress_update > 0 && $last_progress_value === $progress &&
                 $time_since_progress_update > $no_update_timeout) {
            
            nmkr_log_data_sync('Recovery check detected stuck progress', 
                'warning', array(
                    'progress' => $progress,
                    'last_progress' => $last_progress_value,
                    'time_since_update' => $time_since_progress_update,
                    'has_running_jobs' => $has_running_jobs,
                    'batch_size' => $batch_size,
                    'batch_delay' => $batch_delay,
                    'calculated_timeout' => $no_update_timeout
                )
            );
            
            // Only mark as error if there are no jobs running
            if (!$has_running_jobs) {
                update_option('nmkr_sync_error', 'Sync process stalled - no progress after multiple attempts');
                $error = 'Sync process stalled - no progress after multiple attempts';
                
                // Update sync stats with error status
                if (isset($sync_data['sync_stats_id'])) {
                    nmkr_update_sync_stats($sync_data['sync_stats_id'], [
                        'status' => 'failed',
                        'end_time' => nmkr_get_timestamp(),
                        'error_message' => 'Sync process stalled - no progress after multiple attempts'
                    ]);
                }
                
                // Clear the sync data to prevent further issues
                nmkr_clear_sync_data();
                update_option('nmkr_sync_in_progress', false);
                delete_transient('nmkr_sync_in_progress');
                
                // Force cleanup of any remaining jobs
                nmkr_clear_sync_jobs('stalled_sync', true, true);
            }
        }
        // Update progress tracking if progress has changed
        else if ($progress != $last_progress_value) {
            update_option('nmkr_last_progress_update_time', $current_time);
            update_option('nmkr_last_progress_value', $progress);
        }
        // Check for no activity for a long time
        else if ($sync_data && !$has_running_jobs) {
            $last_update_time = isset($sync_data['last_update_time']) ? $sync_data['last_update_time'] : 0;
            $time_since_update = $current_time - $last_update_time;
            
            if ($last_update_time > 0 && $time_since_update > $no_update_timeout) {
                nmkr_log_data_sync('Recovery check found stalled sync process - no updates for extended period', 
                    'error', array(
                        'time_since_update' => $time_since_update,
                        'has_running_jobs' => $has_running_jobs,
                        'batch_size' => $batch_size,
                        'batch_delay' => $batch_delay,
                        'calculated_timeout' => $no_update_timeout
                    )
                );
                
                update_option('nmkr_sync_error', 'Sync process stalled - no updates for an extended period');
                $error = 'Sync process stalled - no updates for an extended period';
                
                // Update sync stats with error status
                if (isset($sync_data['sync_stats_id'])) {
                    nmkr_update_sync_stats($sync_data['sync_stats_id'], [
                        'status' => 'failed',
                        'end_time' => nmkr_get_timestamp(),
                        'error_message' => 'Sync process stalled - no updates for an extended period'
                    ]);
                }
                
                // Clear the sync data to prevent further issues
                nmkr_clear_sync_data();
                update_option('nmkr_sync_in_progress', false);
                delete_transient('nmkr_sync_in_progress');
                
                // Force cleanup of any remaining jobs
                nmkr_clear_sync_jobs('stalled_sync', true, true);
            }
        }
    }
    
    if ($sync_data) {
        // Extract project and token statistics from sync data
        $total_projects = isset($sync_data['total_projects']) ? $sync_data['total_projects'] : 0;
        $completed_projects = isset($sync_data['completed_projects']) ? $sync_data['completed_projects'] : 0;
        $total_tokens = isset($sync_data['total_tokens']) ? $sync_data['total_tokens'] : 0;
        $completed_tokens = isset($sync_data['completed_tokens']) ? $sync_data['completed_tokens'] : 0;
        
        // Calculate remaining items
        $remaining_projects = isset($sync_data['projects_to_process']) ? count($sync_data['projects_to_process']) : 0;
        
        // Fix: Calculate remaining tokens directly from total and completed
        $remaining_tokens = max(0, $total_tokens - $completed_tokens);
        
        // Handle case where all tokens show as processed but stage isn't completed
        if ($progress === 100 && $remaining_tokens === 0 && $remaining_projects === 0 
            && $total_tokens > 0 && $completed_tokens === $total_tokens) {
            // All work appears complete, but status wasn't updated
            nmkr_update_sync_progress(100, 100, '✅ All data synchronized');
            $progress = 100;
            
            // Update sync stats
            if (isset($sync_data['sync_stats_id'])) {
                nmkr_update_sync_stats($sync_data['sync_stats_id'], [
                    'status' => 'completed',
                    'end_time' => nmkr_get_timestamp()
                ]);
            }
            
            // Clean up
            nmkr_clear_sync_data();
        }
        
        // Format batch info for frontend consumption
        $batch_info = array(
            'total_projects' => $total_projects,
            'completed_projects' => $completed_projects,
            'remaining_projects' => $remaining_projects,
            'total_tokens' => $total_tokens,
            'completed_tokens' => $completed_tokens,
            'remaining_tokens' => $remaining_tokens,
            // Include profile timeouts for frontend use
            'profile_settings' => array(
                'batch_size' => $batch_size,
                'batch_delay' => $batch_delay,
                'no_update_timeout' => $no_update_timeout,
                'no_jobs_timeout' => $no_jobs_timeout
            )
        );
    }
    
    // Format progress details
    $progress_details = null;
    if ($total_items > 0 && ($total_projects > 0 || $total_tokens > 0)) {
        $projects_status = $total_projects > 0 ? "🗂️ Projects: $completed_projects/$total_projects" : "";
        $tokens_status = $total_tokens > 0 ? "🪙 Tokens: $completed_tokens/$total_tokens" : "";
        $separator = ($total_projects > 0 && $total_tokens > 0) ? " • " : "";
        
        $progress_details = "📊 Overall progress: " . $projects_status . $separator . $tokens_status;
    }
    
    // Check if sync is near completion
    $near_completion = get_option('nmkr_sync_near_completion', false);
    
    // Enhanced AJAX response with unified progress data and live metrics
    $sync_in_progress_flag = (bool) get_option('nmkr_sync_in_progress', false);
    $user_requested_abort = (bool) get_option('nmkr_sync_user_stopped', false);
    
    $response_data = array(
        'in_progress'  => $sync_in_progress_flag,
        'progress'     => (int)  $progress,
        'current_item' => (string) $current_item,
        'error'        => (string) $error,
        'aborted'      => $user_requested_abort,
        'finished'     => ($progress === 100 && !$user_requested_abort)
    );
    
    // Always include live metrics in heartbeat payload
    // Get current sync stats for live metrics (fixes variable scope issue)
    $current_stats = nmkr_get_sync_stats();
    if (!$current_stats) {
        $current_stats = array();
    }
    
    $response_data['live_metrics'] = array(
        'total_projects' => $current_stats['total_projects'] ?? 0,
        'total_tokens' => $current_stats['total_tokens'] ?? 0,
        'total_sync_duration' => $current_stats['total_duration'] ?? 0,
        'total_api_time' => $current_stats['total_api_time'] ?? 0,
        'average_response_time' => $current_stats['average_time'] ?? 0,
        'api_requests' => $current_stats['request_count'] ?? 0,
        'memory_usage' => $current_stats['memory_used'] ?? 0
    );

    // Delete the live stats transient only when sync is finalized
    if ($progress === 100) {
        delete_transient('nmkr_current_sync_stats_live');
        
        // Clean up old metrics transients to ensure clean state for next sync
        delete_transient('nmkr_active_sync_metrics');
        delete_transient('nmkr_sync_performance_metrics');
    }
    
    wp_send_json_success($response_data);
    
    } catch (Exception $e) {
        // ** FINAL CATCH: Handle any unexpected errors in progress handler **
        $error_msg = 'Critical error in sync progress handler: ' . $e->getMessage();
        nmkr_log_data_sync($error_msg, 'error', array(
            'exception' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ));
        
        wp_send_json_error(array(
            'message' => 'Failed to retrieve synchronization progress',
            'error_code' => 'progress_handler_failure',
            'technical_details' => $e->getMessage()
        ));
    }
}

/**
 * AJAX handler to stop an active synchronization process
 * This will clear all scheduled jobs and clean up sync data
 */
function nmkr_stop_sync_handler() {
    check_ajax_referer('nmkr_sync_nonce', 'nonce');
    
    // Force parameter for handling stuck syncs
    $force = isset($_POST['force']) && $_POST['force'] ? true : false;
    
    // Get the current sync data to see if we have an active sync stats record
    $sync_data = nmkr_get_sync_data();
    
    // Get current sync stage and status
    $current_item = get_option('nmkr_sync_current_item', '');
    $current_progress = get_option('nmkr_sync_progress', 0);
    
    // Check database for active syncs if no sync data found
    if (!$sync_data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'nmkr_sync_stats';
        $active_sync = $wpdb->get_row(
            "SELECT * FROM $table_name WHERE status IN ('initializing', 'processing_projects', 'processing_tokens') ORDER BY id DESC LIMIT 1",
            ARRAY_A
        );
        
        if ($active_sync) {
            // Found active sync in database that needs to be handled
            $force = true;
            nmkr_log_data_sync(
                'Manual stop found active sync in database without active sync_data',
                'warning',
                array(
                    'active_sync_id' => $active_sync['id'],
                    'status' => $active_sync['status']
                )
            );
        }
    }
    
    // Log that we're stopping the sync process
    nmkr_log_data_sync(
        'Manual stop requested by user',
        'info',
        array(
            'sync_data' => $sync_data ? 'exists' : 'not found',
            'current_item' => $current_item,
            'current_progress' => $current_progress,
            'force' => $force
        )
    );
    
    // Log UI status update for stop request
    nmkr_log_ui_status('UI: User clicked Stop Synchronization button - stopping process', 'info');
    
    // Update sync status to indicate stopping is in progress (consistent 0→100 reset)
    nmkr_update_sync_progress(0, 100, '⏹️ Cleaning Up Resources');
    
    // Log UI status update for the stage change
    nmkr_log_ui_status('UI: Changed status message to "⏹️ Stopping Synchronization - Cleaning Up Resources"', 'info');
    
    // Always mark sync as not in progress to avoid stuck state
    update_option('nmkr_sync_in_progress', false);
    delete_transient('nmkr_sync_in_progress');
    
    update_option('nmkr_sync_user_stopped', true);
    
    // IMPORTANT: Manually unschedule all cron events first (before calling nmkr_clear_sync_jobs)
    // This provides an additional layer of assurance that cron jobs will be stopped
    wp_clear_scheduled_hook('nmkr_process_batch_hook');
    wp_clear_scheduled_hook('nmkr_sync_cron_hook');
    wp_clear_scheduled_hook('nmkr_install_sync_cron_hook');
    
    // Add a small delay to let any running processes complete their current operation
    usleep(500000); // 500ms delay
    
    // Clean up all sync jobs and data with force parameter
    $cleanup_result = nmkr_clear_sync_jobs('manual_stop', true, $force);
    
    // Double-check that we've removed all scheduled events after cleanup
    if ($force) {
        // Forcefully clear all cron events again
        wp_clear_scheduled_hook('nmkr_process_batch_hook');
        wp_clear_scheduled_hook('nmkr_sync_cron_hook');
        wp_clear_scheduled_hook('nmkr_install_sync_cron_hook');
    }
    
    // If we have no sync_data but a successful cleanup, log a warning
    if (!$sync_data && $cleanup_result['success']) {
        nmkr_log_data_sync(
            'Sync stopped but no active sync stats found in sync_data',
            'warning',
            array(
                'cleanup_result' => $cleanup_result
            )
        );
    }
    
    // Get the last sync time to return to the frontend
    $last_sync_metrics = nmkr_get_last_sync_metrics();
    $last_sync_time = isset($last_sync_metrics['last_sync_time']) ? $last_sync_metrics['last_sync_time'] : null;
    
    // Mark the sync as manually stopped in history
    if ($cleanup_result['success']) {
        // Log the stop in the cron job
        nmkr_log_data_sync(
            'Cron cleanup performed',
            'info',
            array(
                'operation' => 'sync',
                'progress' => array(
                    'current' => 0,
                    'total' => 100,
                    'estimated_time_remaining' => 'N/A'
                ),
                'context' => 'manual_stop',
                'force' => $force,
                'cleared_jobs' => $cleanup_result['cleared_jobs'],
                'cleared_data' => $cleanup_result['cleared_data'],
                'sync_stats_id' => $sync_data && isset($sync_data['sync_stats_id']) ? $sync_data['sync_stats_id'] : null
            )
        );
        
        // Mark sync as manually stopped
        update_option('nmkr_sync_in_progress', false);
        // Reset progress to 0% using consistent denominator (100 for UI reset)
        nmkr_update_sync_progress(0, 100, '⛔ Synchronization Stopped');
        // Clear stale item label on manual stop for clean UI state
        update_option('nmkr_sync_current_item', '');
        
        // Clear sync data to prevent stuck state
        nmkr_clear_sync_data();
        
        // Clean up old metrics transients to ensure clean state for next sync
        delete_transient('nmkr_active_sync_metrics');
        delete_transient('nmkr_sync_performance_metrics');
        
        // Log UI status update for manual stop completion
        nmkr_log_ui_status('UI: Changed status to "⛔ Synchronization Stopped", reset progress bar to 0%', 'info');
        nmkr_log_ui_status('UI: Restoring "Start Synchronization" button, hiding "Stop Synchronization" button', 'info');
        
        // Ensure API connection status is fresh on next check
        delete_transient('nmkr_api_connection_status');
        
        // IMPORTANT: Block any future sync stage updates by setting a block flag
        // This prevents any lingering batch processes from changing the stage back
        update_option('nmkr_sync_stop_requested', time());
        
        wp_send_json_success(array(
            'message' => 'Synchronization process stopped successfully',
            'cleared_jobs' => $cleanup_result['cleared_jobs'],
            'cleared_data' => $cleanup_result['cleared_data'],
            'last_sync_time' => $last_sync_time,
            'force_applied' => $force
        ));
    } else {
        // Log the failure
        nmkr_log_data_sync(
            'Failed to stop sync process',
            'error',
            array(
                'error' => $cleanup_result['message'],
                'cleanup_result' => $cleanup_result
            )
        );
        
        // Log UI status update for failure
        nmkr_log_ui_status('UI: Failed to stop synchronization, showing error message to user', 'error');
        
        wp_send_json_error(array(
            'message' => 'Failed to stop synchronization process: ' . $cleanup_result['message'],
            'cleanup_result' => $cleanup_result
        ));
    }
}

/**
 * AJAX handler for restarting a stalled batch process
 */
function nmkr_restart_sync_batch_handler() {
    check_ajax_referer('nmkr_sync_nonce', 'nonce');
    
    // Log the restart attempt
    nmkr_log_data_sync('Attempting to restart sync batch process', 'warning', array(
        'context' => 'recovery_restart',
        'request_time' => current_time('mysql')
    ));
    
    // Get current sync data
    $sync_data = nmkr_get_sync_data();
    
    // Check if there's an active sync
    if (!$sync_data || !get_option('nmkr_sync_in_progress', false)) {
        // No active sync to restart
        wp_send_json_error(array(
            'message' => 'No active synchronization to restart',
            'error' => 'no_active_sync'
        ));
        wp_die();
    }
    
    // Update the last update time to prevent another timeout
    $sync_data['last_update_time'] = time();
    $sync_data['recovery_attempted'] = true;
    nmkr_save_sync_data($sync_data);
    
    // Update progress info to show recovery (maintain current progress)
    $current_progress = get_option('nmkr_sync_progress', 0);
    $total_items     = get_option('nmkr_sync_total_items', 0);
    nmkr_update_sync_progress( $current_progress, $total_items, 'Recovering synchronization process' );
    
    // Schedule a new immediate batch job
    if (!wp_next_scheduled('nmkr_process_batch_hook')) {
        wp_schedule_single_event(time() + 5, 'nmkr_process_batch_hook');
        
        nmkr_log_data_sync('Scheduled new batch processing job', 'info', array(
            'scheduled_time' => time() + 5,
            'context' => 'recovery_restart'
        ));
        
        wp_send_json_success(array(
            'message' => 'Batch processing restarted',
            'next_batch' => time() + 5
        ));
    } else {
        nmkr_log_data_sync('Batch processing already scheduled', 'warning');
        
        wp_send_json_success(array(
            'message' => 'Batch processing already scheduled',
            'already_scheduled' => true
        ));
    }
    
    wp_die();
}

/**
 * AJAX handler for force stopping a synchronization process
 */
function nmkr_force_stop_sync_handler() {
    try {
        check_ajax_referer('nmkr_sync_nonce', 'nonce');
        
        // ** ENHANCED ERROR HANDLING: Parameter Validation **
        $context = isset($_POST['context']) ? sanitize_text_field($_POST['context']) : 'manual_force_stop';
        $reason = isset($_POST['reason']) ? sanitize_text_field($_POST['reason']) : 'user_requested';
        
        // Validate context values
        $valid_contexts = ['manual_force_stop', 'stall_detected', 'frontend_timeout', 'recovery_mode'];
        if (!in_array($context, $valid_contexts)) {
            $context = 'manual_force_stop';
        }
        
        // Log force stop request with additional context
        nmkr_log_data_sync('Force stop sync requested', 'warning', array(
            'context' => $context,
            'reason' => $reason,
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'unknown',
            'ip_address' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown'
        ));
        
        // Log UI status update for force stop request
        if ($reason === 'stall_detected') {
            nmkr_log_ui_status('UI: Force stop triggered by stall detection - attempting cleanup', 'warning');
        } else {
            nmkr_log_ui_status('UI: User requested force stop of synchronization process', 'warning');
        }
        
        // ** ENHANCED ERROR HANDLING: Force Stop Execution **
        try {
            $result = nmkr_force_stop_sync($context);
            
            if (!is_array($result)) {
                throw new Exception('Invalid response from force stop function: expected array, got ' . gettype($result));
            }
            
            if (!isset($result['success'])) {
                throw new Exception('Force stop function returned invalid response structure');
            }
            
        } catch (Exception $e) {
            nmkr_log_data_sync('Force stop execution failed: ' . $e->getMessage(), 'error');
            wp_send_json_error(array(
                'message' => 'Critical error during force stop: ' . $e->getMessage(),
                'error_code' => 'force_stop_execution_failed',
                'technical_details' => $e->getMessage()
            ));
            wp_die();
        }
        
        // ** ENHANCED ERROR HANDLING: Result Processing **
        if ($result['success']) {
            // Log UI status update for successful force stop
            nmkr_log_ui_status('UI: Force stop completed successfully, informing user', 'info');
            
            // Prepare success response with enhanced data
            $response_data = array(
                'message' => 'Synchronization was forcibly stopped and cleaned up',
                'context' => $context,
                'reason' => $reason
            );
            
            // Include cleanup details if available
            if (isset($result['cleared_jobs'])) {
                $response_data['cleared_jobs'] = $result['cleared_jobs'];
            }
            if (isset($result['cleared_data'])) {
                $response_data['cleared_data'] = $result['cleared_data'];
            }
            if (isset($result['active_syncs_terminated'])) {
                $response_data['active_syncs_terminated'] = $result['active_syncs_terminated'];
            }
            
            // Add specific messaging for stall detection
            if ($reason === 'stall_detected') {
                $response_data['message'] = 'Stalled synchronization was forcibly stopped and cleaned up. You can now restart the sync.';
                $response_data['stall_recovery'] = true;
            }
            
            wp_send_json_success($response_data);
        } else {
            // Handle force stop failure
            $error_message = isset($result['message']) ? $result['message'] : 'Unknown error during force stop';
            
            nmkr_log_data_sync('Force stop failed: ' . $error_message, 'error', array(
                'context' => $context,
                'reason' => $reason,
                'result' => $result
            ));
            
            // Log UI status update for failed force stop
            nmkr_log_ui_status('UI: Force stop failed, showing error message to user', 'error');
            
            wp_send_json_error(array(
                'message' => 'Failed to force stop synchronization: ' . $error_message,
                'error_code' => 'force_stop_failed',
                'technical_details' => $error_message,
                'context' => $context
            ));
        }
        
    } catch (Exception $e) {
        // ** FINAL CATCH: Handle any unexpected errors in force stop handler **
        $error_msg = 'Critical error in force stop AJAX handler: ' . $e->getMessage();
        nmkr_log_data_sync($error_msg, 'error', array(
            'exception' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ));
        
        nmkr_log_ui_status('UI: Critical error during force stop - displaying generic error message', 'error');
        
        wp_send_json_error(array(
            'message' => 'A critical error occurred while force stopping synchronization',
            'error_code' => 'force_stop_handler_failure',
            'technical_details' => $e->getMessage()
        ));
    }
    
    wp_die();
}

/**
 * AJAX handler for checking the health of the sync process
 */
function nmkr_check_sync_health_handler() {
    check_ajax_referer('nmkr_sync_nonce', 'nonce');
    
    // Log UI status update for health check request
    nmkr_log_ui_status('UI: Sync health check requested from frontend', 'debug');
    
    // Get health status information
    $health_status = nmkr_check_sync_health();
    
    // Log UI status update for health check response
    if ($health_status['is_stalled']) {
        nmkr_log_ui_status('UI: Sending stalled sync status to frontend, may trigger recovery UI', 'info');
    }
    
    wp_send_json_success($health_status);
    wp_die();
}

/**
 * Background job function to execute sync process
 * This runs the actual sync work in the background, called via wp_schedule_single_event
 */
function nmkr_execute_sync_background_job() {
    // Log that the background job has started
    nmkr_log_data_sync('🚀 Background sync job started via wp_schedule_single_event', 'info', array(
        'timestamp' => current_time('mysql'),
        'memory_limit' => ini_get('memory_limit'),
        'max_execution_time' => ini_get('max_execution_time')
    ));
    
    // ** 1. Clear any existing sync jobs **
    try {
        $cleanup_result = nmkr_clear_sync_jobs('sync_start', true);
        
        if (!$cleanup_result['success']) {
            throw new Exception('Failed to clear existing sync jobs: ' . $cleanup_result['message']);
        }
    } catch (Exception $e) {
        $error_details = NMKR_Sync_Common_Errors::cleanupFailed($e->getMessage());
        nmkr_log_data_sync('Background Job Error: Cleanup failed - ' . $e->getMessage(), 'error');
        update_option('nmkr_sync_error', $error_details['formatted_display']);
        update_option('nmkr_sync_in_progress', false);
        delete_transient('nmkr_sync_in_progress');
        return;
    }
    
    // ** 2. Set initial progress options/transient **
    try {
        // Clean up any old metrics transients to ensure clean state
        delete_transient('nmkr_active_sync_metrics');
        delete_transient('nmkr_sync_performance_metrics');
        delete_transient('nmkr_current_sync_stats_live');
        
        update_option('nmkr_sync_error', ''); // Clear any previous errors
        update_option('nmkr_sync_in_progress', true);
        set_transient('nmkr_sync_in_progress', true, NMKR_SYNC_TRANSIENT_TTL);
        
        // Log UI status update for progress bar and status
        nmkr_log_ui_status('UI: Changed status to "⏳ Starting Sync Process", showing progress bar at 0%', 'info');
        nmkr_log_ui_status('UI: Hiding "Start Synchronization" button, showing "Stop Synchronization" button', 'info');
    } catch (Exception $e) {
        nmkr_log_data_sync('Background Job Error: Failed to reset progress options - ' . $e->getMessage(), 'error');
        update_option('nmkr_sync_error', 'Failed to initialize sync progress tracking');
        update_option('nmkr_sync_in_progress', false);
        delete_transient('nmkr_sync_in_progress');
        return;
    }
    
    // Update stage to indicate background processing has begun
    nmkr_update_sync_progress(0, 100, 'Background job launched');
    
    try {
        // Execute the actual sync process
        $response = nmkr_sync_data();
        
        // Handle different response types and set appropriate completion/error states
        if (is_wp_error($response)) {
            // WP_Error response - set error state
            nmkr_log_data_sync('❌ Background sync job completed with WP_Error: ' . $response->get_error_message(), 'error');
            
            update_option('nmkr_sync_error', $response->get_error_message());
            nmkr_update_sync_progress(0, 100, 'Background sync failed: ' . $response->get_error_message());
            update_option('nmkr_sync_in_progress', false);
            delete_transient('nmkr_sync_in_progress');
            
        } elseif (is_array($response) && isset($response['success'])) {
            if ($response['success']) {
                // Successful completion - set completed state
                nmkr_log_data_sync('✅ Background sync job completed successfully', 'info', array(
                    'response_message' => $response['message'] ?? 'No message provided'
                ));
                
                // Progress already handled by nmkr_sync_data() - no need to override
                // The main sync function will have set the final progress correctly
                update_option('nmkr_sync_error', '');
                update_option('nmkr_sync_in_progress', false);
                delete_transient('nmkr_sync_in_progress');
                
            } else {
                // Failed completion - set error state
                nmkr_log_data_sync('❌ Background sync job completed with error: ' . ($response['message'] ?? 'Unknown error'), 'error');
                
                update_option('nmkr_sync_error', $response['message'] ?? 'Unknown sync error');
                nmkr_update_sync_progress(0, 100, 'Background sync failed');
                update_option('nmkr_sync_in_progress', false);
                delete_transient('nmkr_sync_in_progress');
            }
        } else {
            // Handle legacy string responses or unexpected response types
            if (is_string($response) && strpos($response, 'successfully') !== false) {
                // Legacy successful string response - set completed state
                nmkr_log_data_sync('✅ Background sync job completed successfully (legacy response)', 'info', array(
                    'response' => $response
                ));
                
                // Progress already handled by nmkr_sync_data() - no need to override
                // The main sync function will have set the final progress correctly
                update_option('nmkr_sync_error', '');
                update_option('nmkr_sync_in_progress', false);
                delete_transient('nmkr_sync_in_progress');
                
            } else {
                // Unexpected response - treat as error
                nmkr_log_data_sync('❌ Background sync job completed with unexpected response', 'warning', array(
                    'response_type' => gettype($response),
                    'response' => is_scalar($response) ? $response : 'Non-scalar response'
                ));
                
                update_option('nmkr_sync_error', 'Sync completed with unexpected response');
                nmkr_update_sync_progress(0, 100, 'Background sync completed with unexpected response');
                update_option('nmkr_sync_in_progress', false);
                delete_transient('nmkr_sync_in_progress');
            }
        }
        
    } catch (Exception $e) {
        // Critical error in background job - set error state
        nmkr_log_data_sync('💥 Critical error in background sync job: ' . $e->getMessage(), 'error', array(
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ));
        
        update_option('nmkr_sync_error', 'Critical error in background sync: ' . $e->getMessage());
        nmkr_update_sync_progress(0, 100, 'Background sync crashed');
        update_option('nmkr_sync_in_progress', false);
        delete_transient('nmkr_sync_in_progress');
    }
}                