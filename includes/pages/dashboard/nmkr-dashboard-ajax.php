<?php
/**
 * Connector for NMKR Dashboard AJAX Handlers
 *
 * AJAX handlers specific to the dashboard functionality.
 *
 * @package NMKR_Connect
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Hook for checking API status
add_action('wp_ajax_nmkr_check_api_status', 'nmkr_check_api_status');

// Hook for getting sync statistics via AJAX
add_action('wp_ajax_nmkr_get_sync_statistics', 'nmkr_get_sync_statistics_ajax');

// Hook for storing active sync metrics
add_action('wp_ajax_nmkr_store_active_metrics', 'nmkr_store_active_metrics_ajax');

// Hook for clearing all logs
add_action('wp_ajax_nmkr_clear_all_logs', 'nmkr_clear_all_logs_ajax');

// Hook for clearing individual log sections
add_action('wp_ajax_nmkr_clear_section_logs', 'nmkr_clear_section_logs_ajax');

// AJAX handler for checking API connection status
function nmkr_check_api_status() {
    // Check nonce for security
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'nmkr_dashboard_nonce')) {
        wp_send_json_error(['message' => 'Invalid security token']);
        wp_die();
    }
    
    // Capability: view dashboard
    if ( ! current_user_can( 'nmkr_view_dashboard' ) ) {
        wp_send_json_error( array( 'message' => __( 'Forbidden', 'connector-for-nmkr' ) ), 403 );
        wp_die();
    }
    
    // Prevent caching of status responses
    nocache_headers();
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    
    // Get current API key and sync status
    $options = get_option('nmkr_connect_options');
    $api_key = isset($options['api_key']) ? $options['api_key'] : '';
    
    // Read canonical synchronization state without mutating lifecycle data.
    $canonical_state = nmkr_get_canonical_dashboard_sync_state();
    $sync_data = $canonical_state['sync_data'];
    $finished = $canonical_state['finished'];
    $finalization_pending = $canonical_state['finalization_pending'];
    $running = $canonical_state['running'];
    $progress = get_transient('nmkr_sync_progress');
    $progress = ($progress !== false) ? (int) $progress : 0;
    $progress_val  = $progress;
    $heartbeat_age = function_exists('nmkr_get_heartbeat_age') ? nmkr_get_heartbeat_age() : -1;
    $last_update   = (int) get_option('nmkr_last_progress_update_time', 0);
    $ttl           = defined('NMKR_SYNC_TRANSIENT_TTL') ? (int) NMKR_SYNC_TRANSIENT_TTL : HOUR_IN_SECONDS;
    $grace         = min($ttl, 300);

    // Compute recovery banner recency without mutating recovery state.
    $last_result      = get_option('nmkr_sync_last_result', '');
    $last_recovery_at = (int) get_option('nmkr_sync_last_recovery_at', 0);
    $recent_window    = 6 * HOUR_IN_SECONDS;
    $show_banner      = ( $last_recovery_at > 0 && ( time() - $last_recovery_at ) <= $recent_window );
    if ( ! $show_banner ) {
        $last_result = '';
        $last_recovery_at = 0;
    }

    if (empty($api_key)) {
        // API key is missing - log this event
        nmkr_log_api_status('Dashboard API status check: No API key set. User needs to configure API key in settings.', 'warning');
        
        // Log UI status update
        nmkr_log_ui_status('UI: Displaying "Disconnected - No API key set" message to user', 'info');
        
        // API key is missing
        wp_send_json_success([
            'message' => '⛓️‍💥 Disconnected - No API key set. Please configure your API key in the <a href="options-general.php?page=nmkr-connect-settings">Settings page</a>.',
            'connected' => false,
            'sync_in_progress' => $running,
            'finished' => $finished,
            'finalization_pending' => $finalization_pending,
            'progress' => $progress_val,
            'heartbeat_age' => $heartbeat_age,
            'last_update' => $last_update,
            'grace' => $grace,
            'last_result' => $last_result,
            'last_recovery_at' => $last_recovery_at,
        ]);
    } else if (nmkr_is_api_connected()) {
        // API connected successfully - log this event
        nmkr_log_api_status('Dashboard API status check: Connection successful and displayed to user', 'info');
        
        // Log UI status update
        nmkr_log_ui_status('UI: Displaying "Connected" status to user', 'info');
        
        wp_send_json_success([
            'message' => '✅ Connected', 
            'connected' => true,
            'sync_in_progress' => $running,
            'finished' => $finished,
            'finalization_pending' => $finalization_pending,
            'progress' => $progress_val,
            'heartbeat_age' => $heartbeat_age,
            'last_update' => $last_update,
            'grace' => $grace,
            'last_result' => $last_result,
            'last_recovery_at' => $last_recovery_at,
        ]);
    } else {
        // API connection failed - log this event
        nmkr_log_api_status('Dashboard API status check: Connection failed with valid API key. Advised user to verify credentials.', 'error');
        
        // Log UI status update
        nmkr_log_ui_status('UI: Displaying "Disconnected - Unable to establish connection" error to user', 'warning');
        
        wp_send_json_success([
            'message' => '⛓️‍💥 Disconnected - Unable to establish connection with NMKR API. Please verify your API key credentials.',
            'connected' => false,
            'sync_in_progress' => $running,
            'finished' => $finished,
            'finalization_pending' => $finalization_pending,
            'progress' => $progress_val,
            'heartbeat_age' => $heartbeat_age,
            'last_update' => $last_update,
            'grace' => $grace,
            'last_result' => $last_result,
            'last_recovery_at' => $last_recovery_at,
        ]);
    }

    wp_die();
}

// Function to return sync statistics via AJAX
function nmkr_get_sync_statistics_ajax() {
    // Check nonce for security
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'nmkr_dashboard_nonce')) {
        wp_send_json_error(['message' => 'Invalid security token']);
        wp_die();
    }
    
    // Capability: view dashboard
    if ( ! current_user_can( 'nmkr_view_dashboard' ) ) {
        wp_send_json_error( array( 'message' => __( 'Forbidden', 'connector-for-nmkr' ) ), 403 );
        wp_die();
    }
    
    // Prevent caching of statistics responses
    nocache_headers();
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    
    // Begin scoped buffer and suppress display errors to prevent stray output corrupting JSON
    $__nmkr_prev_display_errors = ini_get('display_errors');
    @ini_set('display_errors', '0');
    ob_start();
    
    $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'automatic';
    $request_type = isset($_POST['request_type']) ? sanitize_text_field($_POST['request_type']) : 'completed';
    $valid_request_types = array( 'active', 'completed' );
    if ( ! in_array( $request_type, $valid_request_types, true ) ) {
        $request_type = 'completed';
    }
    $force_refresh = isset($_POST['force_refresh']) && $_POST['force_refresh'] === 'true';
    
    // If force_refresh is true, clear any cached option values
    if ($force_refresh) {
        // Clear any cached metrics to force a fresh DB read
        wp_cache_delete('nmkr_sync_metrics', 'nmkr');
        
        if ($request_type === 'active') {
            // Preserve the live transient; it is the source of truth for active metrics.
            
            // Log UI status update
            nmkr_log_ui_status('UI: Refreshing active sync metrics display (forced refresh)', 'debug');
        }
        
        // Re-check the last_sync_time option to get fresh data (only if requesting completed metrics)
        if ($request_type === 'completed') {
            $last_sync_time = get_option('nmkr_last_sync_time', '', true); // Force a fresh read
            if (!empty($last_sync_time)) {
                update_option('nmkr_last_sync_time', $last_sync_time);
            }
            
            // Log UI status update
            nmkr_log_ui_status('UI: Refreshing completed sync statistics display (forced refresh)', 'debug');
        }
    }

    if ($request_type === 'active') {
        // Get active sync metrics (live statistics) using the enhanced function
        $performance_data = nmkr_get_sync_stats(); // This now returns all 7 metrics
        
        if ($performance_data) {
            // Format all 7 metrics for the expanded active panel
            $formatted_metrics = array(
                'progress' => (function() {
                    $progress = get_transient('nmkr_sync_progress');
                    return ($progress !== false) ? (int) $progress : 0;
                })(),
                
                // Progress metrics (newly added to active panel)
                'total_projects' => $performance_data['total_projects'] ?? 0,
                'total_tokens' => $performance_data['total_tokens'] ?? 0,
                'total_sync_duration' => $performance_data['total_duration'] ?? 0,
                
                // Performance metrics (existing, enhanced)
                'total_api_time' => $performance_data['total_api_time'] ?? 0,
                'average_response_time' => array_key_exists('average_time', $performance_data) ? $performance_data['average_time'] : null,
                'api_requests' => $performance_data['request_count'] ?? 0,
                'memory_usage' => $performance_data['memory_used'] ?? 0,
                
                // Add formatting and styling classes
                'response_time_class' => array_key_exists('average_time', $performance_data) && $performance_data['average_response_time'] !== null ? nmkr_get_response_time_color_class($performance_data['average_time']) : 'status-neutral',
                'memory_class' => nmkr_get_memory_color_class($performance_data['memory_used'] ?? 0)
            );
            
            // Log UI status update with expanded metrics info
            $log_message = sprintf(
                'UI: Displaying expanded active sync metrics - Projects: %d, Tokens: %d, Duration: %.1fs, Response time: %.2f, Memory: %.1fMB',
                $formatted_metrics['total_projects'],
                $formatted_metrics['total_tokens'],
                $formatted_metrics['total_sync_duration'],
                $formatted_metrics['average_response_time'],
                $formatted_metrics['memory_usage']
            );
            nmkr_log_ui_status($log_message, 'info');
            
            $response = $formatted_metrics;
            if (ob_get_length()) { ob_clean(); }
            if ($__nmkr_prev_display_errors !== false) { @ini_set('display_errors', $__nmkr_prev_display_errors); }
            wp_send_json_success($response);
        } else {
            // No active sync, return empty state with all 7 metrics
            nmkr_log_ui_status('UI: No active sync metrics to display, showing empty state for all 7 metrics', 'info');
            
            $response = array(
                'progress' => 0,
                // Progress metrics
                'total_projects' => 0,
                'total_tokens' => 0,
                'total_sync_duration' => 0,
                // Performance metrics
                'total_api_time' => 0,
                'average_response_time' => null,
                'api_requests' => 0,
                'memory_usage' => 0,
                // Styling classes
                'response_time_class' => 'status-neutral',
                'memory_class' => 'status-neutral'
            );
            if (ob_get_length()) { ob_clean(); }
            if ($__nmkr_prev_display_errors !== false) { @ini_set('display_errors', $__nmkr_prev_display_errors); }
            wp_send_json_success($response);
        }
    } else {
        // Get completed sync metrics (historical data only)
        $stats = nmkr_get_sync_statistics();
        
        // If this is a manual stop request, we need to get the last successful sync time
        if ($type === 'manual_stop') {
            // Get the actual last successful sync time from options
            $last_sync_time = get_option('nmkr_last_sync_time', '');
            
            if (!empty($last_sync_time)) {
                $stats['last_sync_time'] = $last_sync_time;
                
                // Log UI status update
                nmkr_log_ui_status('UI: Updating statistics after manual sync stop - Last sync: ' . $last_sync_time, 'info');
            } else {
                $stats['last_sync_time'] = 'No previous synchronization data available';
                
                // Log UI status update
                nmkr_log_ui_status('UI: No previous sync data available after manual stop', 'warning');
            }
        }
        
        // If this marks sync as completed, ensure we have the latest data
        if ($type === 'sync_completed') {
            // Force a refresh of our cache to get the most recent metrics
            wp_cache_delete('nmkr_sync_metrics', 'nmkr');
            
            // Get the most recent sync time directly from option
            $last_sync_time = get_option('nmkr_last_sync_time', '', true); // Force fresh read
            
            if (!empty($last_sync_time)) {
                // Get fresh stats with the updated sync time
                $stats = nmkr_get_sync_statistics();
                
                // Ensure UTC is added for consistency
                if (strpos($stats['last_sync_time'], 'UTC') === false) {
                    $stats['last_sync_time'] = $stats['last_sync_time'] . ' UTC';
                }
                
                // Log UI status update with completion data
                $log_message = sprintf(
                    'UI: Displaying completed sync statistics - Last sync: %s, Projects: %s, Tokens: %s',
                    $stats['last_sync_time'],
                    $stats['total_projects'],
                    $stats['total_tokens']
                );
                nmkr_log_ui_status($log_message, 'info');
            } else {
                // Log that we couldn't find sync time
                nmkr_log_ui_status('UI: Unable to find last sync time after completion', 'warning');
            }
        } else {
            // Normal statistics refresh
            // Log what's being displayed to the user
            $log_message = sprintf(
                'UI: Displaying sync statistics - Last sync: %s, Projects: %s, Tokens: %s',
                $stats['last_sync_time'],
                $stats['total_projects'],
                $stats['total_tokens']
            );
            nmkr_log_ui_status($log_message, 'debug');
        }
        
        $response = $stats;
        if (ob_get_length()) { ob_clean(); }
        if ($__nmkr_prev_display_errors !== false) { @ini_set('display_errors', $__nmkr_prev_display_errors); }
        wp_send_json_success($response);
    }
    
    wp_die();
}

/**
 * AJAX handler to store active sync metrics in a transient
 * This allows the JS to store metrics that can be retrieved by PHP functions
 */
