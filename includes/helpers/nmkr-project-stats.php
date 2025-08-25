<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Returns counters for a project without N+1:
 * total_tokens, minted_count, sold_count, reserved_active_count, available_count
 *
 * Robust to schema differences:
 * - Some installs store sell_date / reserved_until in nmkr_token_details (td)
 * - Others may not have those columns at all
 */
function nmkr_get_project_counters( $project_uid ) {
    global $wpdb;

    $t_table   = $wpdb->prefix . 'nmkr_tokens';
    $td_table  = $wpdb->prefix . 'nmkr_token_details';

    // Physical names as stored in INFORMATION_SCHEMA
    $t_physical  = $wpdb->base_prefix . 'nmkr_tokens';
    $td_physical = $wpdb->base_prefix . 'nmkr_token_details';

    // Helper: does table/column exist?
    $col_exists = function( $table_name, $column_name ) use ( $wpdb ) {
        $sql = "
            SELECT COUNT(*) 
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = %s
              AND COLUMN_NAME  = %s
        ";
        return (int) $wpdb->get_var( $wpdb->prepare( $sql, $table_name, $column_name ) ) > 0;
    };

    $has_td_table        = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s",
            $td_physical
        )
    ) > 0;

    $use_td_sell_date    = $has_td_table && $col_exists( $td_physical, 'sell_date' );
    $use_td_reserved     = $has_td_table && $col_exists( $td_physical, 'reserved_until' );

    // Build SQL fragments depending on what exists
    $sold_expr = $use_td_sell_date
        ? "SUM(CASE WHEN td.sell_date IS NOT NULL THEN 1 ELSE 0 END) AS sold_count,"
        : "0 AS sold_count,";

    // Expression that yields a UNIX timestamp for reserved_until if present, otherwise NULL
    // Handles two formats: numeric epoch string or datetime.
    $reserved_ts = $use_td_reserved
        ? "(CASE
               WHEN td.reserved_until REGEXP '^[0-9]+$' THEN td.reserved_until
               ELSE UNIX_TIMESTAMP(td.reserved_until)
           END)"
        : "NULL";

    $reserved_active_expr = $use_td_reserved
        ? "SUM( CASE WHEN td.reserved_until IS NOT NULL AND {$reserved_ts} > UNIX_TIMESTAMP() THEN 1 ELSE 0 END ) AS reserved_active_count,"
        : "0 AS reserved_active_count,";

    // Available = not minted AND (not reserved-in-future) AND (not sold)
    // If we can't see sell_date/reserved_until, we omit those checks.
    $available_conditions = [];
    $available_conditions[] = "t.minted = 0";
    if ( $use_td_sell_date ) {
        $available_conditions[] = "td.sell_date IS NULL";
    }
    if ( $use_td_reserved ) {
        $available_conditions[] = "( td.reserved_until IS NULL OR {$reserved_ts} <= UNIX_TIMESTAMP() )";
    }
    $available_where = implode( ' AND ', $available_conditions );

    $sql = "
        SELECT
            COUNT(*) AS total_tokens,
            SUM(CASE WHEN t.minted = 1 THEN 1 ELSE 0 END) AS minted_count,
            {$sold_expr}
            {$reserved_active_expr}
            SUM( CASE WHEN {$available_where} THEN 1 ELSE 0 END ) AS available_count
        FROM {$t_table} t
        " . ( $has_td_table ? "LEFT JOIN {$td_table} td ON t.token_uid = td.token_uid" : "" ) . "
        WHERE t.project_uid = %s
    ";

    $row = $wpdb->get_row( $wpdb->prepare( $sql, $project_uid ) );

    if ( ! $row ) {
        $row = (object) [
            'total_tokens'          => 0,
            'minted_count'          => 0,
            'sold_count'            => 0,
            'reserved_active_count' => 0,
            'available_count'       => 0,
        ];
        return $row;
    }

    foreach ( ['total_tokens','minted_count','sold_count','reserved_active_count','available_count'] as $k ) {
        if ( ! isset( $row->$k ) || $row->$k === null ) {
            $row->$k = 0;
        }
    }

    return $row;
}
