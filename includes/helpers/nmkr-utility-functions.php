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

    switch ($destination) {
        case 'file':
            return !empty($options['log_to_debug_file']);

        case 'dashboard':
            return !empty($options['log_to_dashboard']);

        default:
            return false;
    }
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
 * Return the site-scoped synchronization owner.
 *
 * The owner deliberately has no expiry. An interrupted worker therefore fails
 * closed until an ownership-aware recovery flow is implemented.
 */
function nmkr_get_sync_owner() {
    $owner = get_option('nmkr_sync_owner', false);
    return is_array($owner) ? $owner : false;
}

/** Bypass a request-local stale option/notoptions entry inside the DB lock. */
function nmkr_refresh_sync_owner_cache() {
    if (function_exists('wp_cache_delete')) {
        wp_cache_delete('nmkr_sync_owner', 'options');
        wp_cache_delete('notoptions', 'options');
    }
}

/** Read one site option directly from authoritative storage. */
function nmkr_get_uncached_option_value($option_name, $default = false) {
    global $wpdb;
    $serialized = $wpdb->get_var($wpdb->prepare(
        "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
        (string) $option_name
    ));
    return $serialized === null ? $default : maybe_unserialize($serialized);
}

function nmkr_is_valid_sync_run_id($run_id) {
    return is_string($run_id) && (bool) preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', $run_id);
}

function nmkr_sync_owner_lock_name($options_identity = null, $blog_id = null, $database_identity = null) {
    global $wpdb;
    $database = $database_identity !== null ? (string) $database_identity : (defined('DB_NAME') ? (string) DB_NAME : '');
    $options = $options_identity !== null ? (string) $options_identity : (string) $wpdb->options;
    $site_id = $blog_id !== null ? (int) $blog_id : (int) get_current_blog_id();
    $identity = $database . '|' . $options . '|' . $site_id;
    return 'nmkr_owner_' . substr(hash('sha256', $identity), 0, 40);
}

