<?php
/**
 * NMKR Connect Analytics Cron Handler
 *
 * Handles the daily purge of old analytics data
 *
 * @package NMKR_Connect
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Purge old analytics events based on retention settings
 * 
 * This function is called by the daily cron job to remove old analytics data
 * Uses chunked deletes with a time budget to avoid timeouts
 */
function nmkr_analytics_purge_old_events() {
    global $wpdb;
    
    // Get retention days from options (fallback to 90 days)
    $options = get_option('nmkr_connect_options', array());
    $retention_days = isset($options['analytics_retention_days']) ? intval($options['analytics_retention_days']) : 90;
    
    // Ensure retention is within valid range
    $retention_days = max(7, min(365, $retention_days));
    
    // Get analytics debug setting
    $analytics_debug = isset($options['analytics_debug']) ? $options['analytics_debug'] : false;
    
    // Single-site (Phase A): purge rows with site_id IS NULL
    $table_name = $wpdb->prefix . 'nmkr_analytics';
    $limit = 1000;
    $time_budget = 20; // seconds
    $start_time = microtime(true);
    $total_deleted = 0;
    
    // Check if table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    if (!$table_exists) {
        if ($analytics_debug && function_exists('nmkr_log_data_sync')) {
            nmkr_log_data_sync('Analytics purge: Table does not exist', 'debug');
        }
        return;
    }
    
    // Chunked delete loop
    do {
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name 
             WHERE site_id IS NULL 
               AND event_ts < ( NOW() - INTERVAL %d DAY )
             ORDER BY event_ts ASC 
             LIMIT %d",
            $retention_days,
            $limit
        ));
        
        if ($deleted === false) {
            // Database error
            if ($analytics_debug && function_exists('nmkr_log_data_sync')) {
                nmkr_log_data_sync('Analytics purge: Database error during deletion', 'error');
            }
            break;
        }
        
        $total_deleted += $deleted;
        
        // Check time budget
        $elapsed = microtime(true) - $start_time;
        if ($elapsed >= $time_budget) {
            if ($analytics_debug && function_exists('nmkr_log_data_sync')) {
                nmkr_log_data_sync("Analytics purge: Time budget reached ({$elapsed}s), stopping", 'debug');
            }
            break;
        }
        
        // Small delay between chunks to prevent overwhelming the database
        if ($deleted > 0) {
            usleep(100000); // 0.1 second
        }
        
    } while ($deleted == $limit);
    
    // Log summary if debug is enabled
    if ($analytics_debug && function_exists('nmkr_log_data_sync')) {
        $elapsed = microtime(true) - $start_time;
        $elapsed_ms = round($elapsed * 1000, 2);
        nmkr_log_data_sync(
            "Analytics purge completed: {$total_deleted} rows deleted in {$elapsed_ms}ms (retention: {$retention_days} days)",
            'info'
        );
    }
}
