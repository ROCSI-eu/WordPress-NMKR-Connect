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
 * @param bool        $option_aborted Durable abort marker
 * @param bool        $transient_aborted Transient abort marker
 * @return bool
 */
function nmkr_is_sync_canonically_finished($sync_data, $option_in_progress, $transient_in_progress, $option_aborted, $transient_aborted) {
    return is_array($sync_data)
        && isset($sync_data['status'], $sync_data['completed'])
        && $sync_data['status'] === 'completed'
        && $sync_data['completed'] === true
        && !$option_in_progress
        && !$transient_in_progress
        && !$option_aborted
        && !$transient_aborted;
}

function nmkr_acquire_sync_finalization_lock($sync_stats_id) {
    global $wpdb;
    $lock_name = 'nmkr_sync_finalize_' . (int) $sync_stats_id;
    return (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 10)', $lock_name)) === 1;
}

function nmkr_release_sync_finalization_lock($sync_stats_id) {
    global $wpdb;
    $lock_name = 'nmkr_sync_finalize_' . (int) $sync_stats_id;
    $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
}

function nmkr_is_valid_sync_end_time($end_time) {
    $parsed = DateTime::createFromFormat('!Y-m-d H:i:s', (string) $end_time);
    return $parsed && $parsed->format('Y-m-d H:i:s') === (string) $end_time;
}

/** Return a validated durable receipt for the exact run, if one exists. */
function nmkr_get_sync_metrics_receipt($sync_stats_id) {
    global $wpdb;
    $sync_stats_id = (int) $sync_stats_id;
    if ($sync_stats_id <= 0) {
        return false;
    }
    $receipt_key = 'nmkr_sync_finalizing_' . $sync_stats_id;
    $serialized = $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $receipt_key));
    if ($serialized === null) {
        return false;
    }
    $receipt = maybe_unserialize($serialized);
    if (!is_array($receipt) || (int) ($receipt['sync_stats_id'] ?? 0) !== $sync_stats_id
        || (int) ($receipt['metrics_id'] ?? 0) <= 0 || !nmkr_is_valid_sync_end_time($receipt['end_time'] ?? '')) {
        return false;
    }
    $metrics_table = $wpdb->prefix . 'nmkr_sync_metrics';
    $metric = $wpdb->get_row(
        $wpdb->prepare("SELECT id, last_sync_time FROM $metrics_table WHERE id = %d", (int) $receipt['metrics_id']),
        ARRAY_A
    );
    if (!$metric || (int) $metric['id'] !== (int) $receipt['metrics_id']
        || (string) $metric['last_sync_time'] !== (string) $receipt['end_time']) {
        return false;
    }
    return $receipt;
}

function nmkr_sync_has_committed_success($sync_stats_id) {
    return nmkr_get_sync_metrics_receipt($sync_stats_id) !== false;
}

/**
 * Persist the run's metrics row and durable receipt as one atomic unit.
 *
 * The advisory lock serializes contenders for the same history ID. The
 * transaction makes an interruption between the metrics insert and receipt
 * insert roll back both writes. This requires the WordPress options and NMKR
 * metrics tables to use a transaction-capable storage engine; otherwise the
 * function fails closed rather than claiming exactly-once persistence.
 * Caller must hold the run-owned advisory lock for the full finalizer.
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
        $metrics_id = nmkr_save_sync_metrics($metrics, true);
        if (!$metrics_id) {
            $wpdb->query('ROLLBACK');
            return false;
        }

        $receipt = array('sync_stats_id' => $sync_stats_id, 'metrics_id' => (int) $metrics_id, 'end_time' => $end_time);
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
    }
}

/** Check whether any durable receipt row exists, including an invalid one. */
function nmkr_sync_metrics_receipt_record_exists($sync_stats_id) {
    global $wpdb;
    $key = 'nmkr_sync_finalizing_' . (int) $sync_stats_id;
    return $wpdb->get_var($wpdb->prepare("SELECT option_id FROM {$wpdb->options} WHERE option_name = %s", $key)) !== null;
}

