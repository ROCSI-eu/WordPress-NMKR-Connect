<?php
/**
 * TTL (in seconds) for all NMKR Connect sync-related transients.
 * Used to cache both active sync metrics and dashboard AJAX state.
 */
if (!defined('NMKR_SYNC_TRANSIENT_TTL')) {
    define('NMKR_SYNC_TRANSIENT_TTL', HOUR_IN_SECONDS);
}

// Performance tracking functions
function nmkr_start_performance_tracking($operation) {
    // Reset the sync stats at the start of a new sync operation
    if ($operation === 'sync_data') {
        delete_transient('nmkr_current_sync_stats_live');
        delete_transient('nmkr_current_sync_stats_summary');
    }
    
    // Get current stats or initialize new ones
    $current_stats = get_transient('nmkr_current_sync_stats_live');
    if (!$current_stats) {
        $current_stats = array(
            'start_time' => microtime(true),
            'request_count' => 0,
            'successful_requests' => 0,
            'failed_requests' => 0,
            'retry_count' => 0,
            'total_api_time' => 0,
            'request_times' => array(),
            'throttle_wait_duration' => 0.0,
            'backoff_wait_duration' => 0.0,
            'operation_start_time' => microtime(true),
            'total_projects' => 0,
            'total_tokens' => 0,
            'db_queries' => 0,
            'db_duration' => 0
        );
    }
    
    set_transient('nmkr_current_sync_stats_live', $current_stats, NMKR_SYNC_TRANSIENT_TTL);
    // Only live transient is set here
    
    return [
        'operation' => $operation,
        'start_time' => microtime(true),
        'start_memory' => memory_get_usage(),
        'current_stats' => $current_stats
    ];
}

function nmkr_end_performance_tracking($tracking_data) {
    $end_time = microtime(true);
    $end_memory = memory_get_usage();
    $duration = ($end_time - $tracking_data['start_time']); // In seconds
    
    // Get current sync stats
    $current_stats = get_transient('nmkr_current_sync_stats_live');
    if (!$current_stats) {
        $current_stats = array(
            'start_time' => $tracking_data['start_time'],
            'request_count' => 0,
            'successful_requests' => 0,
            'failed_requests' => 0,
            'total_api_time' => 0,
            'request_times' => array(),
            'operation_start_time' => $tracking_data['start_time'],
            'total_projects' => 0,
            'total_tokens' => 0,
            'db_queries' => 0,
            'db_duration' => 0
        );
    }
    
    // HTTP attempts are recorded explicitly at the dispatch boundary. Generic
    // labels must never imply that a request happened.
    // Entity/query counters are recorded explicitly after a confirmed write.
    
    set_transient('nmkr_current_sync_stats_live', $current_stats, NMKR_SYNC_TRANSIENT_TTL);
    // Only live transient is set here
    
    // Calculate total elapsed time
    $total_duration = ($end_time - $current_stats['operation_start_time']); // In seconds
    
    $performance_data = [
        'operation' => $tracking_data['operation'],
        'duration' => round($duration, 2),
        'total_duration' => round($total_duration, 2),
        'memory_used' => round(($end_memory - $tracking_data['start_memory']) / 1024 / 1024, 2),
        'timestamp' => nmkr_get_timestamp(),
        'request_count' => $current_stats['request_count'],
        'successful_requests' => $current_stats['successful_requests'],
        'failed_requests' => $current_stats['failed_requests'],
        'total_api_time' => round($current_stats['total_api_time'], 2),
        'total_projects' => $current_stats['total_projects'],
        'total_tokens' => $current_stats['total_tokens'],
        'db_queries' => $current_stats['db_queries'],
        'db_duration' => round($current_stats['db_duration'], 2)
    ];

    // Sanity clamp to avoid incoherent totals in UI
    if ($performance_data['total_api_time'] > $performance_data['total_duration']) {
        $performance_data['total_api_time'] = $performance_data['total_duration'];
    }

    // Calculate average response time if we have API requests
    if ($current_stats['request_count'] > 0 && !empty($current_stats['request_times'])) {
        $average_time = array_sum($current_stats['request_times']) / $current_stats['request_count'];
        $performance_data['average_time'] = $average_time; // In seconds
    } else {
        $performance_data['average_time'] = null;
    }

    return $performance_data;
}

