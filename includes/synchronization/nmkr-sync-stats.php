<?php
/**
 * NMKR Connect Sync Metrics Handler
 *
 * @package NMKR_Connect
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Validate that sync metrics are complete before saving
 *
 * @param array $metrics Array of performance metrics to validate
 * @return bool True if metrics are complete, false otherwise
 */
function nmkr_validate_metrics_complete($metrics) {
    if (empty($metrics) || !is_array($metrics)) {
        nmkr_log_data_sync('❌ Metrics validation failed: metrics array is empty or invalid.');
        return false;
    }
    
    // Check for required sync completion indicators
    $required_fields = [
        'last_sync_time',
        'total_projects',
        'total_tokens'
    ];
    
    foreach ($required_fields as $field) {
        if (!isset($metrics[$field])) {
            nmkr_log_data_sync("❌ Metrics validation failed: required field '{$field}' is missing.");
            return false;
        }
    }
    
    // Additional validation: ensure we have meaningful data
    if (isset($metrics['total_projects']) && $metrics['total_projects'] <= 0) {
        nmkr_log_data_sync('❌ Metrics validation failed: total_projects is zero or negative, indicating incomplete sync.');
        return false;
    }
    
    // Check if sync is still in progress (should not save metrics while sync is running)
    $sync_in_progress = get_option('nmkr_sync_in_progress', false);
    if ($sync_in_progress) {
        nmkr_log_data_sync('❌ Metrics validation failed: sync is still in progress, will not save incomplete metrics.');
        return false;
    }
    
    nmkr_log_data_sync('✅ Metrics validation passed: all required fields present and sync is complete.');
    return true;
}

/**
 * Save sync metrics to the database
 *
 * @param array $metrics Array of performance metrics to save
 * @return bool|int False on failure, number of rows affected on success
 */
function nmkr_save_sync_metrics($metrics) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'nmkr_sync_metrics';

    // Validate metrics are complete before saving
    if (!nmkr_validate_metrics_complete($metrics)) {
        nmkr_log_data_sync('❌ Metrics save aborted: validation failed.');
        return false;
    }

    // Defensive defaults for all expected fields
    $metrics_defaults = [
        'total_projects'         => 0,
        'total_tokens'           => 0,
        'total_sync_duration'    => 0.0,
        'total_api_time'         => 0.0,
        'average_response_time'  => 0.0,
        'api_requests'           => 0,
        'memory_usage'           => 0.0,
    ];

    // Fill in missing or null values with defaults
    foreach ($metrics_defaults as $key => $default) {
        if (!isset($metrics[$key]) || is_null($metrics[$key])) {
            nmkr_log_data_sync("ℹ️ Metrics field '{$key}' was missing or null. Defaulting to: {$default}");
            $metrics[$key] = $default;
        }
    }

    // Prepare data for insertion
    $data = array(
        'last_sync_time' => $metrics['last_sync_time'],
        'total_projects' => isset($metrics['total_projects']) ? intval($metrics['total_projects']) : 0,
        'total_tokens' => isset($metrics['total_tokens']) ? intval($metrics['total_tokens']) : 0,
        'total_sync_duration' => isset($metrics['total_sync_duration']) ? floatval($metrics['total_sync_duration']) : 0,
        'total_api_time' => isset($metrics['total_api_time']) ? floatval($metrics['total_api_time']) : 0,
        'average_response_time' => isset($metrics['average_response_time']) ? floatval($metrics['average_response_time']) : 0,
        'api_requests' => isset($metrics['api_requests']) ? intval($metrics['api_requests']) : 0,
        'memory_usage' => isset($metrics['memory_usage']) ? floatval($metrics['memory_usage']) : 0,
        'created_at' => nmkr_get_timestamp()
    );

    // Validate timing relationships to prevent unit conversion bugs
    if ($data['total_api_time'] > $data['total_sync_duration'] && $data['total_sync_duration'] > 0) {
        nmkr_log_data_sync('⚠️ Warning: total_api_time (' . $data['total_api_time'] . 's) exceeds total_sync_duration (' . $data['total_sync_duration'] . 's). This may indicate a unit conversion issue.', 'warning');
    }

    // Insert the data
    if ($wpdb->insert($table_name, $data)) {
        nmkr_log_data_sync('✅ Sync metrics saved successfully.');
        return $wpdb->insert_id;
    } else {
        nmkr_log_data_sync('❌ Insert to wp_nmkr_sync_metrics failed. Error: ' . $wpdb->last_error);
        nmkr_log_data_sync('❌ Insert data: ' . print_r($data, true));
        return false;
    }

    return $wpdb->insert_id;
}

