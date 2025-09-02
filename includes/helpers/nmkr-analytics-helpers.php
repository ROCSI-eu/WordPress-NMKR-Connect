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
function nmkr_hash_ip_address($ip_address) {
    if (empty($ip_address) || !is_string($ip_address)) {
        return '';
    }
    
    // Use HMAC-SHA256 with plugin-specific pepper
    $pepper = nmkr_get_analytics_pepper();
    return hash_hmac('sha256', $ip_address, $pepper, true);
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
