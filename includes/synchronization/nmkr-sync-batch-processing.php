<?php
/**
 * NMKR Connect - Batch Processing Functions
 *
 * This file contains the batch processing functionality for NMKR Connect
 * It handles processing tokens and projects in batches to avoid memory issues.
 *
 * @package NMKR Connect
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . 'nmkr-sync-core.php';



/**
 * Process tokens in batches to avoid memory issues
 *
 * @param string $project_uid The project UID
 * @param array $tokens Array of tokens to process
 * @param int $batch_start The starting index
 * @param int $batch_size The batch size
 * @param int $completed_steps Number of steps completed
 * @param int $total_steps Total number of steps
 * @return int Number of successfully processed tokens
 */
function nmkr_process_tokens_batch($project_uid, $tokens, $batch_start, $batch_size, $completed_steps, $total_steps) {
    // Initialize tracking
    $batch_end = min($batch_start + $batch_size, count($tokens));
    $processed_count = 0;
    $error_count = 0;
    $batch_start_time = microtime(true);
    $token_timeout = 30; // Maximum seconds for processing a single token
    $batch_timeout = 300; // Maximum seconds for the entire batch (5 mins)
    
    // Log token batch processing (throttled)
    if (!nmkr_should_throttle_logs()) {
        nmkr_log_data_sync('Processing token batch ' . ($batch_start + 1) . '-' . $batch_end . ' of ' . count($tokens) . ' for project ' . $project_uid);
    }
    
    // Get project details for better logs
    global $wpdb;
    $table_name = $wpdb->prefix . 'nmkr_projects';
    $project = $wpdb->get_row(
        $wpdb->prepare("SELECT project_name FROM $table_name WHERE project_uid = %s", $project_uid),
        ARRAY_A
    );
    $project_name = $project ? $project['project_name'] : 'Unknown Project';

    // Process each token with improved state tracking and error handling
    for ($i = $batch_start; $i < $batch_end; $i++) {
        // Check if batch timeout exceeded
        if (microtime(true) - $batch_start_time > $batch_timeout) {
            nmkr_log_data_sync('Batch timeout exceeded after processing ' . $processed_count . ' tokens', 'warning');
            break;
        }
        
        // Check if wp-admin is being used, and if so, check if user canceled the operation
        if (is_admin()) {
            $sync_stop_requested = (bool) get_transient('nmkr_sync_user_stopped');
            if ( ! $sync_stop_requested ) {
                $sync_stop_requested = (bool) get_option('nmkr_sync_user_stopped', false);
            }
            if ($sync_stop_requested) {
                nmkr_log_data_sync('Sync process was manually stopped by user during token processing');
                delete_transient('nmkr_sync_user_stopped');
                return $processed_count;
            }
            
            // Check if we're running out of memory - use a lower threshold (80% instead of 90%)
            $memory_limit = ini_get('memory_limit');
            if ($memory_limit) {
                $memory_limit_bytes = wp_convert_hr_to_bytes($memory_limit);
                $memory_usage = memory_get_usage(true);
                
                // If we're using more than 80% of available memory, stop this batch
                if ($memory_usage > $memory_limit_bytes * 0.8) {
                    nmkr_log_data_sync('Memory usage exceeding 80% threshold during token processing, pausing batch. Using ' . size_format($memory_usage) . ' of ' . $memory_limit, 'warning');
                    break;
                }
            }
        }
        
        $token = $tokens[$i];
        $token_start_time = microtime(true);
        
        // Get the token's unique identifier (handle both 'uid' and 'token_uid' for compatibility)
        $token_uid = isset($token['uid']) ? $token['uid'] : 
                    (isset($token['token_uid']) ? $token['token_uid'] : null);
                    
        if (!$token_uid) {
            if (!nmkr_should_throttle_logs()) {
                nmkr_log_data_sync('Missing token unique identifier', 'error', array('token' => $token));
            }
            $error_count++;
            continue;
        }
        
        // Get token name with fallbacks
        $token_name = isset($token['name']) ? $token['name'] : 
                     (isset($token['token_name']) ? $token['token_name'] : 
                     ($token_uid ? $token_uid : 'Token #' . ($i+1)));
        
        // Log token processing (throttled)
        if (!nmkr_should_throttle_logs()) {
            nmkr_log_data_sync('Processing token: ' . $token_name . ' (' . ($i+1) . '/' . count($tokens) . ') in project: ' . $project_name);
        }
        
        try {
            // Store the basic token info
            $token_tracking = nmkr_start_performance_tracking('store_token_' . $token_uid);
            nmkr_store_token($token, $project_uid);
            $token_performance = nmkr_end_performance_tracking($token_tracking);
            
            // Only include token details for supported tokens
            $token_supported = true; // Assuming all tokens are supported for now
            
            if ($token_supported) {
                // Check for token processing timeout
                if (microtime(true) - $token_start_time > $token_timeout) {
                    throw new Exception("Token processing timeout exceeded for " . $token_name);
                }
                
                // Add throttling delay between API calls to avoid rate limiting
                if ($i > $batch_start) {
                    $delay = 500000; // 500ms delay
                    usleep($delay);
                }
                
                $details_tracking = nmkr_start_performance_tracking('fetch_token_details_' . $token_uid);
                $token_details = nmkr_connect_fetch_nft_details($token_uid);
                $details_performance = nmkr_end_performance_tracking($details_tracking);
                
                if (is_wp_error($token_details)) {
                    nmkr_log_data_sync('Error fetching token details for ' . $token_name . ': ' . $token_details->get_error_message(), 'error');
                    $error_count++;
                    
                    // If we hit too many errors in a row, we might need to break the batch
                    if ($error_count >= 3) {
                        nmkr_log_data_sync('Too many consecutive errors (' . $error_count . ') in token batch processing, pausing batch', 'error');
                        break;
                    }
                    
                    continue;
                }
                
                // Reset error counter on success
                $error_count = 0;
                
                // Store token details
                $store_details_tracking = nmkr_start_performance_tracking('store_token_details_' . $token_uid);
                nmkr_store_token_details($token_uid, $token_details);
                $store_details_performance = nmkr_end_performance_tracking($store_details_tracking);
                
                // Emit granular progress every 2 tokens (increased from 5 for smoother updates)
                if ((($i - $batch_start + 1) % 2 === 0) || ($i === $batch_end - 1)) {
                    $token_index = $completed_steps + $processed_count;
                    $total_tokens_in_stage = $total_steps; // Or use a more precise count if available
                    nmkr_update_sync_progress($token_index, $total_tokens_in_stage, '🪙 Processing Token: ' . $token_name);
                }
                
                // Progress already updated above with nmkr_update_sync_progress() call
                
                // Update sync data ONLY after successful processing
                $current_sync_data = nmkr_get_sync_data();
                if ($current_sync_data) {
                    $current_sync_data['completed_tokens'] = ($current_sync_data['completed_tokens'] ?? 0) + 1;
                    $current_sync_data['last_token_processed'] = $token_uid;
                    $current_sync_data['last_update_time'] = time();
                    $current_sync_data['current_token_name'] = $token_name;
                    $current_sync_data['current_token_index'] = $i;
                    $current_sync_data['current_batch_progress'] = round(($i - $batch_start + 1) / ($batch_end - $batch_start) * 100);
                    nmkr_save_sync_data($current_sync_data);
                }
                
                // Update sync heartbeat to indicate active processing
                nmkr_update_sync_heartbeat();
                
                // Incremented only after successful processing
                $processed_count++;
                
                // Check for token processing timeout after storage
                if (microtime(true) - $token_start_time > $token_timeout) {
                    nmkr_log_data_sync('Token processing took too long (' . round(microtime(true) - $token_start_time, 2) . 's) for ' . $token_name . ', but completed successfully', 'warning');
                }
            } else {
                // For unsupported tokens, still count them as processed
                $processed_count++;
                
                // Update progress for unsupported tokens too
                nmkr_update_sync_progress($completed_steps + $processed_count, $total_steps, 'Skipped unsupported token: ' . $token_name);
            }
        } catch (Exception $e) {
            nmkr_log_data_sync('Error processing token ' . $token_name . ': ' . $e->getMessage(), 'error');
            $error_count++;
            
            // If we hit too many errors in a row, we might need to break the batch
            if ($error_count >= 3) {
                nmkr_log_data_sync('Too many consecutive errors (' . $error_count . ') in token batch processing, pausing batch', 'error');
                break;
            }
        }
    }
    
    // Log batch completion with timing information (throttled)
    $batch_duration = microtime(true) - $batch_start_time;
    if (!nmkr_should_throttle_logs()) {
        nmkr_log_data_sync('Completed token batch ' . ($batch_start + 1) . '-' . $batch_end . ' of ' . count($tokens) . 
                          ' for project ' . $project_name . ' in ' . round($batch_duration, 2) . 's. ' . 
                          'Processed: ' . $processed_count . ', Errors: ' . $error_count);
    }
    
    // Return the actual number of successfully processed tokens, not just the batch size
    return $processed_count;
}

