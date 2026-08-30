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
 * @return string Hex string (64 chars) or empty string on error
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
    $analytics_mode = isset($options['analytics_mode']) ? $options['analytics_mode'] : 'custom';
    
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
function nmkr_prepare_analytics_metadata($metadata, $depth = 0, &$nodes = 0, &$valid = null) {
    $valid = true;
    if (!is_array($metadata) || $depth > NMKR_ANALYTICS_META_MAX_DEPTH) {
        $valid = false;
        return array();
    }

    $sanitized = array();
    foreach ($metadata as $key => $value) {
        $nodes++;
        if ($nodes > NMKR_ANALYTICS_META_MAX_NODES || strlen((string) $key) > NMKR_ANALYTICS_META_STRING_BYTES) {
            $valid = false;
            return array();
        }
        $key = sanitize_key($key);
        if ($key === '') {
            $valid = false;
            return array();
        }
        if (is_string($value)) {
            if (strlen($value) > NMKR_ANALYTICS_META_STRING_BYTES) {
                $valid = false;
                return array();
            }
            $sanitized[$key] = sanitize_text_field($value);
        } elseif (is_int($value) || is_float($value) || is_bool($value) || is_null($value)) {
            $sanitized[$key] = $value;
        } elseif (is_array($value)) {
            $child_valid = true;
            $sanitized[$key] = nmkr_prepare_analytics_metadata($value, $depth + 1, $nodes, $child_valid);
            if (!$child_valid) {
                $valid = false;
                return array();
            }
        } else {
            $valid = false;
            return array();
        }
    }
    $encoded = wp_json_encode($sanitized);
    if ($depth === 0 && (!is_string($encoded) || strlen($encoded) > NMKR_ANALYTICS_META_MAX_BYTES)) {
        $valid = false;
        return array();
    }
    return $sanitized;
}

if (!defined('NMKR_ANALYTICS_RAW_BODY_MAX_BYTES')) define('NMKR_ANALYTICS_RAW_BODY_MAX_BYTES', 8192);
if (!defined('NMKR_ANALYTICS_META_MAX_DEPTH')) define('NMKR_ANALYTICS_META_MAX_DEPTH', 3);
if (!defined('NMKR_ANALYTICS_META_MAX_NODES')) define('NMKR_ANALYTICS_META_MAX_NODES', 32);
if (!defined('NMKR_ANALYTICS_META_STRING_BYTES')) define('NMKR_ANALYTICS_META_STRING_BYTES', 256);
if (!defined('NMKR_ANALYTICS_META_MAX_BYTES')) define('NMKR_ANALYTICS_META_MAX_BYTES', 2048);
if (!defined('NMKR_ANALYTICS_CLAIM_TTL')) define('NMKR_ANALYTICS_CLAIM_TTL', 30);

/** Fixed-length, privacy-preserving option name for public-ingestion state. */
function nmkr_analytics_state_key($kind, array $dimensions) {
    return 'nmkr_ai_' . substr(preg_replace('/[^a-z0-9_]/', '', strtolower((string) $kind)), 0, 8) . '_' . hash('sha256', "nmkr-connect-analytics-v1\0" . implode("\0", array_map('strval', $dimensions)));
}

/** Read canonical state directly so persistent object-cache coherence cannot affect admission. */
function nmkr_analytics_option_read($name) {
    global $wpdb;
    $raw = $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", $name));
    return null === $raw ? null : maybe_unserialize($raw);
}

/** Atomically establish or take over an expired bounded claim. */
function nmkr_analytics_claim_acquire($name, $ttl, $now = null) {
    global $wpdb;
    $now = null === $now ? time() : (int) $now;
    $token = bin2hex(random_bytes(16));
    $value = array('token' => $token, 'status' => 'claim', 'expires' => $now + max(1, (int) $ttl));
    if (add_option($name, $value, '', false)) return $token;
    $old = nmkr_analytics_option_read($name);
    if (null === $old || !is_array($old) || empty($old['expires'])) return null;
    if ((int) $old['expires'] >= $now) return false;
    $updated = $wpdb->query($wpdb->prepare("UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s", maybe_serialize($value), $name, maybe_serialize($old)));
    if (1 !== $updated) {
        $current = nmkr_analytics_option_read($name);
        return is_array($current) && !empty($current['token']) && !empty($current['expires']) ? false : null;
    }
    wp_cache_delete($name, 'options');
    return $token;
}

