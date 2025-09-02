<?php
/**
 * NMKR Connect Analytics Helper Functions
 *
 * Helper functions for analytics data processing and privacy compliance
 *
 * @package NMKR_Connect
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get analytics pepper for IP hashing
 * 
 * Derives a plugin-specific pepper from AUTH_SALT for consistent IP hashing
 * 
 * @return string Binary pepper for hash_hmac
 */
function nmkr_get_analytics_pepper() {
    // Derive a plugin-specific pepper from AUTH_SALT
    // This ensures consistent hashing across installations while maintaining privacy
    return hash('sha256', AUTH_SALT . '|nmkr-analytics-pepper', true);
}

/**
 * Hash IP address for privacy compliance
 * 
 * Creates a privacy-compliant hash of the IP address using HMAC-SHA256
 * 
 * @param string $ip_address The IP address to hash
 * @return string Binary hash digest (32 bytes) or empty string on error
 */
function nmkr_hash_ip_address($ip_address = '') {
    // Allow optional parameter; if empty, derive from server vars
    if (empty($ip_address) || !is_string($ip_address)) {
        $ip_address = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
    }
    if ($ip_address === '') {
        return '';
    }

    // Use HMAC-SHA256 with plugin-specific pepper; return hex for portability
    $pepper = nmkr_get_analytics_pepper();
    return hash_hmac('sha256', $ip_address, $pepper, false); // hex string
}

/**
 * Generate UUID v4 for session tracking
 * 
 * Creates a UUID v4 string for anonymous session identification
 * 
 * @return string UUID v4 string
 */
function nmkr_generate_session_id() {
    // Generate UUID v4 for session tracking
    // Format: xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx
    $data = random_bytes(16);
    
    // Set version (4) and variant bits
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // Version 4
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // Variant 1
    
    // Convert to hex and format as UUID
    $hex = bin2hex($data);
    return sprintf(
        '%s-%s-%s-%s-%s',
        substr($hex, 0, 8),
        substr($hex, 8, 4),
        substr($hex, 12, 4),
        substr($hex, 16, 4),
        substr($hex, 20, 12)
    );
}

/**
 * Check if analytics tracking is enabled
 * 
 * Determines if analytics tracking should be active based on settings
 * 
 * @return bool True if analytics should be tracked
 */
function nmkr_is_analytics_enabled() {
    $options = get_option('nmkr_connect_options', array());
    $analytics_mode = isset($options['analytics_mode']) ? $options['analytics_mode'] : 'minimal';
    
    return $analytics_mode !== 'off';
}

/**
 * Check if user consent is required for analytics
 * 
 * @return bool True if user consent is required
 */
function nmkr_analytics_requires_consent() {
    $options = get_option('nmkr_connect_options', array());
    return isset($options['analytics_require_consent']) ? $options['analytics_require_consent'] : false;
}

/**
 * Get analytics sample rate
 * 
 * @return float Sample rate between 0 and 1
 */
function nmkr_get_analytics_sample_rate() {
    $options = get_option('nmkr_connect_options', array());
    $sample_rate = isset($options['analytics_sample_rate']) ? floatval($options['analytics_sample_rate']) : 1.0;
    
    // Ensure valid range
    return max(0, min(1, $sample_rate));
}

/**
 * Check if logged-in users should be tracked
 * 
 * @return bool True if logged-in users should be tracked
 */
function nmkr_should_track_logged_in_users() {
    $options = get_option('nmkr_connect_options', array());
    return isset($options['analytics_track_logged_in']) ? $options['analytics_track_logged_in'] : false;
}

/**
 * Get current user ID for analytics (if tracking is enabled)
 * 
 * @return int|null User ID or null if not logged in or tracking disabled
 */
function nmkr_get_analytics_user_id() {
    if (!nmkr_should_track_logged_in_users()) {
        return null;
    }
    
    $user_id = get_current_user_id();
    return $user_id > 0 ? $user_id : null;
}

/**
 * Truncate string for analytics storage
 * 
 * Ensures strings don't exceed database column limits
 * 
 * @param string $string The string to truncate
 * @param int $max_length Maximum length (default 255)
 * @return string Truncated string
 */
function nmkr_truncate_for_analytics($string, $max_length = 255) {
    if (empty($string) || !is_string($string)) {
        return '';
    }
    
    if (strlen($string) <= $max_length) {
        return $string;
    }
    
    return substr($string, 0, $max_length - 3) . '...';
}

