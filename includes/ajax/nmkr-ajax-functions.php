<?php
/**
 * NMKR Connect AJAX Functions
 *
 * This file contains AJAX handlers for NMKR Connect plugin.
 *
 * @package NMKR_Connect
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Include required files
require_once plugin_dir_path(dirname(__FILE__)) . 'api/nmkr-api.php';
require_once plugin_dir_path(dirname(__FILE__)) . 'api/nmkr-api-functions.php';

// Register AJAX functions
function nmkr_register_ajax_functions() {
    // Action for nmkr_get_api_status is now handled in dashboard/nmkr-dashboard-ajax.php
    // Note: nmkr_sync_progress_handler is registered in synchronization/nmkr-sync-ajax-handlers.php
}
add_action('init', 'nmkr_register_ajax_functions');

// AJAX handler for fetching API status
function nmkr_get_api_status() {
    // Check for admin capabilities
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
        return;
    }
    
    // Get the current API key
    $options = get_option('nmkr_connect_options', []);
    $api_key = isset($options['api_key']) ? $options['api_key'] : '';
    
    if (empty($api_key)) {
        wp_send_json_error(['message' => 'API key not configured']);
        return;
    }
    
    // Use the API function directly instead of instantiating a class
    $result = nmkr_is_api_connected();
    
    if ($result) {
        wp_send_json_success([
            'message' => 'API connection successful',
            'status' => 'connected'
        ]);
    } else {
        wp_send_json_error([
            'message' => 'API connection failed',
            'status' => 'error'
        ]);
    }
} 

// Anonymous + logged-in analytics ingestion (fallback)
add_action( 'wp_ajax_nopriv_nmkr_analytics_event', 'nmkr_analytics_event_ajax' );
add_action( 'wp_ajax_nmkr_analytics_event',        'nmkr_analytics_event_ajax' );

function nmkr_analytics_event_ajax() {
    nocache_headers();
    $raw  = file_get_contents( 'php://input' );
    $body = json_decode( $raw, true );
    $resp = nmkr_analytics_ingest_common( $body, 'ajax' );
    if ( $resp instanceof WP_REST_Response ) {
        status_header( $resp->get_status() );
        exit;
    }
    if ( is_array( $resp ) && isset( $resp['status'] ) ) {
        status_header( (int) $resp['status'] );
        exit;
    }
    status_header( 204 );
    exit;
}