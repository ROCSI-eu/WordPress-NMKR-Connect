<?php
/**
 * NMKR Connect Dashboard Statistics
 *
 * Functions for processing and displaying dashboard statistics.
 *
 * @package NMKR_Connect
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Function to get the latest sync time from the database
function nmkr_get_last_sync_time() {
    $metrics = nmkr_get_last_sync_metrics();
    
    // First try to get time from metrics
    if ($metrics && !empty($metrics['last_sync_time'])) {
        return $metrics['last_sync_time'] . ' UTC';
    }
    
    // If no metrics found, try to get from options
    $last_sync_time = get_option('nmkr_last_sync_time', '');
    if (!empty($last_sync_time)) {
        return $last_sync_time . ' UTC';
    }
    
    // No sync time found anywhere
    return 'No synchronization done yet';
}

/**
 * Get metrics for the last successful sync
 * This function is used only for displaying historical/completed sync data
 * 
 * @return array An array of metrics for the last completed sync
 */
function nmkr_get_sync_statistics() {
    // Get last successful sync metrics from database
    // These are ONLY stored after a sync completes successfully, never during an active sync
    $last_sync_metrics = nmkr_get_last_sync_metrics();
    
    // Get the last successful sync time from option (might be more recent than the metrics)
    $last_successful_sync_time = get_option('nmkr_last_sync_time', '');
    
    // If we have no metrics but we do have a last sync time in options, create basic metrics
    if (!$last_sync_metrics && !empty($last_successful_sync_time)) {
        return array(
            'last_sync_time' => date('Y-m-d H:i:s', strtotime($last_successful_sync_time)),
            'total_projects' => '-',
            'total_tokens' => '-',
            'total_sync_duration' => '-',
            'total_api_time' => '-',
            'average_response_time' => '-',
            'api_requests' => '-',
            'memory_usage' => '-',
            'response_time_class' => 'status-neutral',
            'memory_class' => 'status-neutral'
        );
    } else if (!$last_sync_metrics) {
        // No sync metrics and no last sync time - truly no sync yet
        return array(
            'last_sync_time' => 'No synchronization done yet',
            'total_projects' => '-',
            'total_tokens' => '-',
            'total_sync_duration' => '-',
            'total_api_time' => '-',
            'average_response_time' => '-',
            'api_requests' => '-',
            'memory_usage' => '-',
            'response_time_class' => 'status-neutral',
            'memory_class' => 'status-neutral'
        );
    }

    // Format the stats for display
    return array(
        'last_sync_time' => date('Y-m-d H:i:s', strtotime($last_sync_metrics['last_sync_time'])),
        'total_projects' => $last_sync_metrics['total_projects'],
        'total_tokens' => $last_sync_metrics['total_tokens'],
        'total_sync_duration' => number_format($last_sync_metrics['total_sync_duration'], 2) . 's',
        'total_api_time' => number_format($last_sync_metrics['total_api_time'], 2) . 's',
        'average_response_time' => $last_sync_metrics['average_response_time'] > 0 ? 
            ($last_sync_metrics['average_response_time'] < 1 ? 
                number_format($last_sync_metrics['average_response_time'] * 1000, 2) . 'ms' : 
                number_format($last_sync_metrics['average_response_time'], 2) . 's') : '-',
        'api_requests' => $last_sync_metrics['api_requests'],
        'memory_usage' => $last_sync_metrics['memory_usage'] . 'MB',
        'response_time_class' => nmkr_get_response_time_color_class($last_sync_metrics['average_response_time']),
        'memory_class' => nmkr_get_memory_color_class($last_sync_metrics['memory_usage'])
    );
}

/**
 * Get metrics for an active/ongoing sync operation
 * This function retrieves metrics related to the currently running sync
 * 
 * @return array|boolean An array of active sync metrics, or false if no sync is in progress
 */
function nmkr_get_active_sync_metrics() {
    // Check if sync is currently in progress
    $progress = get_transient('nmkr_sync_progress');
    $progress = ($progress !== false) ? $progress : 0;
    $error = get_transient('nmkr_sync_error');
    $error = ($error !== false) ? $error : '';
    $is_sync_in_progress = ($progress > 0 && $progress < 100 && empty($error));
    
    if (!$is_sync_in_progress) {
        return false;
    }
    
    // Get current metrics from transient - these are always live metrics
    $current_metrics = get_transient('nmkr_current_sync_stats_live');
    
    // Default metrics - ensure we always have values 
    $default_response = array(
        'progress' => ($progress !== false) ? $progress : 0,
        'average_response_time' => '0.00ms',
        'response_time_class' => 'status-excellent',
        'api_requests' => '0',
        'memory_usage' => '0.00MB',
        'memory_class' => 'status-excellent'
    );
    
    if (!$current_metrics) {
        // No active metrics available yet
        return $default_response;
    }
    
    // Format average response time
    $avg_response_time = isset($current_metrics['average_response_time']) ? $current_metrics['average_response_time'] : 0;
    $formatted_avg_response_time = $avg_response_time > 0 ? 
        ($avg_response_time < 1 ? 
            number_format($avg_response_time * 1000, 2) . 'ms' : 
            number_format($avg_response_time, 2) . 's') : '0.00ms';
    
    // Format API requests
    $api_requests = isset($current_metrics['api_requests']) ? $current_metrics['api_requests'] : 0;
    $formatted_api_requests = (string)$api_requests;
    
    // Format memory usage
    $memory_usage = isset($current_metrics['memory_usage']) ? $current_metrics['memory_usage'] : 0;
    $formatted_memory_usage = $memory_usage > 0 ? $memory_usage . 'MB' : '0.00MB';
    
    // Get color classes
    $response_time_class = nmkr_get_response_time_color_class($avg_response_time);
    $memory_class = nmkr_get_memory_color_class($memory_usage);
    
    // Return formatted and color-coded metrics
    return array(
        'progress' => ($progress !== false) ? $progress : 0,
        'average_response_time' => $formatted_avg_response_time,
        'response_time_class' => $response_time_class,
        'api_requests' => $formatted_api_requests,
        'memory_usage' => $formatted_memory_usage,
        'memory_class' => $memory_class,
        // Include raw values for debugging
        'raw_avg_time' => $avg_response_time,
        'raw_requests' => $api_requests,
        'raw_memory' => $memory_usage
    );
}

// Function to get color class based on response time
function nmkr_get_response_time_color_class($seconds) {
    if ($seconds < 0.5) {
        return 'status-excellent'; // Green
    } else if ($seconds < 1) {
        return 'status-good'; // Yellow
    } else if ($seconds < 2) {
        return 'status-warning'; // Orange
    } else {
        return 'status-critical'; // Red
    }
}

// Function to get color class based on memory usage
function nmkr_get_memory_color_class($mb) {
    if ($mb < 50) {
        return 'status-excellent'; // Green
    } else if ($mb < 100) {
        return 'status-good'; // Yellow
    } else if ($mb < 200) {
        return 'status-warning'; // Orange
    } else {
        return 'status-critical'; // Red
    }
}  