<?php
/*
Plugin Name: Connector for NMKR
Description: WordPress plugin to synchronize and display NMKR Studio projects and tokens.
Version: 1.0.0
Requires at least: 5.8
Requires PHP: 7.4
License: MIT
License URI: https://opensource.org/license/mit/
Author: Romanian - European Cyber Space Initiative 🇷🇴 🇪🇺 🌐
Text Domain: connector-for-nmkr
*/

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
if (!defined('NMKR_CONNECT_PLUGIN_FILE')) {
    define('NMKR_CONNECT_PLUGIN_FILE', __FILE__);
}

// Include core files needed for activation
require_once plugin_dir_path(__FILE__) . 'includes/api/nmkr-api.php';
require_once plugin_dir_path(__FILE__) . 'includes/database/nmkr-database-structure.php';
// Replace the single settings file with the new modular files
require_once plugin_dir_path(__FILE__) . 'includes/pages/settings/nmkr-settings-core.php';
// The settings-core.php file includes the other settings modules
// Roles & access helpers (RBAC foundation)
require_once plugin_dir_path(__FILE__) . 'includes/roles/nmkr-roles.php';
require_once plugin_dir_path(__FILE__) . 'includes/helpers/nmkr-access-helpers.php';

// Activation does not run on ordinary updates; upgrade the site schema once after loading it.
add_action('plugins_loaded', 'nmkr_connect_maybe_upgrade_schema', 5);

// Register activation hook
function nmkr_connect_activate() {
    // Create tables and record the schema version only after run_id verification.
    nmkr_connect_create_tables();
    nmkr_connect_maybe_upgrade_schema();
    
    // Ensure NMKR roles & capabilities exist
    nmkr_roles_install_caps();
    
    // Set default options if not already set
    $options = get_option('nmkr_connect_options', array());
    
    // Set defaults for sync settings if not already set
    if (!isset($options['sync_profile'])) {
        $profiles = nmkr_get_sync_profiles();
        $balanced_profile = $profiles['balanced']['settings'];
        
        $options['sync_profile'] = 'balanced';
        $options['sync_batch_size'] = $balanced_profile['sync_batch_size'];
        $options['sync_batch_delay'] = $balanced_profile['sync_batch_delay'];
        $options['sync_initial_interval'] = $balanced_profile['sync_initial_interval'];
        $options['sync_max_interval'] = $balanced_profile['sync_max_interval'];
        $options['sync_interval_increase'] = $balanced_profile['sync_interval_increase'];
        $options['sync_interval_decrease'] = $balanced_profile['sync_interval_decrease'];
        $options['sync_max_errors'] = $balanced_profile['sync_max_errors'];
    }

    // Analytics is opt-in. Preserve explicit settings on existing installations.
    if (!isset($options['analytics_mode'])) {
        $options['analytics_mode'] = 'off';
    }
    if (!isset($options['analytics_require_consent'])) {
        $options['analytics_require_consent'] = 1;
    }
    if (!isset($options['analytics_remove_on_uninstall'])) {
        $options['analytics_remove_on_uninstall'] = 1;
    }

    // Set defaults for Debug Settings - explicitly disabled
    if (!isset($options['debug_enabled'])) {
        $options['debug_enabled'] = 0;
        $options['log_to_debug_file'] = 0;
        $options['log_to_dashboard'] = 0;
        $options['api_debug_enabled'] = 0;
        $options['sync_debug_enabled'] = 0;
        $options['ui_debug_enabled'] = 0;
        $options['performance_debug_enabled'] = 0;
        $options['log_throttle_enabled'] = 0;
    }
    
    // Save options
    update_option('nmkr_connect_options', $options);
    
    // Attempt stale-state recovery on activation (safe & idempotent)
    if ( function_exists('nmkr_detect_and_recover_stale_sync') ) {
        nmkr_detect_and_recover_stale_sync();
    }

    // Schedule analytics purge cron if not already scheduled
    $stagger_minutes = get_current_blog_id() % 53;
    // Staggered daily schedule at ~03:00 site local time.
    // If it's already past today's 03:00, schedule for tomorrow.
    $now_ts     = current_time( 'timestamp' );          // site-local timestamp
    $base_today = strtotime( '03:00', $now_ts );        // 03:00 today in site TZ
    $base_ts    = ( $base_today <= $now_ts )
        ? strtotime( '+1 day 03:00', $now_ts )          // tomorrow 03:00
        : $base_today;                                  // today 03:00 (future)
    $staggered_ts = $base_ts + ( $stagger_minutes * MINUTE_IN_SECONDS );

    if ( ! wp_next_scheduled( 'nmkr_analytics_purge_daily' ) ) {
        wp_schedule_event( $staggered_ts, 'daily', 'nmkr_analytics_purge_daily' );
    }

    // Flush rewrite rules
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'nmkr_connect_activate');