/** Record exactly one synchronization HTTP dispatch. */
function nmkr_record_api_attempt($duration, $successful, $is_retry = false, $context = array()) {
    $stats = get_transient('nmkr_current_sync_stats_live');
    if (!is_array($stats)) $stats = array();
    foreach (array('request_count', 'successful_requests', 'failed_requests', 'retry_count') as $key) {
        $stats[$key] = isset($stats[$key]) ? (int) $stats[$key] : 0;
    }
    $stats['request_times'] = isset($stats['request_times']) && is_array($stats['request_times']) ? $stats['request_times'] : array();
    $stats['total_api_time'] = isset($stats['total_api_time']) ? (float) $stats['total_api_time'] : 0.0;
    $duration = max(0.0, (float) $duration);
    $stats['request_count']++;
    $stats[$successful ? 'successful_requests' : 'failed_requests']++;
    if ($is_retry) $stats['retry_count']++;
    $stats['request_times'][] = $duration;
    $stats['total_api_time'] += $duration;
    $stats['average_time'] = array_sum($stats['request_times']) / count($stats['request_times']);
    set_transient('nmkr_current_sync_stats_live', $stats, NMKR_SYNC_TRANSIENT_TTL);
    if (!is_array($context) || empty($context['run_id']) || empty($context['sync_stats_id'])) return true;

    $error = function () {
        return new WP_Error('nmkr_api_metric_evidence_persistence_failure', 'Synchronization API attempt evidence could not be persisted.');
    };
    if (!function_exists('nmkr_get_sync_data') || !function_exists('nmkr_save_sync_data')) return $error();
    $sync_data = nmkr_get_sync_data();
    if (!is_array($sync_data) || !hash_equals((string) ($sync_data['run_id'] ?? ''), (string) $context['run_id'])
        || (int) ($sync_data['sync_stats_id'] ?? 0) !== (int) $context['sync_stats_id']) return $error();

    $evidence_keys = array('request_count', 'successful_requests', 'failed_requests', 'retry_count', 'total_api_time', 'request_times');
    $evidence = array_intersect_key($stats, array_flip($evidence_keys));
    // Wait totals remain useful durable evidence, but are not part of the
    // dispatch record whose exact persistence gates further HTTP work.
    foreach (array('throttle_wait_duration', 'backoff_wait_duration') as $key) {
        if (array_key_exists($key, $stats)) $evidence[$key] = $stats[$key];
    }
    $sync_data['api_metric_evidence'] = $evidence;
    if (nmkr_save_sync_data($sync_data) !== true) return $error();

    $saved = nmkr_get_sync_data();
    if (!is_array($saved) || !hash_equals((string) ($saved['run_id'] ?? ''), (string) $context['run_id'])
        || (int) ($saved['sync_stats_id'] ?? 0) !== (int) $context['sync_stats_id']
        || !isset($saved['api_metric_evidence']) || !is_array($saved['api_metric_evidence'])) return $error();
    foreach ($evidence_keys as $key) {
        if (!array_key_exists($key, $saved['api_metric_evidence']) || $saved['api_metric_evidence'][$key] !== $evidence[$key]) return $error();
    }
    return true;
}

/** Record one confirmed database storage operation without prefix inference. */
function nmkr_record_database_operation($entity, $duration) {
    $stats = get_transient('nmkr_current_sync_stats_live');
    if (!is_array($stats)) $stats = array();
    $stats['db_queries'] = (int) ($stats['db_queries'] ?? 0) + 1;
    $stats['db_duration'] = (float) ($stats['db_duration'] ?? 0) + max(0.0, (float) $duration);
    if ($entity === 'project') $stats['total_projects'] = (int) ($stats['total_projects'] ?? 0) + 1;
    if ($entity === 'token') $stats['total_tokens'] = (int) ($stats['total_tokens'] ?? 0) + 1;
    set_transient('nmkr_current_sync_stats_live', $stats, NMKR_SYNC_TRANSIENT_TTL);
}