function nmkr_store_active_metrics_ajax() {
    // Verify nonce for security
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'nmkr_dashboard_nonce')) {
        wp_send_json_error('Invalid security token');
        wp_die();
    }
    
    // Capability: manage synchronization and its dashboard-side metrics/log writes.
    if ( ! current_user_can( 'nmkr_manage_sync' ) ) {
        wp_send_json_error( array( 'message' => __( 'Forbidden', 'connector-for-nmkr' ) ), 403 );
        wp_die();
    }
    
    // Get the metrics array
    $metrics = isset($_POST['metrics']) ? $_POST['metrics'] : array();
    
    if (empty($metrics) || !is_array($metrics)) {
        wp_send_json_error('Invalid metrics data');
        wp_die();
    }
    
    // Check if this is a UI log message
    if (isset($metrics['ui_log'])) {
        $log_type = isset($_POST['log_type']) ? sanitize_text_field($_POST['log_type']) : 'info';
        nmkr_log_ui_status($metrics['ui_log'], $log_type);
        
        // If this is just a UI log message with no metrics, we can return now
        if (count($metrics) === 1) {
            wp_send_json_success(array('logged' => true));
            wp_die();
        }
    }
    
    // Get existing stats from the unified live transient
    $current_stats = get_transient('nmkr_current_sync_stats_live');
    if (!$current_stats) {
        $current_stats = array();
    }
    
    // Update only the metrics fields, preserving existing data
    if (isset($metrics['average_response_time'])) {
        $current_stats['average_response_time'] = floatval($metrics['average_response_time']);
    }
    
    if (isset($metrics['api_requests'])) {
        $current_stats['api_requests'] = (int) round($metrics['api_requests']);
    }
    
    if (isset($metrics['memory_usage'])) {
        $current_stats['memory_usage'] = floatval($metrics['memory_usage']);
    }
    
    // Update timestamp
    $current_stats['updated_at'] = time();
    
    // Save updated stats back to the unified transient
    set_transient('nmkr_current_sync_stats_live', $current_stats, NMKR_SYNC_TRANSIENT_TTL);
    
    // Log UI metrics update
    $log_message = sprintf(
        'UI: Updated active sync metrics - Response time: %.2fs, API requests: %d, Memory: %.2fMB',
        $current_stats['average_response_time'],
        $current_stats['api_requests'],
        $current_stats['memory_usage']
    );
    nmkr_log_ui_status($log_message, 'debug');
    
    // Return success with the stored data for verification
    wp_send_json_success($current_stats);
    wp_die();
}

