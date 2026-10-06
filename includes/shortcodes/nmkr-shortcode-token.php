<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once dirname( __FILE__, 2 ) . '/helpers/nmkr-availability.php';
require_once dirname( __FILE__, 2 ) . '/helpers/nmkr-lightbox.php';
require_once dirname( __FILE__, 2 ) . '/helpers/nmkr-projects-util.php';
require_once dirname( __FILE__, 2 ) . '/helpers/nmkr-ui-helpers.php';

function nmkr_shortcode_token( $atts ) {
    global $wpdb;

    $atts = shortcode_atts(
        array(
            'token_uid' => '',
        ),
        $atts,
        'nmkr_shortcode_token'
    );

    nmkr_enqueue_shortcode_foundation();

    if ( empty( $atts['token_uid'] ) ) {
        $active_project_uid = nmkr_get_active_project_uid_single( $atts );
        if ( empty( $active_project_uid ) ) {
            return nmkr_render_shortcode_state(
                __( 'No projects available. Please synchronize with NMKR Studio first.', 'rocsi-connector-for-nmkr' )
            );
        }

        $token_obj = nmkr_get_first_token_for_project( $active_project_uid, true );
        if ( ! $token_obj ) {
            return nmkr_render_shortcode_state(
                __( 'No tokens found for the selected project.', 'rocsi-connector-for-nmkr' )
            );
        }
        $atts['token_uid'] = $token_obj->token_uid;
    }

    $t  = $wpdb->prefix . 'nmkr_tokens';
    $td = $wpdb->prefix . 'nmkr_token_details';

    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned table identifiers; token UID is prepared.
    $token = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT t.*, td.* FROM {$t} t LEFT JOIN {$td} td ON td.token_uid = t.token_uid WHERE t.token_uid = %s",
            $atts['token_uid']
        )
    );

    if ( ! $token ) {
        return nmkr_render_shortcode_state(
            __( 'Token not found.', 'rocsi-connector-for-nmkr' ),
            'warning'
        );
    }

    if ( function_exists( 'nmkr_print_buy_button_styles_once' ) ) {
        nmkr_print_buy_button_styles_once();
    }

    nmkr_enqueue_analytics_frontend();
    nmkr_connect_enqueue_style_asset(
        'nmkr-shortcode-token',
        'css/nmkr-shortcode-token.css',
        array( 'nmkr-shortcode-foundation' )
    );

    $alt = ! empty( $token->token_name )
        ? $token->token_name
        : ( ! empty( $token->asset_name ) ? $token->asset_name : __( 'Token', 'rocsi-connector-for-nmkr' ) );

    $status_label = nmkr_token_status_label( $token );
    $status_class = strtolower( $status_label );
    $buyable      = nmkr_token_is_buyable( $token );

    $output = '<div class="nmkr-shortcode nmkr-shortcode-token">';
    $output .= '<article class="nmkr-single-token" data-nmkr-evt="view" data-nmkr-shortcode="token" data-nmkr-token-uid="' . esc_attr( $token->token_uid ) . '" data-nmkr-id="token:' . esc_attr( $token->token_uid ) . '">';

    $output .= '<div class="nmkr-single-token-media">';
    $output .= nmkr_get_token_image_markup( $token, $alt, 'nmkr-token-image' );
    $output .= '</div>';

    $output .= '<div class="nmkr-single-token-content">';
    $output .= '<h2>' . esc_html( $alt ) . '</h2>';
    $output .= '<span class="nmkr-token-status ' . esc_attr( $status_class ) . '">'
        . '<span class="screen-reader-text">' . esc_html__( 'Status:', 'rocsi-connector-for-nmkr' ) . ' </span>'
        . esc_html( $status_label )
        . '</span>';

    $price_html = nmkr_render_token_price_badges( $token );
    if ( $price_html ) {
        $output .= $price_html;
    }

    $output .= '<div class="nmkr-token-meta">';
    if ( ! empty( $token->series ) ) {
        $output .= '<div class="nmkr-token-meta-item"><strong>' . esc_html__( 'Series:', 'rocsi-connector-for-nmkr' ) . '</strong> ' . esc_html( $token->series ) . '</div>';
    }
    if ( ! empty( $token->asset_name ) ) {
        $output .= '<div class="nmkr-token-meta-item"><strong>' . esc_html__( 'Asset:', 'rocsi-connector-for-nmkr' ) . '</strong> ' . esc_html( $token->asset_name ) . '</div>';
    }
    $output .= '</div>';

    if ( $buyable && ! empty( $token->payment_gateway_link ) ) {
        $output .= '<a href="' . esc_url( $token->payment_gateway_link ) . '"'
            . ' class="nmkr-buy-button"'
            . ' target="_blank" rel="noopener noreferrer"'
            . ' aria-label="' . esc_attr__( 'Buy token with NMKR Pay', 'rocsi-connector-for-nmkr' ) . '"'
            . ' data-nmkr-evt="click" data-nmkr-cta="buy" data-nmkr-shortcode="token"'
            . ' data-nmkr-token-uid="' . esc_attr( $token->token_uid ) . '"'
            . ' data-nmkr-id="token:' . esc_attr( $token->token_uid ) . '"'
            . '><span aria-hidden="true">💳</span> '
            . esc_html__( 'Buy with NMKR Pay', 'rocsi-connector-for-nmkr' )
            . '</a>';
    } else {
        $output .= '<p class="nmkr-token-availability">'
            . esc_html__( 'This token is not currently available for purchase.', 'rocsi-connector-for-nmkr' )
            . '</p>';
    }

    $output .= '</div></article></div>';

    if ( function_exists( 'nmkr_print_lightbox_once' ) ) {
        nmkr_print_lightbox_once();
    }

    return $output;
}

add_shortcode('nmkr-token', 'nmkr_shortcode_token');
