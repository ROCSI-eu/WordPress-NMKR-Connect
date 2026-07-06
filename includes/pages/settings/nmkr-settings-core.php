<?php
/**
 * NMKR Connect - Core Settings Module
 *
 * Handles the initialization, registration, and rendering of the main settings page.
 *
 * @package NMKR Connect
 * @subpackage Settings
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin file constant if not already defined
if (!defined('NMKR_CONNECT_PLUGIN_FILE')) {
    define('NMKR_CONNECT_PLUGIN_FILE', dirname(dirname(dirname(dirname(__FILE__)))) . '/nmkr-connect.php');
}

// Include the other settings modules
require_once dirname(__FILE__) . '/nmkr-settings-sections.php';
require_once dirname(__FILE__) . '/nmkr-settings-validation.php';

/**
 * Register all settings for the NMKR Connect plugin
 */
function nmkr_connect_register_settings() {
    // Register the main settings group
    register_setting(
        'nmkr_connect_settings_group',
        'nmkr_connect_options',
        'nmkr_connect_sanitize_options'
    );

    // Add API Settings section
    add_settings_section(
        'nmkr_connect_api_section',
        'API Settings',
        'nmkr_connect_api_section_callback',
        'nmkr-connect-settings'
    );

    // Add API Key field
    add_settings_field(
        'nmkr_api_key',
        'API Key',
        'nmkr_api_key_field_callback',
        'nmkr-connect-settings',
        'nmkr_connect_api_section'
    );

    // Add Synchronization Settings section
    add_settings_section(
        'nmkr_connect_sync_section',
        'Synchronization Settings',
        'nmkr_connect_sync_section_callback',
        'nmkr-connect-settings'
    );

    // Add Synchronization Profile field
    add_settings_field(
        'nmkr_sync_profile',
        'Synchronization Profile',
        'nmkr_sync_profile_field_callback',
        'nmkr-connect-settings',
        'nmkr_connect_sync_section'
    );

    // Add Batch Size field
    add_settings_field(
        'nmkr_sync_batch_size',
        'Batch Size',
        'nmkr_sync_batch_size_field_callback',
        'nmkr-connect-settings',
        'nmkr_connect_sync_section'
    );

    // Add Batch Delay field
    add_settings_field(
        'nmkr_sync_batch_delay',
        'Delay Between Batches (seconds)',
        'nmkr_sync_batch_delay_field_callback',
        'nmkr-connect-settings',
        'nmkr_connect_sync_section'
    );

    // Add Initial Polling Interval field
    add_settings_field(
        'nmkr_sync_initial_interval',
        'Initial Polling Interval (ms)',
        'nmkr_sync_initial_interval_field_callback',
        'nmkr-connect-settings',
        'nmkr_connect_sync_section'
    );

    // Add Maximum Polling Interval field
    add_settings_field(
        'nmkr_sync_max_interval',
        'Maximum Polling Interval (ms)',
        'nmkr_sync_max_interval_field_callback',
        'nmkr-connect-settings',
        'nmkr_connect_sync_section'
    );

    // Add Interval Increase Factor field
    add_settings_field(
        'nmkr_sync_interval_increase',
        'Interval Increase Factor',
        'nmkr_sync_interval_increase_field_callback',
        'nmkr-connect-settings',
        'nmkr_connect_sync_section'
    );

    // Add Interval Decrease Factor field
    add_settings_field(
        'nmkr_sync_interval_decrease',
        'Interval Decrease Factor',
        'nmkr_sync_interval_decrease_field_callback',
        'nmkr-connect-settings',
        'nmkr_connect_sync_section'
    );

    // Add Maximum Error Count field
    add_settings_field(
        'nmkr_sync_max_errors',
        'Maximum Error Count',
        'nmkr_sync_max_errors_field_callback',
        'nmkr-connect-settings',
        'nmkr_connect_sync_section'
    );

    // Add WordPress Debug Settings section
    add_settings_section(
        'nmkr_connect_wp_debug_section',
        'Debug Settings',
        'nmkr_connect_wp_debug_section_callback',
        'nmkr-connect-settings'
    );

    // Add General Debug toggle
    add_settings_field(
        'nmkr_debug_enabled',
        'Enable Debug Logging Controls',
        'nmkr_debug_enabled_field_callback',
        'nmkr-connect-settings',
        'nmkr_connect_wp_debug_section'
    );

    // Add Log to Debug File toggle
    add_settings_field(
        'nmkr_log_to_debug_file',
        'Enable Logging to debug.log',
        'nmkr_log_to_debug_file_field_callback',
        'nmkr-connect-settings',
        'nmkr_connect_wp_debug_section'
    );

    // Add Log to Dashboard toggle
    add_settings_field(
        'nmkr_log_to_dashboard',
        'Enable Logging to Dashboard Logs',
        'nmkr_log_to_dashboard_field_callback',
        'nmkr-connect-settings',
        'nmkr_connect_wp_debug_section'
    );

    // Add API Connection Status Debug toggle
    add_settings_field(
        'nmkr_api_debug_enabled',
        'Enable API Connection Status Logging',
        'nmkr_api_debug_enabled_field_callback',
        'nmkr-connect-settings',
        'nmkr_connect_wp_debug_section'
    );

    // Add WordPress Debug toggle
    add_settings_field(
        'nmkr_sync_debug_enabled',
        'Enable Data Synchronization Logging',
        'nmkr_sync_debug_enabled_field_callback',
        'nmkr-connect-settings',
        'nmkr_connect_wp_debug_section'
    );

    // Add UI Debug toggle
    add_settings_field(
        'nmkr_ui_debug_enabled',
        'Enable User Interface Status Logging',
        'nmkr_ui_debug_enabled_field_callback',
        'nmkr-connect-settings',
        'nmkr_connect_wp_debug_section'
    );

    // Add Performance Debug toggle
    add_settings_field(
        'nmkr_performance_debug_enabled',
        'Enable Performance Logging',
        'nmkr_performance_debug_enabled_field_callback',
        'nmkr-connect-settings',
        'nmkr_connect_wp_debug_section'
    );

    // Add Log Throttle toggle
    add_settings_field(
        'nmkr_log_throttle_enabled',
        'Throttle Log Output (Recommended)',
        'nmkr_log_throttle_enabled_field_callback',
        'nmkr-connect-settings',
        'nmkr_connect_wp_debug_section'
    );

    // Add Log Retention Limit field
    add_settings_field(
        'nmkr_log_retention_limit',
        'Log Retention Limit',
        'nmkr_log_retention_limit_field_callback',
        'nmkr-connect-settings',
        'nmkr_connect_wp_debug_section'
    );

    // Add Analytics & Privacy Settings section
    add_settings_section(
        'nmkr_analytics_section',
        __('Analytics & Privacy', 'nmkr-connect'),
        'nmkr_connect_analytics_section_callback',
        'nmkr-connect-settings'
    );

    // Add Analytics Mode field
    add_settings_field(
        'nmkr_analytics_mode',
        'Analytics Mode',
        'nmkr_analytics_mode_field_callback',
        'nmkr-connect-settings',
        'nmkr_analytics_section'
    );

    // Add GA4 Measurement ID field
    add_settings_field(
        'nmkr_ga4_measurement_id',
        'GA4 Measurement ID',
        'nmkr_ga4_measurement_id_field_callback',
        'nmkr-connect-settings',
        'nmkr_analytics_section'
    );

    // Add GA4 API Secret field
    add_settings_field(
        'nmkr_ga4_api_secret',
        'GA4 API Secret',
        'nmkr_ga4_api_secret_field_callback',
        'nmkr-connect-settings',
        'nmkr_analytics_section'
    );

    // Add Analytics Retention Days field
    add_settings_field(
        'nmkr_analytics_retention_days',
        'Data Retention (Days)',
        'nmkr_analytics_retention_days_field_callback',
        'nmkr-connect-settings',
        'nmkr_analytics_section'
    );

    // Add Track Logged In Users field
    add_settings_field(
        'nmkr_analytics_track_logged_in',
        'Track Logged In Users',
        'nmkr_analytics_track_logged_in_field_callback',
        'nmkr-connect-settings',
        'nmkr_analytics_section'
    );

    // Add Require Consent field
    add_settings_field(
        'nmkr_analytics_require_consent',
        'Require User Consent',
        'nmkr_analytics_require_consent_field_callback',
        'nmkr-connect-settings',
        'nmkr_analytics_section'
    );

    // Add Sample Rate field
    add_settings_field(
        'nmkr_analytics_sample_rate',
        'Sample Rate',
        'nmkr_analytics_sample_rate_field_callback',
        'nmkr-connect-settings',
        'nmkr_analytics_section'
    );

    // Add Remove on Uninstall field
    add_settings_field(
        'nmkr_analytics_remove_on_uninstall',
        'Remove Data on Uninstall',
        'nmkr_analytics_remove_on_uninstall_field_callback',
        'nmkr-connect-settings',
        'nmkr_analytics_section'
    );

    // Add Analytics Debug field
    add_settings_field(
        'nmkr_analytics_debug',
        'Enable Analytics Debug',
        'nmkr_analytics_debug_field_callback',
        'nmkr-connect-settings',
        'nmkr_analytics_section'
    );
}
add_action('admin_init', 'nmkr_connect_register_settings');

