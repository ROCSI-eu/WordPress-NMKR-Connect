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

        // Namespaced, lightweight CSS for the primary button.
        // Keep it minimal to avoid theme collisions.
        echo '<style id="nmkr-buy-button-styles">
        .nmkr-buy-button{display:inline-flex;align-items:center;gap:.5rem;background:#2196f3;color:#fff;border:none;border-radius:6px;padding:10px 20px;font-weight:600;text-decoration:none;line-height:1;transition:background .15s ease;box-shadow:0 1px 2px rgba(0,0,0,.05)}
        .nmkr-buy-button:hover{background:#1976d2}
        .nmkr-buy-button:focus{outline:2px solid #1976d2;outline-offset:2px}
        .nmkr-buy-button[aria-disabled="true"], .nmkr-buy-button.is-disabled{opacity:.55;cursor:not-allowed;pointer-events:none}
        @media (max-width:480px){.nmkr-buy-button{width:100%;justify-content:center}}
        </style>';
    }
}
