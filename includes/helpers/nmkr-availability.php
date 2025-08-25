<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function nmkr_has_active_reservation( $reserved_until ) {
    if ( empty( $reserved_until ) ) {
        return false;
    }
    $ts = is_numeric($reserved_until) ? (int) $reserved_until : strtotime( $reserved_until );
    return $ts !== false && $ts > time();
}

function nmkr_token_is_buyable( $token ) {
    $minted   = ! empty( $token->minted );
    $sold     = isset( $token->sell_date ) && ! empty( $token->sell_date );
    $reserved = nmkr_has_active_reservation( $token->reserved_until ?? null );
    return ( ! $minted && ! $sold && ! $reserved );
}

function nmkr_token_status_label( $token ) {
    if ( ! empty( $token->minted ) ) {
        return __( 'Minted', 'nmkr-connect' );
    }
    if ( isset( $token->sell_date ) && ! empty( $token->sell_date ) ) {
        return __( 'Sold', 'nmkr-connect' );
    }
    if ( nmkr_has_active_reservation( $token->reserved_until ?? null ) ) {
        return __( 'Reserved', 'nmkr-connect' );
    }
    return __( 'Available', 'nmkr-connect' );
}
