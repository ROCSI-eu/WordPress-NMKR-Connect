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