/** Execute an owner mutation while holding the per-site MySQL advisory lock. */
function nmkr_with_sync_owner_lock($callback) {
    global $wpdb;
    $lock_name = nmkr_sync_owner_lock_name();
    $acquired = (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', $lock_name, 5));
    if ($acquired !== 1) {
        return new WP_Error('sync_owner_lock_unavailable', __('Synchronization ownership is temporarily unavailable.', 'nmkr-connect'));
    }
    try {
        // All admission inspection and mutation happens on this DB connection
        // inside this lock; option caching is invalidated by the WP option API.
        return call_user_func($callback);
    } finally {
        $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
    }
}

/** Execute legacy recovery mutations only inside an atomically ownerless window. */
function nmkr_with_ownerless_legacy_recovery($callback) {
    return nmkr_with_sync_owner_lock(function () use ($callback) {
        nmkr_refresh_sync_owner_cache();
        if (get_option('nmkr_sync_owner', false) !== false) {
            return array('owner_preserved' => true);
        }
        return call_user_func($callback);
    });
}

/** Re-read and maintain one exact finalizing owner while serialization is held. */
function nmkr_maintain_exact_finalizing_owner($expected_run_id, $expected_sync_stats_id) {
    return nmkr_with_sync_owner_lock(function () use ($expected_run_id, $expected_sync_stats_id) {
        $owner = nmkr_get_uncached_option_value('nmkr_sync_owner', false);
        $sync_data = nmkr_get_uncached_option_value('nmkr_sync_data', array());
        $run_id = is_array($sync_data) ? (string) ($sync_data['run_id'] ?? '') : '';
        $sync_stats_id = is_array($sync_data) ? (int) ($sync_data['sync_stats_id'] ?? 0) : 0;

        if ($owner === false) {
            $terminal = $run_id === (string) $expected_run_id
                && $sync_stats_id === (int) $expected_sync_stats_id
                && function_exists('nmkr_is_sync_terminal_status')
                && nmkr_is_sync_terminal_status($sync_data['status'] ?? '');
            $verified = $terminal && function_exists('nmkr_verify_sync_terminal_result')
                && nmkr_verify_sync_terminal_result($sync_data, true);
            return array('owner_preserved' => true, 'concurrent_terminalization' => $verified, 'terminal_observed' => $terminal);
        }
        if (!is_array($owner) || ($owner['mode'] ?? '') !== 'direct' || ($owner['state'] ?? '') !== 'finalizing'
            || (string) ($owner['run_id'] ?? '') !== (string) $expected_run_id
            || (int) ($owner['sync_stats_id'] ?? 0) !== (int) $expected_sync_stats_id
            || $run_id !== (string) $expected_run_id || $sync_stats_id !== (int) $expected_sync_stats_id
            || ($sync_data['status'] ?? '') !== 'finalizing') {
            return array('owner_preserved' => true, 'stale_observer' => true);
        }
        $resume = nmkr_get_uncached_option_value(nmkr_sync_finalization_resume_key($sync_stats_id), false);
        $scheduled = nmkr_maintain_sync_finalization_resume($sync_data, true, $resume);
        return array('owner_preserved' => true, 'finalization_scheduled' => $scheduled);
    });
}

/** Atomically admit a single direct run. */
function nmkr_admit_sync_owner($run_id) {
    if (!nmkr_is_valid_sync_run_id($run_id)) {
        return new WP_Error('invalid_run_id', __('Invalid synchronization run identifier.', 'nmkr-connect'));
    }
    return nmkr_with_sync_owner_lock(function () use ($run_id) {
        nmkr_refresh_sync_owner_cache();
        if (function_exists('nmkr_sync_start_blocked_by_finalization')
            && nmkr_sync_start_blocked_by_finalization(nmkr_get_sync_data())) {
            return new WP_Error('sync_finalization_pending', __('Synchronization finalization is still pending.', 'nmkr-connect'));
        }
        if (nmkr_get_sync_owner() !== false) {
            return new WP_Error('sync_already_owned', __('A synchronization is already queued or running.', 'nmkr-connect'));
        }
        $now = gmdate('c');
        $owner = array('run_id' => $run_id, 'mode' => 'direct', 'state' => 'queued', 'sync_stats_id' => 0, 'created_at' => $now, 'updated_at' => $now, 'heartbeat_at' => $now, 'checkpoint_seq' => 0, 'safe_phase' => 'queued');
        if (!add_option('nmkr_sync_owner', $owner, '', 'no')) {
            return new WP_Error('sync_already_owned', __('A synchronization is already queued or running.', 'nmkr-connect'));
        }
        return $owner;
    });
}

/** Atomically compare and transition the exact owner. */
function nmkr_transition_sync_owner($run_id, $from_state, $to_state, $sync_stats_id = null) {
    return nmkr_with_sync_owner_lock(function () use ($run_id, $from_state, $to_state, $sync_stats_id) {
        nmkr_refresh_sync_owner_cache();
        $owner = nmkr_get_sync_owner();
        if (!$owner || !hash_equals((string) ($owner['run_id'] ?? ''), (string) $run_id)
            || ($owner['mode'] ?? '') !== 'direct' || ($owner['state'] ?? '') !== $from_state) {
            return false;
        }
        if ($sync_stats_id !== null) {
            $existing_id = (int) ($owner['sync_stats_id'] ?? 0);
            if ($existing_id && $existing_id !== (int) $sync_stats_id) {
                return false;
            }
            $owner['sync_stats_id'] = (int) $sync_stats_id;
        }
        // A Stop that wins the binding race must never be overwritten back to running.
        if (($owner['state'] ?? '') === 'stop_requested' && $from_state === 'running' && $to_state === 'running') {
            $to_state = 'stop_requested';
        }
        $owner['state'] = $to_state;
        $owner['updated_at'] = gmdate('c');
        $owner['heartbeat_at'] = $owner['updated_at'];
        update_option('nmkr_sync_owner', $owner, false);
        return nmkr_get_sync_owner() === $owner ? $owner : false;
    });
}

/** Request cooperative termination of one exact direct run. */
function nmkr_request_exact_sync_stop($run_id, $reason = 'user_requested') {
    if (!nmkr_is_valid_sync_run_id($run_id)) return new WP_Error('invalid_run_id', __('Invalid synchronization run identifier.', 'nmkr-connect'));
    return nmkr_with_sync_owner_lock(function () use ($run_id, $reason) {
        $owner = nmkr_get_uncached_option_value('nmkr_sync_owner', false);
        if (!is_array($owner) || !hash_equals((string) ($owner['run_id'] ?? ''), $run_id) || ($owner['mode'] ?? '') !== 'direct') return new WP_Error('sync_owner_mismatch', __('Synchronization ownership no longer matches this run.', 'nmkr-connect'));
        if (($owner['state'] ?? '') === 'finalizing') return new WP_Error('sync_finalization_pending', __('Synchronization finalization is still pending.', 'nmkr-connect'));
        if (($owner['state'] ?? '') === 'queued' && (int) ($owner['sync_stats_id'] ?? -1) === 0) return nmkr_cancel_exact_queued_sync_owner_locked($run_id, 'cancelled');
        if (!in_array(($owner['state'] ?? ''), array('running', 'stop_requested'), true)) return new WP_Error('sync_owner_state_invalid', __('Synchronization cannot be stopped in its current state.', 'nmkr-connect'));
        $requested_at = $owner['stop_requested_at'] ?? gmdate('c');
        $owner['state'] = 'stop_requested';
        $owner['stop_requested_at'] = $requested_at;
        $owner['stop_reason'] = sanitize_text_field($reason);
        $owner['updated_at'] = gmdate('c');
        // WordPress returns false for an unchanged option too; readback is authoritative.
        update_option('nmkr_sync_owner', $owner, false);
        $verified = nmkr_get_uncached_option_value('nmkr_sync_owner', false);
        if (!is_array($verified) || !hash_equals((string) ($verified['run_id'] ?? ''), $run_id)
            || ($verified['mode'] ?? '') !== 'direct' || (int) ($verified['sync_stats_id'] ?? -1) !== (int) ($owner['sync_stats_id'] ?? -1)
            || ($verified['state'] ?? '') !== 'stop_requested' || ($verified['stop_requested_at'] ?? '') !== $requested_at
            || ($verified['stop_reason'] ?? '') !== $owner['stop_reason']) return new WP_Error('sync_stop_readback_failed', __('Synchronization stop could not be verified.', 'nmkr-connect'));
        return $verified;
    });
}

/** Authoritative worker fence. Call only at safe boundaries. */
function nmkr_sync_run_checkpoint($run_id, $sync_stats_id = null, $phase = '') {
    $result = nmkr_with_sync_owner_lock(function () use ($run_id, $sync_stats_id, $phase) {
        $owner = nmkr_get_uncached_option_value('nmkr_sync_owner', false);
        if (!is_array($owner) || !hash_equals((string) ($owner['run_id'] ?? ''), (string) $run_id)
            || ($owner['mode'] ?? '') !== 'direct'
            || ($sync_stats_id !== null && (int) ($owner['sync_stats_id'] ?? 0) !== (int) $sync_stats_id)) return 'owner_mismatch';
        if (($owner['state'] ?? '') === 'stop_requested') return 'stop_requested';
        if (($owner['state'] ?? '') === 'finalizing') return 'finalizing';
        if (($owner['state'] ?? '') !== 'running') return 'owner_mismatch';
        $now = gmdate('c');
        $owner['heartbeat_at'] = $now;
        $owner['updated_at'] = $now;
        $owner['checkpoint_seq'] = (int) ($owner['checkpoint_seq'] ?? 0) + 1;
        if ($phase !== '') $owner['safe_phase'] = sanitize_key($phase);
        $updated = update_option('nmkr_sync_owner', $owner, false);
        $verified = nmkr_get_uncached_option_value('nmkr_sync_owner', false);
        if ($verified !== $owner) return 'checkpoint_persistence_failed';
        // An unchanged write is valid only when the authoritative record proves it.
        if ($updated === false && ((int) ($verified['checkpoint_seq'] ?? -1) !== (int) $owner['checkpoint_seq']
            || ($verified['heartbeat_at'] ?? '') !== $owner['heartbeat_at']
            || ($verified['safe_phase'] ?? '') !== ($owner['safe_phase'] ?? ''))) return 'checkpoint_persistence_failed';
        return 'continue';
    });
    return is_wp_error($result) ? 'checkpoint_lock_failed' : $result;
}

/** Bind an inserted direct-run history row without losing a racing Stop request. */
function nmkr_bind_exact_sync_history_owner($run_id, $sync_stats_id) {
    global $wpdb;
    $sync_stats_id = (int) $sync_stats_id;
    if (!nmkr_is_valid_sync_run_id($run_id) || $sync_stats_id <= 0) return new WP_Error('sync_history_binding_invalid_input', __('Invalid synchronization history binding.', 'nmkr-connect'));
    $result = nmkr_with_sync_owner_lock(function () use ($run_id, $sync_stats_id, $wpdb) {
        $owner = nmkr_get_uncached_option_value('nmkr_sync_owner', false);
        if (!is_array($owner) || !hash_equals((string) ($owner['run_id'] ?? ''), $run_id) || ($owner['mode'] ?? '') !== 'direct') return new WP_Error('sync_history_binding_owner_mismatch', __('Synchronization ownership no longer matches this run.', 'nmkr-connect'));
        if ((int) ($owner['sync_stats_id'] ?? -1) !== 0 || !in_array(($owner['state'] ?? ''), array('running', 'stop_requested'), true)) return new WP_Error('sync_history_binding_owner_state_invalid', __('Synchronization ownership is not bindable.', 'nmkr-connect'));
        $history = $wpdb->get_row($wpdb->prepare("SELECT id, run_id FROM {$wpdb->prefix}nmkr_sync_stats WHERE id = %d", $sync_stats_id), ARRAY_A);
        if (!is_array($history) || (int) ($history['id'] ?? 0) !== $sync_stats_id) return new WP_Error('sync_history_binding_history_missing', __('Synchronization history is missing.', 'nmkr-connect'));
        if (!hash_equals((string) ($history['run_id'] ?? ''), $run_id)) return new WP_Error('sync_history_binding_history_run_mismatch', __('Synchronization history belongs to another run.', 'nmkr-connect'));
        $owner['sync_stats_id'] = $sync_stats_id; $owner['updated_at'] = gmdate('c');
        if (!update_option('nmkr_sync_owner', $owner, false)) return new WP_Error('sync_history_binding_update_failed', __('Synchronization history binding could not be persisted.', 'nmkr-connect'));
        $verified = nmkr_get_uncached_option_value('nmkr_sync_owner', false);
        if ($verified !== $owner) return new WP_Error('sync_history_binding_readback_failed', __('Synchronization history binding could not be verified.', 'nmkr-connect'));
        return $verified;
    });
    return is_wp_error($result) && $result->get_error_code() === 'sync_owner_lock_unavailable' ? new WP_Error('sync_history_binding_lock_unavailable', $result->get_error_message()) : $result;
}

function nmkr_sync_owner_matches($run_id, $state = null, $sync_stats_id = null) {
    $owner = nmkr_get_sync_owner();
    return is_array($owner) && nmkr_is_valid_sync_run_id($run_id)
        && hash_equals((string) ($owner['run_id'] ?? ''), (string) $run_id)
        && ($owner['mode'] ?? '') === 'direct'
        && ($state === null || ($owner['state'] ?? '') === $state)
        && ($sync_stats_id === null || (int) ($owner['sync_stats_id'] ?? 0) === (int) $sync_stats_id);
}

/** Release only the exact owner; old callbacks can never remove a successor. */
function nmkr_release_sync_owner($run_id, $sync_stats_id = null, $state = null) {
    return nmkr_with_sync_owner_lock(function () use ($run_id, $sync_stats_id, $state) {
        nmkr_refresh_sync_owner_cache();
        if (!nmkr_sync_owner_matches($run_id, $state, $sync_stats_id)) {
            return false;
        }
        delete_option('nmkr_sync_owner');
        return nmkr_get_sync_owner() === false;
    });
}

/** Owner transitions are successful only when the updated record is returned. */
function nmkr_sync_owner_transition_succeeded($result) {
    return is_array($result) && !empty($result['run_id']) && !empty($result['mode']) && !empty($result['state']);
}

/**
 * Clear provisional direct-run state and release only the exact owner.
 * A lock/release failure intentionally leaves the owner blocking admission,
 * while dashboard active markers are still made explicitly false.
 */
function nmkr_cleanup_failed_direct_sync($run_id, $sync_stats_id, $error_message) {
    $sync_stats_id = (int) $sync_stats_id;
    if (!nmkr_sync_owner_matches($run_id, 'running', $sync_stats_id)) {
        return false;
    }
    update_option('nmkr_sync_error', (string) $error_message);
    update_option('nmkr_sync_status', 'failed');
    update_option('nmkr_sync_in_progress', false);
    delete_transient('nmkr_sync_in_progress');
    foreach (array('nmkr_sync_progress', 'nmkr_sync_current_item', 'nmkr_sync_current_count', 'nmkr_sync_total_items') as $key) {
        delete_transient($key);
    }
    $released = nmkr_release_sync_owner($run_id, $sync_stats_id, 'running');
    return $released === true;
}

function nmkr_cleanup_failed_queued_sync($run_id) {
    return nmkr_cancel_exact_queued_sync_owner($run_id);
}

/**
 * Cancel one admitted, but not yet claimed, direct run.
 *
 * The owner advisory lock is the atomic boundary: a worker may change queued
 * to running only before this callback enters the lock or after it returns.
 * This function deliberately never releases a running or finalizing owner.
 */
function nmkr_cancel_exact_queued_sync_owner($run_id, $status = '') {
    if (!nmkr_is_valid_sync_run_id($run_id)) {
        return new WP_Error('invalid_run_id', __('Invalid synchronization run identifier.', 'nmkr-connect'));
    }
    return nmkr_with_sync_owner_lock(function () use ($run_id, $status) {
        return nmkr_cancel_exact_queued_sync_owner_locked($run_id, $status);
    });
}

/** Internal locked implementation; callers must already hold the owner lock. */
function nmkr_cancel_exact_queued_sync_owner_locked($run_id, $status = '') {
    $owner = nmkr_get_uncached_option_value('nmkr_sync_owner', false);
    if (!is_array($owner) || !hash_equals((string) ($owner['run_id'] ?? ''), (string) $run_id)
        || ($owner['mode'] ?? '') !== 'direct' || ($owner['state'] ?? '') !== 'queued'
        || (int) ($owner['sync_stats_id'] ?? -1) !== 0) {
        return false;
    }

    wp_clear_scheduled_hook('nmkr_execute_sync_background', array($run_id));
    if (wp_next_scheduled('nmkr_execute_sync_background', array($run_id)) !== false) {
        return new WP_Error('sync_queued_event_cleanup_failed', __('Synchronization event cleanup could not be verified.', 'nmkr-connect'));
    }

    update_option('nmkr_sync_in_progress', false);
    delete_transient('nmkr_sync_in_progress');
    foreach (array('nmkr_sync_progress', 'nmkr_sync_current_item', 'nmkr_sync_current_count', 'nmkr_sync_total_items', 'nmkr_sync_user_stopped') as $key) {
        delete_transient($key);
    }
    $markers_clean = !get_option('nmkr_sync_in_progress', false) && !get_transient('nmkr_sync_in_progress');
    foreach (array('nmkr_sync_progress', 'nmkr_sync_current_item', 'nmkr_sync_current_count', 'nmkr_sync_total_items', 'nmkr_sync_user_stopped') as $key) {
        $markers_clean = $markers_clean && get_transient($key) === false;
    }
    if (!$markers_clean) {
        return new WP_Error('sync_queued_cleanup_failed', __('Synchronization scheduling cleanup could not be verified.', 'nmkr-connect'));
    }
    if ($status !== '') {
        update_option('nmkr_sync_status', (string) $status);
    }

    delete_option('nmkr_sync_owner');
    if (nmkr_get_uncached_option_value('nmkr_sync_owner', false) !== false) {
        return new WP_Error('sync_queued_owner_cleanup_failed', __('Synchronization ownership cleanup could not be verified.', 'nmkr-connect'));
    }
    return true;
}

/**
 * Serialize a privileged cleanup decision with Start admission.
 * The ownerless callback is invoked while the advisory lock is still held.
 */
function nmkr_coordinate_sync_cleanup($intent, $ownerless_callback) {
    return nmkr_with_sync_owner_lock(function () use ($intent, $ownerless_callback) {
        $owner = nmkr_get_uncached_option_value('nmkr_sync_owner', false);
        if ($owner === false) {
            // An owner may be released only after a direct run hands off to
            // durable finalization. Never let an administrative ownerless
            // cleanup erase that handoff or its retry evidence.
            $sync_data = nmkr_get_uncached_option_value('nmkr_sync_data', array());
            $finalization_pending = is_array($sync_data) && (
                ($sync_data['status'] ?? '') === 'finalizing'
                || (function_exists('nmkr_sync_start_blocked_by_finalization') && nmkr_sync_start_blocked_by_finalization($sync_data))
            );
            if ($finalization_pending) {
                return new WP_Error('sync_finalization_pending', __('Synchronization finalization is still pending.', 'nmkr-connect'));
            }
            return call_user_func($ownerless_callback);
        }
        if (!is_array($owner) || !nmkr_is_valid_sync_run_id((string) ($owner['run_id'] ?? ''))
            || ($owner['mode'] ?? '') !== 'direct') {
            return new WP_Error('sync_owner_recovery_required', __('Synchronization ownership requires recovery before cleanup.', 'nmkr-connect'));
        }
        if (($owner['state'] ?? '') === 'queued' && (int) ($owner['sync_stats_id'] ?? -1) === 0 && $intent === 'cancel') {
            return nmkr_cancel_exact_queued_sync_owner_locked((string) $owner['run_id'], 'cancelled');
        }
        if (($owner['state'] ?? '') === 'running') {
            return new WP_Error('direct_stop_requires_cooperative_worker', __('The direct synchronization worker is already running and cannot be stopped safely yet.', 'nmkr-connect'));
        }
        if (($owner['state'] ?? '') === 'finalizing') {
            return new WP_Error('sync_finalization_pending', __('Synchronization finalization is still pending.', 'nmkr-connect'));
        }
        return new WP_Error('sync_owner_recovery_required', __('Synchronization ownership requires recovery before cleanup.', 'nmkr-connect'));
    });
}

/** Resolve a failed history binding without touching a successor owner. */
function nmkr_handle_sync_owner_binding_failure($result, $run_id, $sync_stats_id) {
    global $wpdb;
    $sync_stats_id = (int) $sync_stats_id;
    $history_table = $wpdb->prefix . 'nmkr_sync_stats';
    $exact_original_owner = is_wp_error($result) && nmkr_sync_owner_matches($run_id, 'running', 0);
    $before = $wpdb->get_row($wpdb->prepare("SELECT id, status, end_time FROM $history_table WHERE id = %d", $sync_stats_id), ARRAY_A);
    if (!$before || nmkr_is_sync_terminal_status($before['status'] ?? '')) {
        if ($exact_original_owner) {
            nmkr_cleanup_failed_direct_sync_markers('history_cleanup_error');
            return new WP_Error('sync_history_terminalization_failed', 'Failed to terminalize synchronization history.');
        }
        return new WP_Error('sync_owner_mismatch', 'Synchronization owner changed; orphan history was not modified.');
    }
    $updated = nmkr_update_sync_stats($sync_stats_id, array(
        'status' => 'failed',
        'error_message' => 'Synchronization ownership changed during initialization.',
        'end_time' => nmkr_get_timestamp(),
    ));
    $history = $wpdb->get_row($wpdb->prepare("SELECT id, status, end_time FROM $history_table WHERE id = %d", $sync_stats_id), ARRAY_A);
    if (!$updated || !$history || (int) ($history['id'] ?? 0) !== $sync_stats_id
        || !in_array(strtolower((string) ($history['status'] ?? '')), array('failed', 'error'), true)
        || !nmkr_is_valid_sync_end_time($history['end_time'] ?? '')) {
        if ($exact_original_owner) {
            nmkr_cleanup_failed_direct_sync_markers('history_cleanup_error');
            return new WP_Error('sync_history_terminalization_failed', 'Failed to terminalize synchronization history.');
        }
        return new WP_Error('sync_owner_mismatch', 'Synchronization owner changed; orphan history cleanup failed.');
    }
    if ($exact_original_owner) {
        nmkr_cleanup_failed_direct_sync($run_id, 0, 'Failed to bind synchronization history ownership.');
        return new WP_Error('sync_owner_binding_failed', 'Failed to bind synchronization history ownership.');
    }
    return new WP_Error('sync_owner_mismatch', 'Synchronization owner changed during history binding.');
}

function nmkr_cleanup_failed_direct_sync_markers($status) {
    update_option('nmkr_sync_in_progress', false);
    delete_transient('nmkr_sync_in_progress');
    foreach (array('nmkr_sync_progress', 'nmkr_sync_current_item', 'nmkr_sync_current_count', 'nmkr_sync_total_items') as $key) {
        delete_transient($key);
    }
    update_option('nmkr_sync_status', (string) $status);
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
    $sync_data     = nmkr_get_sync_data();
    $is_finalizing = is_array($sync_data) && isset($sync_data['status']) && $sync_data['status'] === 'finalizing';
    $heartbeat_age = function_exists('nmkr_get_heartbeat_age') ? nmkr_get_heartbeat_age() : -1;
    $last_update   = (int) get_option('nmkr_last_progress_update_time', 0);

    $ttl   = defined('NMKR_SYNC_TRANSIENT_TTL') ? (int) NMKR_SYNC_TRANSIENT_TTL : HOUR_IN_SECONDS;
    $grace = min($ttl, 300);

    $stale_heartbeat = ($heartbeat_age < 0 || $heartbeat_age >= $grace);
    $stale_progress  = ($last_update <= 0 || ( time() - $last_update ) >= $grace);
    $is_stale        = ( ( $in_progress || $is_finalizing ) && ( $stale_heartbeat || $stale_progress ) );

    if ( ! $is_stale ) {
        return array('stale'=>false,'recovered'=>false,'grace'=>$grace,'heartbeat_age'=>$heartbeat_age,'last_update'=>$last_update);
    }

    $raw_owner = get_option('nmkr_sync_owner', false);
    if ($raw_owner !== false) {
        $owner = is_array($raw_owner) ? $raw_owner : false;
        $exact_stopped_recovery = $owner && ($owner['mode'] ?? '') === 'direct'
            && ($owner['state'] ?? '') === 'stop_requested'
            && is_array($sync_data)
            && (string) ($owner['run_id'] ?? '') === (string) ($sync_data['run_id'] ?? '')
            && (int) ($owner['sync_stats_id'] ?? 0) === (int) ($sync_data['sync_stats_id'] ?? 0)
            && function_exists('nmkr_resume_stopped_sync_recovery');
        if ($exact_stopped_recovery) {
            $recovered = nmkr_resume_stopped_sync_recovery((string) $owner['run_id'], (int) $owner['sync_stats_id']);
            return array('stale'=>true,'recovered'=>is_array($recovered) && ($recovered['status'] ?? '') === 'stopped','owner_preserved'=>nmkr_get_sync_owner() !== false,'grace'=>$grace,'heartbeat_age'=>$heartbeat_age,'last_update'=>$last_update);
        }
        $exact_finalizing = $owner && ($owner['mode'] ?? '') === 'direct'
            && ($owner['state'] ?? '') === 'finalizing'
            && is_array($sync_data) && ($sync_data['status'] ?? '') === 'finalizing'
            && (string) ($owner['run_id'] ?? '') === (string) ($sync_data['run_id'] ?? '')
            && (int) ($owner['sync_stats_id'] ?? 0) === (int) ($sync_data['sync_stats_id'] ?? 0);
        if ($exact_finalizing) {
            $maintenance = nmkr_maintain_exact_finalizing_owner((string) $owner['run_id'], (int) $owner['sync_stats_id']);
            if (is_wp_error($maintenance)) {
                return array('stale'=>true,'recovered'=>false,'owner_preserved'=>true,'maintenance_lock_error'=>true,'grace'=>$grace,'heartbeat_age'=>$heartbeat_age,'last_update'=>$last_update);
            }
            return array_merge(array('stale'=>true,'recovered'=>false,'owner_preserved'=>true,'grace'=>$grace,'heartbeat_age'=>$heartbeat_age,'last_update'=>$last_update), $maintenance);
        }
        if (!$owner || ($owner['mode'] ?? '') !== 'direct'
            || !in_array(($owner['state'] ?? ''), array('queued', 'running'), true)) {
            update_option('nmkr_sync_status', 'owner_recovery_required');
        }
        return array('stale'=>true,'recovered'=>false,'owner_preserved'=>true,'grace'=>$grace,'heartbeat_age'=>$heartbeat_age,'last_update'=>$last_update);
    }

    $legacy_result = nmkr_with_ownerless_legacy_recovery(function () use ($is_finalizing, $sync_data, $grace, $heartbeat_age, $last_update) {
    // Admin/dashboard stale detection may maintain the dedicated resume event,
    // but it never performs finalization in this request.
    if ($is_finalizing) {
        $scheduled = nmkr_maintain_sync_finalization_resume($sync_data);
        if (!$scheduled) {
            update_option('nmkr_sync_status', 'finalization_error');
        }
        return array('stale'=>true,'recovered'=>false,'finalization_scheduled'=>$scheduled,'grace'=>$grace,'heartbeat_age'=>$heartbeat_age,'last_update'=>$last_update);
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
    });
    if (is_wp_error($legacy_result)) {
        return array('stale'=>true,'recovered'=>false,'owner_preserved'=>true,'recovery_lock_error'=>true,'grace'=>$grace,'heartbeat_age'=>$heartbeat_age,'last_update'=>$last_update);
    }
    if (is_array($legacy_result) && !empty($legacy_result['owner_preserved'])) {
        return array('stale'=>true,'recovered'=>false,'owner_preserved'=>true,'grace'=>$grace,'heartbeat_age'=>$heartbeat_age,'last_update'=>$last_update);
    }
    return $legacy_result;
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
