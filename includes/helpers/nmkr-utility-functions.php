<?php
/**
 * NMKR Connect Utility Functions
 *
 * @package NMKR_Connect
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define sleep time for progress updates (in microseconds)
if (!defined('NMKR_SYNC_SLEEP_TIME')) {
    define('NMKR_SYNC_SLEEP_TIME', 50000); // 50ms default
}

/**
 * Get a timestamp in the website's timezone
 *
 * This function standardizes timestamp creation across the plugin to ensure
 * all timestamps are in the website's configured timezone.
 *
 * @param string $format The format to return: 'mysql' for MySQL datetime format (Y-m-d H:i:s), 
 *                      'unix' for UNIX timestamp
 * @return string|int MySQL formatted date string or UNIX timestamp
 */
function nmkr_get_timestamp($format = 'mysql') {
    if ($format === 'unix') {
        // Get Unix timestamp in site's timezone
        return current_time('timestamp', 0);
    } else {
        // Get MySQL format timestamp in site's timezone
        return current_time('mysql', 0);
    }
}

// Helper function to generate hash for token data
function nmkr_generate_token_hash($token_data) {
    if (!is_array($token_data) || empty($token_data)) {
        return '';
    }
    
    // Sort by keys to ensure consistent hashing
    ksort($token_data);
    
    // Flatten the array recursively
    $serialized = json_encode($token_data);
    
    // Generate hash
    return md5($serialized);
}

// Helper function to generate hash for project data
function nmkr_generate_project_hash($project_data) {
    if (!is_array($project_data) || empty($project_data)) {
        return '';
    }
    
    // Sort by keys to ensure consistent hashing
    ksort($project_data);
    
    // Flatten the array recursively
    $serialized = json_encode($project_data);
    
    // Generate hash
    return md5($serialized);
}

// Helper function to generate hash for token details data
function nmkr_generate_token_details_hash($token_details) {
    if (!is_array($token_details) || empty($token_details)) {
        return '';
    }
    
    // Sort by keys to ensure consistent hashing
    ksort($token_details);
    
    // Flatten the array recursively
    $serialized = json_encode($token_details);
    
    // Generate hash
    return md5($serialized);
}

/**
 * Generate a correlation ID for tracking synchronization processes
 *
 * @return string Unique correlation ID
 */
function nmkr_generate_correlation_id() {
    return uniqid('sync_', true);
}

// Logging toggle helpers
function nmkr_should_log_sync() {
    $options = get_option('nmkr_connect_options');
    return !empty($options['debug_enabled']) && !empty($options['sync_debug_enabled']);
}

function nmkr_should_log_api() {
    $options = get_option('nmkr_connect_options');
    return !empty($options['debug_enabled']) && !empty($options['api_debug_enabled']);
}

function nmkr_should_log_ui() {
    $options = get_option('nmkr_connect_options');
    return !empty($options['debug_enabled']) && !empty($options['ui_debug_enabled']);
}

function nmkr_should_log_performance() {
    $options = get_option('nmkr_connect_options');
    return !empty($options['debug_enabled']) && !empty($options['performance_debug_enabled']);
}

function nmkr_should_throttle_logs() {
    $options = get_option('nmkr_connect_options');
    return isset($options['debug_enabled'], $options['log_throttle_enabled'])
        && $options['debug_enabled'] && $options['log_throttle_enabled'];
}

function nmkr_should_log_to($destination) {
    $options = get_option('nmkr_connect_options');
    return match ($destination) {
        'file' => !empty($options['log_to_debug_file']),
        'dashboard' => !empty($options['log_to_dashboard']),
        default => false
    };
}

/**
 * Get the log retention limit for database logs
 * 
 * @return int Number of log entries to retain (1-1000, default 100)
 */
function nmkr_get_log_retention_limit() {
    $options = get_option('nmkr_connect_options');
    $limit = isset($options['log_retention_limit']) ? $options['log_retention_limit'] : 100;
    $limit = intval($limit);
    if ($limit < 1) {
        $limit = 1;
    } elseif ($limit > 1000) {
        $limit = 1000;
    }
    return $limit;
}

/**
 * Trim dashboard log options to the configured retention limit.
 *
 * @param int|null $limit Optional retention limit. Defaults to saved setting.
 * @return array Summary of before/after counts for each dashboard log option.
 */