/**
 * Helper function to check if all batches in token batches are complete
 * 
 * @param array $token_batches The token batches to check
 * @return bool True if all batches are complete, false otherwise
 */
function check_all_batches_complete($token_batches) {
    if (empty($token_batches)) {
        return true;
    }
    
    foreach ($token_batches as $project_uid => $batch_data) {
        if (isset($batch_data['current_batch']) && isset($batch_data['total_batches']) && 
            $batch_data['current_batch'] < $batch_data['total_batches']) {
            return false;
        }
    }
    
    return true;
}

/**
 * Helper function to get optimized batch size based on memory usage and previous performance
 * 
 * @return int The optimal batch size
 */
function nmkr_get_sync_batch_size() {
    static $adaptive_batch_size = null;
    
    // If we've already calculated an adaptive batch size during this request, return it
    if ($adaptive_batch_size !== null) {
        return $adaptive_batch_size;
    }
    
    // Get configured batch size from options
    $options = get_option('nmkr_connect_options');
    $configured_batch_size = isset($options['sync_batch_size']) ? intval($options['sync_batch_size']) : 5;
    
    // Start with the configured batch size
    $batch_size = $configured_batch_size;
    
    // Get current memory usage
    $memory_usage = memory_get_usage(true);
    $memory_limit = ini_get('memory_limit');
    $memory_limit_bytes = nmkr_convert_to_bytes($memory_limit);
    $memory_percentage = ($memory_usage / $memory_limit_bytes) * 100;
    
    // Get current sync data to check for previous batch performance
    $sync_data = nmkr_get_sync_data();
    
    // Log memory usage for debugging
    nmkr_log_data_sync('Memory usage: ' . round($memory_percentage, 2) . '% (' . 
                      size_format($memory_usage) . ' of ' . $memory_limit . ')', 'info');
    
    // Adjust batch size based on memory usage
    if ($memory_percentage > 70) {
        // High memory usage - reduce batch size
        $batch_size = max(1, round($configured_batch_size * 0.5));
        nmkr_log_data_sync('High memory usage detected, reducing batch size to ' . $batch_size, 'warning');
    } else if ($memory_percentage > 50) {
        // Moderate memory usage - slightly reduce batch size
        $batch_size = max(1, round($configured_batch_size * 0.75));
        nmkr_log_data_sync('Moderate memory usage detected, adjusting batch size to ' . $batch_size, 'info');
    } else if ($memory_percentage < 30 && $sync_data && isset($sync_data['last_batch_success'])) {
        // Low memory usage and previous batch was successful - can increase slightly
        $batch_size = min($configured_batch_size * 2, $configured_batch_size + 3);
        nmkr_log_data_sync('Low memory usage detected, increasing batch size to ' . $batch_size, 'info');
    }
    
    // Check if there have been errors in previous batches
    if ($sync_data && isset($sync_data['recent_errors']) && $sync_data['recent_errors'] > 1) {
        // If we've had multiple errors, reduce batch size more aggressively
        $batch_size = max(1, floor($batch_size / 2));
        nmkr_log_data_sync('Multiple errors in recent batches, reducing batch size to ' . $batch_size, 'warning');
    }
    
    // Store the calculated value for this request
    $adaptive_batch_size = max(1, $batch_size);
    
    return $adaptive_batch_size;
}

