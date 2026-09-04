<?php
/**
 * Connector for NMKR Dashboard Core
 *
 * Core functionality for the Connector for NMKR Dashboard
 *
 * @package NMKR_Connect
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Include other dashboard components
require_once plugin_dir_path(__FILE__) . 'nmkr-dashboard-ui.php';
require_once plugin_dir_path(__FILE__) . 'nmkr-dashboard-stats.php';
require_once plugin_dir_path(__FILE__) . 'nmkr-dashboard-ajax.php';

// Include necessary API and utility functions
require_once plugin_dir_path(dirname(dirname(dirname(__FILE__)))) . 'includes/api/nmkr-api-functions.php';
require_once plugin_dir_path(dirname(dirname(dirname(__FILE__)))) . 'includes/synchronization/nmkr-sync-core.php';
require_once plugin_dir_path(dirname(dirname(dirname(__FILE__)))) . 'includes/synchronization/nmkr-sync-batch-processing.php';
require_once plugin_dir_path(dirname(dirname(dirname(__FILE__)))) . 'includes/synchronization/nmkr-sync-progress-tracking.php';
require_once plugin_dir_path(dirname(dirname(dirname(__FILE__)))) . 'includes/synchronization/nmkr-sync-error-handling.php';
require_once plugin_dir_path(dirname(dirname(dirname(__FILE__)))) . 'includes/synchronization/nmkr-sync-ajax-handlers.php';
require_once plugin_dir_path(dirname(dirname(dirname(__FILE__)))) . 'includes/synchronization/nmkr-sync-stats.php';
require_once plugin_dir_path(dirname(dirname(dirname(__FILE__)))) . 'includes/database/nmkr-database-functions.php';
require_once plugin_dir_path(dirname(dirname(dirname(__FILE__)))) . 'includes/helpers/nmkr-performance-functions.php';
require_once plugin_dir_path(dirname(dirname(dirname(__FILE__)))) . 'includes/helpers/nmkr-utility-functions.php';

/**
 * Main function to render the Connector for NMKR Dashboard page.
 * 
 * This function initializes the dashboard structure and includes all necessary components,
 * delegating to specialized functions for stats, UI, and Ajax functionality.
 */
function nmkr_connect_dashboard_page() {
    if ( ! current_user_can( 'nmkr_view_dashboard' ) ) {
        if ( function_exists( 'nmkr_render_access_denied_page' ) ) {
            nmkr_render_access_denied_page( __( 'Dashboard', 'connector-for-nmkr' ) );
            return;
        }
        wp_die( esc_html__( 'Access denied.', 'connector-for-nmkr' ) );
    }
    $can_manage_sync = current_user_can( 'nmkr_manage_sync' );

    // Proactive stale-state recovery on dashboard load
    if ( $can_manage_sync && function_exists('nmkr_detect_and_recover_stale_sync') ) {
        nmkr_detect_and_recover_stale_sync();
    }
    // Log UI status update for dashboard page load
    nmkr_log_ui_status('UI: Dashboard page loaded by user', 'info');
    
    // Get initial sync statistics
    $initial_stats = nmkr_get_sync_statistics();

    // Create nonce for the dashboard
    $dashboard_nonce = wp_create_nonce('nmkr_dashboard_nonce');
    ?>
    <div class="wrap nmkr-dashboard">
        <h1 class="center-text">Connector for NMKR Dashboard</h1>

        <?php 
        // Render dashboard UI elements
        nmkr_render_dashboard_styles();
        nmkr_render_api_status_panel();
        nmkr_render_sync_data_panel($dashboard_nonce, $can_manage_sync);
        nmkr_render_sync_statistics_panel($initial_stats);
        nmkr_render_debug_logs_panel($can_manage_sync); // Add debug logs panel at the bottom
        nmkr_render_dashboard_scripts($dashboard_nonce, $can_manage_sync);
        ?>
    </div>
    <?php
}

// Function to ensure we have a last sync time option
function nmkr_ensure_last_sync_time() {
    global $wpdb;
    
    // Check if the option exists
    $last_sync_time = get_option('nmkr_last_sync_time', '');
    
    // If the option doesn't exist, try to create it from the metrics table
    if (empty($last_sync_time)) {
        // Check if the metrics table exists
        $metrics_table = $wpdb->prefix . 'nmkr_sync_metrics';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$metrics_table'") === $metrics_table;
        
        if ($table_exists) {
            // Get the last sync time from the metrics table
            $last_sync = $wpdb->get_row(
                "SELECT last_sync_time FROM $metrics_table ORDER BY id DESC LIMIT 1",
                ARRAY_A
            );
            
            if ($last_sync && !empty($last_sync['last_sync_time'])) {
                // Create the option from the metrics data
                update_option('nmkr_last_sync_time', $last_sync['last_sync_time']);
            }
        }
        
        // Alternatively, check for sync records in the sync stats table
        $stats_table = $wpdb->prefix . 'nmkr_sync_stats';
        $stats_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$stats_table'") === $stats_table;
        
        if ($stats_table_exists) {
            // Get the last successful sync from the stats table
            $last_successful_sync = $wpdb->get_row(
                "SELECT end_time FROM $stats_table WHERE status = 'completed' ORDER BY end_time DESC LIMIT 1",
                ARRAY_A
            );
            
            if ($last_successful_sync && !empty($last_successful_sync['end_time'])) {
                // Create the option from the metrics data if it doesn't exist yet
                if (empty(get_option('nmkr_last_sync_time', ''))) {
                    update_option('nmkr_last_sync_time', $last_successful_sync['end_time']);
                }
            }
        }
        
        // As a last resort, check if we have any projects/tokens in the database
        $projects_table = $wpdb->prefix . 'nmkr_projects';
        $tokens_table = $wpdb->prefix . 'nmkr_tokens';
        
        $has_projects = false;
        $has_tokens = false;
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$projects_table'") === $projects_table) {
            $project_count = $wpdb->get_var("SELECT COUNT(*) FROM $projects_table");
            $has_projects = $project_count > 0;
        }
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$tokens_table'") === $tokens_table) {
            $token_count = $wpdb->get_var("SELECT COUNT(*) FROM $tokens_table");
            $has_tokens = $token_count > 0;
        }
        
        // If we have projects or tokens but no sync time, create a default one
        if (($has_projects || $has_tokens) && empty(get_option('nmkr_last_sync_time', ''))) {
            $current_time = nmkr_get_timestamp();
            update_option('nmkr_last_sync_time', $current_time);
        }
    }
}

// Run the ensure function on admin init
add_action('admin_init', 'nmkr_ensure_last_sync_time');