// Include other plugin files after activation definition
require_once plugin_dir_path(__FILE__) . 'includes/api/nmkr-api-functions.php';
require_once plugin_dir_path(__FILE__) . 'includes/helpers/nmkr-media-helpers.php';
require_once plugin_dir_path(__FILE__) . 'includes/helpers/nmkr-utility-functions.php';
require_once plugin_dir_path(__FILE__) . 'includes/helpers/nmkr-performance-functions.php';
require_once plugin_dir_path(__FILE__) . 'includes/helpers/nmkr-sync-status-constants.php';
require_once plugin_dir_path(__FILE__) . 'includes/helpers/nmkr-analytics-helpers.php';
require_once plugin_dir_path(__FILE__) . 'includes/database/nmkr-database-functions.php';
require_once plugin_dir_path(__FILE__) . 'includes/synchronization/nmkr-sync-core.php';
require_once plugin_dir_path(__FILE__) . 'includes/synchronization/nmkr-sync-batch-processing.php';
require_once plugin_dir_path(__FILE__) . 'includes/synchronization/nmkr-sync-progress-tracking.php';
require_once plugin_dir_path(__FILE__) . 'includes/synchronization/nmkr-sync-error-handling.php';
require_once plugin_dir_path(__FILE__) . 'includes/synchronization/nmkr-sync-stats.php';
require_once plugin_dir_path(__FILE__) . 'includes/ajax/nmkr-ajax-functions.php';
require_once plugin_dir_path(__FILE__) . 'includes/synchronization/nmkr-sync-ajax-handlers.php';
require_once plugin_dir_path(__FILE__) . 'includes/menus/nmkr-admin-menu.php';
require_once plugin_dir_path(__FILE__) . 'includes/pages/dashboard/nmkr-dashboard-core.php';
require_once plugin_dir_path(__FILE__) . 'includes/pages/projects/nmkr-projects.php';
require_once plugin_dir_path(__FILE__) . 'includes/pages/shortcodes/nmkr-shortcodes.php';
require_once plugin_dir_path(__FILE__) . 'includes/pages/analytics/nmkr-analytics-admin.php';
require_once plugin_dir_path(__FILE__) . 'includes/pages/analytics/nmkr-analytics-ajax.php';
require_once plugin_dir_path(__FILE__) . 'includes/shortcodes/nmkr-shortcode-grid.php';
require_once plugin_dir_path(__FILE__) . 'includes/shortcodes/nmkr-shortcode-list.php';
require_once plugin_dir_path(__FILE__) . 'includes/shortcodes/nmkr-shortcode-carousel.php';
require_once plugin_dir_path(__FILE__) . 'includes/shortcodes/nmkr-shortcode-token.php';
require_once plugin_dir_path(__FILE__) . 'includes/shortcodes/nmkr-shortcode-project.php';
require_once plugin_dir_path(__FILE__) . 'includes/analytics/nmkr-analytics-cron.php';
require_once plugin_dir_path(__FILE__) . 'includes/analytics/nmkr-analytics-endpoints.php';

