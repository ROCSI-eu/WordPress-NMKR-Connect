<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'rest_api_init', function() {
    register_rest_route( 'nmkr-connect/v1', '/analytics', [
        'methods'             => 'POST',
        'permission_callback' => '__return_true',
        'callback'            => 'nmkr_analytics_rest_ingest',
        'args'                => [], // raw JSON body
    ] );
} );

function nmkr_analytics_rest_ingest( WP_REST_Request $req ) {
    $body = json_decode( $req->get_body(), true );
    return nmkr_analytics_ingest_common( $body, 'rest' );
}