/**
 * Set the capability required to save NMKR Connect settings.
 *
 * @param string $capability Default option page capability.
 * @return string Required capability for NMKR Connect settings saves.
 */
function nmkr_connect_settings_option_page_capability( $capability ) {
    return 'nmkr_manage_settings';
}
add_filter(
    'option_page_capability_nmkr_connect_settings_group',
    'nmkr_connect_settings_option_page_capability'
);

/**
 * Add the settings page to WordPress Settings menu
 */
function nmkr_connect_add_settings_page() {
    add_options_page(
        'NMKR Connect Settings',
        'NMKR Connect',
        'nmkr_manage_settings',
        'nmkr-connect-settings',
        'nmkr_connect_settings_page'
    );
}
add_action('admin_menu', 'nmkr_connect_add_settings_page');

/**
 * Add settings link to the plugins page
 */
function nmkr_connect_add_settings_link($links) {
    $settings_link = '<a href="options-general.php?page=nmkr-connect-settings">' . __('Settings') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(NMKR_CONNECT_PLUGIN_FILE), 'nmkr_connect_add_settings_link');

/**
 * Render the main settings page
 */
function nmkr_connect_settings_page() {
    if ( ! current_user_can( 'nmkr_manage_settings' ) ) {
        if ( function_exists( 'nmkr_render_access_denied_page' ) ) {
            nmkr_render_access_denied_page( __( 'NMKR Settings', 'nmkr-connect' ) );
            return;
        }
        wp_die( esc_html__( 'Access denied.', 'nmkr-connect' ) );
    }
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <form action="options.php" method="post">
            <?php
            settings_fields('nmkr_connect_settings_group');
            do_settings_sections('nmkr-connect-settings');
            ?>
            <div class="submit">
                <?php submit_button('Save Settings', 'primary', 'submit', false); ?>
                <button type="button" id="nmkr-reset-defaults" class="button button-secondary">
                    Reset to Defaults
                </button>
            </div>
        </form>
    </div>

    <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            const toggleButton = document.getElementById('toggle_api_key_visibility');
            const apiKeyInput = document.getElementById('nmkr_api_key');
            
            if (!toggleButton || !apiKeyInput) {
                return;
            }

            toggleButton.addEventListener('click', function() {
                if (apiKeyInput.type === 'password') {
                    apiKeyInput.type = 'text';
                    toggleButton.textContent = 'Hide';
                } else {
                    apiKeyInput.type = 'password';
                    toggleButton.textContent = 'Show';
                }
            });
        });
    </script>

    <script>
    jQuery(document).ready(function($) {
        $('#nmkr-reset-defaults').on('click', function() {
            if (confirm('Are you sure you want to reset all settings to their default values?')) {
                // Store current API key
                const currentApiKey = $('#nmkr_api_key').val();
                
                // Reset Sync Settings
                $('#nmkr_sync_profile').val('balanced');
                $('#nmkr_sync_batch_size').val('5');
                $('#nmkr_sync_batch_delay').val('2');
                $('input[name="nmkr_connect_options[sync_initial_interval]"]').val('1000');
                $('input[name="nmkr_connect_options[sync_max_interval]"]').val('30000');
                $('input[name="nmkr_connect_options[sync_interval_increase]"]').val('2.0');
                $('input[name="nmkr_connect_options[sync_interval_decrease]"]').val('0.5');
                $('input[name="nmkr_connect_options[sync_max_errors]"]').val('3');
                
                // Reset Debug Settings - ensure they are disabled by default
                $('#nmkr_debug_enabled').prop('checked', false);
                $('#nmkr_log_to_debug_file').prop('checked', false).prop('disabled', true);
                $('#nmkr_log_to_dashboard').prop('checked', false).prop('disabled', true);
                $('#nmkr_api_debug_enabled').prop('checked', false).prop('disabled', true);
                $('#nmkr_sync_debug_enabled').prop('checked', false).prop('disabled', true);
                $('#nmkr_ui_debug_enabled').prop('checked', false).prop('disabled', true);
                $('#nmkr_performance_debug_enabled').prop('checked', false).prop('disabled', true);
                $('#nmkr_log_throttle_enabled').prop('checked', false).prop('disabled', true);
                $('#nmkr_log_retention_limit').val('100').prop('disabled', true);
                
                // Reset Analytics & Privacy Settings
                $('#nmkr_analytics_mode').val('custom');
                $('#nmkr_ga4_measurement_id').val('');
                $('#nmkr_ga4_api_secret').val('');
                $('#nmkr_analytics_retention_days').val('90');
                $('#nmkr_analytics_track_logged_in').prop('checked', false);
                $('#nmkr_analytics_require_consent').prop('checked', false);
                $('#nmkr_analytics_sample_rate').val('1');
                $('#nmkr_analytics_remove_on_uninstall').prop('checked', true);
                $('#nmkr_analytics_debug').prop('checked', false);

                // Restore API key
                $('#nmkr_api_key').val(currentApiKey);
                
                // Show success message
                alert('Settings have been reset to defaults. Click "Save Changes" to apply.');
            }
        });
    });
    </script>
    <?php
}