function nmkr_trim_dashboard_logs_to_retention($limit = null) {
    $limit = null === $limit ? nmkr_get_log_retention_limit() : intval($limit);
    if ($limit < 1) {
        $limit = 1;
    } elseif ($limit > 1000) {
        $limit = 1000;
    }

    $log_options = array(
        'nmkr_sync_logs',
        'nmkr_api_logs',
        'nmkr_ui_logs',
        'nmkr_performance_logs',
    );
    $summary = array();

    foreach ($log_options as $option_name) {
        $logs = get_option($option_name, array());
        if (!is_array($logs)) {
            update_option($option_name, array(), false);

            $summary[$option_name] = array(
                'before' => 0,
                'after' => 0,
                'repaired' => true,
            );
            continue;
        }

        $before = count($logs);
        $trimmed_logs = array_slice($logs, 0, $limit);
        $after = count($trimmed_logs);

        if ($after !== $before) {
            update_option($option_name, $trimmed_logs, false);
        }

        $summary[$option_name] = array(
            'before' => $before,
            'after' => $after,
            'repaired' => false,
        );
    }

    return $summary;
}

// Consolidated write_log function for all logging functions
if (!function_exists('write_log')) {
    /**
     * Write to WordPress debug.log if WP_DEBUG_LOG is enabled
     */
    function write_log($message) {
        // Respect plugin logging master switches to avoid unintended writes to debug.log
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

/**
 * Validate and sanitize log data to prevent oversized entries
 * 
 * @param mixed $data The data to validate
 * @param string $message The message to validate
 * @return array Array with sanitized 'data' and 'message'
 */
function nmkr_validate_log_data($data, $message) {
    // Validate message length (max 1000 characters)
    if (strlen($message) > 1000) {
        $message = substr($message, 0, 997) . '...';
    }
    
    // Validate data size
    if (!empty($data)) {
        if (is_array($data) || is_object($data)) {
            $json_data = json_encode($data);
            if (strlen($json_data) > 5000) {
                $data = ['warning' => 'Log data too large, truncated'];
            }
        } elseif (is_string($data) && strlen($data) > 5000) {
            $data = ['warning' => 'Log data too large, truncated'];
        }
    }
    
    return [
        'data' => $data,
        'message' => $message
    ];
}

// Function to log sync status updates to WordPress DEBUG.log instead of custom log
function nmkr_log_data_sync($message, $type = 'info', $data = array()) {
    // Gate logging using centralized helper function
    if (!nmkr_should_log_sync()) {
        return;
    }
    
    // Validate and sanitize log data
    $validated = nmkr_validate_log_data($data, $message);
    $message = $validated['message'];
    $data = $validated['data'];
    
    // Format timestamp in WordPress style
    $timestamp = current_time('mysql');
    
    // Log to debug.log file if enabled
    if (nmkr_should_log_to('file')) {
        // Create a clean, simple log entry
        write_log("[NMKR Connect Sync] " . $message);
        
        // For errors and warnings, log the data separately
        if (($type === 'error' || $type === 'warning') && !empty($data)) {
            write_log("[NMKR Connect Sync] Error details: " . print_r($data, true));
        }
    }
    
    // Log to dashboard (database) if enabled
    if (nmkr_should_log_to('dashboard')) {
        $sync_stats_id = null;
        $sync_data = get_option('nmkr_sync_data', array());
        if (is_array($sync_data) && isset($sync_data['sync_stats_id'])) {
            $sync_stats_id = intval($sync_data['sync_stats_id']);
        }

        // Store in WordPress options for recent logs
        $sync_logs = get_option('nmkr_sync_logs', array());
        if (!is_array($sync_logs)) {
            $sync_logs = array();
        }
        $log_entry = [
            'timestamp' => $timestamp,
            'type' => $type,
            'message' => $message,
            'data' => $data,
            'category' => 'sync'
        ];
        if ($sync_stats_id) {
            $log_entry['sync_stats_id'] = $sync_stats_id;
        }
        array_unshift($sync_logs, $log_entry);
        $sync_logs = array_slice($sync_logs, 0, nmkr_get_log_retention_limit()); // Keep last N logs based on setting
        update_option('nmkr_sync_logs', $sync_logs, false); // Set autoload=false to prevent performance issues
    }
}

/**
 * Log API connection status
 * 
 * @param string $message The message to log
 * @param string $type The type of log entry (info, warning, error, debug)
 * @param array $data Additional data to log
 * @return void
 */
function nmkr_log_api_status($message, $type = 'info', $data = array()) {
    // Gate logging using centralized helper function
    if (!nmkr_should_log_api()) {
        return;
    }
    
    // Validate and sanitize log data
    $validated = nmkr_validate_log_data($data, $message);
    $message = $validated['message'];
    $data = $validated['data'];
    
    // Format timestamp in WordPress style
    $timestamp = current_time('mysql');
    
    // Log to debug.log file if enabled
    if (nmkr_should_log_to('file')) {
        // Create a clean, simple log entry
        write_log("[NMKR Connect API] " . $message);
        
        // For errors and warnings, log the data separately
        if (($type === 'error' || $type === 'warning') && !empty($data)) {
            write_log("[NMKR Connect API] Error details: " . print_r($data, true));
        }
    }
    
    // Log to dashboard (database) if enabled
    if (nmkr_should_log_to('dashboard')) {
        // Store in WordPress options for recent logs
        $api_logs = get_option('nmkr_api_logs', array());
        if (!is_array($api_logs)) {
            $api_logs = array();
        }
        array_unshift($api_logs, [
            'timestamp' => $timestamp,
            'type' => $type,
            'message' => $message,
            'data' => $data,
            'category' => 'api'
        ]);
        $api_logs = array_slice($api_logs, 0, nmkr_get_log_retention_limit()); // Keep last N logs based on setting
        update_option('nmkr_api_logs', $api_logs, false); // Set autoload=false to prevent performance issues
    }
}

/**
 * Log User Interface status events and interactions
 * 
 * @param string $message The message to log
 * @param string $type The type of log entry (info, warning, error, debug)
 * @param array $data Additional data to log
 * @return void
 */
function nmkr_log_ui_status($message, $type = 'info', $data = array()) {
    // Gate logging using centralized helper function
    if (!nmkr_should_log_ui()) {
        return;
    }
    
    // Validate and sanitize log data
    $validated = nmkr_validate_log_data($data, $message);
    $message = $validated['message'];
    $data = $validated['data'];
    
    // Format timestamp in WordPress style
    $timestamp = current_time('mysql');
    
    // Log to debug.log file if enabled
    if (nmkr_should_log_to('file')) {
        // Create a clean, simple log entry
        write_log("[NMKR Connect UI Status] " . $message);
        
        // For errors and warnings, log the data separately
        if (($type === 'error' || $type === 'warning') && !empty($data)) {
            write_log("[NMKR Connect UI Status] Error details: " . print_r($data, true));
        }
    }
    
    // Log to dashboard (database) if enabled
    if (nmkr_should_log_to('dashboard')) {
        // Store in WordPress options for recent logs
        $ui_logs = get_option('nmkr_ui_logs', array());
        if (!is_array($ui_logs)) {
            $ui_logs = array();
        }
        array_unshift($ui_logs, [
            'timestamp' => $timestamp,
            'type' => $type,
            'message' => $message,
            'data' => $data,
            'category' => 'ui'
        ]);
        $ui_logs = array_slice($ui_logs, 0, nmkr_get_log_retention_limit()); // Keep last N logs based on setting
        update_option('nmkr_ui_logs', $ui_logs, false); // Set autoload=false to prevent performance issues
    }
}

// Function to manage sync data in a unified way
function nmkr_get_sync_data() {
    return get_option('nmkr_sync_data', array());
}

// Function to save sync data in a unified way
function nmkr_save_sync_data($data) {
    return update_option('nmkr_sync_data', $data);
}

// Function to clear sync data
function nmkr_clear_sync_data() {
    return delete_option('nmkr_sync_data');
}

/**
 * Update sync heartbeat to indicate backend activity
 * This prevents false-positive stall detection warnings during sync
 */
function nmkr_update_sync_heartbeat() {
    update_option('nmkr_sync_heartbeat', time());
}

/**
 * Get time since last heartbeat
 * @return int Seconds since last heartbeat, or -1 if no heartbeat exists
 */
function nmkr_get_heartbeat_age() {
    $heartbeat = get_option('nmkr_sync_heartbeat', 0);
    if ($heartbeat === 0) {
        return -1;
    }
    return time() - $heartbeat;
}

/**
 * Clean up sync heartbeat when sync ends
 */
function nmkr_cleanup_sync_heartbeat() {
    delete_option('nmkr_sync_heartbeat');
}

/**
 * Detect and recover a stale sync state (idempotent).
 * Returns: ['stale'=>bool,'recovered'=>bool,'grace'=>int,'heartbeat_age'=>int,'last_update'=>int]
 * NOTE: Callers must enforce caps when invoking from admin flows.
 */
function nmkr_detect_and_recover_stale_sync() {
    // Reentrancy guard (60s)
    if ( get_transient('nmkr_stale_recovery_running') ) {
        return array('stale'=>false,'recovered'=>false,'grace'=>0,'heartbeat_age'=>-1,'last_update'=>0);
    }
    set_transient('nmkr_stale_recovery_running', true, 60);

    $in_progress   = (bool) get_option('nmkr_sync_in_progress', false);
    $heartbeat_age = function_exists('nmkr_get_heartbeat_age') ? nmkr_get_heartbeat_age() : -1;
    $last_update   = (int) get_option('nmkr_last_progress_update_time', 0);

    $ttl   = defined('NMKR_SYNC_TRANSIENT_TTL') ? (int) NMKR_SYNC_TRANSIENT_TTL : HOUR_IN_SECONDS;
    $grace = min($ttl, 300);

    $stale_heartbeat = ($heartbeat_age < 0 || $heartbeat_age >= $grace);
    $stale_progress  = ($last_update <= 0 || ( time() - $last_update ) >= $grace);
    $is_stale        = ( $in_progress && ( $stale_heartbeat || $stale_progress ) );

    if ( ! $is_stale ) {
        return array('stale'=>false,'recovered'=>false,'grace'=>$grace,'heartbeat_age'=>$heartbeat_age,'last_update'=>$last_update);
    }

    // Clear only sync-state; keep user settings
    update_option('nmkr_sync_in_progress', false);

    // Options
    delete_option('nmkr_sync_error');
    delete_option('nmkr_sync_near_completion');
    delete_option('nmkr_last_progress_update_time');
    delete_option('nmkr_last_progress_value');
    delete_option('nmkr_sync_heartbeat');

    // Transients
    delete_transient('nmkr_sync_progress');
    delete_transient('nmkr_sync_current_item');
    delete_transient('nmkr_sync_current_count');
    delete_transient('nmkr_sync_total_items');
    delete_transient('nmkr_current_sync_stats_live');
    delete_transient('nmkr_current_sync_stats_summary');
    delete_transient('nmkr_sync_user_stopped');

    // Info markers
    update_option('nmkr_sync_last_result', 'recovered_stale');
    update_option('nmkr_sync_last_recovery_at', time());

    if ( function_exists('nmkr_log_ui_status') ) {
        $iso = gmdate('c', $last_update > 0 ? $last_update : time());
        $age = ( $last_update > 0 ) ? ( time() - $last_update ) : -1;
        nmkr_log_ui_status(
            sprintf(
                __('[NMKR Connect Recovery] Stale sync detected (last_update=%1$s, age=%2$d sec). State cleared; UI set to idle.', 'nmkr-connect'),
                $iso,
                (int) $age
            ),
            'info'
        );
    }

    return array('stale'=>true,'recovered'=>true,'grace'=>$grace,'heartbeat_age'=>$heartbeat_age,'last_update'=>$last_update);
}

// --- Safe PID helper -----------------------------------------------
if ( ! function_exists( 'nmkr_safe_getpid' ) ) {
    /**
     * Return a safe integer PID on hosts where getmypid() may be disabled.
     *
     * @return int
     */
    function nmkr_safe_getpid() {
        try {
            if ( function_exists( 'getmypid' ) ) {
                $pid = @getmypid();
                if ( is_int( $pid ) && $pid > 0 ) {
                    return $pid;
                }
            }
        } catch ( Throwable $e ) {
            // no-op
        }
        // fallback: non-sensitive pseudo pid
        return (int) wp_rand( 1000, 999999 );
    }
}

/**
 * Render standardized price badges for tokens
 * 
 * @param object $token Token object with price and price_solana properties
 * @param bool $wrap Whether to wrap badges in a container div (default: true)
 * @return string HTML markup for price badges, or empty string if no prices
 */
function nmkr_render_token_price_badges($token, $wrap = true) {
    $prices = array();
    
    // Check for ADA price (price field, divided by 1e6)
    if (!empty($token->price) && $token->price > 0) {
        $ada_price = $token->price / 1000000;
        $prices[] = sprintf(
            '<span class="nmkr-price-badge nmkr-price-ada">%s ADA</span>',
            number_format_i18n($ada_price, 2)
        );
    }
    
    // Check for SOL price (price_solana field, divided by 1e9)
    if (!empty($token->price_solana) && $token->price_solana > 0) {
        $sol_price = $token->price_solana / 1000000000;
        $prices[] = sprintf(
            '<span class="nmkr-price-badge nmkr-price-sol">%s SOL</span>',
            number_format_i18n($sol_price, 4)
        );
    }
    
    // Return empty string if no prices
    if (empty($prices)) {
        return '';
    }
    
    // Return badges with or without wrapper
    if ($wrap) {
        return sprintf(
            '<div class="nmkr-token-price">%s</div>',
            implode(' ', $prices)
        );
    } else {
        return implode(' ', $prices);
    }
}
