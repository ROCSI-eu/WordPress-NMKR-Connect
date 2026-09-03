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
    $input = is_array($input) ? $input : array();
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
        $valid_profiles = array_keys(nmkr_get_sync_profiles());
        $sync_profile = sanitize_text_field($input['sync_profile']);
        $sanitized_input['sync_profile'] = in_array($sync_profile, $valid_profiles, true) ? $sync_profile : 'balanced';
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
    $debug_enabled = !empty($input['debug_enabled']);
    $has_destination = $debug_enabled && (!empty($input['log_to_debug_file']) || !empty($input['log_to_dashboard']));
    
    // Debug Enabled
    $sanitized_input['debug_enabled'] = $debug_enabled ? 1 : 0;

    // Debug destinations are dependent on the master debug toggle.
    $sanitized_input['log_to_debug_file'] = ($debug_enabled && !empty($input['log_to_debug_file'])) ? 1 : 0;
    $sanitized_input['log_to_dashboard'] = ($debug_enabled && !empty($input['log_to_dashboard'])) ? 1 : 0;

    // Log types and throttling require both master debug and at least one logging destination.
    $sanitized_input['api_debug_enabled'] = ($has_destination && !empty($input['api_debug_enabled'])) ? 1 : 0;
    $sanitized_input['sync_debug_enabled'] = ($has_destination && !empty($input['sync_debug_enabled'])) ? 1 : 0;
    $sanitized_input['ui_debug_enabled'] = ($has_destination && !empty($input['ui_debug_enabled'])) ? 1 : 0;
    $sanitized_input['performance_debug_enabled'] = ($has_destination && !empty($input['performance_debug_enabled'])) ? 1 : 0;
    $sanitized_input['log_throttle_enabled'] = ($has_destination && !empty($input['log_throttle_enabled'])) ? 1 : 0;

    // Log Retention Limit. Disabled controls are not submitted, so fall back to the default
    // whenever debug is off instead of preserving stale values from earlier saves.
    if (!$debug_enabled) {
        $sanitized_input['log_retention_limit'] = 100;
    } elseif (isset($input['log_retention_limit'])) {
        $sanitized_input['log_retention_limit'] = intval($input['log_retention_limit']);
        // Ensure it's within valid range
        $sanitized_input['log_retention_limit'] = max(1, min(1000, $sanitized_input['log_retention_limit']));
    } else {
        $sanitized_input['log_retention_limit'] = 100; // Default value
    }
    
    // Sanitize Analytics Settings
    
    // Analytics Mode
    if (isset($input['analytics_mode'])) {
        $valid_modes = array('off', 'custom', 'ga4', 'both');
        $sanitized_input['analytics_mode'] = in_array($input['analytics_mode'], $valid_modes, true) ? $input['analytics_mode'] : 'off';
    } elseif (isset($existing_options['analytics_mode'])) {
        $sanitized_input['analytics_mode'] = $existing_options['analytics_mode'];
    } else {
        $sanitized_input['analytics_mode'] = 'off'; // Safe default
    }
    
    // Analytics Retention Days
    if (isset($input['analytics_retention_days'])) {
        $sanitized_input['analytics_retention_days'] = intval($input['analytics_retention_days']);
        // Ensure it's within valid range
        $sanitized_input['analytics_retention_days'] = max(7, min(365, $sanitized_input['analytics_retention_days']));
    } elseif (isset($existing_options['analytics_retention_days'])) {
        $sanitized_input['analytics_retention_days'] = $existing_options['analytics_retention_days'];
    } else {
        $sanitized_input['analytics_retention_days'] = 90; // Default value
    }
    
    // Analytics Track Logged In
    $sanitized_input['analytics_track_logged_in'] = isset($input['analytics_track_logged_in']) ? 1 : 0;
    
    // Analytics Require Consent
    $sanitized_input['analytics_require_consent'] = isset($input['analytics_require_consent']) ? 1 : 0;
    
    // Analytics Sample Rate
    if (isset($input['analytics_sample_rate'])) {
        $sanitized_input['analytics_sample_rate'] = floatval($input['analytics_sample_rate']);
        // Ensure it's within valid range and round to 2 decimals
        $sanitized_input['analytics_sample_rate'] = round(max(0, min(1, $sanitized_input['analytics_sample_rate'])), 2);
    } elseif (isset($existing_options['analytics_sample_rate'])) {
        $sanitized_input['analytics_sample_rate'] = $existing_options['analytics_sample_rate'];
    } else {
        $sanitized_input['analytics_sample_rate'] = 1.0; // Default value
    }
    
    // Analytics Remove on Uninstall
    $sanitized_input['analytics_remove_on_uninstall'] = isset($input['analytics_remove_on_uninstall']) ? 1 : 0;
    
    // Analytics Debug
    $sanitized_input['analytics_debug'] = isset($input['analytics_debug']) ? 1 : 0;

    // GA4 Measurement ID (optional)
    if (isset($input['nmkr_ga4_measurement_id'])) {
        $mid = strtoupper(trim(sanitize_text_field($input['nmkr_ga4_measurement_id'])));
        $sanitized_input['nmkr_ga4_measurement_id'] = preg_match('/^G-[A-Z0-9]+$/', $mid) ? $mid : '';
    } elseif (isset($existing_options['nmkr_ga4_measurement_id'])) {
        $sanitized_input['nmkr_ga4_measurement_id'] = $existing_options['nmkr_ga4_measurement_id'];
    }

    // GA4 API Secret (optional)
    if (isset($input['nmkr_ga4_api_secret'])) {
        $sanitized_input['nmkr_ga4_api_secret'] = sanitize_text_field($input['nmkr_ga4_api_secret']);
    } elseif (isset($existing_options['nmkr_ga4_api_secret'])) {
        $sanitized_input['nmkr_ga4_api_secret'] = $existing_options['nmkr_ga4_api_secret'];
    }
    
    // Future-proof: merge with existing options so unknown/future keys aren’t dropped on save.
    if ( ! isset( $existing_options ) || ! is_array( $existing_options ) ) {
        $existing_options = get_option( 'nmkr_connect_options', array() );
    }
    $sanitized_input = array_merge( (array) $existing_options, (array) $sanitized_input );

    if (function_exists('nmkr_trim_dashboard_logs_to_retention')) {
        nmkr_trim_dashboard_logs_to_retention($sanitized_input['log_retention_limit']);
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
        'log_throttle_enabled' => 0,
        'log_retention_limit' => 100,
        'analytics_mode' => 'off',
        'analytics_retention_days' => 90,
        'analytics_track_logged_in' => 0,
        'analytics_require_consent' => 1,
        'analytics_sample_rate' => 1.0,
        'analytics_remove_on_uninstall' => 1,
        'analytics_debug' => 0
    );
    
    return $defaults;
}