/** Remove only terminalization-owned active evidence. */
function nmkr_cleanup_sync_terminal_markers($success) {
    update_option('nmkr_sync_in_progress', false);
    delete_transient('nmkr_sync_in_progress');
    delete_option('nmkr_sync_near_completion');
    nmkr_cleanup_sync_heartbeat();
    foreach (array('nmkr_process_batch_hook', 'nmkr_execute_sync_background', 'nmkr_sync_cron_hook', 'nmkr_install_sync_cron_hook') as $hook) {
        wp_clear_scheduled_hook($hook);
    }
    if ($success) {
        update_option('nmkr_sync_user_stopped', false);
        delete_transient('nmkr_sync_user_stopped');
    }
    delete_transient('nmkr_current_sync_stats_live');
    delete_transient('nmkr_active_sync_metrics');
    delete_transient('nmkr_sync_performance_metrics');
}

if (!function_exists('nmkr_sync_finalization_checkpoint')) {
    /** Synthetic-test checkpoint; production intentionally performs no work. */
    function nmkr_sync_finalization_checkpoint($point, $sync_stats_id) {
        return;
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
    $initial_sync_data = nmkr_get_sync_data();
    $sync_stats_id = isset($initial_sync_data['sync_stats_id']) ? (int) $initial_sync_data['sync_stats_id'] : 0;
    if ($sync_stats_id <= 0 || !nmkr_acquire_sync_finalization_lock($sync_stats_id)) {
        return false;
    }

    try {
        // Re-read after ownership is acquired. A contender may have completed
        // the entire transition while this process waited for the lock.
        $sync_data = nmkr_get_sync_data();
        if (!is_array($sync_data) || (int) ($sync_data['sync_stats_id'] ?? 0) !== $sync_stats_id) {
            return false;
        }

        if (nmkr_is_sync_terminal_status($sync_data['status'] ?? '')) {
            // Terminal retries finish idempotent cleanup rather than returning
            // early and permanently retaining post-terminal leftovers.
            nmkr_cleanup_sync_terminal_markers(in_array(($sync_data['status'] ?? ''), array('completed', 'success'), true));
            return $sync_data;
        }

        $end_time = !empty($final['end_time']) ? $final['end_time'] : nmkr_get_timestamp();
        $receipt = false;
        global $wpdb;
        $history_table = $wpdb->prefix . 'nmkr_sync_stats';
        $history = $wpdb->get_row($wpdb->prepare("SELECT id, status, end_time FROM $history_table WHERE id = %d", $sync_stats_id), ARRAY_A);
        if (!$history || (int) $history['id'] !== $sync_stats_id) {
            return false;
        }
        if (nmkr_is_sync_terminal_status($history['status'])) {
            $allowed = $success ? array('completed', 'success') : array('failed', 'error');
            if (!in_array(strtolower($history['status']), $allowed, true)) {
                return false;
            }
            if ($success) {
                $receipt = nmkr_get_sync_metrics_receipt($sync_stats_id);
                if (!$receipt || (string) $history['end_time'] !== (string) $receipt['end_time']) {
                    return false;
                }
            } else {
                $end_time = $history['end_time'];
            }
        }

        if ($success) {
            // Keep the run detectably active throughout durable persistence.
            $sync_data['status'] = 'finalizing';
            $sync_data['completed'] = false;
            nmkr_save_sync_data($sync_data);
            update_option('nmkr_sync_in_progress', true);
            set_transient('nmkr_sync_in_progress', true, NMKR_SYNC_TRANSIENT_TTL);

            $receipt = $receipt ?: nmkr_get_sync_metrics_receipt($sync_stats_id);
            if (!$receipt) {
                if (nmkr_sync_metrics_receipt_record_exists($sync_stats_id)) {
                    return false; // malformed receipt or missing/mismatched metric
                }
                $metrics = isset($final['metrics']) && is_array($final['metrics'])
                    ? $final['metrics'] : get_transient('nmkr_current_sync_stats_live');
                if (empty($metrics) || !is_array($metrics)) {
                    return false;
                }
                $performance = nmkr_get_sync_stats();
                $metrics['total_projects'] = $metrics['total_projects'] ?? ($sync_data['total_projects'] ?? 0);
                $metrics['total_tokens'] = $metrics['total_tokens'] ?? ($sync_data['total_tokens'] ?? 0);
                $metrics['total_sync_duration'] = $metrics['total_sync_duration'] ?? ($performance['total_duration'] ?? 0);
                $metrics['total_api_time'] = $metrics['total_api_time'] ?? 0;
                $metrics['average_response_time'] = $metrics['average_response_time'] ?? ($performance['average_time'] ?? 0);
                $metrics['api_requests'] = $metrics['api_requests'] ?? ($performance['request_count'] ?? 0);
                $metrics['memory_usage'] = $metrics['memory_usage'] ?? ($performance['memory_used'] ?? 0);
                $receipt = nmkr_persist_sync_metrics_once($sync_stats_id, $metrics, $end_time);
                if (!$receipt) {
                    return false;
                }
            }
            $end_time = $receipt['end_time'];
            nmkr_sync_finalization_checkpoint('receipt_committed', $sync_stats_id);
        }

        $history = $wpdb->get_row($wpdb->prepare("SELECT id, status, end_time FROM $history_table WHERE id = %d", $sync_stats_id), ARRAY_A);
        if (!$history || (int) $history['id'] !== $sync_stats_id) {
            return false;
        }

        $expected_statuses = $success ? array('completed', 'success') : array('failed', 'error');
        if (nmkr_is_sync_terminal_status($history['status'])) {
            if (!in_array(strtolower($history['status']), $expected_statuses, true)
                || (string) $history['end_time'] !== (string) $end_time) {
                return false;
            }
        } else {
            $history_update = array('status' => $success ? 'completed' : 'failed', 'end_time' => $end_time);
            foreach (array('items_processed', 'items_successful', 'items_failed', 'items_skipped', 'token_details_synced') as $counter) {
                if (isset($final[$counter])) {
                    $history_update[$counter] = (int) $final[$counter];
                }
            }
            if (!$success && !empty($error_message)) {
                $history_update['error_message'] = $error_message;
            }
            if (!nmkr_update_sync_stats($sync_stats_id, $history_update)) {
                return false;
            }
        }

        $history = $wpdb->get_row($wpdb->prepare("SELECT id, status, end_time FROM $history_table WHERE id = %d", $sync_stats_id), ARRAY_A);
        if (!$history || (int) $history['id'] !== $sync_stats_id
            || !in_array(strtolower($history['status']), $expected_statuses, true)
            || !nmkr_is_valid_sync_end_time($history['end_time'])
            || (string) $history['end_time'] !== (string) $end_time) {
            return false;
        }

        if ($success) {
            update_option('nmkr_last_sync_time', $end_time);
            nmkr_update_sync_progress(100, 100, '✅ Synchronization Completed');
            update_option('nmkr_sync_status', 'completed');
            set_transient('nmkr_sync_status', 'completed', NMKR_SYNC_TRANSIENT_TTL);
        } else {
            nmkr_update_sync_progress(0, 100, '❌ Synchronization Failed: ' . $error_message, true);
            update_option('nmkr_sync_error', $error_message);
            update_option('nmkr_sync_status', 'failed');
            set_transient('nmkr_sync_error', $error_message, NMKR_SYNC_TRANSIENT_TTL);
            set_transient('nmkr_sync_status', 'failed', NMKR_SYNC_TRANSIENT_TTL);
        }

        // All cleanup precedes the final terminal sync-data write.
        nmkr_cleanup_sync_terminal_markers($success);
        nmkr_sync_finalization_checkpoint('before_terminal_record', $sync_stats_id);
        $terminal = array(
            'status' => $success ? 'completed' : 'failed',
            'completed' => true,
            'sync_stats_id' => $sync_stats_id,
            'end_time' => $end_time,
        );
        if (!$success && !empty($error_message)) {
            $terminal['error_code'] = 'sync_failed';
        }
        if (!nmkr_save_sync_data($terminal)) {
            return false;
        }
        nmkr_sync_finalization_checkpoint('after_terminal_record', $sync_stats_id);
        return $terminal;
    } catch (Throwable $error) {
        nmkr_log_data_sync('Canonical finalization remains resumable: ' . $error->getMessage(), 'error');
        return false;
    } finally {
        nmkr_release_sync_finalization_lock($sync_stats_id);
    }
}