/**
 * AJAX handler for clearing all debug logs
 */
function nmkr_clear_all_logs_ajax() {
    nocache_headers();

    // Check nonce for security
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'nmkr_clear_logs_nonce')) {
        wp_send_json_error(['message' => 'Invalid security token']);
        wp_die();
    }
    
    // Check user capabilities
    if ( ! current_user_can( 'nmkr_manage_sync' ) ) {
        wp_send_json_error( array( 'message' => __( 'Forbidden', 'connector-for-nmkr' ) ), 403 );
        wp_die();
    }
    
    // Check if sync is in progress
    $sync_in_progress = get_option('nmkr_sync_in_progress', false);
    if ($sync_in_progress) {
        wp_send_json_error(['message' => 'Logs cannot be cleared while sync is in progress']);
        wp_die();
    }
    
    try {
        // Clear all log types
        update_option('nmkr_sync_logs', array(), false);
        update_option('nmkr_api_logs', array(), false);
        update_option('nmkr_ui_logs', array(), false);
        update_option('nmkr_performance_logs', array(), false);

        // Intentionally do not write a dashboard log entry here; doing so would recreate a visible log immediately after clearing.
        
        wp_send_json_success(['message' => 'All logs cleared successfully']);
    } catch (Exception $e) {
        error_log('NMKR log-clear operation failed: ' . $e->getMessage());
        wp_send_json_error(['message' => __('Logs could not be cleared.', 'connector-for-nmkr'), 'error_code' => 'log_clear_failed']);
    }
    
    wp_die();
}

