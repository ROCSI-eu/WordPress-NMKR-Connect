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
    nocache_headers();
    $content_length = (int) $req->get_header('content-length');
    if ($content_length > NMKR_ANALYTICS_RAW_BODY_MAX_BYTES) return new WP_REST_Response(null, 413);
    $decoded = nmkr_analytics_decode_body($req->get_body());
    if (200 !== $decoded['status']) return new WP_REST_Response(null, $decoded['status']);
    return nmkr_analytics_ingest_common($decoded['body'], 'rest');
}
