<?php
/**
 * Build the canonical shortcode literal from validated local identifiers.
 * No query, network request or WordPress state mutation occurs here.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function nmkr_quick_shortcode_valid_uid( $uid ) {
    return is_string( $uid )
        && 1 === preg_match( '/^[A-Za-z0-9._:-]{1,255}$/D', $uid );
}

function nmkr_quick_shortcode_build( $type, $project_uid, $token_uid, $allow_user_select ) {
    $project_types = array( 'nmkr-grid', 'nmkr-token-list', 'nmkr-carousel', 'nmkr-project' );
    if ( 'nmkr-token' === $type ) {
        return nmkr_quick_shortcode_valid_uid( $token_uid )
            ? '[nmkr-token token_uid="' . $token_uid . '"]'
            : '';
    }
    if ( ! in_array( $type, $project_types, true ) || ! nmkr_quick_shortcode_valid_uid( $project_uid ) ) {
        return '';
    }
    $allow = '0' === $allow_user_select ? '0' : '1';
    return '[' . $type . ' project_uid="' . $project_uid . '" allow_user_select="' . $allow . '"]';
}
