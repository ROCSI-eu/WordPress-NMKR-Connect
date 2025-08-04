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
function nmkr_throttle_api_call() {
    global $nmkr_api_call_times, $nmkr_api_rate_limited, $nmkr_api_cooldown_until;
    
    $current_time = microtime(true);
    
    // Check if we're in a cooldown period
    if ($nmkr_api_rate_limited && $current_time < $nmkr_api_cooldown_until) {
        $wait_time = $nmkr_api_cooldown_until - $current_time;
        nmkr_log_api_status('API in cooldown period, waiting ' . round($wait_time, 2) . ' seconds', 'warning');
        
        // Sleep to respect cooldown period
        if ($wait_time > 0) {
            // For longer waits, use multiple short sleeps to allow process interruption
            if ($wait_time > 1) {
                $chunks = ceil($wait_time);
                for ($i = 0; $i < $chunks; $i++) {
                    if ($i == $chunks - 1) {
                        // Last chunk - sleep for remainder
                        $remainder = $wait_time - (floor($wait_time));
                        usleep($remainder * 1000000);
                    } else {
                        // Full second chunks
                        sleep(1);
                    }
                }
            } else {
                // Short wait - use microseconds
                usleep($wait_time * 1000000);
            }
        }
        
        // Reset rate limited flag if cooldown period is over
        if (microtime(true) >= $nmkr_api_cooldown_until) {
            $nmkr_api_rate_limited = false;
            nmkr_log_api_status('API cooldown period ended, resuming normal operation', 'info');
        }
        
        return;
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
            usleep($delay_needed * 1000000);
        }
    }
    
    // Record this API call
    $nmkr_api_call_times[] = microtime(true);
    
    // If we've hit the rate limit, enter cooldown period
    if (count($nmkr_api_call_times) >= NMKR_API_RATE_LIMIT) {
        $nmkr_api_rate_limited = true;
        $nmkr_api_cooldown_until = microtime(true) + NMKR_API_COOLDOWN_PERIOD;
        nmkr_log_api_status('API rate limit reached, entering cooldown period for ' . NMKR_API_COOLDOWN_PERIOD . ' seconds', 'warning');
    }
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

// Fetch NMKR account projects
function nmkr_connect_fetch_projects() {
    return nmkr_tracked_api_call_v2('fetch_projects', function() {
        $options = get_option('nmkr_connect_options');
        $nmkr_api_key = isset($options['api_key']) ? $options['api_key'] : '';
        
        if (!$nmkr_api_key) {
            nmkr_log_data_sync('API key not set', 'error');
            return new WP_Error('api_key_not_set', 'API key not set');
        }

        nmkr_log_api_status('Fetching projects list from API');
        
        // Apply throttling before making the API call
        nmkr_throttle_api_call();
        
        $api_url = NMKR_API_URL . '/ListProjects';
        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $nmkr_api_key,
            ),
            'timeout' => 30 // Increase timeout to 30 seconds for potentially large responses
        );

        $response = wp_remote_get($api_url, $args);
        
        if (is_wp_error($response)) {
            nmkr_log_data_sync('Error fetching projects: ' . $response->get_error_message(), 'error');
            return new WP_Error('api_request_failed', 'Error fetching projects: ' . $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $projects = json_decode($body, true);
        
        // Check for JSON parsing errors
        if (json_last_error() !== JSON_ERROR_NONE) {
            $error_message = 'Failed to parse projects response: ' . json_last_error_msg();
            nmkr_log_data_sync($error_message, 'error');
            return new WP_Error('json_parse_error', $error_message);
        }
        
        nmkr_log_api_status('Received projects list from API, count: ' . count($projects));
        
        return $projects;
    });
}

