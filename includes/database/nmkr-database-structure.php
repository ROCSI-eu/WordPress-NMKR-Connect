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


/** Current version for NMKR-owned database schema, independent of plugin version. */
define('NMKR_CONNECT_SCHEMA_VERSION', '1');
define('NMKR_CONNECT_SCHEMA_VERSION_OPTION', 'nmkr_connect_schema_version');
define('NMKR_CONNECT_SCHEMA_UPGRADE_LOCK_OPTION', 'nmkr_connect_schema_upgrade_lock');
define('NMKR_CONNECT_SCHEMA_UPGRADE_LOCK_TTL', 300);

/** Return the complete definition for the sync-history table. */
function nmkr_connect_sync_stats_schema_sql() {
    global $wpdb;
    $table = $wpdb->prefix . 'nmkr_sync_stats';
    $charset_collate = $wpdb->get_charset_collate();

    return "CREATE TABLE $table (
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
}

/** Return the authoritative serialized lock row, bypassing the option cache. */
function nmkr_connect_get_schema_upgrade_lock_row() {
    global $wpdb;
    $serialized = $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", NMKR_CONNECT_SCHEMA_UPGRADE_LOCK_OPTION));
    if ($serialized === null) return false;
    return array('serialized' => $serialized, 'value' => maybe_unserialize($serialized));
}

/** Clear cached option state after a successful direct lock-row mutation. */
function nmkr_connect_clear_schema_upgrade_lock_cache() {
    wp_cache_delete(NMKR_CONNECT_SCHEMA_UPGRADE_LOCK_OPTION, 'options');
    wp_cache_delete('alloptions', 'options');
}

/** Return the authoritative index state for the fixed sync-history table. */
function nmkr_connect_sync_stats_index_state() {
    global $wpdb;
    $table = $wpdb->prefix . 'nmkr_sync_stats';
    $indexes = $wpdb->get_results("SHOW INDEX FROM $table", ARRAY_A);
    $by_name = array();
    foreach ((array) $indexes as $index) if (isset($index['Key_name'], $index['Column_name'])) $by_name[$index['Key_name']][] = $index;
    $required = false;
    foreach ($by_name as $entries) {
        if (count($entries) === 1 && (int) $entries[0]['Non_unique'] === 0 && $entries[0]['Column_name'] === 'run_id') $required = true;
    }
    return array('required' => $required, 'run_id_name_exists' => isset($by_name['run_id']));
}

/** Verify the run-scoped history schema directly instead of trusting dbDelta output. */
function nmkr_connect_verify_sync_stats_schema() {
    global $wpdb;
    $table = $wpdb->prefix . 'nmkr_sync_stats';
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) return false;
    $run_id = false;
    foreach ((array) $wpdb->get_results("SHOW COLUMNS FROM $table", ARRAY_A) as $column) if (isset($column['Field']) && $column['Field'] === 'run_id') $run_id = $column;
    return $run_id && strtoupper((string) $run_id['Null']) === 'YES' && preg_match('/^char\(36\)/i', (string) $run_id['Type']) && nmkr_connect_sync_stats_index_state()['required'];
}

/** Generate a non-secret, strong lock ownership token. */
function nmkr_connect_schema_upgrade_token() {
    if (function_exists('wp_generate_uuid4')) return wp_generate_uuid4();
    return bin2hex(random_bytes(16));
}

