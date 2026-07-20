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
add_action('nmkr_execute_sync_background', 'nmkr_execute_sync_background_job', 10, 1);

/**
 * AJAX handler for starting the synchronization process
 */
function nmkr_start_sync_handler() {
    // Verify nonce for security
    check_ajax_referer('nmkr_sync_nonce', 'nonce');
    
    if ( ! current_user_can( 'nmkr_manage_sync' ) ) {
        wp_send_json_error( array( 'message' => __( 'Forbidden', 'nmkr-connect' ) ), 403 );
    }

    $admitted_run_id = '';
    $scheduled = false;
    try {
        $run_id = wp_generate_uuid4();
        $owner = nmkr_admit_sync_owner($run_id);
        if (!is_array($owner)) {
            $owner_error = is_wp_error($owner) ? $owner : new WP_Error('sync_admission_failed', __('Synchronization admission failed.', 'nmkr-connect'));
            $conflict = in_array($owner_error->get_error_code(), array('sync_already_owned', 'sync_finalization_pending'), true);
            wp_send_json_error(array(
                'message' => $owner_error->get_error_message(),
                'error_code' => $owner_error->get_error_code(),
            ), $conflict ? 409 : 503);
            return;
        }
        $admitted_run_id = $run_id;

        // Remove only obsolete pre-ownership, no-argument events. New events
        // always carry a run ID and are never matched by these empty args.
        wp_clear_scheduled_hook('nmkr_execute_sync_background', array());

        // Clear past recovery notes only after this request owns admission.
        delete_option('nmkr_sync_last_result');
        delete_option('nmkr_sync_last_recovery_at');

        // Log UI status update for starting sync
        nmkr_log_ui_status('UI: User clicked Start Synchronization button - initializing sync process', 'info');
        
        delete_transient('nmkr_sync_progress');
        delete_transient('nmkr_sync_current_item');
        delete_transient('nmkr_sync_current_count');
        delete_transient('nmkr_sync_total_items');
        delete_transient('nmkr_last_progress_update_time');
        delete_transient('nmkr_last_progress_value');
        delete_transient('nmkr_sync_user_stopped');
        
        // Initialize progress to 0% for fresh start
        set_transient('nmkr_sync_progress', 0, NMKR_SYNC_TRANSIENT_TTL);
        set_transient('nmkr_sync_current_item', 'Initializing synchronization...', NMKR_SYNC_TRANSIENT_TTL);
        set_transient('nmkr_sync_current_count', 0, NMKR_SYNC_TRANSIENT_TTL);
        set_transient('nmkr_sync_user_stopped', false, NMKR_SYNC_TRANSIENT_TTL);
        
        nmkr_log_ui_status('TRANSIENT CLEANUP: Cleared stale progress transients and initialized to 0%', 'debug');
        
        update_option('nmkr_sync_in_progress', true);
        set_transient('nmkr_sync_in_progress', true, NMKR_SYNC_TRANSIENT_TTL);
        
        // Schedule the sync to run in the background via WP-Cron
        $event_args = array($run_id);
        $scheduled = wp_schedule_single_event(time(), 'nmkr_execute_sync_background', $event_args);
        if (!$scheduled && !wp_next_scheduled('nmkr_execute_sync_background', $event_args)) {
            // Roll back only while this exact queued owner still exists.
            $released = nmkr_cancel_exact_queued_sync_owner($run_id, 'failed');
            if ($released === true) {
                $error_code = 'sync_schedule_failed';
                $status_code = 500;
            } elseif (nmkr_sync_owner_matches($run_id, 'queued', 0)) {
                $error_code = 'sync_schedule_cleanup_pending';
                $status_code = 500;
            } else {
                $error_code = 'sync_schedule_owner_changed';
                $status_code = 409;
            }
            wp_send_json_error(array(
                'message' => __('Failed to schedule synchronization.', 'nmkr-connect'),
                'error_code' => $error_code,
            ), $status_code);
            return;
        }
        
        // Let the client know we queued the job successfully
        wp_send_json_success(array('run_id' => $run_id, 'owner_state' => 'queued'));
        return;
        
    } catch (Throwable $e) {
        $rollback = $admitted_run_id !== '' ? nmkr_cancel_exact_queued_sync_owner($admitted_run_id, 'failed') : false;
        $error_code = 'ajax_handler_failure';
        $status_code = 500;
        if ($rollback === true) {
            $error_code = 'sync_start_rollback_completed';
        } elseif (is_wp_error($rollback)) {
            $error_code = $rollback->get_error_code() === 'sync_owner_lock_unavailable'
                ? 'sync_start_rollback_lock_unavailable'
                : 'sync_start_rollback_retained';
        } elseif ($admitted_run_id !== '') {
            $error_code = 'sync_start_rollback_owner_changed';
            $status_code = 409;
        }
        nmkr_log_data_sync('Synchronization start initialization failed.', 'error', array('error_code' => $error_code));
        nmkr_log_ui_status('UI: Synchronization start initialization failed', 'error');
        wp_send_json_error(array(
            'message' => __('Synchronization could not be initialized safely.', 'nmkr-connect'),
            'error_code' => $error_code,
        ), $status_code);
    }
}