// Register deactivation hook
function nmkr_connect_deactivate() {
    wp_clear_scheduled_hook('nmkr_analytics_purge_daily');
    // Clean up volatile sync state on deactivation
    delete_option('nmkr_sync_status');
    delete_option('nmkr_sync_in_progress');
    delete_option('nmkr_sync_error');
    delete_option('nmkr_sync_near_completion');
    delete_option('nmkr_last_progress_update_time');
    delete_option('nmkr_last_progress_value');
    delete_option('nmkr_sync_heartbeat');
    delete_option('nmkr_sync_last_result');
    delete_option('nmkr_sync_last_recovery_at');
    delete_transient('nmkr_sync_in_progress');
    delete_transient('nmkr_last_sync_error');
    delete_transient('nmkr_sync_progress');
    delete_transient('nmkr_sync_current_item');
    delete_transient('nmkr_sync_current_count');
    delete_transient('nmkr_sync_total_items');
    delete_transient('nmkr_current_sync_stats_live');
    delete_transient('nmkr_current_sync_stats_summary');
    delete_transient('nmkr_sync_user_stopped');
}
register_deactivation_hook(__FILE__, 'nmkr_connect_deactivate');

// Full cleanup of plugin data is handled in nmkr_connect_uninstall()

// Register uninstall hook
function nmkr_connect_uninstall() {
    global $wpdb;

    wp_clear_scheduled_hook('nmkr_analytics_purge_daily');
    if (function_exists('nmkr_roles_uninstall_caps')) {
        nmkr_roles_uninstall_caps();
    }

    //
    // A. Drop NMKR custom tables
    //
    $options = get_option('nmkr_connect_options', array());
    $drop_analytics = !empty($options['analytics_remove_on_uninstall']);

    $tables = array_filter([
        $wpdb->prefix . 'nmkr_projects',
        $wpdb->prefix . 'nmkr_tokens',
        $wpdb->prefix . 'nmkr_token_details',
        $wpdb->prefix . 'nmkr_sync_stats',
        $wpdb->prefix . 'nmkr_sync_metrics',
        $drop_analytics ? ($wpdb->prefix . 'nmkr_analytics') : null,
    ]);

    foreach ($tables as $table) {
        $wpdb->query("DROP TABLE IF EXISTS $table");
    }

    //
    // B. Delete all NMKR-related options
    //
    $option_keys = [
        'nmkr_api_key',
        'nmkr_last_sync_time',
        'nmkr_sync_status',
        'nmkr_connect_options',
        'nmkr_sync_logs',
        'nmkr_api_logs',
        'nmkr_ui_logs',
        'nmkr_performance_logs',
        'nmkr_sync_in_progress',
        'nmkr_sync_progress',
        'nmkr_sync_current_item',
        'nmkr_sync_total_items',
        'nmkr_sync_current_count',
        'nmkr_sync_error',
        'nmkr_sync_start_time',
        'nmkr_last_progress_update_time',
        'nmkr_last_progress_value',
        'nmkr_sync_near_completion',
        'nmkr_sync_stop_requested',
        'nmkr_sync_data',
        'nmkr_sync_owner',
        'nmkr_sync_last_result',
        'nmkr_sync_last_recovery_at',
        'nmkr_analytics_cleanup_cursor',
        NMKR_CONNECT_SCHEMA_VERSION_OPTION,
        NMKR_CONNECT_SCHEMA_UPGRADE_LOCK_OPTION
    ];

    foreach ($option_keys as $key) {
        delete_option($key);
        delete_site_option($key);
    }

    // Admission counters and deduplication records use digest-suffixed option
    // names, so they cannot be represented in the fixed option whitelist.
    // Remove them with the analytics table when analytics cleanup is enabled.
    if ($drop_analytics) {
        $analytics_state_pattern = $wpdb->esc_like('nmkr_ai_') . '%';
        $analytics_state_cursor = '';

        do {
            $analytics_state_names = $wpdb->get_col($wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name > %s ORDER BY option_name ASC LIMIT 1000",
                $analytics_state_pattern,
                $analytics_state_cursor
            ));

            foreach ($analytics_state_names as $analytics_state_name) {
                $analytics_state_cursor = $analytics_state_name;
                delete_option($analytics_state_name);
            }
        } while (count($analytics_state_names) === 1000);
    }

    //
    // C. Delete all NMKR-related transients
    //
    $transient_keys = [
        'nmkr_sync_in_progress',
        'nmkr_last_sync_error',
        'nmkr_current_sync_stats_live',
        'nmkr_current_sync_stats_summary',
        'nmkr_stop_sync_requested',
        'nmkr_sync_batch_state',
        'nmkr_api_connection_status'
    ];

    foreach ($transient_keys as $key) {
        delete_transient($key);
        delete_site_transient($key);
    }
}
register_uninstall_hook(__FILE__, 'nmkr_connect_uninstall');