/**
 * Prepare analytics metadata for storage
 * 
 * Sanitizes and prepares metadata for JSON storage
 * 
 * @param array $metadata Raw metadata array
 * @return array Sanitized metadata array
 */
function nmkr_prepare_analytics_metadata($metadata) {
    if (!is_array($metadata)) {
        return array();
    }
    
    $sanitized = array();
    
    foreach ($metadata as $key => $value) {
        // Sanitize key
        $key = sanitize_key($key);
        
        // Sanitize value based on type
        if (is_string($value)) {
            $sanitized[$key] = sanitize_text_field($value);
        } elseif (is_numeric($value)) {
            $sanitized[$key] = is_float($value) ? floatval($value) : intval($value);
        } elseif (is_bool($value)) {
            $sanitized[$key] = (bool) $value;
        } elseif (is_array($value)) {
            // Recursively sanitize nested arrays
            $sanitized[$key] = nmkr_prepare_analytics_metadata($value);
        }
        // Skip other types for safety
    }
    
    return $sanitized;
}

/**
 * Returns true if the (session_id, element_id, event_type) was already seen in the TTL window.
 */
function nmkr_analytics_seen_once( $session_id, $element_id, $event_type, $ttl_seconds = 7200 ) {
    $session_id  = substr( preg_replace('/[^a-zA-Z0-9\-]/', '', (string) $session_id ), 0, 64 );
    $element_id  = substr( preg_replace('/[^a-zA-Z0-9\:\-\_]/', '', (string) $element_id ), 0, 128 );
    $event_type  = $event_type === 'click' ? 'click' : 'view';
    if ( empty( $session_id ) || empty( $element_id ) ) {
        return false; // cannot dedupe; let higher layers validate
    }
    $key = 'nmkr_analytics:' . $session_id . ':' . $element_id . ':' . $event_type;
    if ( get_transient( $key ) ) {
        return true;
    }
    set_transient( $key, 1, absint( $ttl_seconds ) );
    return false;
}

/**
 * Simple sliding window rate limiter per anonymized IP.
 * Returns true if limited; caller should return 429.
 */
function nmkr_analytics_rate_limited( $anon_ip_sha, $window_seconds = 300, $max_events = 120 ) {
    $anon_ip_sha = substr( preg_replace('/[^a-f0-9]/', '', strtolower( (string) $anon_ip_sha ) ), 0, 64 );
    if ( empty( $anon_ip_sha ) ) {
        return false; // can't rate-limit without a key
    }
    $key   = 'nmkr_analytics_ip:' . $anon_ip_sha;
    $state = get_site_transient( $key );
    $now   = time();
    if ( ! is_array( $state ) || empty( $state['start'] ) || empty( $state['count'] ) || ( $now - (int) $state['start'] ) > $window_seconds ) {
        $state = [ 'start' => $now, 'count' => 1 ];
        set_site_transient( $key, $state, $window_seconds );
        return false;
    }
    $state['count']++;
    set_site_transient( $key, $state, $window_seconds );
    return ( $state['count'] > $max_events );
}

/**
 * Common ingestion logic for REST/AJAX ingestion.
 * $source: 'rest' | 'ajax'
 */