function nmkr_record_api_wait($kind, $duration) {
    $key = $kind === 'backoff' ? 'backoff_wait_duration' : 'throttle_wait_duration';
    $stats = get_transient('nmkr_current_sync_stats_live');
    if (!is_array($stats)) $stats = array();
    $stats[$key] = (float) ($stats[$key] ?? 0) + max(0.0, (float) $duration);
    set_transient('nmkr_current_sync_stats_live', $stats, NMKR_SYNC_TRANSIENT_TTL);
}

function nmkr_log_performance_data($performance_data) {
    // Gate logging using centralized helper function
    if (!nmkr_should_log_performance()) {
        return;
    }
    
    // Validate performance data size to prevent oversized entries
    if (!empty($performance_data)) {
        $json_data = json_encode($performance_data);
        if (strlen($json_data) > 5000) {
            // Keep essential performance metrics but truncate excessive data
            $performance_data = [
                'operation' => isset($performance_data['operation']) ? $performance_data['operation'] : 'unknown',
                'duration' => isset($performance_data['duration']) ? $performance_data['duration'] : 0,
                'memory_used' => isset($performance_data['memory_used']) ? $performance_data['memory_used'] : 0,
                'request_count' => isset($performance_data['request_count']) ? $performance_data['request_count'] : 0,
                'average_time' => array_key_exists('average_time', $performance_data) ? $performance_data['average_time'] : null,
                'warning' => 'Performance data too large, truncated to essential metrics'
            ];
        }
    }
    
    // Add write_log function if not already defined
    if (!function_exists('write_log')) {
        /**
         * Write to WordPress debug.log if WP_DEBUG_LOG is enabled
         */
        function write_log($message) {
            $opts = function_exists('get_option') ? get_option('nmkr_connect_options') : null;
            $plugin_debug_enabled = is_array($opts) && !empty($opts['debug_enabled']);
            $log_to_file_enabled  = is_array($opts) && !empty($opts['log_to_debug_file']);
            if (!(true === WP_DEBUG_LOG && $plugin_debug_enabled && $log_to_file_enabled)) {
                return;
            }
            if (is_array($message) || is_object($message)) {
                error_log(print_r($message, true));
            } else {
                error_log($message);
            }
        }
    }
    
    // Log to dashboard (database) if enabled
    if (nmkr_should_log_to('dashboard')) {
        // Store in WordPress options with configurable retention limit
        $performance_logs = get_option('nmkr_performance_logs', array());
        if (!is_array($performance_logs)) {
            $performance_logs = array();
        }
        array_unshift($performance_logs, $performance_data);
        $performance_logs = array_slice($performance_logs, 0, nmkr_get_log_retention_limit());
        update_option('nmkr_performance_logs', $performance_logs, false); // Set autoload=false to prevent performance issues
    }

    // Log to debug.log file if enabled
    if (nmkr_should_log_to('file')) {
        $log_message = sprintf(
            'NMKR Performance: %s completed in %.2fms (Avg: %.2fms/req, Reqs: %d, Mem: %.2fMB)',
            $performance_data['operation'],
            $performance_data['duration'],
            $performance_data['average_time'],
            $performance_data['request_count'],
            $performance_data['memory_used']
        );
        write_log($log_message);
    }
}