// Enqueue the lazy loading script for token images
function nmkr_enqueue_lazy_loading_script() {
    wp_enqueue_script('nmkr-lazy-loading', plugins_url('js/nmkr-lazy-loading.js', __FILE__), array(), '1.0', true);
}
add_action('wp_enqueue_scripts', 'nmkr_enqueue_lazy_loading_script');

// Enqueue admin scripts for NMKR pages
function nmkr_enqueue_admin_assets($hook) {
    if (strpos($hook, 'nmkr') !== false) {
        // Enqueue constants first (with cache-busting by filemtime)
        $base_url = plugin_dir_url(__FILE__);
        $base_dir = plugin_dir_path(__FILE__);
        $js_constants_rel = 'js/nmkr-sync-status-constants.js';
        $js_progress_rel  = 'js/nmkr-sync-progress.js';
        $js_constants_ver = @filemtime($base_dir . $js_constants_rel) ?: '1.0';
        $js_progress_ver  = @filemtime($base_dir . $js_progress_rel)  ?: '1.0';
        wp_enqueue_script('nmkr-sync-status-constants', $base_url . $js_constants_rel, array(), $js_constants_ver, true);
        
        // Then enqueue the main progress script with constants as dependency
        wp_enqueue_script('nmkr-sync-progress', $base_url . $js_progress_rel, array('jquery', 'nmkr-sync-status-constants'), $js_progress_ver, true);
        
        // Localize the script with runtime sync controls
        wp_localize_script(
            'nmkr-sync-progress',
            'nmkrSyncProgress',
            array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'nmkr_sync_nonce' ),
                'dashboardNonce' => wp_create_nonce( 'nmkr_dashboard_nonce' ),
                'resume'   => (bool) get_option('nmkr_sync_in_progress', false),
                'options'  => array(
                    'sync_initial_interval'   => isset($options['sync_initial_interval']) ? $options['sync_initial_interval'] : 1000,
                    'sync_max_interval'       => isset($options['sync_max_interval'])   ? $options['sync_max_interval']   : 30000,
                    'sync_interval_increase'  => isset($options['sync_interval_increase']) ? $options['sync_interval_increase'] : 2.0,
                    'sync_interval_decrease'  => isset($options['sync_interval_decrease']) ? $options['sync_interval_decrease'] : 0.5,
                    'sync_max_errors'         => isset($options['sync_max_errors'])      ? $options['sync_max_errors']      : 3,
                    'batch_size'              => isset($options['batch_size'])           ? $options['batch_size']           : 10,
                    'batch_delay'             => isset($options['batch_delay'])          ? $options['batch_delay']          : 1,
                )
            )
        );
    }

    // Enqueue Analytics dashboard assets only on the Analytics page.
    // Most WP builds emit a hook like: 'nmkr-connect_page_nmkr-connect-analytics'
    if (
        $hook === 'nmkr-connect_page_nmkr-connect-analytics'
        || strpos($hook, 'nmkr-connect-analytics') !== false
    ) {
        $base_url = plugin_dir_url(__FILE__);
        $base_dir = plugin_dir_path(__FILE__);

        $analytics_js_rel = 'js/admin/nmkr-analytics-dashboard.js';
        $analytics_js_ver = @filemtime( $base_dir . $analytics_js_rel ) ?: '1.0';

        // Ensure the directory structure exists in the project (js/admin/)
        wp_enqueue_script(
            'nmkr-analytics-dashboard',
            $base_url . $analytics_js_rel,
            array(),
            $analytics_js_ver,
            true
        );

        // Enqueue minimal CSS for analytics dashboard
        $analytics_css_rel = 'css/admin/nmkr-analytics-dashboard.css';
        $analytics_css_ver = @filemtime( $base_dir . $analytics_css_rel ) ?: '1.0';
        wp_enqueue_style(
            'nmkr-analytics-dashboard',
            $base_url . $analytics_css_rel,
            array(),
            $analytics_css_ver
        );

        // Load a dedicated RTL stylesheet on RTL sites (replaces the LTR file)
        wp_style_add_data('nmkr-analytics-dashboard', 'rtl', 'replace');

        // Localize runtime config (no network calls yet)
        wp_localize_script(
            'nmkr-analytics-dashboard',
            'nmkrAnalyticsDashboard',
            array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                // Reuse existing dashboard nonce; endpoints will check this in PR-2+
                'nonce'    => wp_create_nonce( 'nmkr_dashboard_nonce' ),
                'i18n' => array(
                'title'         => esc_html__( 'Analytics', 'connector-for-nmkr' ),
                'loading'       => esc_html__( 'Loading…', 'connector-for-nmkr' ),
                'noData'        => esc_html__( 'No data yet for the selected range.', 'connector-for-nmkr' ),
                'invalidRange'  => esc_html__( 'Custom range must be ≤ 365 days.', 'connector-for-nmkr' ),
                'apply'         => esc_html__( 'Apply', 'connector-for-nmkr' ),
                'from'          => esc_html__( 'From', 'connector-for-nmkr' ),
                'to'            => esc_html__( 'To', 'connector-for-nmkr' ),
                'range'         => esc_html__( 'Range', 'connector-for-nmkr' ),
                'shortcodeType' => esc_html__( 'Shortcode type', 'connector-for-nmkr' ),
                'views'         => esc_html__( 'Views', 'connector-for-nmkr' ),
                'clicks'        => esc_html__( 'Clicks', 'connector-for-nmkr' ),
                'ctr'           => esc_html__( 'CTR', 'connector-for-nmkr' ),
                'sort'          => esc_html__( 'Sort', 'connector-for-nmkr' ),
                'topProjects'   => esc_html__( 'Top Projects', 'connector-for-nmkr' ),
                'topTokens'     => esc_html__( 'Top Tokens',   'connector-for-nmkr' ),
                'uid'           => esc_html__( 'UID',          'connector-for-nmkr' ),
                'searchUid'     => esc_html__( 'Search UID prefix', 'connector-for-nmkr' ),
                'search'        => esc_html__( 'Search',       'connector-for-nmkr' ),
                'reset'         => esc_html__( 'Reset',        'connector-for-nmkr' ),
                'prev'          => esc_html__( 'Prev',         'connector-for-nmkr' ),
                'next'          => esc_html__( 'Next',         'connector-for-nmkr' ),
                'page'          => esc_html__( 'Page',         'connector-for-nmkr' ),
                'of'            => esc_html__( 'of',           'connector-for-nmkr' ),
                'noResults'     => esc_html__( 'No results found.', 'connector-for-nmkr' ),
                'error'         => esc_html__( 'Something went wrong.', 'connector-for-nmkr' ),
                'exports'       => esc_html__( 'Exports', 'connector-for-nmkr' ),
                'exportWhat'    => esc_html__( 'Data', 'connector-for-nmkr' ),
                'exportFormat'  => esc_html__( 'Format', 'connector-for-nmkr' ),
                'timeseries'    => esc_html__( 'Timeseries', 'connector-for-nmkr' ),
                'breakdown'     => esc_html__( 'Shortcode Breakdown', 'connector-for-nmkr' ),
                'csv'           => esc_html__( 'CSV', 'connector-for-nmkr' ),
                'json'          => esc_html__( 'JSON', 'connector-for-nmkr' ),
                'download'      => esc_html__( 'Download', 'connector-for-nmkr' ),
                'currentView'   => esc_html__( 'Current view/page', 'connector-for-nmkr' ),
                'noteExport'    => esc_html__( 'Exports reflect current filters; top lists export the current page.', 'connector-for-nmkr' ),
                ),
                'shortcodeTypes' => array(
                    array('value' => '',         'label' => esc_html__( 'All shortcodes', 'connector-for-nmkr' )),
                    array('value' => 'grid',     'label' => esc_html__( 'Grid', 'connector-for-nmkr' )),
                    array('value' => 'list',     'label' => esc_html__( 'List', 'connector-for-nmkr' )),
                    array('value' => 'carousel', 'label' => esc_html__( 'Carousel', 'connector-for-nmkr' )),
                    array('value' => 'token',    'label' => esc_html__( 'Single Token', 'connector-for-nmkr' )),
                    array('value' => 'project',  'label' => esc_html__( 'Single Project', 'connector-for-nmkr' )),
                ),
                'ranges' => array(
                    array('value' => '24h',   'label' => esc_html__( 'Last 24 hours', 'connector-for-nmkr' )),
                    array('value' => '7d',    'label' => esc_html__( 'Last 7 days', 'connector-for-nmkr' )),
                    array('value' => '30d',   'label' => esc_html__( 'Last 30 days', 'connector-for-nmkr' )),
                    array('value' => 'custom','label' => esc_html__( 'Custom range', 'connector-for-nmkr' )),
                ),
                'defaults' => array(
                    'range'   => '7d',
                    'bucket'  => 'day',
                    'perPage' => 10,
                ),
            )
        );
    }
}
add_action('admin_enqueue_scripts', 'nmkr_enqueue_admin_assets');