/**
 * AJAX handler for cleanup jobs
 */
function nmkr_cleanup_sync_jobs_handler() {
    check_ajax_referer('nmkr_sync_nonce', 'nonce');
    
    if ( ! current_user_can( 'nmkr_manage_sync' ) ) {
        wp_send_json_error( array( 'message' => __( 'Forbidden', 'nmkr-connect' ) ), 403 );
    }
    
    $context = isset($_POST['context']) ? sanitize_text_field($_POST['context']) : 'manual_cleanup';
    $clear_data = isset($_POST['clear_data']) ? (bool) $_POST['clear_data'] : true;
    
    $result = nmkr_coordinate_sync_cleanup('generic', function () use ($context, $clear_data) {
        return nmkr_clear_sync_jobs_ownerless($context, $clear_data);
    });
    if (is_wp_error($result)) {
        wp_send_json_error(array(
            'message' => $result->get_error_message(),
            'error_code' => $result->get_error_code(),
        ), in_array($result->get_error_code(), array('direct_stop_requires_cooperative_worker', 'sync_finalization_pending'), true) ? 409 : 503);
    }
    
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
    // Hint proxies and FastCGI not to cache the lightweight progress payload.
    nocache_headers();
    // Verify nonce for security
    check_ajax_referer('nmkr_sync_nonce', 'nonce');
    
    if ( ! current_user_can( 'nmkr_view_dashboard' ) ) {
        wp_send_json_error( array( 'message' => __( 'Forbidden', 'nmkr-connect' ) ), 403 );
    }
    
    nmkr_log_ui_status('AJAX HANDLER: nmkr_sync_progress_handler called by process ' . nmkr_safe_getpid(), 'debug');
    
    // Begin a scoped output buffer to prevent stray output from corrupting JSON
    $__nmkr_prev_display_errors = ini_get('display_errors');
    @ini_set('display_errors', '0');
    ob_start();
    
    try {
        // ** ENHANCED ERROR HANDLING: Parameter Validation **
        $is_recovery = isset($_POST['recovery']) && $_POST['recovery'];
        
        try {
            $progress_raw = get_transient('nmkr_sync_progress');
            $progress_raw = ($progress_raw !== false) ? $progress_raw : 0;
            $progress_int = (int) $progress_raw;
            $progress = $progress_int;
            $current_item = get_transient('nmkr_sync_current_item');
            $current_item = ($current_item !== false) ? $current_item : '';
            $current_count = get_transient('nmkr_sync_current_count');
            $current_count = ($current_count !== false) ? $current_count : 0;
            $error = get_transient('nmkr_sync_error');
            $error = ($error !== false) ? $error : '';
            $last_update_time = (int) get_option('nmkr_last_progress_update_time', 0);
            
            // Log transient read for cross-process debugging
            nmkr_log_ui_status('TRANSIENT READ: Progress ' . $progress . '% read from transient by AJAX process ' . nmkr_safe_getpid(), 'debug');
            
            // Fallback: prefer transient; optionally consult the option if transient reads as 0 mid-run.
            // NOTE: Avoid direct SQL/COMMIT here to keep the handler fast and side-effect free.
            if ((int) $progress_int === 0 && (int) $current_count > 0) {
                $option_progress = get_option('nmkr_sync_progress', 0);
                if (is_numeric($option_progress) && (int) $option_progress > 0) {
                    $progress = (int) $option_progress;
                    $progress_int = $progress; // ensure response returns the fresh value
                    nmkr_log_ui_status('UI: Used option fallback for progress: ' . $progress . '%', 'debug');
                }
            }
            
            // Prefer the real total set by nmkr_update_sync_progress(); fallback to 100 only if missing.
            $total_items_raw = get_transient('nmkr_sync_total_items');
            if ($total_items_raw === false || (int) $total_items_raw <= 0) {
                // Durable fallback for very early reads or after manual reset
                $total_items_raw = get_option('nmkr_sync_total_items', 0);
            }
            $total_items = (int) $total_items_raw > 0 ? (int) $total_items_raw : 100;
            
            // Get performance stats from transient (lightweight read)
            $current_stats = get_transient('nmkr_current_sync_stats_live');
            if (($current_stats === false || $current_stats === null)) {
                $current_stats = [
                    'average_time'   => 0,
                    'request_count'  => 0,
                    'memory_used'    => 0,
                    'total_duration' => 0,
                    'total_api_time' => 0,
                ];
            }

            // Optional fast-path override: compute fresh live metrics only when explicitly requested
            if (!empty($_POST['force_metrics'])) {
                try {
                    $forced_stats = nmkr_get_sync_stats(); // lightweight; no heavy DB scans
                    if (is_array($forced_stats) && !empty($forced_stats)) {
                        // Merge over the placeholder/live snapshot without adding new heavy fields
                        $current_stats['total_duration'] = isset($forced_stats['total_duration']) ? $forced_stats['total_duration'] : $current_stats['total_duration'];
                        $current_stats['request_count']  = isset($forced_stats['request_count']) ? (int) $forced_stats['request_count'] : $current_stats['request_count'];
                        $current_stats['memory_used']    = isset($forced_stats['memory_used']) ? (float) $forced_stats['memory_used'] : $current_stats['memory_used'];
                        $current_stats['average_time']   = isset($forced_stats['average_time']) ? (float) $forced_stats['average_time'] : $current_stats['average_time'];
                        $current_stats['total_api_time'] = isset($forced_stats['total_api_time']) ? (float) $forced_stats['total_api_time'] : $current_stats['total_api_time'];
                        $current_stats['total_projects']  = isset($forced_stats['total_projects']) ? (int) $forced_stats['total_projects'] : ($current_stats['total_projects'] ?? 0);
                        $current_stats['total_tokens']    = isset($forced_stats['total_tokens']) ? (int) $forced_stats['total_tokens'] : ($current_stats['total_tokens'] ?? 0);
                        $current_stats['updated_at']      = time();
                    }
                } catch (Exception $e) {
                    // Keep handler side-effect-free on failure; do not throw
                }
            }
        } catch (Exception $e) {
            nmkr_log_data_sync('Progress handler error: Failed to retrieve sync options - ' . $e->getMessage(), 'error');
            // Clean any stray output captured during handler execution
            if (ob_get_length()) { ob_clean(); }
            if ($__nmkr_prev_display_errors !== false) { @ini_set('display_errors', $__nmkr_prev_display_errors); }
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
            // Clean any stray output captured during handler execution
            if (ob_get_length()) { ob_clean(); }
            if ($__nmkr_prev_display_errors !== false) { @ini_set('display_errors', $__nmkr_prev_display_errors); }
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
                'UI: Reporting sync progress to frontend - Progress: %.1f%%, Item: %s, Count: %d/%d (Transients used: %s)',
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
        nmkr_with_ownerless_legacy_recovery(function () use (&$error, &$sync_data, $progress, $has_running_jobs, $batch_size, $batch_delay, $no_jobs_timeout, $no_update_timeout) {
        
        // Check when the sync started
        $sync_start_time_raw = get_transient('nmkr_sync_start_time');
        $sync_start_time = ($sync_start_time_raw !== false) ? $sync_start_time_raw : 0;
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
                $active_statuses = array('initializing', 'processing_projects', 'processing_tokens');
                $status_placeholders = implode(', ', array_fill(0, count($active_statuses), '%s'));
                $active_sync = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT * FROM $table_name WHERE status IN ($status_placeholders) ORDER BY id DESC LIMIT 1",
                        $active_statuses
                    ),
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
                nmkr_clear_sync_jobs_ownerless('stalled_sync', true, true);
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
                nmkr_clear_sync_jobs_ownerless('stalled_sync', true, true);
            }
        }
        return true;
        });
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
    $near_completion_raw = get_option('nmkr_sync_near_completion', false);
    $near_completion = ($near_completion_raw !== false) ? $near_completion_raw : false;
    
    // Enhanced AJAX response with unified progress data and live metrics
    $sync_in_progress_raw = get_transient('nmkr_sync_in_progress');
    $sync_in_progress_flag = ($sync_in_progress_raw !== false) ? (bool) $sync_in_progress_raw : false;
    $sync_in_progress_option = (bool) get_option('nmkr_sync_in_progress', false);
    $user_requested_abort = get_transient('nmkr_sync_user_stopped');
    $user_requested_abort = ($user_requested_abort !== false) ? (bool) $user_requested_abort : false;
    $durable_user_requested_abort = (bool) get_option('nmkr_sync_user_stopped', false);
    $resume_pending = is_array($sync_data) && !empty($sync_data['sync_stats_id'])
        ? nmkr_sync_finalization_resume_pending((int) $sync_data['sync_stats_id']) : false;
    
    $canonically_finished = nmkr_is_sync_canonically_finished(
        $sync_data,
        $sync_in_progress_option,
        $sync_in_progress_flag,
        $durable_user_requested_abort,
        $user_requested_abort,
        $resume_pending
    );
    $response_data = array(
        'in_progress'  => $sync_in_progress_flag,
        'progress'     => $progress_int,
        'current_item' => (string) $current_item,
        'error'        => (string) $error,
        'aborted'      => $user_requested_abort,
        'finished'     => $canonically_finished,
        'total_items'  => $total_items,
    );
    $owner = nmkr_get_sync_owner();
    $response_data['owner_state'] = is_array($owner) ? (string) ($owner['state'] ?? '') : 'released';
    $active_direct_owner = is_array($owner) && ($owner['mode'] ?? '') === 'direct'
        && nmkr_is_valid_sync_run_id((string) ($owner['run_id'] ?? ''))
        && in_array($response_data['owner_state'], array('queued', 'running', 'stop_requested', 'finalizing'), true);
    $terminal_sync_data = is_array($sync_data) && nmkr_is_sync_terminal_status($sync_data['status'] ?? '')
        && nmkr_is_valid_sync_run_id((string) ($sync_data['run_id'] ?? ''));
    $response_data['run_id'] = $active_direct_owner ? (string) $owner['run_id'] : ($terminal_sync_data ? (string) $sync_data['run_id'] : '');
    $response_data['owner_mismatch'] = $active_direct_owner && is_array($sync_data) && !empty($sync_data['run_id'])
        && !hash_equals((string) $owner['run_id'], (string) $sync_data['run_id']);
    $response_data['stop_pending'] = $response_data['owner_state'] === 'stop_requested';
    $same_active_run_terminal = $active_direct_owner && !$response_data['owner_mismatch'] && $terminal_sync_data
        && hash_equals((string) $owner['run_id'], (string) $sync_data['run_id']);
    $response_data['terminal_outcome'] = (!$active_direct_owner && $terminal_sync_data) || $same_active_run_terminal ? (string) $sync_data['status'] : '';
    if ($active_direct_owner && (!$same_active_run_terminal || $response_data['owner_mismatch'])) {
        $response_data['finished'] = false;
        $response_data['aborted'] = false;
    }

    // Always include live metrics in heartbeat payload using already-fetched transient only
    // (keep handler lightweight; no additional DB reads here)
    $avg_seconds = isset($current_stats['average_time']) ? (float) $current_stats['average_time'] : 0.0;
    $mem_mb      = isset($current_stats['memory_used']) ? (float) $current_stats['memory_used'] : 0.0;
    $response_data['live_metrics'] = array(
        'total_projects'        => isset($current_stats['total_projects']) ? (int) $current_stats['total_projects'] : 0,
        'total_tokens'          => isset($current_stats['total_tokens']) ? (int) $current_stats['total_tokens'] : 0,
        'total_sync_duration'   => isset($current_stats['total_duration']) ? (float) $current_stats['total_duration'] : 0.0,
        'total_api_time'        => isset($current_stats['total_api_time']) ? (float) $current_stats['total_api_time'] : 0.0,
        'average_response_time' => $avg_seconds,
        'api_requests'          => isset($current_stats['request_count']) ? (int) $current_stats['request_count'] : 0,
        'memory_usage'          => $mem_mb,
        // optional duplicates for legacy/interop without breaking existing keys
        'avg_api_ms'            => (int) round($avg_seconds * 1000),
        'memory_bytes'          => (int) round($mem_mb * 1024 * 1024),
        'updated_at'            => isset($current_stats['updated_at']) ? (int) $current_stats['updated_at'] : time(),
    );

    // Clean any stray output captured during handler execution
    if (ob_get_length()) { ob_clean(); }
    if ($__nmkr_prev_display_errors !== false) { @ini_set('display_errors', $__nmkr_prev_display_errors); }
    wp_send_json_success($response_data);
    
    } catch (Exception $e) {
        // ** FINAL CATCH: Handle any unexpected errors in progress handler **
        $error_msg = 'Critical error in sync progress handler: ' . $e->getMessage();
        $buf = '';
        if (ob_get_length()) { $buf = ob_get_clean(); }
        if ($__nmkr_prev_display_errors !== false) { @ini_set('display_errors', $__nmkr_prev_display_errors); }
        nmkr_log_data_sync('Progress handler buffered output: ' . substr($buf, 0, 300), 'warning');
        nmkr_log_data_sync($error_msg, 'error', array(
            'exception' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ));
        
        wp_send_json_error(array(
            'message' => 'Critical error in progress handler'
        ));
    }
}

