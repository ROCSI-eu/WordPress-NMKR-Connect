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

add_action('nmkr_resume_sync_finalization', 'nmkr_resume_sync_finalization', 10, 1);

function nmkr_sync_finalization_resume_key($sync_stats_id) {
    return 'nmkr_sync_finalization_resume_' . (int) $sync_stats_id;
}

function nmkr_schedule_sync_finalization_resume($sync_stats_id, $attempt = 0) {
    $sync_stats_id = (int) $sync_stats_id;
    if ($sync_stats_id <= 0 || $attempt >= 5) {
        return false;
    }
    $args = array($sync_stats_id);
    if (!wp_next_scheduled('nmkr_resume_sync_finalization', $args)) {
        $delay = min(30, 3 * (2 ** max(0, $attempt)));
        return (bool) wp_schedule_single_event(time() + $delay, 'nmkr_resume_sync_finalization', $args);
    }
    return true;
}

function nmkr_clear_sync_finalization_resume($sync_stats_id) {
    $sync_stats_id = (int) $sync_stats_id;
    // Delete the option first: a hard stop before hook removal leaves an event
    // that can finish cleanup. The reverse order can strand an option forever.
    delete_option(nmkr_sync_finalization_resume_key($sync_stats_id));
    wp_clear_scheduled_hook('nmkr_resume_sync_finalization', array($sync_stats_id));
}

function nmkr_save_sync_finalization_resume($sync_stats_id, $final) {
    $key = nmkr_sync_finalization_resume_key($sync_stats_id);
    $existing = get_option($key, false);
    $record = array('sync_stats_id' => (int) $sync_stats_id, 'attempt' => is_array($existing) ? (int) ($existing['attempt'] ?? 0) : 0);
    if (!empty($final['run_id'])) {
        $record['run_id'] = (string) $final['run_id'];
    }
    if (!empty($final['end_time'])) {
        $record['end_time'] = (string) $final['end_time'];
    }
    if (!empty($final['metrics']) && is_array($final['metrics'])) {
        $record['metrics'] = array_intersect_key($final['metrics'], array_flip(array(
            'total_projects', 'total_tokens', 'total_sync_duration', 'total_api_time',
            'average_response_time', 'api_requests', 'memory_usage',
        )));
    }
    foreach (array('items_processed', 'items_successful', 'items_failed', 'items_skipped', 'token_details_synced') as $counter) {
        if (isset($final[$counter])) {
            $record[$counter] = (int) $final[$counter];
        }
    }
    if (is_array($existing) && !empty($existing['end_time'])) {
        $record['end_time'] = $existing['end_time'];
    }
    update_option($key, $record, false);
    return get_option($key, false) === $record ? $record : false;
}

function nmkr_build_final_sync_metrics($provided, $sync_data, $totals = array()) {
    $provided = is_array($provided) ? $provided : array();
    $sync_data = is_array($sync_data) ? $sync_data : array();
    $performance = nmkr_get_sync_stats();
    $performance = is_array($performance) ? $performance : array();
    $metrics = array(
        'total_projects' => (int) ($totals['total_projects'] ?? $provided['total_projects'] ?? $sync_data['total_projects'] ?? 0),
        'total_tokens' => (int) ($totals['total_tokens'] ?? $provided['total_tokens'] ?? $sync_data['total_tokens'] ?? 0),
        'total_sync_duration' => (float) ($provided['total_sync_duration'] ?? $performance['total_duration'] ?? 0),
        'total_api_time' => (float) ($provided['total_api_time'] ?? $performance['total_api_time'] ?? 0),
        'average_response_time' => (float) ($provided['average_response_time'] ?? $performance['average_time'] ?? 0),
        'api_requests' => (int) ($provided['api_requests'] ?? $performance['request_count'] ?? 0),
        'memory_usage' => (float) ($provided['memory_usage'] ?? $performance['memory_used'] ?? 0),
    );
    return $metrics;
}

function nmkr_sync_finalization_resume_pending($sync_stats_id) {
    $sync_stats_id = (int) $sync_stats_id;
    return get_option(nmkr_sync_finalization_resume_key($sync_stats_id), false) !== false
        || (bool) wp_next_scheduled('nmkr_resume_sync_finalization', array($sync_stats_id));
}