/** Acquire a site-scoped schema lock, using a serialized compare-and-swap for stale recovery. */
function nmkr_connect_acquire_schema_upgrade_lock() {
    global $wpdb;
    $token = nmkr_connect_schema_upgrade_token();
    $lock = array('token' => $token, 'created_at' => time());
    if (add_option(NMKR_CONNECT_SCHEMA_UPGRADE_LOCK_OPTION, $lock, '', 'no')) {
        nmkr_connect_clear_schema_upgrade_lock_cache();
        $readback = nmkr_connect_get_schema_upgrade_lock_row();
        return $readback && is_array($readback['value']) && hash_equals($token, (string) $readback['value']['token']) ? $token : false;
    }
    $observed = nmkr_connect_get_schema_upgrade_lock_row();
    if (!$observed || !is_array($observed['value']) || empty($observed['value']['created_at']) || (int) $observed['value']['created_at'] >= time() - NMKR_CONNECT_SCHEMA_UPGRADE_LOCK_TTL) return false;
    $replacement = maybe_serialize($lock);
    $changed = $wpdb->query($wpdb->prepare("UPDATE {$wpdb->options} SET option_value = %s, autoload = 'no' WHERE option_name = %s AND option_value = %s", $replacement, NMKR_CONNECT_SCHEMA_UPGRADE_LOCK_OPTION, $observed['serialized']));
    if ($changed !== 1) return false;
    nmkr_connect_clear_schema_upgrade_lock_cache();
    $readback = nmkr_connect_get_schema_upgrade_lock_row();
    return $readback && $readback['serialized'] === $replacement && is_array($readback['value']) && hash_equals($token, (string) $readback['value']['token']) ? $token : false;
}

/** Atomically delete only the exact serialized lock record owned by this worker. */
function nmkr_connect_release_schema_upgrade_lock($token) {
    global $wpdb;
    $observed = nmkr_connect_get_schema_upgrade_lock_row();
    if (!$observed || !is_array($observed['value']) || !isset($observed['value']['token']) || !hash_equals((string) $observed['value']['token'], (string) $token)) return false;
    $changed = $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", NMKR_CONNECT_SCHEMA_UPGRADE_LOCK_OPTION, $observed['serialized']));
    if ($changed !== 1) return false;
    nmkr_connect_clear_schema_upgrade_lock_cache();
    return nmkr_connect_get_schema_upgrade_lock_row() === false;
}

/** Upgrade and verify the sync-history run_id schema without touching existing rows. */
function nmkr_connect_upgrade_sync_stats_schema() {
    global $wpdb;
    if (!function_exists('dbDelta')) require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta(nmkr_connect_sync_stats_schema_sql());

    if (nmkr_connect_verify_sync_stats_schema()) return true;

    $table = $wpdb->prefix . 'nmkr_sync_stats';
    $columns = $wpdb->get_results("SHOW COLUMNS FROM $table", ARRAY_A);
    $has_run_id = false;
    foreach ((array) $columns as $column) if (isset($column['Field']) && $column['Field'] === 'run_id') $has_run_id = true;
    if (!$has_run_id) return false;

    // dbDelta can omit an index alteration on some supported database paths.
    $index_state = nmkr_connect_sync_stats_index_state();
    if ($index_state['required']) return true;
    if ($index_state['run_id_name_exists']) return false;
    $altered = $wpdb->query("ALTER TABLE $table ADD UNIQUE KEY run_id (run_id)");
    if ($altered === false || !empty($wpdb->last_error)) return false;
    return nmkr_connect_verify_sync_stats_schema();
}

/** Perform the one-time normal-load schema upgrade and record only verified success. */
function nmkr_connect_maybe_upgrade_schema() {
    if (get_option(NMKR_CONNECT_SCHEMA_VERSION_OPTION, '') === NMKR_CONNECT_SCHEMA_VERSION) return true;
    $token = nmkr_connect_acquire_schema_upgrade_lock();
    if (!$token) return false;
    try {
        if (get_option(NMKR_CONNECT_SCHEMA_VERSION_OPTION, '') === NMKR_CONNECT_SCHEMA_VERSION) return true;
        if (!nmkr_connect_upgrade_sync_stats_schema()) return false;
        return update_option(NMKR_CONNECT_SCHEMA_VERSION_OPTION, NMKR_CONNECT_SCHEMA_VERSION, false) || get_option(NMKR_CONNECT_SCHEMA_VERSION_OPTION, '') === NMKR_CONNECT_SCHEMA_VERSION;
    } finally {
        nmkr_connect_release_schema_upgrade_lock($token);
    }
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
    $sync_stats_sql = nmkr_connect_sync_stats_schema_sql();

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

    if (!function_exists('dbDelta')) require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
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
