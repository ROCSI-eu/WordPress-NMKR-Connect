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
 * Return every history status treated as terminal by synchronization code.
 *
 * @return array
 */
function nmkr_sync_terminal_statuses() {
    return array('completed', 'success', 'failed', 'error', 'stopped', 'cancelled', 'aborted');
}

/**
 * Determine whether a synchronization history status is terminal.
 *
 * @param string $status History status
 * @return bool
 */
function nmkr_is_sync_terminal_status($status) {
    return in_array(strtolower((string) $status), nmkr_sync_terminal_statuses(), true);
}

/**
 * Determine whether polling may expose successful completion.
 *
 * @param array|false $sync_data Canonical sync data
 * @param bool        $option_in_progress Durable in-progress marker
 * @param bool        $transient_in_progress Transient in-progress marker
 * @param bool        $aborted Abort marker
 * @return bool
 */
function nmkr_is_sync_canonically_finished($sync_data, $option_in_progress, $transient_in_progress, $aborted) {
    return is_array($sync_data)
        && isset($sync_data['status'], $sync_data['completed'])
        && $sync_data['status'] === 'completed'
        && $sync_data['completed'] === true
        && !$option_in_progress
        && !$transient_in_progress
        && !$aborted;
}

/**
 * Persist the run's metrics row and durable receipt as one atomic unit.
 *
 * The advisory lock serializes contenders for the same history ID. The
 * transaction makes an interruption between the metrics insert and receipt
 * insert roll back both writes. This requires the WordPress options and NMKR
 * metrics tables to use a transaction-capable storage engine; otherwise the
 * function fails closed rather than claiming exactly-once persistence.
 *
 * @param int    $sync_stats_id Run-owned history ID
 * @param array  $metrics Final metrics
 * @param string $end_time Authoritative completion timestamp
 * @return array|false Receipt or false
 */
function nmkr_persist_sync_metrics_once($sync_stats_id, $metrics, $end_time) {
    global $wpdb;

    $sync_stats_id = (int) $sync_stats_id;
    if ($sync_stats_id <= 0 || !is_array($metrics)) {
        return false;
    }

    $metrics_table = $wpdb->prefix . 'nmkr_sync_metrics';
    $options_table = $wpdb->options;
    $receipt_key = 'nmkr_sync_finalizing_' . $sync_stats_id;
    $lock_name = 'nmkr_sync_finalize_' . $sync_stats_id;
    $lock_acquired = (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 10)', $lock_name)) === 1;
    if (!$lock_acquired) {
        return false;
    }

    try {
        $engines = $wpdb->get_col(
            $wpdb->prepare(
                'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (%s, %s)',
                $metrics_table,
                $options_table
            )
        );
        if (count($engines) !== 2 || count(array_diff(array_map('strtolower', $engines), array('innodb'))) > 0) {
            nmkr_log_data_sync('Canonical finalization requires transactional metrics and options tables.', 'error');
            return false;
        }

        if ($wpdb->query('START TRANSACTION') === false) {
            return false;
        }
        $serialized_receipt = $wpdb->get_var(
            $wpdb->prepare("SELECT option_value FROM $options_table WHERE option_name = %s FOR UPDATE", $receipt_key)
        );
        if ($serialized_receipt !== null) {
            $receipt = maybe_unserialize($serialized_receipt);
            if ($wpdb->query('COMMIT') === false) {
                $wpdb->query('ROLLBACK');
                return false;
            }
            return is_array($receipt) ? $receipt : false;
        }

        $metrics['last_sync_time'] = $end_time;
        $metrics_id = nmkr_save_sync_metrics($metrics);
        if (!$metrics_id) {
            $wpdb->query('ROLLBACK');
            return false;
        }

        $receipt = array('metrics_id' => (int) $metrics_id, 'end_time' => $end_time);
        $inserted = $wpdb->insert(
            $options_table,
            array('option_name' => $receipt_key, 'option_value' => maybe_serialize($receipt), 'autoload' => 'no'),
            array('%s', '%s', '%s')
        );
        if (!$inserted) {
            $wpdb->query('ROLLBACK');
            return false;
        }

        if ($wpdb->query('COMMIT') === false) {
            $wpdb->query('ROLLBACK');
            return false;
        }
        if (function_exists('wp_cache_delete')) {
            wp_cache_delete($receipt_key, 'options');
        }
        return $receipt;
    } catch (Throwable $error) {
        $wpdb->query('ROLLBACK');
        nmkr_log_data_sync('Canonical metrics transaction rolled back: ' . $error->getMessage(), 'error');
        return false;
    } finally {
        $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
    }
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
    
    // A terminal record is the durable idempotency receipt. Never relabel any
    // terminal outcome or touch its history/metrics on repeated cleanup.
    if (!empty($sync_stats_id) && isset($sync_data['status'], $sync_data['sync_stats_id'])
        && nmkr_is_sync_terminal_status($sync_data['status'])
        && (int) $sync_data['sync_stats_id'] === (int) $sync_stats_id) {
        return $sync_data;
    }

    $end_time = !empty($final['end_time']) ? $final['end_time'] : nmkr_get_timestamp();

    if ($success) {
        $metrics = isset($final['metrics']) && is_array($final['metrics'])
            ? $final['metrics'] : get_transient('nmkr_current_sync_stats_live');
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

        if (empty($metrics) || !is_array($metrics)) {
            return false;
        }
        $receipt = nmkr_persist_sync_metrics_once($sync_stats_id, $metrics, $end_time);
        if (!$receipt || empty($receipt['metrics_id']) || empty($receipt['end_time'])) {
            return false;
        }
        $end_time = $receipt['end_time'];

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
        
        if ($history && !nmkr_is_sync_terminal_status($history['status'])) {
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
        update_option('nmkr_sync_user_stopped', false);
        delete_transient('nmkr_sync_user_stopped');
        
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
    wp_clear_scheduled_hook('nmkr_install_sync_cron_hook');
    
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
    // Keep the small run-owned receipt durable. Removing it would reopen the
    // insert race for a contender that entered with an active sync snapshot
    // and acquired the advisory lock after this process released it.
    
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