// Idempotent safety-net in case activation did not run (e.g., manual file updates)
add_action( 'admin_init', 'nmkr_roles_ensure_caps' );

/**
 * Plugin initialization, register hooks
 */
function nmkr_init() {
    // Register shortcodes
    add_shortcode('nmkr-token', 'nmkr_shortcode_token');
    add_shortcode('nmkr-token-list', 'nmkr_shortcode_list');
    add_shortcode('nmkr-project', 'nmkr_shortcode_project');
    add_shortcode('nmkr-carousel', 'nmkr_shortcode_carousel');
    add_shortcode('nmkr-grid', 'nmkr_shortcode_grid');
    
    // Register analytics purge cron hook
    add_action('nmkr_analytics_purge_daily', 'nmkr_analytics_purge_old_events');
    
    // Lightweight reschedule check for analytics cron (best-effort safety net)
    if ( is_admin() && current_user_can( 'manage_options' ) ) {
        if ( ! wp_next_scheduled( 'nmkr_analytics_purge_daily' ) ) {
            // Staggered daily schedule at ~03:00 site local time.
            // If it's already past today's 03:00, schedule for tomorrow.
            $stagger_minutes = get_current_blog_id() % 53;
            $now_ts          = current_time( 'timestamp' );          // site-local timestamp
            $base_today      = strtotime( '03:00', $now_ts );        // 03:00 today in site TZ
            $base_ts         = ( $base_today <= $now_ts )
                ? strtotime( '+1 day 03:00', $now_ts )              // tomorrow 03:00
                : $base_today;                                      // today 03:00 (future)
            $staggered_ts    = $base_ts + ( $stagger_minutes * MINUTE_IN_SECONDS );

            wp_schedule_event( $staggered_ts, 'daily', 'nmkr_analytics_purge_daily' );
        }
    }
}