/**
 * AJAX handler to stop an active synchronization process
 * This will clear all scheduled jobs and clean up sync data
 */
function nmkr_stop_sync_handler() {
    check_ajax_referer('nmkr_sync_nonce', 'nonce');
    if (!current_user_can('nmkr_manage_sync')) {
        wp_send_json_error(array('message' => __('Forbidden', 'nmkr-connect')), 403);
    }
    $run_id = isset($_POST['run_id']) ? sanitize_text_field(wp_unslash($_POST['run_id'])) : '';
    $owner = nmkr_get_sync_owner();
    if (is_array($owner)) {
        if (!nmkr_is_valid_sync_run_id($run_id)) {
            wp_send_json_error(array('message' => __('An exact synchronization run identifier is required.', 'nmkr-connect'), 'error_code' => 'invalid_run_id'), 400);
        }
        $result = nmkr_request_exact_sync_stop($run_id, 'user_requested');
        if (is_wp_error($result)) wp_send_json_error(array('message' => $result->get_error_message(), 'error_code' => $result->get_error_code()), 409);
        if ($result === true) wp_send_json_success(array('run_id' => $run_id, 'owner_state' => 'released', 'completed' => true, 'terminal_outcome' => 'cancelled'));
        if (is_array($result) && ($result['state'] ?? '') === 'stop_requested') wp_send_json_success(array('run_id' => $run_id, 'owner_state' => 'stop_requested', 'stop_pending' => true, 'completed' => false));
        wp_send_json_error(array('message' => __('Synchronization ownership no longer matches this run.', 'nmkr-connect'), 'error_code' => 'sync_owner_mismatch'), 409);
    }
    if ($owner !== false) {
        wp_send_json_error(array('message' => __('Synchronization ownership requires recovery.', 'nmkr-connect'), 'error_code' => 'sync_owner_recovery_required'), 409);
    }
    // Legacy ownerless Stop remains supported, but it is never selected for a direct run.
    $force = !empty($_POST['force']);
    $result = nmkr_coordinate_sync_cleanup('cancel', function () use ($force) {
        return nmkr_stop_ownerless_sync($force);
    });
    if (is_wp_error($result)) {
        $code = $result->get_error_code();
        wp_send_json_error(array('message' => $result->get_error_message(), 'error_code' => $code), 409);
    }
    if (is_array($result) && !empty($result['success'])) wp_send_json_success($result);
    wp_send_json_error(array('message' => __('Synchronization cleanup could not be verified.', 'nmkr-connect'), 'error_code' => 'sync_stop_cleanup_failed'), 503);

}