// Fetch NMKR account tokens (NFTs) for a project
function nmkr_connect_fetch_nfts($project_id) {
    $options = get_option('nmkr_connect_options');
    $nmkr_api_key = isset($options['api_key']) ? $options['api_key'] : '';
    
    if (!$nmkr_api_key) {
        nmkr_log_data_sync('API key not set for token fetch', 'error');
        return new WP_Error('api_key_not_set', 'API key not set');
    }

    nmkr_log_api_status('Fetching tokens for project ID: ' . $project_id);
    
    $api_url = NMKR_API_URL . '/GetNfts/' . $project_id . '/all/50/1';
    $args = array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $nmkr_api_key,
        ),
    );

    $response = wp_remote_get($api_url, $args);
    if (is_wp_error($response)) {
        nmkr_log_data_sync('Error fetching tokens: ' . $response->get_error_message(), 'error');
        return new WP_Error('api_request_failed', 'Error fetching tokens: ' . $response->get_error_message());
    }

    $body = wp_remote_retrieve_body($response);
    $tokens = json_decode($body, true);
    
    nmkr_log_api_status('Received tokens for project ID: ' . $project_id . ', count: ' . count($tokens));
    
    return $tokens;
}

/**
 * Fetch NMKR account tokens (NFTs) by project UID
 * 
 * @param string $project_uid The unique identifier of the project
 * @return array|WP_Error The tokens data or WP_Error on failure
 */
function nmkr_connect_fetch_nfts_by_project($project_uid) {
    return nmkr_tracked_api_call_v2('fetch_tokens_' . $project_uid, function() use ($project_uid) {
        $options = get_option('nmkr_connect_options');
        $nmkr_api_key = isset($options['api_key']) ? $options['api_key'] : '';
        
        if (!$nmkr_api_key) {
            nmkr_log_data_sync('API key not set for token fetch by project UID', 'error');
            return new WP_Error('api_key_not_set', 'API key not set');
        }

        nmkr_log_api_status('Fetching tokens for project UID: ' . $project_uid);
        
        // Apply throttling before making the API call
        nmkr_throttle_api_call();

        // The API endpoint for fetching NFTs by project UID
        $api_url = NMKR_API_URL . '/GetNfts/' . $project_uid . '/all/50/1';
        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $nmkr_api_key,
            ),
            'timeout' => 45 // Longer timeout for potentially large collections
        );

        $response = wp_remote_get($api_url, $args);

        if (is_wp_error($response)) {
            $error_message = 'Error fetching tokens by project UID: ' . $response->get_error_message();
            nmkr_log_data_sync($error_message, 'error', array(
                'project_uid' => $project_uid
            ));
            return new WP_Error('api_request_failed', $error_message);
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $error_message = 'Failed to parse API response for tokens by project UID: ' . json_last_error_msg();
            nmkr_log_data_sync($error_message, 'error', array(
                'project_uid' => $project_uid,
                'json_error' => json_last_error_msg()
            ));
            return new WP_Error('json_parse_error', $error_message);
        }
        
        nmkr_log_api_status('Received tokens for project UID: ' . $project_uid . ', count: ' . count($data));

        return $data;
    });
}

// Fetch details of a specific NFT by its uid
function nmkr_connect_fetch_nft_details($token_uid) {
    return nmkr_tracked_api_call_v2('fetch_token_details_' . $token_uid, function() use ($token_uid) {
        $options = get_option('nmkr_connect_options');
        $api_key = isset($options['api_key']) ? $options['api_key'] : '';
        
        nmkr_log_api_status('Fetching details for token UID: ' . $token_uid);
        
        // Apply throttling before making the API call
        nmkr_throttle_api_call();
        
        // Update endpoint to use the correct path from documentation
        $url = NMKR_API_URL . '/GetNftDetailsById/' . $token_uid;
        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30
        );

        $response = wp_remote_get($url, $args);
        
        if (is_wp_error($response)) {
            $error_message = 'Error fetching token details: ' . $response->get_error_message();
            nmkr_log_data_sync($error_message, 'error', array('token_uid' => $token_uid));
            return new WP_Error('api_request_failed', $error_message);
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $error_message = 'Failed to parse token details response: ' . json_last_error_msg();
            nmkr_log_data_sync($error_message, 'error', array(
                'token_uid' => $token_uid,
                'json_error' => json_last_error_msg()
            ));
            return new WP_Error('json_parse_error', $error_message);
        }
        
        nmkr_log_api_status('Received details for token UID: ' . $token_uid);
        
        return $data;
    });
}