/**
 * AJAX handler for clearing individual log sections
 */
function nmkr_clear_section_logs_ajax() {
    nocache_headers();

    // Check nonce for security
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'nmkr_clear_logs_nonce')) {
        wp_send_json_error(['message' => 'Invalid security token']);
        wp_die();
    }
    
    // Check user capabilities
    if ( ! current_user_can( 'nmkr_manage_sync' ) ) {
        wp_send_json_error( array( 'message' => __( 'Forbidden', 'connector-for-nmkr' ) ), 403 );
        wp_die();
    }
    
    // Check if sync is in progress
    $sync_in_progress = get_option('nmkr_sync_in_progress', false);
    if ($sync_in_progress) {
        wp_send_json_error(['message' => 'Logs cannot be cleared while sync is in progress']);
        wp_die();
    }
    
    // Get and validate log type
    $log_type = isset($_POST['log_type']) ? sanitize_text_field($_POST['log_type']) : '';
    $valid_types = ['sync', 'api', 'ui', 'performance'];
    
    if (!in_array($log_type, $valid_types)) {
        wp_send_json_error(['message' => 'Invalid log type specified']);
        wp_die();
    }
    
    try {
        // Clear the specific log type
        $option_name = 'nmkr_' . $log_type . '_logs';
        update_option($option_name, array(), false);

        // Intentionally do not write a dashboard log entry here; doing so would recreate a visible log immediately after clearing.
        
        wp_send_json_success(['message' => ucfirst($log_type) . ' logs cleared successfully', 'log_type' => $log_type]);
    } catch (Exception $e) {
        error_log('NMKR section log-clear operation failed: ' . $e->getMessage());
        wp_send_json_error(['message' => __('The selected logs could not be cleared.', 'connector-for-nmkr'), 'error_code' => 'section_log_clear_failed']);
    }
    
    wp_die();
}
