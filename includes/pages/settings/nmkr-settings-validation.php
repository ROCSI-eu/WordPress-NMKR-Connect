<?php
/**
 * NMKR Connect - Settings Validation Module
 *
 * Handles the validation, sanitization, and default values for plugin settings.
 *
 * @package NMKR Connect
 * @subpackage Settings
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Sanitize and validate options before saving to database
 *
 * @param array $input The raw options data from the form
 * @return array Sanitized options data
 */
function nmkr_connect_sanitize_options($input) {
    $sanitized_input = array();
    
    // Preserve existing options that might not be in this input
    $existing_options = get_option('nmkr_connect_options', array());
    
    // Sanitize API key
    if (isset($input['api_key'])) {
        $sanitized_input['api_key'] = sanitize_text_field($input['api_key']);
    } elseif (isset($existing_options['api_key'])) {
        $sanitized_input['api_key'] = $existing_options['api_key'];
    }
    
    // Sanitize Sync Settings
    
    // Sync Profile
    if (isset($input['sync_profile'])) {
        $sanitized_input['sync_profile'] = sanitize_text_field($input['sync_profile']);
    } elseif (isset($existing_options['sync_profile'])) {
        $sanitized_input['sync_profile'] = $existing_options['sync_profile'];
    } else {
        $sanitized_input['sync_profile'] = 'balanced';
    }
    
    // Batch Size
    if (isset($input['sync_batch_size'])) {
        $sanitized_input['sync_batch_size'] = intval($input['sync_batch_size']);
        // Ensure it's within valid range
        $sanitized_input['sync_batch_size'] = max(1, min(10, $sanitized_input['sync_batch_size']));
    } elseif (isset($existing_options['sync_batch_size'])) {
        $sanitized_input['sync_batch_size'] = $existing_options['sync_batch_size'];
    } else {
        $sanitized_input['sync_batch_size'] = 5; // Default value
    }
    
    // Batch Delay
    if (isset($input['sync_batch_delay'])) {
        $sanitized_input['sync_batch_delay'] = intval($input['sync_batch_delay']);
        // Ensure it's within valid range
        $sanitized_input['sync_batch_delay'] = max(1, min(10, $sanitized_input['sync_batch_delay']));
    } elseif (isset($existing_options['sync_batch_delay'])) {
        $sanitized_input['sync_batch_delay'] = $existing_options['sync_batch_delay'];
    } else {
        $sanitized_input['sync_batch_delay'] = 2; // Default value
    }
    
    // Initial Interval
    if (isset($input['sync_initial_interval'])) {
        $sanitized_input['sync_initial_interval'] = intval($input['sync_initial_interval']);
        // Ensure it's within valid range
        $sanitized_input['sync_initial_interval'] = max(100, min(5000, $sanitized_input['sync_initial_interval']));
    } elseif (isset($existing_options['sync_initial_interval'])) {
        $sanitized_input['sync_initial_interval'] = $existing_options['sync_initial_interval'];
    } else {
        $sanitized_input['sync_initial_interval'] = 1000; // Default value
    }
    
    // Max Interval
    if (isset($input['sync_max_interval'])) {
        $sanitized_input['sync_max_interval'] = intval($input['sync_max_interval']);
        // Ensure it's within valid range
        $sanitized_input['sync_max_interval'] = max(1000, min(30000, $sanitized_input['sync_max_interval']));
    } elseif (isset($existing_options['sync_max_interval'])) {
        $sanitized_input['sync_max_interval'] = $existing_options['sync_max_interval'];
    } else {
        $sanitized_input['sync_max_interval'] = 30000; // Default value
    }
    
    // Interval Increase
    if (isset($input['sync_interval_increase'])) {
        $sanitized_input['sync_interval_increase'] = floatval($input['sync_interval_increase']);
        // Ensure it's within valid range
        $sanitized_input['sync_interval_increase'] = max(1.1, min(3, $sanitized_input['sync_interval_increase']));
    } elseif (isset($existing_options['sync_interval_increase'])) {
        $sanitized_input['sync_interval_increase'] = $existing_options['sync_interval_increase'];
    } else {
        $sanitized_input['sync_interval_increase'] = 2.0; // Default value
    }
    
    // Interval Decrease
    if (isset($input['sync_interval_decrease'])) {
        $sanitized_input['sync_interval_decrease'] = floatval($input['sync_interval_decrease']);
        // Ensure it's within valid range
        $sanitized_input['sync_interval_decrease'] = max(0.1, min(0.9, $sanitized_input['sync_interval_decrease']));
    } elseif (isset($existing_options['sync_interval_decrease'])) {
        $sanitized_input['sync_interval_decrease'] = $existing_options['sync_interval_decrease'];
    } else {
        $sanitized_input['sync_interval_decrease'] = 0.5; // Default value
    }
    
    // Max Errors
    if (isset($input['sync_max_errors'])) {
        $sanitized_input['sync_max_errors'] = intval($input['sync_max_errors']);
        // Ensure it's within valid range
        $sanitized_input['sync_max_errors'] = max(1, min(10, $sanitized_input['sync_max_errors']));
    } elseif (isset($existing_options['sync_max_errors'])) {
        $sanitized_input['sync_max_errors'] = $existing_options['sync_max_errors'];
    } else {
        $sanitized_input['sync_max_errors'] = 3; // Default value
    }
    
    // Sanitize Debug Settings
    
    // Debug Enabled
    $sanitized_input['debug_enabled'] = isset($input['debug_enabled']) ? 1 : 0;
    
    // Log to Debug File - independent toggle
    $sanitized_input['log_to_debug_file'] = isset($input['log_to_debug_file']) ? 1 : 0;
    
    // Log to Dashboard - independent toggle
    $sanitized_input['log_to_dashboard'] = isset($input['log_to_dashboard']) ? 1 : 0;
    
    // API Debug Enabled - only enable if debug_enabled is also enabled
    $sanitized_input['api_debug_enabled'] = (isset($input['debug_enabled']) && $input['debug_enabled'] && isset($input['api_debug_enabled'])) ? 1 : 0;
    
    // WP Debug Enabled - only enable if debug_enabled is also enabled
    $sanitized_input['sync_debug_enabled'] = (isset($input['debug_enabled']) && $input['debug_enabled'] && isset($input['sync_debug_enabled'])) ? 1 : 0;
    
    // UI Debug Enabled - only enable if debug_enabled is also enabled
    $sanitized_input['ui_debug_enabled'] = (isset($input['debug_enabled']) && $input['debug_enabled'] && isset($input['ui_debug_enabled'])) ? 1 : 0;
    
    // Performance Debug Enabled - only enable if debug_enabled is also enabled
    $sanitized_input['performance_debug_enabled'] = (isset($input['debug_enabled']) && $input['debug_enabled'] && isset($input['performance_debug_enabled'])) ? 1 : 0;
    
    // Log Throttle Enabled - only enable if debug_enabled is also enabled
    $sanitized_input['log_throttle_enabled'] = (isset($input['debug_enabled']) && $input['debug_enabled'] && isset($input['log_throttle_enabled'])) ? 1 : 0;
    
    // Log Retention Limit
    if (isset($input['log_retention_limit'])) {
        $sanitized_input['log_retention_limit'] = intval($input['log_retention_limit']);
        // Ensure it's within valid range
        $sanitized_input['log_retention_limit'] = max(1, min(1000, $sanitized_input['log_retention_limit']));
    } elseif (isset($existing_options['log_retention_limit'])) {
        $sanitized_input['log_retention_limit'] = $existing_options['log_retention_limit'];
    } else {
        $sanitized_input['log_retention_limit'] = 100; // Default value
    }
    
    return $sanitized_input;
}

/**
 * Get default settings for the plugin
 * 
 * @return array Default settings
 */
function nmkr_get_default_settings() {
    // Get the balanced profile settings
    $profiles = nmkr_get_sync_profiles();
    $balanced_profile = $profiles['balanced']['settings'];
    
    // Build default settings array
    $defaults = array(
        'api_key' => '',
        'sync_profile' => 'balanced',
        'sync_batch_size' => $balanced_profile['sync_batch_size'],
        'sync_batch_delay' => $balanced_profile['sync_batch_delay'],
        'sync_initial_interval' => $balanced_profile['sync_initial_interval'],
        'sync_max_interval' => $balanced_profile['sync_max_interval'],
        'sync_interval_increase' => $balanced_profile['sync_interval_increase'],
        'sync_interval_decrease' => $balanced_profile['sync_interval_decrease'],
        'sync_max_errors' => $balanced_profile['sync_max_errors'],
        'debug_enabled' => 0,
        'log_to_debug_file' => 0,
        'log_to_dashboard' => 0,
        'api_debug_enabled' => 0,
        'sync_debug_enabled' => 0,
        'ui_debug_enabled' => 0,
        'performance_debug_enabled' => 0,
        'log_retention_limit' => 100
    );
    
    return $defaults;
} 