function nmkr_analytics_claim_release($name, $token) {
    global $wpdb;
    $state = nmkr_analytics_option_read($name);
    if (!is_array($state) || !hash_equals((string) $state['token'], (string) $token)) return false;
    $deleted = $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $name, maybe_serialize($state)));
    wp_cache_delete($name, 'options');
    return 1 === $deleted;
}

function nmkr_analytics_claim_finalize($name, $token, $ttl = 7200, $now = null) {
    global $wpdb;
    $now = null === $now ? time() : (int) $now;
    $old = nmkr_analytics_option_read($name);
    if (!is_array($old) || !hash_equals((string) $old['token'], (string) $token)) return false;
    $new = array('token' => $token, 'status' => 'done', 'expires' => $now + max(1, (int) $ttl));
    $updated = $wpdb->query($wpdb->prepare("UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s", maybe_serialize($new), $name, maybe_serialize($old)));
    wp_cache_delete($name, 'options');
    return 1 === $updated;
}

function nmkr_analytics_dedupe_claim($session_id, $element_id, $event_type, $ttl_seconds = 7200) {
    $key = nmkr_analytics_state_key('dedupe', array($session_id, $element_id, $event_type));
    $token = nmkr_analytics_claim_acquire($key, NMKR_ANALYTICS_CLAIM_TTL);
    return array('key' => $key, 'token' => $token, 'ttl' => max(1, (int) $ttl_seconds));
}

/** Fixed-window admission backed by durable option state and a database advisory lock. */
function nmkr_analytics_rate_limit_outcome($anon_ip_sha, $window_seconds = 300, $max_events = 120, $now = null) {
    global $wpdb;
    $anon_ip_sha = strtolower((string) $anon_ip_sha);
    if (!preg_match('/^[a-f0-9]{64}$/', $anon_ip_sha)) return 'unavailable';
    $now = null === $now ? time() : (int) $now;
    $state_key = nmkr_analytics_state_key('rate', array($anon_ip_sha));
    $lock_name = 'nmkr_ai_rate_' . substr(hash('sha256', $state_key), 0, 50);
    $window_seconds = max(1, (int) $window_seconds);
    $locked = $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', $lock_name, 1));
    if ('1' !== (string) $locked) return 'unavailable';
    try {
        $old = nmkr_analytics_option_read($state_key);
        if (null !== $old && (!is_array($old) || !isset($old['start'], $old['count'], $old['expires']))) {
            $deleted = $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $state_key, maybe_serialize($old)));
            wp_cache_delete($state_key, 'options');
            if (1 !== $deleted) return 'unavailable';
            $old = null;
        }
        if (null === $old || $now >= (int) $old['expires']) {
            $new = array('start' => $now, 'count' => 1, 'expires' => $now + $window_seconds);
            if (null === $old) {
                if (add_option($state_key, $new, '', false)) return 1 > max(0, (int) $max_events) ? 'quota' : 'allowed';
                return 'unavailable';
            }
        } else {
            $new = $old;
            $new['count'] = (int) $old['count'] + 1;
        }
        $updated = $wpdb->query($wpdb->prepare("UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s", maybe_serialize($new), $state_key));
        wp_cache_delete($state_key, 'options');
        if (1 !== $updated) return 'unavailable';
        return $new['count'] > max(0, (int) $max_events) ? 'quota' : 'allowed';
    } finally {
        $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
    }
}

/** Decode a compact transport body with a deliberate plugin-level byte limit. */
function nmkr_analytics_decode_body($raw) {
    if (!is_string($raw)) return array('status' => 400, 'body' => null);
    if (strlen($raw) > NMKR_ANALYTICS_RAW_BODY_MAX_BYTES) return array('status' => 413, 'body' => null);
    $body = json_decode($raw, true);
    if (JSON_ERROR_NONE !== json_last_error() || !is_array($body) || array_values($body) === $body) return array('status' => 400, 'body' => null);
    return array('status' => 200, 'body' => $body);
}