function nmkr_sync_start_blocked_by_finalization($sync_data) {
    $sync_stats_id = is_array($sync_data) && isset($sync_data['sync_stats_id'])
        && is_numeric($sync_data['sync_stats_id']) && (int) $sync_data['sync_stats_id'] > 0
        ? (int) $sync_data['sync_stats_id'] : 0;
    return (is_array($sync_data) && ($sync_data['status'] ?? '') === 'finalizing')
        || ($sync_stats_id > 0 && nmkr_sync_finalization_resume_pending($sync_stats_id));
}

function nmkr_get_canonical_dashboard_sync_state() {
    $sync_data = nmkr_get_sync_data();
    $option_in_progress = (bool) get_option('nmkr_sync_in_progress', false);
    $transient_in_progress = (bool) get_transient('nmkr_sync_in_progress');
    $option_aborted = (bool) get_option('nmkr_sync_user_stopped', false);
    $transient_aborted = (bool) get_transient('nmkr_sync_user_stopped');
    $sync_stats_id = is_array($sync_data) && isset($sync_data['sync_stats_id']) && is_numeric($sync_data['sync_stats_id'])
        && (int) $sync_data['sync_stats_id'] > 0 ? (int) $sync_data['sync_stats_id'] : 0;
    $resume_pending = $sync_stats_id > 0 ? nmkr_sync_finalization_resume_pending($sync_stats_id) : false;
    $finished = nmkr_is_sync_canonically_finished($sync_data, $option_in_progress, $transient_in_progress, $option_aborted, $transient_aborted, $resume_pending);
    $status = is_array($sync_data) ? (string) ($sync_data['status'] ?? '') : '';
    $active_status = in_array($status, array('initializing', 'finalizing', 'processing', 'processing_projects', 'processing_tokens', 'in_progress', 'running', 'pending'), true);
    return array(
        'sync_data' => $sync_data,
        'finished' => $finished,
        'finalization_pending' => $resume_pending,
        'running' => !$finished && ($active_status || $resume_pending || $option_in_progress || $transient_in_progress),
    );
}

function nmkr_prepare_sync_finalization($sync_stats_id, $final, $sync_data) {
    if (!empty($sync_data['run_id'])) {
        $run_id = (string) $sync_data['run_id'];
        if (!nmkr_sync_owner_matches($run_id, 'finalizing', $sync_stats_id)) {
            return false;
        }
        $final['run_id'] = $run_id;
    }
    $final['metrics'] = nmkr_build_final_sync_metrics($final['metrics'] ?? array(), $sync_data);
    $saved = nmkr_save_sync_finalization_resume($sync_stats_id, $final);
    if (!is_array($saved) || !nmkr_schedule_sync_finalization_resume($sync_stats_id, (int) ($saved['attempt'] ?? 0))
        || !nmkr_sync_finalization_resume_pending($sync_stats_id)) {
        return false;
    }
    return $saved;
}

function nmkr_resume_sync_finalization($sync_stats_id) {
    $key = nmkr_sync_finalization_resume_key($sync_stats_id);
    $record = get_option($key, false);
    $sync_data = nmkr_get_sync_data();
    if (!is_array($record) || (int) ($record['sync_stats_id'] ?? 0) !== (int) $sync_stats_id
        || !is_array($sync_data) || (int) ($sync_data['sync_stats_id'] ?? 0) !== (int) $sync_stats_id) {
        nmkr_clear_sync_finalization_resume($sync_stats_id);
        return false;
    }
    if (!empty($record['run_id'])) {
        if (!isset($sync_data['run_id']) || !hash_equals((string) $record['run_id'], (string) $sync_data['run_id'])) {
            return false;
        }
        $owner = nmkr_get_sync_owner();
        if ($owner === false && nmkr_is_sync_terminal_status($sync_data['status'] ?? '')
            && nmkr_verify_sync_terminal_result($sync_data, true)) {
            nmkr_cleanup_sync_resume_state($sync_stats_id);
            return true;
        }
        if (!nmkr_sync_owner_matches((string) $record['run_id'], 'finalizing', $sync_stats_id)) {
            return false;
        }
    }
    $result = nmkr_sync_data_complete(true, '', $record, true);
    if (is_array($result) && in_array($result['status'] ?? '', array('completed', 'success'), true)) {
        return true;
    }
    // A waiter may have passed validation before another finalizer completed
    // and released the owner. Re-read before resurrecting retry evidence.
    $current_sync_data = nmkr_get_sync_data();
    $current_owner = nmkr_get_sync_owner();
    if (is_array($current_sync_data)
        && (int) ($current_sync_data['sync_stats_id'] ?? 0) === (int) $sync_stats_id
        && (string) ($current_sync_data['run_id'] ?? '') === (string) ($record['run_id'] ?? '')
        && nmkr_is_sync_terminal_status($current_sync_data['status'] ?? '')
        && $current_owner === false
        && nmkr_verify_sync_terminal_result($current_sync_data, true)) {
        nmkr_cleanup_sync_resume_state($sync_stats_id);
        return true;
    }
    if (!empty($record['run_id']) && !nmkr_sync_owner_matches((string) $record['run_id'], 'finalizing', $sync_stats_id)) {
        return false;
    }
    $record['attempt'] = (int) ($record['attempt'] ?? 0) + 1;
    update_option($key, $record, false);
    if ($record['attempt'] >= 5) {
        update_option('nmkr_sync_status', 'finalization_error');
        set_transient('nmkr_sync_status', 'finalization_error', NMKR_SYNC_TRANSIENT_TTL);
        update_option('nmkr_sync_in_progress', true);
        return false;
    }
    return nmkr_schedule_sync_finalization_resume($sync_stats_id, $record['attempt']);
}

