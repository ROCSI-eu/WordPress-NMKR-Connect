<?php
// Include NMKR API
require_once plugin_dir_path(__FILE__) . 'nmkr-api.php';
// Include utility functions for logging
require_once plugin_dir_path(dirname(__FILE__)) . 'helpers/nmkr-utility-functions.php';

// API rate limiting constants and globals
define('NMKR_API_RATE_LIMIT', 10); // Maximum API calls per second
define('NMKR_API_COOLDOWN_PERIOD', 60); // Cooldown period in seconds after rate limit hit
global $nmkr_api_call_times; // Array to store timestamps of recent API calls
$nmkr_api_call_times = array();
global $nmkr_api_rate_limited; // Flag to indicate if we're in a cooldown period
$nmkr_api_rate_limited = false;
global $nmkr_api_cooldown_until; // Timestamp when cooldown period ends
$nmkr_api_cooldown_until = 0;

/**
 * Throttle API calls to avoid hitting rate limits
 * This function will delay execution if necessary to stay within rate limits
 */
function nmkr_throttle_api_call($context = array()) {
    global $nmkr_api_call_times, $nmkr_api_rate_limited, $nmkr_api_cooldown_until;
    $context = is_array($context) ? $context : array();
    $checkpoint = isset($context['checkpoint']) && is_callable($context['checkpoint']) ? $context['checkpoint'] : null;
    $clock = isset($context['clock']) && is_callable($context['clock']) ? $context['clock'] : function () { return microtime(true); };
    $sleeper = isset($context['sleep']) && is_callable($context['sleep']) ? $context['sleep'] : function ($seconds) { usleep((int) round($seconds * 1000000)); };
    $check = function ($phase) use ($checkpoint) { return $checkpoint ? call_user_func($checkpoint, $phase) : true; };
    $halt = $check('before_api_throttle'); if (is_wp_error($halt)) return $halt;
    $current_time = call_user_func($clock);
    
    // Check if we're in a cooldown period
    if ($nmkr_api_rate_limited && $current_time < $nmkr_api_cooldown_until) {
        $wait_time = $nmkr_api_cooldown_until - $current_time;
        nmkr_log_api_status('API in cooldown period, waiting ' . round($wait_time, 2) . ' seconds', 'warning');
        
        while ($wait_time > 0) {
            $halt = $check('before_api_cooldown_wait'); if (is_wp_error($halt)) return $halt;
            $chunk = min(1.0, $wait_time); call_user_func($sleeper, $chunk);
            $halt = $check('after_api_cooldown_wait'); if (is_wp_error($halt)) return $halt;
            $wait_time = $nmkr_api_cooldown_until - call_user_func($clock);
        }
        
        // Reset rate limited flag if cooldown period is over
        if (call_user_func($clock) >= $nmkr_api_cooldown_until) {
            $nmkr_api_rate_limited = false;
            nmkr_log_api_status('API cooldown period ended, resuming normal operation', 'info');
        }
        
        return $check('after_api_throttle');
    }
    
    // Clean up old call timestamps (older than 1 second)
    $nmkr_api_call_times = array_filter($nmkr_api_call_times, function($timestamp) use ($current_time) {
        return $current_time - $timestamp <= 1;
    });
    
    // Check if we're approaching the rate limit
    if (count($nmkr_api_call_times) >= NMKR_API_RATE_LIMIT - 1) {
        // Calculate required delay to stay within rate limit
        $oldest_timestamp = min($nmkr_api_call_times);
        $time_since_oldest = $current_time - $oldest_timestamp;
        
        if ($time_since_oldest < 1) {
            $delay_needed = 1 - $time_since_oldest;
            nmkr_log_api_status('Approaching API rate limit, delaying next call by ' . round($delay_needed * 1000, 2) . 'ms', 'info');
            $halt = $check('before_api_rate_wait'); if (is_wp_error($halt)) return $halt;
            call_user_func($sleeper, min(1.0, $delay_needed));
            $halt = $check('after_api_rate_wait'); if (is_wp_error($halt)) return $halt;
        }
    }
    
    // Record this API call
    $nmkr_api_call_times[] = call_user_func($clock);
    
    // If we've hit the rate limit, enter cooldown period
    if (count($nmkr_api_call_times) >= NMKR_API_RATE_LIMIT) {
        $nmkr_api_rate_limited = true;
        $nmkr_api_cooldown_until = call_user_func($clock) + NMKR_API_COOLDOWN_PERIOD;
        nmkr_log_api_status('API rate limit reached, entering cooldown period for ' . NMKR_API_COOLDOWN_PERIOD . ' seconds', 'warning');
    }
    return $check('after_api_throttle');
}