// Do not edit existing helpers above.
if ( ! function_exists( 'nmkr_ga4_send_event' ) ) {
    /**
     * Fire-and-forget GA4 Measurement Protocol request.
     *
     * @param string $measurement_id e.g. "G-XXXXXXXXXX"
     * @param string $api_secret     GA4 API secret from Data Streams
     * @param string $client_id      Pseudonymous client/session id
     * @param string $event_name     "view_item" | "select_item" | ...
     * @param array  $params         GA4 event params
     */
    function nmkr_ga4_send_event( $measurement_id, $api_secret, $client_id, $event_name, array $params ) {
        if ( empty( $measurement_id ) || empty( $api_secret ) || empty( $client_id ) || empty( $event_name ) ) {
            return;
        }

        $endpoint = add_query_arg(
            array(
                'measurement_id' => $measurement_id,
                'api_secret'     => $api_secret,
            ),
            'https://www.google-analytics.com/mp/collect'
        );

        $body = array(
            'client_id' => (string) $client_id,
            'events'    => array(
                array(
                    'name'   => $event_name,
                    'params' => $params,
                ),
            ),
        );

        wp_remote_post( $endpoint, array(
            'timeout'  => 1,
            'blocking' => false,
            'headers'  => array( 'Content-Type' => 'application/json' ),
            'body'     => wp_json_encode( $body ),
        ) );
    }
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
    $event_type = isset($body['event_type']) && is_string($body['event_type']) ? $body['event_type'] : '';
    if (!in_array($event_type, array('view', 'click'), true)) return new WP_REST_Response(null, 400);
    $shortcode  = isset( $body['shortcode'] ) && is_string($body['shortcode']) ? strtolower($body['shortcode']) : '';
    $allowed_sc = [ 'grid', 'list', 'carousel', 'token', 'project' ];
    if ( ! in_array( $shortcode, $allowed_sc, true ) ) {
        return new WP_REST_Response( null, 400 );
    }
    $project_uid = isset($body['project_uid']) && is_string($body['project_uid']) ? $body['project_uid'] : '';
    $token_uid   = isset($body['token_uid']) && is_string($body['token_uid']) ? $body['token_uid'] : '';
    $element_id  = isset($body['element_id']) && is_string($body['element_id']) ? $body['element_id'] : '';
    $session_id  = isset($body['session_id']) && is_string($body['session_id']) ? strtolower($body['session_id']) : '';
    $ts_client   = isset( $body['ts_client'] )   ? intval( $body['ts_client'] ) : time();

    if (strlen($project_uid) > 64 || ($project_uid !== '' && !preg_match('/^[a-zA-Z0-9_-]+$/', $project_uid)) ||
        strlen($token_uid) > 64 || ($token_uid !== '' && !preg_match('/^[a-zA-Z0-9_-]+$/', $token_uid)) ||
        $element_id === '' || strlen($element_id) > 128 || !preg_match('/^[a-zA-Z0-9:_-]+$/', $element_id) ||
        !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/', $session_id)) {
        return new WP_REST_Response( null, 400 );
    }

    $page_url = isset($body['page_url']) && is_string($body['page_url']) ? $body['page_url'] : '';
    if (strlen($page_url) > 255) return new WP_REST_Response(null, 400);
    $meta = isset($body['meta']) ? $body['meta'] : array();
    $nodes = 0;
    $meta_valid = true;
    $meta = nmkr_prepare_analytics_metadata($meta, 0, $nodes, $meta_valid);
    if (!$meta_valid) return new WP_REST_Response(null, 400);

    // 2) Settings gates
    if ( function_exists('nmkr_is_analytics_enabled') && ! nmkr_is_analytics_enabled() ) {
        return new WP_REST_Response( null, 204 );
    }
    if ( function_exists('nmkr_should_track_logged_in_users') && ! nmkr_should_track_logged_in_users() && is_user_logged_in() ) {
        return new WP_REST_Response( null, 204 );
    }

	// 3) Origin check (same host; normalize hostnames and ignore port)
	$host_expected = parse_url( home_url(), PHP_URL_HOST );
	$origin        = isset( $_SERVER['HTTP_ORIGIN'] ) ? (string) $_SERVER['HTTP_ORIGIN'] : '';
	$origin_h      = $origin ? parse_url( $origin, PHP_URL_HOST ) : '';
	$host_hdr      = isset( $_SERVER['HTTP_HOST'] ) ? (string) $_SERVER['HTTP_HOST'] : '';
	$xfh_raw       = isset( $_SERVER['HTTP_X_FORWARDED_HOST'] ) ? (string) $_SERVER['HTTP_X_FORWARDED_HOST'] : '';

	$normalize_host = function( $h ) {
		$h = strtolower( (string) $h );
		// If comma-separated (proxies), take first
		if ( strpos( $h, ',' ) !== false ) {
			$parts = explode( ',', $h );
			$h = trim( $parts[0] );
		}
		// Strip port
		$h = preg_replace( '/:\\d+$/', '', $h );
		// Strip leading www.
		if ( strpos( $h, 'www.' ) === 0 ) {
			$h = substr( $h, 4 );
		}
		return $h;
	};

	$expected = $normalize_host( $host_expected );
	$origin_n = $normalize_host( $origin_h );
	$host_n   = $normalize_host( $host_hdr );
	$xfh_n    = $normalize_host( $xfh_raw );

	// If Origin header is present and does not match, block
	if ( $origin && $origin_h && $origin_n !== $expected ) {
		return new WP_REST_Response( null, 403 );
	}
	// Otherwise, allow if any presented host header matches expected; else block
	if ( $host_hdr || $xfh_raw ) {
		if ( $host_n !== $expected && $xfh_n !== $expected ) {
			return new WP_REST_Response( null, 403 );
		}
	}

    // Consent, DNT, and sampling (server-side guardrails)
    $requires_consent   = function_exists('nmkr_analytics_requires_consent') ? nmkr_analytics_requires_consent() : false;
    $has_consent_cookie = ( isset($_COOKIE['nmkr_analytics_consent']) && sanitize_text_field($_COOKIE['nmkr_analytics_consent']) === '1' );

    if ( $requires_consent && ! $has_consent_cookie ) {
        return new WP_REST_Response( null, 204 );
    }

    // Respect browser Do Not Track when present
    if ( isset($_SERVER['HTTP_DNT']) && $_SERVER['HTTP_DNT'] === '1' ) {
        return new WP_REST_Response( null, 204 );
    }

    // Simple sampling (drop events when random exceeds sample rate)
    $sample_rate = function_exists('nmkr_get_analytics_sample_rate') ? nmkr_get_analytics_sample_rate() : 1.0;
    if ( $sample_rate < 1.0 ) {
        // Use a stable-ish seed if session available; else random
        $seed = $session_id ?: (string) random_int(0, PHP_INT_MAX);
        // 0 <= hash_mod < 10000
        $hash_mod = hexdec( substr( md5( $seed . ':nmkr' ), 0, 4 ) ) % 10000;
        if ( $hash_mod >= (int) round( $sample_rate * 10000 ) ) {
            return new WP_REST_Response( null, 204 );
        }
    }

    // 4) Prepare metadata (server side)
    $ua        = isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( (string) $_SERVER['HTTP_USER_AGENT'], 0, 255 ) : '';
    $ref       = isset( $_SERVER['HTTP_REFERER'] )     ? substr( (string) $_SERVER['HTTP_REFERER'], 0, 255 ) : '';

    // 5) Anonymize IP and derive user id if allowed
    $ip_hash_hex = '';
    if ( function_exists('nmkr_hash_ip_address') ) {
        $ip_hash_hex = nmkr_hash_ip_address(); // hex string
    }
    $user_id = ( function_exists('nmkr_get_analytics_user_id') ? nmkr_get_analytics_user_id() : 0 );

    // 6) Rate-limit & dedupe
    $rate_outcome = nmkr_analytics_rate_limit_outcome( $ip_hash_hex );
    if ( 'quota' === $rate_outcome ) {
        return new WP_REST_Response( null, 429 );
    }
    if ( 'allowed' !== $rate_outcome ) {
        return new WP_REST_Response( null, 503 );
    }
    $claim = nmkr_analytics_dedupe_claim($session_id, $element_id, $event_type);
    if (null === $claim['token']) {
        return new WP_REST_Response( null, 503 );
    }
    if (false === $claim['token']) {
        return new WP_REST_Response( null, 204 );
    }

    // Make admission durable before invoking either sink. This provides at-most-once
    // sink invocation even if a worker crashes; sink failure may therefore lose an
    // event rather than making already-committed work admissible again.
    if (!nmkr_analytics_claim_finalize($claim['key'], $claim['token'], $claim['ttl'])) {
        return new WP_REST_Response(null, 500);
    }

    // Resolve mode and GA4 credentials from options (already fetched nearby)
    $options = get_option( 'nmkr_connect_options', array() );
    $mode    = isset( $options['analytics_mode'] ) ? $options['analytics_mode'] : 'custom';
    if ( $mode === 'off' ) {
        nmkr_analytics_claim_release($claim['key'], $claim['token']);
        return new WP_REST_Response( null, 204 );
    }

    $measurement_id = isset( $options['nmkr_ga4_measurement_id'] ) ? trim( $options['nmkr_ga4_measurement_id'] ) : '';
    $api_secret     = isset( $options['nmkr_ga4_api_secret'] ) ? trim( $options['nmkr_ga4_api_secret'] ) : '';
    $ga4_active     = in_array( $mode, array( 'ga4', 'both' ), true ) && ! empty( $measurement_id ) && ! empty( $api_secret );

    // Compute GA4 client_id: prefer session_id; fallback to hashed IP hex if present
    $client_id = ! empty( $session_id ) ? $session_id : ( ! empty( $ip_hash_hex ) ? $ip_hash_hex : '' );

    // Build minimal GA4 event payload and dispatch
    if ( $ga4_active && ! empty( $client_id ) ) {
        $ga_event = null;
        $params   = array();

        // Minimal privacy-friendly context
        if ( ! empty( $page_url ) ) {
            $params['page_location'] = $page_url;
        }
        if ( ! empty( $ref ) ) {
            $params['page_referrer'] = $ref;
        }
        if ( ! empty( $shortcode ) ) {
            $params['item_list_name'] = $shortcode;
        }
        $params['engagement_time_msec'] = 1;

        // Items array with token/project identifiers when available
        $items = array();
        if ( ! empty( $token_uid ) ) {
            $items[] = array( 'item_id' => (string) $token_uid );
        }
        if ( ! empty( $project_uid ) ) {
            $items[] = array( 'item_id' => 'project:' . (string) $project_uid );
        }
        if ( ! empty( $items ) ) {
            $params['items'] = $items;
        }

        // Map event types
        if ( $event_type === 'view' ) {
            $ga_event = 'view_item';
        } elseif ( $event_type === 'click' ) {
            $ga_event = 'select_item';
        }

        if ( $ga_event ) {
            nmkr_ga4_send_event( $measurement_id, $api_secret, $client_id, $ga_event, $params );
        }
    }

    $should_insert_db = ( $mode === 'custom' || $mode === 'both' );

    // 8) Insert into DB only if allowed by mode
    if ( $should_insert_db ) {
        $table = $wpdb->prefix . 'nmkr_analytics';
        // Convert hex to binary for storage in BINARY(32) if present
        $ip_hash_bin = '';
        if ( ! empty( $ip_hash_hex ) && ctype_xdigit( $ip_hash_hex ) && strlen( $ip_hash_hex ) === 64 ) {
            $ip_hash_bin = pack('H*', $ip_hash_hex);
        }
        $data = [
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
        ];
        $formats = [
            '%s','%s','%s','%s','%s',
            ( $user_id ? '%d' : '%s' ), // allow NULL for user_id
            '%s','%s','%s','%s','%s','%s'
        ];
        $insert = $wpdb->insert( $table, $data, $formats );
        if ( false === $insert ) {
            return new WP_REST_Response( null, 500 );
        }
    }

    return new WP_REST_Response( null, 204 );
}