// Hook the init function
add_action('init', 'nmkr_init');

// Enqueue frontend analytics scaffold
function nmkr_enqueue_analytics_frontend() {
    static $done = false;
    if ($done) { return; }
    $done = true;

    $base_url = plugin_dir_url(__FILE__);
    $base_dir = plugin_dir_path(__FILE__);
    $script_rel = 'js/nmkr-analytics.js';
    $script_ver = @filemtime($base_dir . $script_rel) ?: '1.0';

    $options = get_option('nmkr_connect_options', array());
    $mode = isset($options['analytics_mode']) ? $options['analytics_mode'] : 'off';
    if ($mode === 'off') { return; }

    wp_enqueue_script('nmkr-analytics', $base_url . $script_rel, array(), $script_ver, true);

    $requiresConsent = isset($options['analytics_require_consent']) ? (bool)$options['analytics_require_consent'] : true;
    $sampleRate = isset($options['analytics_sample_rate']) ? floatval($options['analytics_sample_rate']) : 1.0;
    if ($sampleRate < 0) { $sampleRate = 0; }
    if ($sampleRate > 1) { $sampleRate = 1; }
    $debug = isset($options['analytics_debug']) ? (bool)$options['analytics_debug'] : false;

    $home = home_url();
    $host = parse_url($home, PHP_URL_HOST);

    $has_consent_cookie = (
        isset($_COOKIE['nmkr_analytics_consent'])
        && sanitize_text_field($_COOKIE['nmkr_analytics_consent']) === '1'
    );
    $ga4Enabled = in_array($mode, array('ga4','both'), true)
        && !empty($options['nmkr_ga4_measurement_id'])
        && !empty($options['nmkr_ga4_api_secret']);

    $config = array(
        'mode' => $mode,
        'requiresConsent' => $requiresConsent,
        'hasConsent' => $has_consent_cookie,
        'sampleRate' => $sampleRate,
        'siteOrigin' => $host ? $host : '',
        'debug' => $debug,
        'ga4Enabled' => (bool) $ga4Enabled,
        'transportEnabled' => true,
        'endpoint_rest' => esc_url_raw( rest_url( 'nmkr-connect/v1/analytics' ) ),
        'endpoint_ajax' => esc_url_raw( admin_url( 'admin-ajax.php?action=nmkr_analytics_event' ) ),
    );

    wp_localize_script('nmkr-analytics', 'NMKR_ANALYTICS', $config);
}