function nmkr_analytics_ingest_common( $body, $source = 'rest' ) {
    global $wpdb;

    // 1) Basic shape + sanitize
    if ( ! is_array( $body ) ) {
        return new WP_REST_Response( null, 400 );
    }
    $event_type = ( isset( $body['event_type'] ) && $body['event_type'] === 'click' ) ? 'click' : 'view';
    $shortcode  = isset( $body['shortcode'] ) ? strtolower( (string) $body['shortcode'] ) : '';
    $allowed_sc = [ 'grid', 'list', 'carousel', 'token', 'project' ];
    if ( ! in_array( $shortcode, $allowed_sc, true ) ) {
        return new WP_REST_Response( null, 400 );
    }
    $project_uid = isset( $body['project_uid'] ) ? substr( preg_replace('/[^a-zA-Z0-9\-\_]/','', (string) $body['project_uid'] ), 0, 64 ) : '';
    $token_uid   = isset( $body['token_uid'] )   ? substr( preg_replace('/[^a-zA-Z0-9\-\_]/','', (string) $body['token_uid'] ), 0, 64 ) : '';
    $element_id  = isset( $body['element_id'] )  ? substr( preg_replace('/[^a-zA-Z0-9\:\-\_]/','', (string) $body['element_id'] ), 0, 128 ) : '';
    $session_id  = isset( $body['session_id'] )  ? substr( preg_replace('/[^a-zA-Z0-9\-]/','', (string) $body['session_id'] ), 0, 64 ) : '';
    $ts_client   = isset( $body['ts_client'] )   ? intval( $body['ts_client'] ) : time();

    if ( empty( $element_id ) || empty( $session_id ) ) {
        return new WP_REST_Response( null, 400 );
    }

    // 2) Settings gates
    if ( function_exists('nmkr_is_analytics_enabled') && ! nmkr_is_analytics_enabled() ) {
        return new WP_REST_Response( null, 204 );
    }
    if ( function_exists('nmkr_should_track_logged_in_users') && ! nmkr_should_track_logged_in_users() && is_user_logged_in() ) {
        return new WP_REST_Response( null, 204 );
    }

    // 3) Origin check (same host)
    $host      = parse_url( home_url(), PHP_URL_HOST );
    $origin    = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
    $origin_h  = $origin ? parse_url( $origin, PHP_URL_HOST ) : '';
    $host_hdr  = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
    if ( $origin && $origin_h && strcasecmp( $origin_h, $host ) !== 0 ) {
        return new WP_REST_Response( null, 403 );
    }
    if ( $host_hdr && strcasecmp( $host_hdr, $host ) !== 0 ) {
        return new WP_REST_Response( null, 403 );
    }

    // 4) Prepare metadata (server side)
    $ua        = isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( (string) $_SERVER['HTTP_USER_AGENT'], 0, 255 ) : '';
    $ref       = isset( $_SERVER['HTTP_REFERER'] )     ? substr( (string) $_SERVER['HTTP_REFERER'], 0, 512 ) : '';
    $page_url  = isset( $body['page_url'] )            ? substr( (string) $body['page_url'], 0, 512 ) : '';
    $meta      = isset( $body['meta'] ) && is_array( $body['meta'] ) ? $body['meta'] : [];
    if ( function_exists('nmkr_prepare_analytics_metadata') ) {
        $meta = nmkr_prepare_analytics_metadata( $meta );
    } else {
        $meta = [];
    }

    // 5) Anonymize IP and derive user id if allowed
    $ip_hash_hex = '';
    if ( function_exists('nmkr_hash_ip_address') ) {
        $ip_hash_hex = nmkr_hash_ip_address(); // hex string
    }
    $user_id = ( function_exists('nmkr_get_analytics_user_id') ? nmkr_get_analytics_user_id() : 0 );

    // 6) Rate-limit & dedupe
    if ( function_exists('nmkr_analytics_rate_limited') && nmkr_analytics_rate_limited( $ip_hash_hex ) ) {
        return new WP_REST_Response( null, 429 );
    }
    if ( function_exists('nmkr_analytics_seen_once') && nmkr_analytics_seen_once( $session_id, $element_id, $event_type ) ) {
        return new WP_REST_Response( null, 204 );
    }

    // 7) Mode routing: respect plugin's analytics mode; off means no DB insert
    $options = get_option('nmkr_connect_options', array());
    $mode = isset($options['analytics_mode']) ? $options['analytics_mode'] : 'minimal';
    if ( $mode === 'off' ) {
        return new WP_REST_Response( null, 204 );
    }

    // 8) Insert into DB
    $table = $wpdb->prefix . 'nmkr_analytics';
    // Convert hex to binary for storage in BINARY(32) if present
    $ip_hash_bin = '';
    if ( ! empty( $ip_hash_hex ) && ctype_xdigit( $ip_hash_hex ) && strlen( $ip_hash_hex ) === 64 ) {
        $ip_hash_bin = pack('H*', $ip_hash_hex);
    }
    $insert = $wpdb->insert(
        $table,
        [
            'event_ts'       => current_time( 'mysql', 1 ),
            'event_type'     => $event_type,
            'shortcode_type' => $shortcode,
            'project_uid'    => $project_uid ?: null,
            'token_uid'      => $token_uid ?: null,
            'user_id'        => $user_id ?: null,
            'session_id'     => $session_id,
            'anon_ip_sha256' => $ip_hash_bin !== '' ? $ip_hash_bin : null,
            'user_agent'     => $ua ?: null,
            'referrer'       => $ref ?: null,
            'page_url'       => $page_url ?: null,
            'meta_json'      => wp_json_encode( $meta ),
        ],
        [
            '%s','%s','%s','%s','%s','%d','%s','%s','%s','%s','%s','%s'
        ]
    );
    if ( false === $insert ) {
        return new WP_REST_Response( null, 500 );
    }

    return new WP_REST_Response( null, 204 );
}