/**
 * AJAX handler for restarting a stalled batch process
 */
function nmkr_restart_sync_batch_handler() {
    check_ajax_referer('nmkr_sync_nonce', 'nonce');
    
    if ( ! current_user_can( 'nmkr_manage_sync' ) ) {
        wp_send_json_error( array( 'message' => __( 'Forbidden', 'nmkr-connect' ) ), 403 );
    }
    
    // No supported batch admission lifecycle exists. Keep the endpoint for
    // compatibility, but never let option-shaped data activate this engine.
    wp_send_json_error(array(
        'message' => __('Batch restart is unavailable.', 'nmkr-connect'),
        'error_code' => 'batch_mode_unsupported',
    ), 409);
    return;

    // Log the restart attempt
    nmkr_log_data_sync('Attempting to restart sync batch process', 'warning', array(
        'context' => 'recovery_restart',
        'request_time' => current_time('mysql')
    ));
    
    // Get current sync data
    $sync_data = nmkr_get_sync_data();
    
    // Check if there's an active sync
    $sync_in_progress_check = get_transient('nmkr_sync_in_progress');
    $sync_in_progress_check = ($sync_in_progress_check !== false) ? (bool) $sync_in_progress_check : false;
    if (!$sync_data || !$sync_in_progress_check) {
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
    $current_progress_raw = get_transient('nmkr_sync_progress');
    $current_progress = ($current_progress_raw !== false) ? (int) $current_progress_raw : 0;
    $total_items_raw = get_transient('nmkr_sync_total_items');
    $total_items = ($total_items_raw !== false) ? (int) $total_items_raw : 0;
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
        
        if ( ! current_user_can( 'nmkr_manage_sync' ) ) {
            wp_send_json_error( array( 'message' => __( 'Forbidden', 'nmkr-connect' ) ), 403 );
            wp_die();
        }
        
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
                'error_code' => isset($result['error_code']) ? $result['error_code'] : 'force_stop_failed',
                'context' => $context
            ), in_array(isset($result['error_code']) ? $result['error_code'] : '', array('direct_stop_requires_cooperative_worker', 'sync_finalization_pending'), true) ? 409 : 500);
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
    // Health status is freshness-sensitive while the dashboard is polling.
    nocache_headers();
    check_ajax_referer('nmkr_sync_nonce', 'nonce');
    
    if ( ! current_user_can( 'nmkr_view_dashboard' ) ) {
        wp_send_json_error( array( 'message' => __( 'Forbidden', 'nmkr-connect' ) ), 403 );
    }
    
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
function nmkr_execute_sync_background_job($run_id = '') {
    $run_id = is_string($run_id) ? sanitize_text_field($run_id) : '';
    if (!nmkr_is_valid_sync_run_id($run_id)) {
        // Legacy no-argument events and malformed callbacks are inert.
        return;
    }
    $claimed = nmkr_transition_sync_owner($run_id, 'queued', 'running');
    if (!nmkr_sync_owner_transition_succeeded($claimed)) {
        return;
    }

    // Log that the background job has started
    nmkr_log_data_sync('🚀 Background sync job started via wp_schedule_single_event', 'info', array(
        'timestamp' => current_time('mysql'),
        'memory_limit' => ini_get('memory_limit'),
        'max_execution_time' => ini_get('max_execution_time')
    ));
    
    // Ownership is proven before narrowly scoped initialization. Never invoke
    // the legacy global cleanup here: it can destroy finalization evidence.
    try {
        // Clean up any old metrics transients to ensure clean state
        delete_transient('nmkr_active_sync_metrics');
        delete_transient('nmkr_sync_performance_metrics');
        delete_transient('nmkr_current_sync_stats_live');
        
        delete_transient('nmkr_sync_progress');
        delete_transient('nmkr_sync_current_item');
        delete_transient('nmkr_sync_current_count');
        delete_transient('nmkr_sync_total_items');
        delete_transient('nmkr_last_progress_update_time');
        delete_transient('nmkr_last_progress_value');
        
        // Initialize progress to 0% for fresh start
        set_transient('nmkr_sync_progress', 0, NMKR_SYNC_TRANSIENT_TTL);
        set_transient('nmkr_sync_current_item', 'Initializing synchronization...', NMKR_SYNC_TRANSIENT_TTL);
        set_transient('nmkr_sync_current_count', 0, NMKR_SYNC_TRANSIENT_TTL);
        
        nmkr_log_ui_status('BACKGROUND JOB: Cleared stale progress transients and initialized to 0%', 'debug');
        
        update_option('nmkr_sync_error', ''); // Clear any previous errors
        update_option('nmkr_sync_in_progress', true);
        set_transient('nmkr_sync_in_progress', true, NMKR_SYNC_TRANSIENT_TTL);
        
        // Log UI status update for progress bar and status
        nmkr_log_ui_status('UI: Changed status to "⏳ Starting Sync Process", showing progress bar at 0%', 'info');
        nmkr_log_ui_status('UI: Hiding "Start Synchronization" button, showing "Stop Synchronization" button', 'info');
    } catch (Exception $e) {
        nmkr_log_data_sync('Background Job Error: Failed to reset progress options - ' . $e->getMessage(), 'error');
        if (nmkr_sync_owner_matches($run_id, 'running', 0)) {
            nmkr_cleanup_failed_direct_sync($run_id, 0, 'Failed to initialize sync progress tracking');
        }
        return;
    }
    
    // Update stage to indicate background processing has begun
    nmkr_update_sync_progress(0, 100, 'Background job launched');
    
    try {
        // Execute the actual sync process
        $response = nmkr_sync_data($run_id);
        
        // The run-owned core and canonical finalizer exclusively mutate
        // terminal state. This wrapper must not race a newly admitted owner.
        if (is_wp_error($response) && $response->get_error_code() === 'sync_finalization_pending') {
            nmkr_log_data_sync('Background sync is awaiting resumable terminal cleanup.', 'warning');
        }
        
    } catch (Exception $e) {
        // Critical error in background job - set error state
        nmkr_log_data_sync('💥 Critical error in background sync job: ' . $e->getMessage(), 'error', array(
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ));
        
        // Unknown hard interruptions intentionally retain the owner. Phase
        // 16B.2 will add ownership-safe recovery rather than guessing here.
    }
}