// Function to check if API is connected
function nmkr_is_api_connected() {
    $options = get_option('nmkr_connect_options');
    $api_key = isset($options['api_key']) ? $options['api_key'] : '';

    if (empty($api_key)) {
        nmkr_log_api_status('Dashboard API status check: No API key set', 'warning');
        return false;
    }

    nmkr_log_api_status('Dashboard API status check initiated');
    
    // Throttle this API call
    nmkr_throttle_api_call();
    
    // Make a simple API call to check connection
    $response = wp_remote_get(NMKR_API_URL . '/ListProjects', [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
        ],
        'timeout' => 5, // Shorter timeout (5 seconds) for quick connection check
    ]);

    $is_connected = !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
    
    // Log connection result
    if ($is_connected) {
        nmkr_log_api_status('Dashboard API status check: Connection successful');
    } else {
        $response_code = is_wp_error($response) ? 'WP_Error' : wp_remote_retrieve_response_code($response);
        $error_message = is_wp_error($response) ? $response->get_error_message() : 'HTTP response code: ' . $response_code;
        
        nmkr_log_api_status('Dashboard API status check: Connection failed - ' . $error_message, 'error');
    }
    
    return $is_connected;
}

/**
 * Execute a synchronization GET/JSON operation with bounded retries.
 * Dependencies in $context are injectable for deterministic public tests.
 */
function nmkr_sync_http_json_execute($endpoint_class, $url, $args, $shape, $context = array()) {
    $context = is_array($context) ? $context : array();
    $clock = isset($context['clock']) && is_callable($context['clock']) ? $context['clock'] : function () { return microtime(true); };
    $sleep = isset($context['sleep']) && is_callable($context['sleep']) ? $context['sleep'] : function ($seconds) { usleep((int) round($seconds * 1000000)); };
    $request = isset($context['request']) && is_callable($context['request']) ? $context['request'] : 'wp_remote_get';
    $checkpoint = isset($context['checkpoint']) && is_callable($context['checkpoint']) ? $context['checkpoint'] : null;
    $jitter = isset($context['jitter']) && is_callable($context['jitter']) ? $context['jitter'] : function () { return 0.0; };
    $check = function ($phase) use ($checkpoint) { return $checkpoint ? call_user_func($checkpoint, $phase) : true; };
    $wait = function ($seconds, $kind) use ($sleep, $clock, $check) {
        $remaining = max(0.0, min(30.0, (float) $seconds));
        $started = call_user_func($clock);
        while ($remaining > 0) {
            $halt = $check('before_api_' . $kind . '_wait'); if (is_wp_error($halt)) return $halt;
            $chunk = min(1.0, $remaining); call_user_func($sleep, $chunk); $remaining -= $chunk;
            $halt = $check('after_api_' . $kind . '_wait'); if (is_wp_error($halt)) return $halt;
        }
        if (function_exists('nmkr_record_api_wait')) nmkr_record_api_wait($kind, max(0.0, call_user_func($clock) - $started));
        return true;
    };

    for ($attempt = 1; $attempt <= 3; $attempt++) {
        $halt = $check('before_api_attempt'); if (is_wp_error($halt)) return $halt;
        $throttle_started = call_user_func($clock);
        $halt = nmkr_throttle_api_call($context); if (is_wp_error($halt)) return $halt;
        if (function_exists('nmkr_record_api_wait')) nmkr_record_api_wait('throttle', max(0.0, call_user_func($clock) - $throttle_started));
        $halt = $check('before_api_dispatch'); if (is_wp_error($halt)) return $halt;

        $started = call_user_func($clock);
        $response = call_user_func($request, $url, $args);
        $duration = max(0.0, call_user_func($clock) - $started);
        $valid = false; $retry = false; $error = null;
        if (is_wp_error($response)) {
            $retry = true;
            $error = new WP_Error('nmkr_api_transport_failure', 'Synchronization request transport failure.', array('endpoint' => $endpoint_class));
        } else {
            $status = (int) wp_remote_retrieve_response_code($response);
            if ($status < 200 || $status >= 300) {
                $retry = $status === 408 || $status === 429 || $status >= 500;
                $code = $status === 404 ? 'nmkr_api_endpoint_not_found' : (($status === 401 || $status === 403) ? 'nmkr_api_authorization_failure' : 'nmkr_api_http_failure');
                $error = new WP_Error($code, 'Synchronization endpoint returned an HTTP error.', array('endpoint' => $endpoint_class, 'status' => $status));
            } else {
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);
                if ($body === '' || json_last_error() !== JSON_ERROR_NONE) {
                    $error = new WP_Error('nmkr_api_invalid_json', 'Synchronization endpoint returned invalid JSON.', array('endpoint' => $endpoint_class));
                } elseif (!call_user_func($shape, $data, $body)) {
                    $error = new WP_Error('nmkr_api_invalid_shape', 'Synchronization endpoint returned an unexpected payload.', array('endpoint' => $endpoint_class));
                } else { $valid = true; }
            }
        }
        if (function_exists('nmkr_record_api_attempt')) nmkr_record_api_attempt($duration, $valid, $attempt > 1, $context);
        if ($valid) return $data;
        if (!$retry) return $error;
        if ($attempt === 3) return new WP_Error('nmkr_api_retry_exhausted', 'Synchronization request retry budget exhausted.', array('endpoint' => $endpoint_class));

        $retry_after = 0.0;
        if (!is_wp_error($response) && function_exists('wp_remote_retrieve_header')) {
            $header = wp_remote_retrieve_header($response, 'retry-after');
            if (is_numeric($header)) $retry_after = (float) $header;
            elseif (is_string($header) && ($when = strtotime($header)) !== false) $retry_after = max(0, $when - call_user_func($clock));
        }
        $delay = min(30.0, max($retry_after, pow(2, $attempt - 1) + max(0.0, (float) call_user_func($jitter, $attempt))));
        $halt = $wait($delay, 'backoff'); if (is_wp_error($halt)) return $halt;
    }
}

