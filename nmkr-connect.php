<?php
/*
Plugin Name: NMKR Connect
Description: WordPress plugin to display and manage Cardano and Solana NFTs via NMKR.
Version: 0.1
Author: Romanian - European Cyber Space Initiative 🇷🇴 🇪🇺 🌐
*/

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Load Composer's autoloader
if (file_exists(dirname(__FILE__) . '/vendor/autoload.php')) {
    require_once dirname(__FILE__) . '/vendor/autoload.php';
}

// Include Freemius SDK
require_once dirname(__FILE__) . '/vendor/freemius/wordpress-sdk/start.php';
require_once dirname(__FILE__) . '/vendor/freemius/wordpress-sdk/includes/class-freemius.php';

// Freemius SDK initialization
if (!function_exists('wnc_fs')) {
    function wnc_fs() {
        global $wnc_fs;

        if (!isset($wnc_fs)) {
            $wnc_fs = fs_dynamic_init(array(
                'id'                  => '16536',
                'slug'                => 'nmkr-connect',
                'premium_slug'        => 'wp-nmkr-connect-premium',
                'type'                => 'plugin',
                'public_key'          => 'pk_b03c53dc177e21a2c8657d9083850',
                'is_premium'          => true,
                'has_addons'          => false,
                'has_paid_plans'      => true,
                'menu'                => array(
                    'slug'           => 'nmkr-connect-dashboard',
                    'account'        => true,
                    'contact'        => true,
                    'support'        => true,
                ),
            ));
        }

        return $wnc_fs;
    }

    // Init Freemius.
    wnc_fs();
    // Signal that SDK was initiated.
    do_action('wnc_fs_loaded');
}

// Define plugin constants
define('NMKR_CONNECT_PLUGIN_FILE', __FILE__);

// Include core files needed for activation
require_once plugin_dir_path(__FILE__) . 'includes/api/nmkr-api.php';
require_once plugin_dir_path(__FILE__) . 'includes/database/nmkr-database-structure.php';
// Replace the single settings file with the new modular files
require_once plugin_dir_path(__FILE__) . 'includes/pages/settings/nmkr-settings-core.php';
// The settings-core.php file includes the other settings modules

// Register activation hook
function nmkr_connect_activate() {
    // Create tables
    nmkr_connect_create_tables();
    
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

    // Set defaults for Debug Settings - explicitly disabled
    if (!isset($options['debug_enabled'])) {
        $options['debug_enabled'] = 0;
        $options['log_to_debug_file'] = 0;
        $options['log_to_dashboard'] = 0;
        $options['api_debug_enabled'] = 0;
        $options['sync_debug_enabled'] = 0;
        $options['ui_debug_enabled'] = 0;
        $options['performance_debug_enabled'] = 0;
        $options['log_throttle_enabled'] = 1;
    }
    
    // Save options
    update_option('nmkr_connect_options', $options);
    
    // Attempt stale-state recovery on activation (safe & idempotent)
    if ( function_exists('nmkr_detect_and_recover_stale_sync') ) {
        nmkr_detect_and_recover_stale_sync();
    }

    // Flush rewrite rules
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'nmkr_connect_activate');

// Include other plugin files after activation definition
require_once plugin_dir_path(__FILE__) . 'includes/api/nmkr-api-functions.php';
require_once plugin_dir_path(__FILE__) . 'includes/helpers/nmkr-utility-functions.php';
require_once plugin_dir_path(__FILE__) . 'includes/helpers/nmkr-performance-functions.php';
require_once plugin_dir_path(__FILE__) . 'includes/helpers/nmkr-sync-status-constants.php';
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
require_once plugin_dir_path(__FILE__) . 'includes/shortcodes/nmkr-shortcode-grid.php';
require_once plugin_dir_path(__FILE__) . 'includes/shortcodes/nmkr-shortcode-list.php';
require_once plugin_dir_path(__FILE__) . 'includes/shortcodes/nmkr-shortcode-carousel.php';
require_once plugin_dir_path(__FILE__) . 'includes/shortcodes/nmkr-shortcode-token.php';
require_once plugin_dir_path(__FILE__) . 'includes/shortcodes/nmkr-shortcode-project.php';

// Register deactivation hook
function nmkr_connect_deactivate() {
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

    //
    // A. Drop NMKR custom tables
    //
    $tables = [
        $wpdb->prefix . 'nmkr_projects',
        $wpdb->prefix . 'nmkr_tokens',
        $wpdb->prefix . 'nmkr_token_details',
        $wpdb->prefix . 'nmkr_sync_stats',
        $wpdb->prefix . 'nmkr_sync_metrics',
    ];

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
        'nmkr_ui_logs',
        'nmkr_sync_last_result',
        'nmkr_sync_last_recovery_at'
    ];

    foreach ($option_keys as $key) {
        delete_option($key);
        delete_site_option($key);
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

// Hook to plugin uninstall with Freemius
add_action('fs_after_uninstall_nmkr-connect', 'nmkr_connect_uninstall');

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
}
add_action('admin_enqueue_scripts', 'nmkr_enqueue_admin_assets');

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
}

// Hook the init function
add_action('init', 'nmkr_init');