/** Add suggested disclosure text to the WordPress Privacy Policy Guide. */
function nmkr_connect_add_privacy_policy_content() {
    if (!function_exists('wp_add_privacy_policy_content')) { return; }
    $text = '<p>' . esc_html__('Connector for NMKR analytics is disabled by default. If an administrator enables local analytics, interaction events, page context, token or project identifiers, session identifiers, consent state, and (for opted-in logged-in tracking) a WordPress user ID are stored in this site’s database for the configured retention period. If GA4 mode is deliberately selected and valid credentials are configured, event data is sent to Google Analytics. The plugin does not expose the GA4 API secret to visitors.', 'connector-for-nmkr') . '</p>';
    $text .= '<p>' . esc_html__('The plugin communicates with NMKR Studio when an administrator configures and runs synchronization. Public displays may load token media from remote NMKR, IPFS, or configured gateway locations, which can disclose a visitor’s IP address and request metadata to those providers. Site administrators are responsible for choosing appropriate settings, consent handling, disclosures, and retention. This suggested text does not claim legal compliance.', 'connector-for-nmkr') . '</p>';
    wp_add_privacy_policy_content('Connector for NMKR', wp_kses_post(wpautop($text)));
}
add_action('admin_init', 'nmkr_connect_add_privacy_policy_content');