/**
 * Get the last sync metrics
 *
 * @return array|false Array of metrics or false if none found
 */
function nmkr_get_last_sync_metrics() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'nmkr_sync_metrics';

    // Check if table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    if (!$table_exists) {
        return false;
    }

    $metrics = $wpdb->get_row(
        "SELECT * FROM $table_name ORDER BY id DESC LIMIT 1",
        ARRAY_A
    );

    if ($metrics === null || $wpdb->last_error) {
        return false;
    }

    return $metrics;
}

/**
 * Save sync statistics to the database (for tracking individual sync operations)
 *
 * @param array $stats Array of sync operation statistics
 * @return bool|int False on failure, number of rows affected on success
 */
function nmkr_save_sync_stats($stats) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'nmkr_sync_stats';
    
    // Validate required fields
    if (!isset($stats['sync_type']) || !isset($stats['start_time'])) {
        return false;
    }
    
    // Prepare data for insertion
    $data = array(
        'sync_type' => $stats['sync_type'],
        'start_time' => $stats['start_time'],
        'end_time' => isset($stats['end_time']) ? $stats['end_time'] : null,
        'status' => isset($stats['status']) ? $stats['status'] : 'in_progress',
        'items_processed' => isset($stats['items_processed']) ? intval($stats['items_processed']) : 0,
        'items_successful' => isset($stats['items_successful']) ? intval($stats['items_successful']) : 0,
        'items_failed' => isset($stats['items_failed']) ? intval($stats['items_failed']) : 0,
        'error_message' => isset($stats['error_message']) ? $stats['error_message'] : null,
        'created_at' => nmkr_get_timestamp(),
        'updated_at' => nmkr_get_timestamp()
    );
    
    // Insert the data
    $result = $wpdb->insert($table_name, $data);
    
    if ($result === false) {
        return false;
    }
    
    return $wpdb->insert_id;
}

/**
 * Update existing sync statistics entry
 *
 * @param int $id The ID of the stats entry to update
 * @param array $stats Array of sync operation statistics
 * @return bool True on success, false on failure
 */
function nmkr_update_sync_stats($id, $stats) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'nmkr_sync_stats';
    
    // Prepare data for update
    $data = array();
    
    if (isset($stats['end_time'])) $data['end_time'] = $stats['end_time'];
    if (isset($stats['status'])) $data['status'] = $stats['status'];
    if (isset($stats['items_processed'])) $data['items_processed'] = intval($stats['items_processed']);
    if (isset($stats['items_successful'])) $data['items_successful'] = intval($stats['items_successful']);
    if (isset($stats['items_failed'])) $data['items_failed'] = intval($stats['items_failed']);
    foreach (array('items_skipped', 'token_details_synced') as $optional_counter) {
        if (isset($stats[$optional_counter])) {
            $columns = $wpdb->get_col("DESC $table_name", 0);
            if (in_array($optional_counter, $columns, true)) {
                $data[$optional_counter] = intval($stats[$optional_counter]);
            }
        }
    }
    if (isset($stats['error_message'])) $data['error_message'] = $stats['error_message'];
    if (isset($stats['failure_breakdown'])) {
        // Check if column exists
        $columns = $wpdb->get_col("DESC $table_name", 0);
        if (in_array('failure_breakdown', $columns)) {
            $data['failure_breakdown'] = $stats['failure_breakdown'];
        } else {
            nmkr_log_data_sync("Partial sync failure stats: " . print_r($stats['failure_breakdown'], true), 'warning');
        }
    }
    $data['updated_at'] = nmkr_get_timestamp();
    
    // Update the data
    $result = $wpdb->update(
        $table_name,
        $data,
        array('id' => $id)
    );
    
    return $result !== false;
}

/**
 * Get recent sync statistics
 *
 * @param int $limit Number of records to retrieve
 * @return array|false Array of statistics or false if none found
 */
function nmkr_get_recent_sync_stats($limit = 10) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'nmkr_sync_stats';
    
    // Check if table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    if (!$table_exists) {
        return false;
    }
    
    $stats = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM $table_name ORDER BY start_time DESC LIMIT %d",
            $limit
        ),
        ARRAY_A
    );
    
    if ($stats === null || $wpdb->last_error) {
        return false;
    }
    
    return $stats;
}   