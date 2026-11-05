<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Print buy button CSS exactly once per request.
 */
if ( ! function_exists( 'nmkr_print_buy_button_styles_once' ) ) {
    function nmkr_print_buy_button_styles_once() {
        static $printed = false;
        if ( $printed ) {
            return;
        }
        $printed = true;
        nmkr_connect_enqueue_style_asset( 'nmkr-buy-button', 'css/nmkr-buy-button.css' );
    }
}

/**
 * Enqueue the shared, theme-scoped frontend shortcode design foundation.
 */
if ( ! function_exists( 'nmkr_enqueue_shortcode_foundation' ) ) {
    function nmkr_enqueue_shortcode_foundation() {
        nmkr_connect_enqueue_style_asset(
            'nmkr-shortcode-foundation',
            'css/nmkr-shortcode-foundation.css'
        );
    }
}

/**
 * Render a consistent public shortcode state without leaking styles into the host theme.
 *
 * @param string $message State message.
 * @param string $kind    State kind: empty, warning, or error.
 * @return string
 */
if ( ! function_exists( 'nmkr_render_shortcode_state' ) ) {
    function nmkr_render_shortcode_state( $message, $kind = 'empty' ) {
        nmkr_enqueue_shortcode_foundation();

        $allowed = array( 'empty', 'warning', 'error' );
        $kind = in_array( $kind, $allowed, true ) ? $kind : 'empty';

        return '<div class="nmkr-shortcode nmkr-shortcode-state nmkr-shortcode-state-' . esc_attr( $kind ) . '" role="status">'
            . '<p>' . esc_html( $message ) . '</p>'
            . '</div>';
    }
}

/**
 * Return a CardanoScan policy URL only for an explicitly identified Cardano project.
 *
 * A Solana project may also carry a legacy policy_id value. That value must
 * never turn into a misleading CardanoScan link. Unknown/missing chains fail
 * closed; only the confirmed Cardano policy URL is supported here.
 *
 * @param object $project Project record from the local NMKR projects table.
 * @return string Absolute URL or empty string.
 */
if ( ! function_exists( 'nmkr_get_project_cardanoscan_url' ) ) {
    function nmkr_get_project_cardanoscan_url( $project ) {
        if ( ! is_object( $project )
            || ! isset( $project->blockchain, $project->policy_id )
            || ! is_scalar( $project->blockchain )
            || ! is_scalar( $project->policy_id ) ) {
            return '';
        }

        if ( 'cardano' !== strtolower( trim( (string) $project->blockchain ) ) ) {
            return '';
        }

        $policy_id = trim( (string) $project->policy_id );
        return '' === $policy_id ? '' : 'https://cardanoscan.io/tokenPolicy/' . rawurlencode( $policy_id );
    }
}