// Function to get current sync performance stats
function nmkr_get_sync_stats() {
    $stats = get_transient('nmkr_current_sync_stats_live');
    if (!$stats) return null;

    $memory_peak = memory_get_peak_usage(true);
    
    // Calculate total elapsed time since the beginning of the sync process
    $total_duration = (microtime(true) - $stats['operation_start_time']); // Now in seconds
    
    // Calculate average response time from individual API request times
    $average_time = null;
    if ($stats['request_count'] > 0 && !empty($stats['request_times'])) {
        $average_time = round(array_sum($stats['request_times']) / $stats['request_count'], 2); // Keep in seconds for display
    }
    
    $performance_data = array(
        // Performance metrics
        'total_duration' => round($total_duration, 2),
        'request_count' => $stats['request_count'],
        'memory_used' => round($memory_peak / 1024 / 1024, 2), // Convert to MB
        'average_time' => $average_time,
        'total_api_time' => round($stats['total_api_time'], 2),
        
        // Progress metrics - now included for complete live metrics
        'total_projects' => isset($stats['total_projects']) ? (int) $stats['total_projects'] : 0,
        'total_tokens' => isset($stats['total_tokens']) ? (int) $stats['total_tokens'] : 0
    );

    if ($performance_data['total_api_time'] > $performance_data['total_duration']) {
        $performance_data['total_api_time'] = $performance_data['total_duration'];
    }

    return $performance_data;
}

// Function to format performance metrics for logging
function nmkr_format_performance_metrics($perf) {
    $metrics = array();
    
    // Format duration
    if (isset($perf['duration'])) {
        $metrics[] = sprintf("Duration: %.2fs", max(0, $perf['duration']));
    }
    
    // Format API time
    if (isset($perf['total_api_time'])) {
        $metrics[] = sprintf("API: %.2fs", max(0, $perf['total_api_time']));
    }
    
    // Format request count
    if (isset($perf['request_count'])) {
        $metrics[] = sprintf("Reqs: %d", $perf['request_count']);
    }
    
    // Format average time
    if (isset($perf['average_time'])) {
        $metrics[] = sprintf("Avg: %.2fs", max(0, $perf['average_time']));
    }
    
    // Format memory (ensure positive value and proper unit)
    if (isset($perf['memory_used'])) {
        $memory = abs($perf['memory_used']);
        if ($memory < 1) {
            $metrics[] = sprintf("Mem: %.2fKB", $memory * 1024);
        } else {
            $metrics[] = sprintf("Mem: %.2fMB", $memory);
        }
    }
    
    return implode(' | ', $metrics);
}


function nmkr_get_performance_metrics($stats) {
    $memory_peak = memory_get_peak_usage(true);
    
    // Calculate total elapsed time since the beginning of the sync process
    $total_duration = (microtime(true) - $stats['operation_start_time']); // In seconds
    
    // Calculate average response time from individual API request times
    $average_time = null;
    if ($stats['request_count'] > 0 && !empty($stats['request_times'])) {
        $average_time = array_sum($stats['request_times']) / $stats['request_count']; // Keep in seconds for display
    }
    
    $performance_data = array(
        'total_duration' => round($total_duration, 2),
        'request_count' => $stats['request_count'],
        'memory_used' => round($memory_peak / 1024 / 1024, 2), // Convert to MB
        'average_time' => $average_time,
        'total_api_time' => round($stats['total_api_time'], 2)
    );

    return $performance_data;
}

/**
 * Wrapper function to track API call performance
 *
 * @param string $label Operation label for tracking (e.g., 'fetch_projects', 'fetch_token_details')
 * @param callable $callback Function that makes the actual API call
 * @return mixed Result from the callback function
 */
function nmkr_tracked_api_call_v2($label, $callback) {
    $tracking = nmkr_start_performance_tracking($label);
    try {
        $result = call_user_func($callback);
        nmkr_end_performance_tracking($tracking);
        return $result;
    } catch (Exception $e) {
        // Still track performance even if there's an error
        nmkr_end_performance_tracking($tracking);
        throw $e;
    }
}

function nmkr_get_total_api_request_count() {
    $current_stats = get_transient('nmkr_current_sync_stats_live');
    return $current_stats ? $current_stats['request_count'] : 0;
}

function nmkr_get_memory_usage_mb() {
    return round(memory_get_usage(true) / 1024 / 1024, 2);
}