function nmkr_sync_list_shape($data, $body = '') { return is_array($data) && substr(ltrim($body), 0, 1) === '[' && (empty($data) || array_keys($data) === range(0, count($data) - 1)); }
function nmkr_sync_detail_shape($data, $body = '') { return is_array($data) && !empty($data) && substr(ltrim($body), 0, 1) === '{'; }
function nmkr_sync_api_key() { $options = get_option('nmkr_connect_options'); return is_array($options) ? (string) ($options['api_key'] ?? '') : ''; }
function nmkr_sync_api_args($key, $timeout) { return array('headers' => array('Authorization' => 'Bearer ' . $key), 'timeout' => $timeout); }

// Fetch NMKR account projects
function nmkr_connect_fetch_projects($context = array()) {
    $key = nmkr_sync_api_key(); if ($key === '') return new WP_Error('api_key_not_set', 'API key not set');
    return nmkr_sync_http_json_execute('projects', NMKR_API_URL . '/ListProjects', nmkr_sync_api_args($key, 30), 'nmkr_sync_list_shape', $context);
}

// Compatibility page-one adapter; pagination remains deferred.
function nmkr_connect_fetch_nfts($project_id, $context = array()) {
    return nmkr_connect_fetch_nfts_by_project($project_id, $context);
}

function nmkr_connect_fetch_nfts_by_project($project_uid, $context = array()) {
    $key = nmkr_sync_api_key(); if ($key === '') return new WP_Error('api_key_not_set', 'API key not set');
    return nmkr_sync_http_json_execute('token_list', NMKR_API_URL . '/GetNfts/' . rawurlencode($project_uid) . '/all/50/1', nmkr_sync_api_args($key, 45), 'nmkr_sync_list_shape', $context);
}

function nmkr_connect_fetch_nft_details($token_uid, $context = array()) {
    $key = nmkr_sync_api_key(); if ($key === '') return new WP_Error('api_key_not_set', 'API key not set');
    return nmkr_sync_http_json_execute('token_detail', NMKR_API_URL . '/GetNftDetailsById/' . rawurlencode($token_uid), nmkr_sync_api_args($key, 30), 'nmkr_sync_detail_shape', $context);
}
