<?php
/**
 * ROCSI Connector for NMKR - Core Settings Module
 *
 * Handles the initialization, registration, and rendering of the main settings page.
 *
 * @package ROCSI Connector for NMKR
 * @subpackage Settings
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin file constant if not already defined
if (!defined('NMKR_CONNECT_PLUGIN_FILE')) {
    define('NMKR_CONNECT_PLUGIN_FILE', dirname(dirname(dirname(dirname(__FILE__)))) . '/rocsi-connector-for-nmkr.php');
}

// Include the other settings modules
require_once dirname(__FILE__) . '/nmkr-settings-sections.php';
require_once dirname(__FILE__) . '/nmkr-settings-validation.php';

/**
 * Register all settings for the ROCSI Connector for NMKR plugin
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

    add_settings_section(
        'nmkr_connect_sync_advanced_section',
        'Advanced Synchronization Tuning',
        '__return_false',
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
        'nmkr_connect_sync_advanced_section'
    );

    // Add Batch Delay field
    add_settings_field(
        'nmkr_sync_batch_delay',
        'Delay Between Batches (seconds)',
        'nmkr_sync_batch_delay_field_callback',
        'nmkr-connect-settings',
        'nmkr_connect_sync_advanced_section'
    );

    // Add Initial Polling Interval field
    add_settings_field(
        'nmkr_sync_initial_interval',
        'Initial Polling Interval (ms)',
        'nmkr_sync_initial_interval_field_callback',
        'nmkr-connect-settings',
        'nmkr_connect_sync_advanced_section'
    );

    // Add Maximum Polling Interval field
    add_settings_field(
        'nmkr_sync_max_interval',
        'Maximum Polling Interval (ms)',
        'nmkr_sync_max_interval_field_callback',
        'nmkr-connect-settings',
        'nmkr_connect_sync_advanced_section'
    );

    // Add Interval Increase Factor field
    add_settings_field(
        'nmkr_sync_interval_increase',
        'Interval Increase Factor',
        'nmkr_sync_interval_increase_field_callback',
        'nmkr-connect-settings',
        'nmkr_connect_sync_advanced_section'
    );

    // Add Interval Decrease Factor field
    add_settings_field(
        'nmkr_sync_interval_decrease',
        'Interval Decrease Factor',
        'nmkr_sync_interval_decrease_field_callback',
        'nmkr-connect-settings',
        'nmkr_connect_sync_advanced_section'
    );

    // Add Maximum Error Count field
    add_settings_field(
        'nmkr_sync_max_errors',
        'Maximum Error Count',
        'nmkr_sync_max_errors_field_callback',
        'nmkr-connect-settings',
        'nmkr_connect_sync_advanced_section'
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
        __('Analytics & Privacy', 'rocsi-connector-for-nmkr'),
        'nmkr_connect_analytics_section_callback',
        'nmkr-connect-settings'
    );

    add_settings_section(
        'nmkr_analytics_ga4_section',
        __('GA4 Delivery', 'rocsi-connector-for-nmkr'),
        '__return_false',
        'nmkr-connect-settings'
    );

    add_settings_section(
        'nmkr_analytics_privacy_section',
        __('Privacy & Retention', 'rocsi-connector-for-nmkr'),
        '__return_false',
        'nmkr-connect-settings'
    );

    add_settings_section(
        'nmkr_analytics_debug_section',
        __('Analytics Diagnostics', 'rocsi-connector-for-nmkr'),
        '__return_false',
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
        'nmkr_analytics_ga4_section'
    );

    // Add GA4 API Secret field
    add_settings_field(
        'nmkr_ga4_api_secret',
        'GA4 API Secret',
        'nmkr_ga4_api_secret_field_callback',
        'nmkr-connect-settings',
        'nmkr_analytics_ga4_section'
    );

    // Add Analytics Retention Days field
    add_settings_field(
        'nmkr_analytics_retention_days',
        'Data Retention (Days)',
        'nmkr_analytics_retention_days_field_callback',
        'nmkr-connect-settings',
        'nmkr_analytics_privacy_section'
    );

    // Add Track Logged In Users field
    add_settings_field(
        'nmkr_analytics_track_logged_in',
        'Track Logged In Users',
        'nmkr_analytics_track_logged_in_field_callback',
        'nmkr-connect-settings',
        'nmkr_analytics_privacy_section'
    );

    // Add Require Consent field
    add_settings_field(
        'nmkr_analytics_require_consent',
        'Require User Consent',
        'nmkr_analytics_require_consent_field_callback',
        'nmkr-connect-settings',
        'nmkr_analytics_privacy_section'
    );

    // Add Sample Rate field
    add_settings_field(
        'nmkr_analytics_sample_rate',
        'Sample Rate',
        'nmkr_analytics_sample_rate_field_callback',
        'nmkr-connect-settings',
        'nmkr_analytics_privacy_section'
    );

    // Add Remove on Uninstall field
    add_settings_field(
        'nmkr_analytics_remove_on_uninstall',
        'Remove Data on Uninstall',
        'nmkr_analytics_remove_on_uninstall_field_callback',
        'nmkr-connect-settings',
        'nmkr_analytics_privacy_section'
    );

    // Add Analytics Debug field
    add_settings_field(
        'nmkr_analytics_debug',
        'Enable Analytics Debug',
        'nmkr_analytics_debug_field_callback',
        'nmkr-connect-settings',
        'nmkr_analytics_debug_section'
    );
}
add_action('admin_init', 'nmkr_connect_register_settings');

/**
 * Set the capability required to save ROCSI Connector for NMKR settings.
 *
 * @param string $capability Default option page capability.
 * @return string Required capability for ROCSI Connector for NMKR settings saves.
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
        'ROCSI Connector for NMKR Settings',
        'ROCSI Connector for NMKR',
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
    $settings_link = '<a href="options-general.php?page=nmkr-connect-settings">' . esc_html__( 'Settings', 'rocsi-connector-for-nmkr' ) . '</a>';
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
            nmkr_render_access_denied_page( __( 'ROCSI Connector for NMKR Settings', 'rocsi-connector-for-nmkr' ) );
            return;
        }
        wp_die( esc_html__( 'Access denied.', 'rocsi-connector-for-nmkr' ) );
    }
    ?>
    <div class="wrap nmkr-admin-shell nmkr-settings-wrap">
        <header class="nmkr-settings-header">
            <div>
                <p class="nmkr-settings-eyebrow"><?php esc_html_e( 'ROCSI Connector for NMKR', 'rocsi-connector-for-nmkr' ); ?></p>
                <h1><?php esc_html_e( 'Settings', 'rocsi-connector-for-nmkr' ); ?></h1>
                <p class="nmkr-settings-intro"><?php esc_html_e( 'Configure the NMKR connection and normal synchronization first. Analytics, privacy, and diagnostics remain available without crowding the primary setup path.', 'rocsi-connector-for-nmkr' ); ?></p>
            </div>
        </header>

        <?php settings_errors(); ?>

        <form action="options.php" method="post" class="nmkr-settings-form">
            <?php settings_fields('nmkr_connect_settings_group'); ?>

            <section id="nmkr-settings-api" class="panel nmkr-settings-group" aria-labelledby="nmkr-settings-api-title">
                <div class="nmkr-settings-group-heading">
                    <div>
                        <p class="nmkr-settings-kicker"><?php esc_html_e( 'Primary setup', 'rocsi-connector-for-nmkr' ); ?></p>
                        <h2 id="nmkr-settings-api-title"><?php esc_html_e( 'NMKR API connection', 'rocsi-connector-for-nmkr' ); ?></h2>
                    </div>
                    <span class="dashicons dashicons-admin-links" aria-hidden="true"></span>
                </div>
                <?php nmkr_connect_api_section_callback(); ?>
                <table class="form-table" role="presentation"><tbody>
                    <?php do_settings_fields( 'nmkr-connect-settings', 'nmkr_connect_api_section' ); ?>
                </tbody></table>
            </section>

            <section id="nmkr-settings-sync" class="panel nmkr-settings-group" aria-labelledby="nmkr-settings-sync-title">
                <div class="nmkr-settings-group-heading">
                    <div>
                        <p class="nmkr-settings-kicker"><?php esc_html_e( 'Synchronization', 'rocsi-connector-for-nmkr' ); ?></p>
                        <h2 id="nmkr-settings-sync-title"><?php esc_html_e( 'Synchronization profile', 'rocsi-connector-for-nmkr' ); ?></h2>
                    </div>
                    <span class="dashicons dashicons-update" aria-hidden="true"></span>
                </div>
                <?php nmkr_connect_sync_section_callback(); ?>
                <table class="form-table" role="presentation"><tbody>
                    <?php do_settings_fields( 'nmkr-connect-settings', 'nmkr_connect_sync_section' ); ?>
                </tbody></table>

                <details class="nmkr-settings-disclosure" id="nmkr-settings-sync-advanced">
                    <summary>
                        <span>
                            <strong><?php esc_html_e( 'Advanced synchronization tuning', 'rocsi-connector-for-nmkr' ); ?></strong>
                            <small><?php esc_html_e( 'Batch size, delays, polling intervals, and error thresholds.', 'rocsi-connector-for-nmkr' ); ?></small>
                        </span>
                        <span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
                    </summary>
                    <div class="nmkr-settings-disclosure-body">
                        <p class="description"><?php esc_html_e( 'These values are normally managed by the selected synchronization profile. Change them only when you intentionally need a custom runtime profile.', 'rocsi-connector-for-nmkr' ); ?></p>
                        <table class="form-table" role="presentation"><tbody>
                            <?php do_settings_fields( 'nmkr-connect-settings', 'nmkr_connect_sync_advanced_section' ); ?>
                        </tbody></table>
                    </div>
                </details>
            </section>

            <section id="nmkr-settings-analytics" class="panel nmkr-settings-group" aria-labelledby="nmkr-settings-analytics-title">
                <div class="nmkr-settings-group-heading">
                    <div>
                        <p class="nmkr-settings-kicker"><?php esc_html_e( 'Analytics & privacy', 'rocsi-connector-for-nmkr' ); ?></p>
                        <h2 id="nmkr-settings-analytics-title"><?php esc_html_e( 'Measurement mode and privacy controls', 'rocsi-connector-for-nmkr' ); ?></h2>
                    </div>
                    <span class="dashicons dashicons-chart-bar" aria-hidden="true"></span>
                </div>
                <?php nmkr_connect_analytics_section_callback(); ?>

                <div class="nmkr-settings-subgroup">
                    <h3><?php esc_html_e( 'Measurement mode', 'rocsi-connector-for-nmkr' ); ?></h3>
                    <table class="form-table" role="presentation"><tbody>
                        <?php do_settings_fields( 'nmkr-connect-settings', 'nmkr_analytics_section' ); ?>
                    </tbody></table>
                </div>

                <div class="nmkr-settings-subgroup">
                    <h3><?php esc_html_e( 'GA4 delivery', 'rocsi-connector-for-nmkr' ); ?></h3>
                    <p class="description"><?php esc_html_e( 'These credentials are used only when GA4 or Both mode is selected.', 'rocsi-connector-for-nmkr' ); ?></p>
                    <table class="form-table" role="presentation"><tbody>
                        <?php do_settings_fields( 'nmkr-connect-settings', 'nmkr_analytics_ga4_section' ); ?>
                    </tbody></table>
                </div>

                <div class="nmkr-settings-subgroup">
                    <h3><?php esc_html_e( 'Privacy & retention', 'rocsi-connector-for-nmkr' ); ?></h3>
                    <table class="form-table" role="presentation"><tbody>
                        <?php do_settings_fields( 'nmkr-connect-settings', 'nmkr_analytics_privacy_section' ); ?>
                    </tbody></table>
                </div>
            </section>

            <details id="nmkr-settings-diagnostics" class="nmkr-settings-diagnostics">
                <summary>
                    <span>
                        <strong><?php esc_html_e( 'Diagnostics & debug logging', 'rocsi-connector-for-nmkr' ); ?></strong>
                        <small><?php esc_html_e( 'Advanced logging destinations, categories, retention, and analytics diagnostics.', 'rocsi-connector-for-nmkr' ); ?></small>
                    </span>
                    <span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
                </summary>
                <div class="panel nmkr-settings-diagnostics-body">
                    <div class="nmkr-settings-warning">
                        <span class="dashicons dashicons-warning" aria-hidden="true"></span>
                        <p><?php esc_html_e( 'Diagnostic logging can increase database or debug.log volume. Enable only the destinations and categories you need, then disable them after troubleshooting.', 'rocsi-connector-for-nmkr' ); ?></p>
                    </div>
                    <?php nmkr_connect_wp_debug_section_callback(); ?>
                    <table class="form-table" role="presentation"><tbody>
                        <?php do_settings_fields( 'nmkr-connect-settings', 'nmkr_connect_wp_debug_section' ); ?>
                    </tbody></table>

                    <div class="nmkr-settings-subgroup">
                        <h3><?php esc_html_e( 'Analytics diagnostics', 'rocsi-connector-for-nmkr' ); ?></h3>
                        <table class="form-table" role="presentation"><tbody>
                            <?php do_settings_fields( 'nmkr-connect-settings', 'nmkr_analytics_debug_section' ); ?>
                        </tbody></table>
                    </div>
                </div>
            </details>

            <div class="nmkr-settings-actions">
                <div>
                    <?php submit_button( __( 'Save Settings', 'rocsi-connector-for-nmkr' ), 'primary', 'submit', false ); ?>
                    <button type="button" id="nmkr-reset-defaults" class="button button-secondary">
                        <?php esc_html_e( 'Reset to Defaults', 'rocsi-connector-for-nmkr' ); ?>
                    </button>
                </div>
                <p><?php esc_html_e( 'Reset changes the form values only. Use Save Settings to persist the reset.', 'rocsi-connector-for-nmkr' ); ?></p>
            </div>
        </form>
    </div>
    <?php
}