/**
 * Helper function to get configured batch delay
 * 
 * @return int The batch delay in seconds
 */
function nmkr_get_sync_batch_delay() {
    $options = get_option('nmkr_connect_options');
    return isset($options['sync_batch_delay']) ? intval($options['sync_batch_delay']) : 3;
}

/**
 * Function to handle batch sync processing
 * This function is called by the scheduled WordPress cron job
 */
function nmkr_process_next_batch() {
    // Get the current sync data
    $sync_data = nmkr_get_sync_data();
    
    // If no sync data exists, we can't continue
    if (!$sync_data) {
        nmkr_log_data_sync('No sync data found for batch processing', 'error');
        return;
    }
    
    // Always update the last_update_time to track activity
    $sync_data['last_update_time'] = time();
    nmkr_save_sync_data($sync_data);
    
    // Update heartbeat at start of batch processing
    nmkr_update_sync_heartbeat();
    
    // Set a maximum execution time for this batch processing
    $max_execution_time = 300; // 5 minutes
    $start_time = time();
    
    // Get sync start time to prevent indefinite processing
    $sync_start_time = get_option('nmkr_sync_start_time', time());
    $max_total_time = 7200; // 2 hours max total sync time
    $total_elapsed_time = time() - $sync_start_time;
    
    // Check if total sync time has exceeded the limit
    if ($total_elapsed_time > $max_total_time) {
        nmkr_log_data_sync('Sync process exceeded maximum allowed time of ' . ($max_total_time / 60) . ' minutes', 'error');
        nmkr_sync_data_complete(false, 'Sync process timed out after ' . ($max_total_time / 60) . ' minutes');
        return;
    }
    
    // Check if we're making progress by looking at last updated values
    $last_progress = isset($sync_data['last_progress']) ? $sync_data['last_progress'] : 0;
    $current_progress_raw = get_transient('nmkr_sync_progress');
    $current_progress = ($current_progress_raw !== false) ? (int) $current_progress_raw : 0;
    $failed_attempts = isset($sync_data['failed_attempts']) ? $sync_data['failed_attempts'] : 0;
    $last_token_processed = isset($sync_data['last_token_processed']) ? $sync_data['last_token_processed'] : null;
    $last_token_timestamp = isset($sync_data['last_update_time']) ? $sync_data['last_update_time'] : 0;
    $time_since_last_token = time() - $last_token_timestamp;
    
    // Track metrics for stall detection
    $sync_data['current_progress'] = $current_progress;
    $sync_data['time_since_last_update'] = $time_since_last_token;
    
    // Check if we were processing the same token for too long
    if ($last_token_processed && 
        isset($sync_data['previous_token_processed']) && 
        $sync_data['previous_token_processed'] === $last_token_processed &&
        $time_since_last_token > 120) { // 2 minutes on same token
        
        nmkr_log_data_sync('Stuck on the same token for ' . $time_since_last_token . ' seconds, attempting recovery', 'warning', array(
            'token' => $last_token_processed,
            'last_update_time' => $last_token_timestamp
        ));
        
        // Force advance to next token with better validation
        if (isset($sync_data['current_token_index']) && isset($sync_data['token_batches'])) {
            $recovery_applied = false;
            // Find current batch data
            foreach ($sync_data['token_batches'] as $project_uid => &$batch_data) {
                if ($batch_data['current_batch'] < $batch_data['total_batches']) {
                    // Calculate current position in this batch
                    $batch_start = $batch_data['current_batch'] * $batch_data['batch_size'];
                    $batch_end = min($batch_start + $batch_data['batch_size'], count($batch_data['tokens']));
                    
                    // Only advance if we haven't reached the end of current batch
                    if ($sync_data['current_token_index'] < $batch_end - 1) {
                        $batch_data['current_token_index'] = $sync_data['current_token_index'] + 1;
                        nmkr_log_data_sync('Forcing advance to next token (index ' . $batch_data['current_token_index'] . ') in batch ' . ($batch_data['current_batch'] + 1), 'warning');
                    } else {
                        // We're at the end of current batch, force advance to next batch
                        $batch_data['current_batch']++;
                        nmkr_log_data_sync('Forcing advance to next batch ' . ($batch_data['current_batch'] + 1) . '/' . $batch_data['total_batches'] . ' due to stuck token', 'warning');
                    }
                    $recovery_applied = true;
                    break;
                }
            }
            
            if (!$recovery_applied) {
                nmkr_log_data_sync('Unable to apply token recovery - no active batches found', 'warning');
            }
        }
        
        // Mark this recovery attempt
        $sync_data['recovery_attempts'] = isset($sync_data['recovery_attempts']) ? $sync_data['recovery_attempts'] + 1 : 1;
        $failed_attempts++;
    }
    
    // Save the previous token processed for stuck detection
    $sync_data['previous_token_processed'] = $last_token_processed;
    
    // If progress hasn't changed, increment failed attempts
    if ($last_progress === $current_progress && $time_since_last_token > 60) { // No progress in 1 minute
        $failed_attempts++;
        $sync_data['failed_attempts'] = $failed_attempts;
        
        // Log the stalled state
        nmkr_log_data_sync('No progress since last batch processing', 'warning', array(
            'last_progress' => $last_progress,
            'current_progress' => $current_progress,
            'time_since_update' => $time_since_last_token,
            'failed_attempts' => $failed_attempts
        ));
        
        // If we've had too many failed attempts with no progress (3 attempts), abort the sync
        if ($failed_attempts >= 3) {
            nmkr_log_data_sync('Sync process appears to be stalled - no progress after multiple attempts', 'error', array(
                'failed_attempts' => $failed_attempts,
                'last_progress' => $last_progress,
                'stalled_time' => $time_since_last_token
            ));
            nmkr_sync_data_complete(false, 'Sync process stalled - no progress after multiple attempts');
            return;
        }
    } else {
        // Reset failed attempts counter if we're making progress
        $failed_attempts = 0;
        $sync_data['failed_attempts'] = 0;
        
        // If we had recovery attempts but now we're making progress, clear them
        if (isset($sync_data['recovery_attempts']) && $sync_data['recovery_attempts'] > 0) {
            $sync_data['recovery_attempts'] = 0;
            nmkr_log_data_sync('Recovery successful - progress is being made again', 'info');
        }
    }
    
    // If progress hasn't changed in 10 minutes, something might be stuck
    if ($last_progress === $current_progress && $time_since_last_token > 600) {
        nmkr_log_data_sync('Sync process appears to be stalled - no progress in 10 minutes', 'warning', array(
            'last_progress' => $last_progress,
            'current_progress' => $current_progress,
            'stalled_time' => $time_since_last_token
        ));
        nmkr_sync_data_complete(false, 'Sync process stalled - no progress in 10 minutes');
        return;
    }
    
    // Update progress tracking info
    $sync_data['last_progress'] = $current_progress;
    
    // Get the sync stats ID if available
    $sync_stats_id = isset($sync_data['sync_stats_id']) ? $sync_data['sync_stats_id'] : null;
    
    // Log project processing check
    nmkr_log_data_sync('check_projects', 'info', array('sync_data' => $sync_data));
    
    // Check if we need to process projects
    if (isset($sync_data['projects_to_process']) && !empty($sync_data['projects_to_process'])) {
        $project = array_shift($sync_data['projects_to_process']);
        
        try {
            // Check if we've reached max execution time
            if (time() - $start_time > $max_execution_time) {
                nmkr_log_data_sync('Batch processing reached max execution time', 'warning', array(
                    'max_execution_time' => $max_execution_time,
                    'elapsed_time' => time() - $start_time
                ));
                
                // Save sync data and schedule next batch
                nmkr_save_sync_data($sync_data);
                wp_schedule_single_event(time() + nmkr_get_sync_batch_delay(), 'nmkr_process_batch_hook');
                return;
            }
            
            nmkr_log_data_sync('process_project', 'info', array('project' => $project));
            
            $sync_data['completed_steps']++;
            $project_name = isset($project['name']) && !empty($project['name']) ? $project['name'] : 
                          (isset($project['projectname']) && !empty($project['projectname']) ? $project['projectname'] : 'Unnamed Project');
            
            $progress = nmkr_update_sync_progress(
                $sync_data['completed_steps'], 
                $sync_data['total_steps'],
                'Synchronizing projects - ' . $project_name
            );
            
            nmkr_log_data_sync('project', 'info', array('project_name' => $project_name, 'progress' => $progress));
            
            // Get the project's unique identifier (handle both 'project_uid' and 'uid' for compatibility)
            $project_uid = isset($project['project_uid']) ? $project['project_uid'] : 
                         (isset($project['uid']) ? $project['uid'] : null);
            
            if (!$project_uid) {
                nmkr_log_data_sync('Missing project unique identifier', 'error', array('project' => $project));
                throw new Exception('Missing project unique identifier');
            }
                         
            $project_tracking = nmkr_start_performance_tracking('store_project_' . $project_uid);
            nmkr_store_project($project);
            $project_performance = nmkr_end_performance_tracking($project_tracking);
            
            nmkr_log_data_sync('store_project', 'info', array('project_name' => $project_name, 'performance' => $project_performance));
            
            // Add this project's tokens to the token queue
            if (isset($sync_data['all_tokens'][$project_uid])) {
                $project_tokens = $sync_data['all_tokens'][$project_uid];
                $sync_data['token_batches'][$project_uid] = array(
                    'tokens' => $project_tokens,
                    'current_batch' => 0,
                    'batch_size' => nmkr_get_sync_batch_size(),
                    'total_batches' => ceil(count($project_tokens) / nmkr_get_sync_batch_size())
                );
                
                nmkr_log_data_sync('setup_token_batch', 'info', array('project_uid' => $project_uid, 'batch_size' => nmkr_get_sync_batch_size()));
            }
            
            // Update completed projects count
            $sync_data['completed_projects']++;
            
            // Update sync stats for project processing
            if ($sync_stats_id) {
                nmkr_update_sync_stats($sync_stats_id, [
                    'status' => 'processing_projects',
                    'items_processed' => $sync_data['completed_projects'],
                    'items_successful' => $sync_data['completed_projects']
                ]);
            }
            
            // Save updated sync data
            nmkr_save_sync_data($sync_data);
            nmkr_log_data_sync('update', 'sync_data', array('sync_data' => $sync_data));
            
            // Update heartbeat after successful project processing
            nmkr_update_sync_heartbeat();
            
            // Schedule next batch processing with configured delay
            wp_schedule_single_event(time() + nmkr_get_sync_batch_delay(), 'nmkr_process_batch_hook');
            nmkr_log_data_sync('Scheduled next batch', 'info');
            
        } catch (Exception $e) {
            nmkr_log_data_sync('Error processing project', 'error', array('error' => $e->getMessage(), 'project' => $project));
            
            // Update sync stats with error but continue processing
            if ($sync_stats_id) {
                nmkr_update_sync_stats($sync_stats_id, [
                    'items_failed' => isset($sync_data['items_failed']) ? $sync_data['items_failed'] + 1 : 1,
                    'error_message' => 'Error processing project: ' . $e->getMessage()
                ]);
            }
            
            // Continue with next batch even after error
            nmkr_save_sync_data($sync_data);
            wp_schedule_single_event(time() + nmkr_get_sync_batch_delay(), 'nmkr_process_batch_hook');
        }
        
        return;
    }
    
    // Process token batches if no more projects
    if (isset($sync_data['token_batches']) && !empty($sync_data['token_batches'])) {
        // ** SAFETY CHECK: Track batch processing attempts to prevent infinite loops **
        $batch_attempts_key = 'batch_processing_attempts';
        $current_attempts = isset($sync_data[$batch_attempts_key]) ? $sync_data[$batch_attempts_key] : array();
        
        // Get first project that still has tokens to process
        foreach ($sync_data['token_batches'] as $project_uid => &$batch_data) {
            // Check if we've reached max execution time
            if (time() - $start_time > $max_execution_time) {
                nmkr_log_data_sync('Batch processing reached max execution time during token processing - scheduling next batch');
                
                // Save sync data and schedule next batch
                nmkr_save_sync_data($sync_data);
                wp_schedule_single_event(time() + nmkr_get_sync_batch_delay(), 'nmkr_process_batch_hook');
                return;
            }
            
            // Skip if this project has completed all batches
            if ($batch_data['current_batch'] >= $batch_data['total_batches']) {
                continue;
            }
            
            // ** SAFETY CHECK: Prevent infinite processing of the same batch **
            $batch_key = $project_uid . '_' . $batch_data['current_batch'];
            if (!isset($current_attempts[$batch_key])) {
                $current_attempts[$batch_key] = 0;
            }
            $current_attempts[$batch_key]++;
            
            // If this batch has been attempted too many times, force advance or abort
            if ($current_attempts[$batch_key] > 5) {
                nmkr_log_data_sync('Batch ' . $batch_key . ' has been attempted ' . $current_attempts[$batch_key] . ' times. Forcing advance to prevent infinite loop.', 'error');
                $batch_data['current_batch']++;
                $current_attempts[$batch_key] = 0; // Reset counter
                
                // If we're forcing too many batch advances, something is seriously wrong
                $total_forced_advances = array_sum($current_attempts);
                if ($total_forced_advances > 20) {
                    nmkr_log_data_sync('Too many forced batch advances (' . $total_forced_advances . '). Aborting sync to prevent system overload.', 'error');
                    nmkr_sync_data_complete(false, 'Sync aborted due to excessive retry attempts - possible infinite loop detected');
                    return;
                }
                
                continue;
            }
            
            // Save the updated attempts tracking
            $sync_data[$batch_attempts_key] = $current_attempts;
            
            // Get the tokens for this project
            $project_tokens = $batch_data['tokens'];
            
            // Calculate batch indices
            $batch_start = $batch_data['current_batch'] * $batch_data['batch_size'];
            
            // Get the project name for better logging
            global $wpdb;
            $table_name = $wpdb->prefix . 'nmkr_projects';
            $project = $wpdb->get_row(
                $wpdb->prepare("SELECT project_name FROM $table_name WHERE project_uid = %s", $project_uid),
                ARRAY_A
            );
            $project_name = $project ? $project['project_name'] : 'Unknown Project';
            
            nmkr_log_data_sync('Starting token batch ' . ($batch_data['current_batch'] + 1) . ' of ' . $batch_data['total_batches'] . ' for project: ' . $project_name);
            
            try {
                // Process this batch
                $completed_in_batch = nmkr_process_tokens_batch(
                    $project_uid,
                    $project_tokens,
                    $batch_start,
                    $batch_data['batch_size'],
                    $sync_data['completed_tokens'],
                    $sync_data['total_tokens']
                );
                
                // Update completed tokens count
                $sync_data['completed_tokens'] += $completed_in_batch;
                
                // Track batch success/failure
                if ($completed_in_batch > 0) {
                    $sync_data['last_batch_success'] = true;
                    $sync_data['recent_errors'] = 0; // Reset error counter on successful batch
                    $sync_data['successful_batches'] = isset($sync_data['successful_batches']) ? $sync_data['successful_batches'] + 1 : 1;
                } else {
                    $sync_data['last_batch_success'] = false;
                    $sync_data['recent_errors'] = isset($sync_data['recent_errors']) ? $sync_data['recent_errors'] + 1 : 1;
                    $sync_data['failed_batches'] = isset($sync_data['failed_batches']) ? $sync_data['failed_batches'] + 1 : 1;
                    
                    // Log error for zero processed tokens
                    nmkr_log_data_sync('Warning: Zero tokens processed in batch ' . ($batch_data['current_batch'] + 1) . ' of ' . 
                                       $batch_data['total_batches'] . ' for project: ' . $project_name, 'warning');
                }
                
                // Check if this was the last token batch for this project
                $is_last_batch = ($batch_data['current_batch'] + 1) >= $batch_data['total_batches'];
                
                if ($is_last_batch) {
                    nmkr_log_data_sync('Completed all token batches for project: ' . $project_name);
                } else {
                    nmkr_log_data_sync('Completed token batch ' . ($batch_data['current_batch'] + 1) . ' of ' . $batch_data['total_batches'] . ' for project: ' . $project_name);
                }
                
                // Update sync stats if we have a stats ID
                if ($sync_stats_id) {
                    nmkr_update_sync_stats($sync_stats_id, [
                        'status' => 'processing_tokens',
                        'items_processed' => $sync_data['completed_tokens'],
                        'items_successful' => $sync_data['completed_tokens']
                    ]);
                }
                
                // ** ONLY ADVANCE BATCH IF ACTUAL WORK WAS COMPLETED **
                if ($completed_in_batch > 0) {
                    // Update batch counter only when tokens were successfully processed
                    $batch_data['current_batch']++;
                    nmkr_log_data_sync('Successfully processed ' . $completed_in_batch . ' tokens, advancing to batch ' . $batch_data['current_batch'] . '/' . $batch_data['total_batches'], 'info');
                } else {
                    // If no tokens were processed, don't advance the batch but log the issue
                    nmkr_log_data_sync('No tokens were processed in current batch attempt. Not advancing batch counter. Will retry batch ' . ($batch_data['current_batch'] + 1) . '/' . $batch_data['total_batches'], 'warning');
                    
                    // Increment a retry counter to prevent infinite loops
                    if (!isset($batch_data['retry_count'])) {
                        $batch_data['retry_count'] = 0;
                    }
                    $batch_data['retry_count']++;
                    
                    // If we've retried this batch too many times, force advance to prevent infinite loop
                    if ($batch_data['retry_count'] >= 3) {
                        nmkr_log_data_sync('Batch has been retried ' . $batch_data['retry_count'] . ' times with no progress. Forcing advance to prevent infinite loop.', 'error');
                        $batch_data['current_batch']++;
                        $batch_data['retry_count'] = 0; // Reset retry counter
                    }
                }
                
                // Save updated sync data
                nmkr_save_sync_data($sync_data);
                
                // Update progress with accurate counts
                nmkr_update_sync_progress(
                    $sync_data['completed_tokens'],
                    $sync_data['total_tokens'],
                    'Processing token batch ' . ($batch_data['current_batch']) . '/' . $batch_data['total_batches']
                );
                
                // Check memory usage and adjust batch size if needed
                $memory_usage = memory_get_usage(true);
                $memory_limit = ini_get('memory_limit');
                $memory_limit_bytes = nmkr_convert_to_bytes($memory_limit);
                $memory_percentage = ($memory_usage / $memory_limit_bytes) * 100;
                
                // If memory usage is high, reduce batch size for next batch
                if ($memory_percentage > 70) {
                    $batch_data['batch_size'] = max(1, $batch_data['batch_size'] - 1);
                    nmkr_log_data_sync('High memory usage (' . round($memory_percentage, 1) . '%) - reducing batch size to ' . $batch_data['batch_size']);
                }
                
                // Schedule next batch with increased delay if memory usage is high
                $delay = $memory_percentage > 70 ? nmkr_get_sync_batch_delay() * 1.5 : nmkr_get_sync_batch_delay();
                wp_schedule_single_event(time() + $delay, 'nmkr_process_batch_hook');
                return;
            } catch (Exception $e) {
                // Log error but continue processing
                nmkr_log_data_sync('Error processing token batch: ' . $e->getMessage(), 'error');
                
                // Mark this batch as complete and move to next batch
                $batch_data['current_batch']++;
                nmkr_save_sync_data($sync_data);
                
                // Schedule next batch after a delay
                wp_schedule_single_event(time() + nmkr_get_sync_batch_delay(), 'nmkr_process_batch_hook');
                return;
            }
        }
    }
    
    // If all projects and token batches are complete, wrap up the sync
    if ((!isset($sync_data['projects_to_process']) || empty($sync_data['projects_to_process'])) && 
        (!isset($sync_data['token_batches']) || 
         (is_array($sync_data['token_batches']) && count($sync_data['token_batches']) > 0 && check_all_batches_complete($sync_data['token_batches'])))) {
        
        // Success! All done
        nmkr_log_data_sync('All projects and tokens have been synchronized successfully');
        
        // Finalization: Use continuous progress model
        nmkr_log_data_sync('Starting finalization process (batch processing)', 'info');
        
        // Progress is already managed by main sync - no need to override
        // The batch processing completion doesn't need its own progress updates
        
        nmkr_log_data_sync('Finalization finished (batch processing)', 'info');
        
        // Note: Metrics saving is handled by the authoritative path in nmkr-sync-core.php
        
        // Collect final statistics
        $stats = array(
            'total_projects' => isset($sync_data['total_projects']) ? $sync_data['total_projects'] : 0,
            'completed_projects' => isset($sync_data['completed_projects']) ? $sync_data['completed_projects'] : 0,
            'total_tokens' => isset($sync_data['total_tokens']) ? $sync_data['total_tokens'] : 0,
            'completed_tokens' => isset($sync_data['completed_tokens']) ? $sync_data['completed_tokens'] : 0,
            'sync_time' => time() - $sync_start_time
        );
        
        // Get the endpoint timestamp for calculating duration
        $sync_end_time = time();
        $sync_stats = array(
            'total_duration' => $sync_end_time - $sync_start_time,
            'request_count' => 0,
            'average_time' => 0,
            'total_api_time' => 0,
            'memory_used' => memory_get_usage(true)
        );
        
        // Format nice time output for the log
        $minutes = floor($sync_stats['total_duration'] / 60);
        $seconds = $sync_stats['total_duration'] % 60;
        $time_output = ($minutes > 0 ? $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' and ' : '') . 
                      $seconds . ' second' . ($seconds != 1 ? 's' : '');
        
        nmkr_log_data_sync('Sync completed successfully in ' . $time_output . 
            '. Projects: ' . $stats['completed_projects'] . '/' . $stats['total_projects'] . 
            ', Tokens: ' . $stats['completed_tokens'] . '/' . $stats['total_tokens']);
        
        // Mark sync near completion - all work is done but final flags not yet set
        update_option('nmkr_sync_near_completion', true);
        nmkr_log_data_sync('Marked sync as near completion - batch processing finished, finalizing flags', 'info');
        
        // Complete the sync process
        if (function_exists('nmkr_sync_data_complete')) {
            $batch_metrics = nmkr_build_final_sync_metrics(array(
                'total_sync_duration' => $sync_stats['total_duration'],
                'total_api_time' => $sync_stats['total_api_time'],
                'average_response_time' => $sync_stats['average_time'],
                'api_requests' => $sync_stats['request_count'],
                'memory_usage' => $sync_stats['memory_used'],
            ), $sync_data, array(
                'total_projects' => $stats['total_projects'],
                'total_tokens' => $stats['total_tokens'],
            ));
            $final = array(
                'metrics' => $batch_metrics,
                'items_processed' => isset($sync_data['total_tokens']) ? $sync_data['total_tokens'] : 0,
                'items_successful' => isset($sync_data['completed_tokens']) ? $sync_data['completed_tokens'] : 0,
            );
            $prepared = nmkr_prepare_sync_finalization($sync_stats_id, $final, $sync_data);
            $terminal = $prepared ? nmkr_sync_data_complete(true, '', $prepared, false, true) : false;
            return is_array($terminal) && in_array($terminal['status'] ?? '', array('completed', 'success'), true)
                ? $terminal : new WP_Error('sync_finalization_pending', 'Batch business data completed; terminal cleanup remains pending.');
        }
    }
    
    // If we get here, all processing is complete
    $progress = nmkr_update_sync_progress($sync_data['total_steps'], $sync_data['total_steps'], 'Synchronization completed');
    
    // End overall performance tracking
    $overall_performance = nmkr_end_performance_tracking($sync_data['tracking']);
    
    // Get final sync stats for detailed performance data
    $sync_stats = nmkr_get_sync_stats();
    
    // Get current stats for performance data (no longer saving to database here - handled by sync core)
    $current_stats = get_transient('nmkr_current_sync_stats_live');
    $stats = null;
    if ($current_stats) {
        $total_duration = (microtime(true) - $current_stats['operation_start_time']); // Now in seconds
        $average_response_time = $current_stats['request_count'] > 0 ? 
            array_sum($current_stats['request_times']) / $current_stats['request_count'] : 0;

        // Prepare statistics for logging only
        $stats = array(
            'total_projects' => $current_stats['total_projects'] ?? 0,
            'total_tokens' => $current_stats['total_tokens'] ?? 0,
            'total_sync_duration' => round($total_duration, 2),
            'total_api_time' => round($current_stats['total_api_time'], 2),
            'average_response_time' => round($average_response_time, 2),
            'api_requests' => $current_stats['request_count'],
            'memory_usage' => round(memory_get_peak_usage() / 1024 / 1024, 2)
        );
        
        // Note: Database saving is now handled by the authoritative path in nmkr-sync-core.php
        nmkr_log_data_sync('📊 Batch processing metrics calculated (database save handled by sync core).');
    }
    
    // Update sync stats at completion
    if ($sync_stats_id) {
        nmkr_update_sync_stats($sync_stats_id, [
            'status' => 'completed',
            'end_time' => nmkr_get_timestamp(),
            'items_processed' => $sync_data['total_tokens'],
            'items_successful' => $sync_data['completed_tokens']
        ]);
    }
    
    // Combine overall performance with detailed stats and final statistics
    $final_performance = array_merge($overall_performance, [
        'total_duration' => $sync_stats['total_duration'],
        'request_count' => $sync_stats['request_count'],
        'average_time' => $sync_stats['average_time'],
        'total_api_time' => $sync_stats['total_api_time'],
        'memory_used' => $sync_stats['memory_used'],
        'final_statistics' => $stats
    ]);
    
    // Single comprehensive log message
    nmkr_log_data_sync('Synchronization completed successfully', 'success', array('performance' => $final_performance));
    
    // Mark sync near completion - all work is done but final flags not yet set
    update_option('nmkr_sync_near_completion', true);
    nmkr_log_data_sync('Marked sync as near completion - batch processing complete, finalizing status', 'info');
    
    // Update sync status
    $status = array(
        'completed' => true,
        'timestamp' => nmkr_get_timestamp()
    );

    // Save the final status
    nmkr_save_sync_data($status);
    
    // Clean up
    nmkr_clear_sync_data();

    // Note: Metrics saving is now handled by the single authoritative path in nmkr-sync-core.php
    // Removed fallback mechanism to prevent duplicate database inserts

    return $status;
}
