<?php
/**
 * NMKR Connect Database Structure
 * 
 * This file contains the database structure for NMKR Connect tables
 * 
 * @package NMKR_Connect
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Create the NMKR tables
 */
function nmkr_connect_create_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    // Table for NMKR projects
    $projects_table = $wpdb->prefix . 'nmkr_projects';
    $projects_sql = "CREATE TABLE IF NOT EXISTS $projects_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        project_id varchar(255) NOT NULL,
        project_uid varchar(255) NOT NULL,
        project_name varchar(255) NOT NULL,
        project_url varchar(255),
        project_logo varchar(255),
        state varchar(255),
        free int DEFAULT 0,
        sold int DEFAULT 0,
        reserved int DEFAULT 0,
        total int DEFAULT 0,
        blocked int DEFAULT 0,
        total_blocked int DEFAULT 0,
        total_tokens int DEFAULT 0,
        error int DEFAULT 0,
        unknown_or_burned_state int DEFAULT 0,
        max_token_supply int DEFAULT 0,
        description text,
        address_reservation_time int DEFAULT 0,
        policy_id varchar(255),
        enable_cross_sale_on_payment_gateway tinyint(1) DEFAULT 0,
        ada_payout_wallet_address varchar(255),
        usdc_payout_wallet_address varchar(255),
        enable_fiat_payments tinyint(1) DEFAULT 0,
        payment_gateway_sale_start datetime,
        enable_decentral_payments tinyint(1) DEFAULT 0,
        policy_locks datetime,
        royalty_address varchar(255),
        royalty_percent decimal(5,2) DEFAULT NULL,
        lockslot int DEFAULT NULL,
        disable_manual_mintingbutton tinyint(1) DEFAULT 0,
        disable_random_sales tinyint(1) DEFAULT 0,
        disable_specific_sales tinyint(1) DEFAULT 0,
        twitter_handle varchar(255),
        nmkr_account_options varchar(255),
        crossmint_collection_id varchar(255),
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP,
        synced_at datetime DEFAULT CURRENT_TIMESTAMP,
        hash VARCHAR(255) DEFAULT NULL,
        blockchain varchar(255),
        solana_project_details text,
        PRIMARY KEY (id),
        UNIQUE KEY project_uid (project_uid),
        KEY state (state)
    ) $charset_collate;";

    // Table for NMKR tokens
    $tokens_table = $wpdb->prefix . 'nmkr_tokens';
    $tokens_sql = "CREATE TABLE IF NOT EXISTS $tokens_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        token_id bigint(20) NOT NULL,
        token_uid varchar(255) NOT NULL,
        project_uid varchar(255) NOT NULL,
        token_name varchar(255) NOT NULL,
        display_name varchar(255),
        ipfs_link varchar(255),
        gateway_link varchar(255),
        detail_data text,
        state varchar(50),
        minted tinyint(1) DEFAULT 0,
        policy_id varchar(255),
        asset_id varchar(255),
        asset_name varchar(255),
        fingerprint varchar(255),
        initial_mint_tx_hash varchar(255),
        series varchar(255),
        token_amount int DEFAULT 0,
        price bigint(20) DEFAULT 0,
        price_solana bigint(20) DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP,
        synced_at datetime DEFAULT CURRENT_TIMESTAMP,
        hash VARCHAR(255) DEFAULT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY token_uid (token_uid),
        KEY project_uid (project_uid),
        KEY state (state),
        KEY minted (minted)
    ) $charset_collate;";

    // Table for NMKR token details
    $token_details_table = $wpdb->prefix . 'nmkr_token_details';
    $token_details_sql = "CREATE TABLE IF NOT EXISTS $token_details_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        token_uid varchar(255) NOT NULL,
        receiver_address varchar(255),
        sell_date datetime,
        sold_by varchar(255),
        reserved_until datetime,
        title varchar(255),
        metadata text,
        payment_gateway_link varchar(255),
        send_back_central_payment_lovelace bigint(20),
        send_back_central_payment_lamport bigint(20),
        price_lovelace_central bigint(20),
        upload_source varchar(255),
        price_lamport_central bigint(20),
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP,
        synced_at datetime DEFAULT CURRENT_TIMESTAMP,
        hash VARCHAR(255) DEFAULT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY token_uid (token_uid),
        KEY receiver_address (receiver_address),
        KEY sell_date (sell_date)
    ) $charset_collate;";

    // Table for NMKR sync process tracking (tracks individual sync operations with detailed status information)
    $sync_stats_table = $wpdb->prefix . 'nmkr_sync_stats';
    $sync_stats_sql = "CREATE TABLE IF NOT EXISTS $sync_stats_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        run_id char(36) NULL,
        sync_type varchar(50) NOT NULL,
        start_time datetime NOT NULL,
        end_time datetime,
        status varchar(50),
        items_processed int DEFAULT 0,
        items_successful int DEFAULT 0,
        items_failed int DEFAULT 0,
        error_message text,
        failure_breakdown text,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY run_id (run_id),
        KEY sync_type (sync_type),
        KEY status (status)
    ) $charset_collate;";

    // Table for NMKR sync performance metrics (stores historical performance data after sync completion)
    $sync_metrics_table = $wpdb->prefix . 'nmkr_sync_metrics';
    $sync_metrics_sql = "CREATE TABLE IF NOT EXISTS $sync_metrics_table (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        last_sync_time DATETIME NOT NULL,
        total_projects INT UNSIGNED DEFAULT 0,
        total_tokens INT UNSIGNED DEFAULT 0,
        total_sync_duration FLOAT DEFAULT 0,
        total_api_time FLOAT DEFAULT 0,
        average_response_time FLOAT DEFAULT 0,
        api_requests INT UNSIGNED DEFAULT 0,
        memory_usage FLOAT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";

    // Table for NMKR analytics (stores user engagement events)
    $analytics_table = $wpdb->prefix . 'nmkr_analytics';
    
    // Check if JSON is supported (MySQL 5.7.8+)
    $supports_json = method_exists($wpdb, 'db_version') ? version_compare($wpdb->db_version(), '5.7.8', '>=') : true;
    $meta_column = $supports_json ? 'JSON' : 'LONGTEXT';
    
    $analytics_sql = "CREATE TABLE IF NOT EXISTS $analytics_table (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        event_ts DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        event_type VARCHAR(32) NOT NULL,
        shortcode_type VARCHAR(16) NOT NULL,
        project_uid VARCHAR(255) NULL,
        token_uid VARCHAR(255) NULL,
        user_id BIGINT UNSIGNED NULL,
        session_id CHAR(36) NULL,
        anon_ip_sha256 BINARY(32) NULL,
        user_agent VARCHAR(255) NULL,
        referrer VARCHAR(255) NULL,
        page_url VARCHAR(255) NULL,
        meta_json $meta_column NULL, -- Future: consider functional/JSON indexes on MySQL 8+ if Phase C queries need them
        site_id BIGINT UNSIGNED NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY ix_site_ts (site_id, event_ts),
        KEY ix_type_ts (event_type, event_ts),
        KEY ix_shortcode_ts (shortcode_type, event_ts),
        KEY ix_project_ts (project_uid, event_ts),
        KEY ix_token_ts (token_uid, event_ts),
        KEY ix_event_ts (event_ts)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($projects_sql);
    dbDelta($tokens_sql);
    dbDelta($token_details_sql);
    dbDelta($sync_stats_sql);
    if (function_exists('nmkr_log_data_sync')) {
        nmkr_log_data_sync("✅ Schema updated: 'failure_breakdown' column added to wp_nmkr_sync_stats.", 'sync');
    }
    dbDelta($sync_metrics_sql);
    dbDelta($analytics_sql);
}