function nmkr_maintain_sync_finalization_resume($sync_data) {
    if (!is_array($sync_data) || ($sync_data['status'] ?? '') !== 'finalizing') {
        return false;
    }
    $sync_stats_id = (int) ($sync_data['sync_stats_id'] ?? 0);
    $resume = get_option(nmkr_sync_finalization_resume_key($sync_stats_id), false);
    return is_array($resume) && (int) ($resume['sync_stats_id'] ?? 0) === $sync_stats_id
        ? nmkr_schedule_sync_finalization_resume($sync_stats_id, (int) ($resume['attempt'] ?? 0))
        : false;
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
function nmkr_is_sync_canonically_finished($sync_data, $option_in_progress, $transient_in_progress, $option_aborted, $transient_aborted, $resume_pending = false) {
    return is_array($sync_data)
        && isset($sync_data['status'], $sync_data['completed'])
        && $sync_data['status'] === 'completed'
        && $sync_data['completed'] === true
        && !$option_in_progress
        && !$transient_in_progress
        && !$option_aborted
        && !$transient_aborted
        && !$resume_pending;
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
function nmkr_cleanup_sync_active_markers($success, $sync_data = array()) {
    update_option('nmkr_sync_in_progress', false);
    delete_transient('nmkr_sync_in_progress');
    delete_option('nmkr_sync_near_completion');
    nmkr_cleanup_sync_heartbeat();
    $run_id = is_array($sync_data) ? (string) ($sync_data['run_id'] ?? '') : '';
    if ($run_id !== '') {
        wp_clear_scheduled_hook('nmkr_execute_sync_background', array($run_id));
    } else {
        foreach (array('nmkr_process_batch_hook', 'nmkr_execute_sync_background', 'nmkr_sync_cron_hook', 'nmkr_install_sync_cron_hook') as $hook) {
            wp_clear_scheduled_hook($hook);
        }
    }
    if ($success) {
        update_option('nmkr_sync_user_stopped', false);
        delete_transient('nmkr_sync_user_stopped');
    }
    foreach (array('nmkr_current_sync_stats_live', 'nmkr_active_sync_metrics', 'nmkr_sync_performance_metrics', 'nmkr_sync_worker_started_at', 'nmkr_sync_worker_lock') as $key) {
        delete_transient($key);
    }
    delete_option('nmkr_sync_worker_started_at');
    delete_option('nmkr_sync_worker_lock');

    $clean = !get_option('nmkr_sync_in_progress', false) && !get_transient('nmkr_sync_in_progress')
        && !get_option('nmkr_sync_near_completion', false) && !get_option('nmkr_sync_heartbeat', false)
        && !get_option('nmkr_sync_worker_started_at', false) && !get_option('nmkr_sync_worker_lock', false)
        && !get_transient('nmkr_sync_worker_started_at') && !get_transient('nmkr_sync_worker_lock')
        && !get_transient('nmkr_current_sync_stats_live') && !get_transient('nmkr_active_sync_metrics')
        && !get_transient('nmkr_sync_performance_metrics');
    if ($success) {
        $clean = $clean && !get_option('nmkr_sync_user_stopped', false) && !get_transient('nmkr_sync_user_stopped');
    }
    if ($run_id !== '') {
        $clean = $clean && !wp_next_scheduled('nmkr_execute_sync_background', array($run_id));
    } else {
        foreach (array('nmkr_process_batch_hook', 'nmkr_execute_sync_background', 'nmkr_sync_cron_hook', 'nmkr_install_sync_cron_hook') as $hook) {
            $clean = $clean && !wp_next_scheduled($hook);
        }
    }
    return $clean;
}

function nmkr_cleanup_sync_resume_state($sync_stats_id) {
    nmkr_clear_sync_finalization_resume($sync_stats_id);
    return get_option(nmkr_sync_finalization_resume_key($sync_stats_id), false) === false
        && !wp_next_scheduled('nmkr_resume_sync_finalization', array((int) $sync_stats_id));
}

/** Verify terminal history (and success receipt) before accepting owner absence. */
function nmkr_verify_sync_terminal_result($sync_data, $success) {
    global $wpdb;
    if (!is_array($sync_data) || !nmkr_is_sync_terminal_status($sync_data['status'] ?? '')) {
        return false;
    }
    $sync_stats_id = (int) ($sync_data['sync_stats_id'] ?? 0);
    $history = $wpdb->get_row($wpdb->prepare("SELECT id, status, end_time FROM {$wpdb->prefix}nmkr_sync_stats WHERE id = %d", $sync_stats_id), ARRAY_A);
    $allowed = $success ? array('completed', 'success') : array('failed', 'error');
    if (!$history || (int) ($history['id'] ?? 0) !== $sync_stats_id
        || !in_array(strtolower((string) ($history['status'] ?? '')), $allowed, true)
        || (string) ($history['end_time'] ?? '') !== (string) ($sync_data['end_time'] ?? '')) {
        return false;
    }
    if ($success) {
        $receipt = nmkr_get_sync_metrics_receipt($sync_stats_id);
        return is_array($receipt) && (string) ($receipt['end_time'] ?? '') === (string) $history['end_time'];
    }
    return true;
}

/** Restore exact retry evidence after terminal data was written but release failed. */
function nmkr_restore_sync_finalization_retry($sync_stats_id, $resume_record) {
    if (!is_array($resume_record)) {
        return false;
    }
    update_option(nmkr_sync_finalization_resume_key($sync_stats_id), $resume_record, false);
    return nmkr_schedule_sync_finalization_resume($sync_stats_id, (int) ($resume_record['attempt'] ?? 0))
        && nmkr_sync_finalization_resume_pending($sync_stats_id);
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
function nmkr_sync_data_complete($success = true, $error_message = '', $final = array(), $is_resume = false, $prepared = false) {
    $initial_sync_data = nmkr_get_sync_data();
    $sync_stats_id = isset($initial_sync_data['sync_stats_id']) ? (int) $initial_sync_data['sync_stats_id'] : 0;
    $run_id = is_array($initial_sync_data) ? (string) ($initial_sync_data['run_id'] ?? '') : '';
    if ($run_id !== '') {
        $initial_owner = nmkr_get_sync_owner();
        if ($initial_owner !== false && !nmkr_sync_owner_matches($run_id, 'finalizing', $sync_stats_id)) {
            return false;
        }
    }
    if ($success && $sync_stats_id > 0 && !$is_resume) {
        if (!$prepared) {
            $final = nmkr_prepare_sync_finalization($sync_stats_id, $final, $initial_sync_data);
        }
        if (!is_array($final) || !nmkr_sync_finalization_resume_pending($sync_stats_id)) {
            $initial_sync_data['status'] = 'finalizing';
            $initial_sync_data['completed'] = false;
            nmkr_save_sync_data($initial_sync_data);
            update_option('nmkr_sync_in_progress', true);
            update_option('nmkr_sync_status', 'finalization_error');
            return false;
        }
    }
    if ($sync_stats_id <= 0 || !nmkr_acquire_sync_finalization_lock($sync_stats_id)) {
        return false;
    }

    $resume_record = false;
    $receipt = false;
    $terminal_written = false;
    try {
        // Re-read after ownership is acquired. A contender may have completed
        // the entire transition while this process waited for the lock.
        $sync_data = nmkr_get_sync_data();
        if (!is_array($sync_data) || (int) ($sync_data['sync_stats_id'] ?? 0) !== $sync_stats_id) {
            return false;
        }
        nmkr_sync_finalization_checkpoint('after_finalization_lock', $sync_stats_id);

        if (nmkr_is_sync_terminal_status($sync_data['status'] ?? '')) {
            // Terminal retries finish idempotent cleanup rather than returning
            // early and permanently retaining post-terminal leftovers.
            $compatible = $success
                ? in_array(($sync_data['status'] ?? ''), array('completed', 'success'), true)
                : in_array(($sync_data['status'] ?? ''), array('failed', 'error'), true);
            if (!$compatible) {
                nmkr_clear_sync_finalization_resume($sync_stats_id);
                return false;
            }
            if ((string) ($sync_data['run_id'] ?? '') !== $run_id || !nmkr_verify_sync_terminal_result($sync_data, $success)) {
                return false;
            }
            $owner = nmkr_get_sync_owner();
            if ($owner === false) {
                nmkr_cleanup_sync_resume_state($sync_stats_id);
                return $sync_data;
            }
            if (!nmkr_sync_owner_matches($run_id, 'finalizing', $sync_stats_id)) {
                return false;
            }
            if (!nmkr_cleanup_sync_resume_state($sync_stats_id)) {
                return false;
            }
            $released = nmkr_release_sync_owner($run_id, $sync_stats_id, 'finalizing');
            if ($released !== true) {
                nmkr_restore_sync_finalization_retry($sync_stats_id, $final);
                return false;
            }
            return $sync_data;
        }

        $end_time = !empty($final['end_time']) ? $final['end_time'] : nmkr_get_timestamp();
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
                $metrics = isset($final['metrics']) && is_array($final['metrics']) ? $final['metrics'] : array();
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

        // Phase 1 removes active runtime evidence while preserving the exact
        // durable resume record and event until terminal sync data is durable.
        $resume_record = get_option(nmkr_sync_finalization_resume_key($sync_stats_id), false);
        if (!nmkr_cleanup_sync_active_markers($success, $sync_data)) {
            return false;
        }
        nmkr_sync_finalization_checkpoint('before_terminal_record', $sync_stats_id);
        $terminal = array(
            'status' => $success ? 'completed' : 'failed',
            'completed' => true,
            'sync_stats_id' => $sync_stats_id,
            'end_time' => $end_time,
        );
        if ($run_id !== '') {
            $terminal['run_id'] = $run_id;
        }
        if (!$success && !empty($error_message)) {
            $terminal['error_code'] = 'sync_failed';
        }
        if (!nmkr_save_sync_data($terminal)) {
            if ($success && is_array($resume_record)) {
                update_option(nmkr_sync_finalization_resume_key($sync_stats_id), $resume_record, false);
                nmkr_schedule_sync_finalization_resume($sync_stats_id, (int) ($resume_record['attempt'] ?? 0));
            }
            return false;
        }
        $terminal_written = true;
        nmkr_sync_finalization_checkpoint('after_terminal_record', $sync_stats_id);
        // Phase 2 removes recovery evidence only after the terminal record is
        // durable. Polling remains nonterminal until this readback succeeds.
        if (!nmkr_cleanup_sync_resume_state($sync_stats_id)) {
            if (is_array($resume_record)) {
                update_option(nmkr_sync_finalization_resume_key($sync_stats_id), $resume_record, false);
            }
            nmkr_schedule_sync_finalization_resume($sync_stats_id, (int) ($resume_record['attempt'] ?? 0));
            return false;
        }
        if ($run_id !== '') {
            $released = nmkr_release_sync_owner($run_id, $sync_stats_id, 'finalizing');
            if ($released !== true) {
                nmkr_restore_sync_finalization_retry($sync_stats_id, $resume_record);
                return false;
            }
        }
        return $terminal;
    } catch (Throwable $error) {
        if ($success && !$terminal_written && !empty($receipt) && is_array($resume_record)) {
            update_option(nmkr_sync_finalization_resume_key($sync_stats_id), $resume_record, false);
            nmkr_schedule_sync_finalization_resume($sync_stats_id, (int) ($resume_record['attempt'] ?? 0));
        }
        nmkr_log_data_sync('Canonical finalization remains resumable: ' . $error->getMessage(), 'error');
        return false;
    } finally {
        nmkr_release_sync_finalization_lock($sync_stats_id);
    }
}
