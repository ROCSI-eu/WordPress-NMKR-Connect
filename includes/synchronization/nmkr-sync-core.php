<?php
/**
 * NMKR Connect - Core Synchronization Functions
 *
 * This file contains the core functionality for synchronizing data with the NMKR API.
 * It handles the main synchronization process and initialization.
 *
 * @package NMKR Connect
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Required dependencies
require_once plugin_dir_path(dirname(__FILE__)) . 'database/nmkr-database-functions.php';
require_once plugin_dir_path(dirname(__FILE__)) . 'helpers/nmkr-performance-functions.php';
require_once plugin_dir_path(dirname(__FILE__)) . 'helpers/nmkr-utility-functions.php';
require_once plugin_dir_path(__FILE__) . 'nmkr-sync-batch-processing.php';
require_once plugin_dir_path(__FILE__) . 'nmkr-sync-progress-tracking.php';
require_once plugin_dir_path(__FILE__) . 'nmkr-sync-error-handling.php';

/**
 * Interpret a run-scoped safe-boundary checkpoint in one place. Legacy callers
 * pass no context and remain unchanged; direct workers never issue another API
 * request or business write once this returns an error.
 */
function nmkr_sync_worker_checkpoint($run_id, $sync_stats_id, $phase) {
    if ($run_id === '') return true;
    $state = nmkr_sync_run_checkpoint($run_id, $sync_stats_id, $phase);
    if ($state === 'continue') return true;
    if ($state === 'stop_requested') return new WP_Error('sync_stop_requested', __('Synchronization stop was requested.', 'nmkr-connect'));
    if ($state === 'checkpoint_lock_failed') return new WP_Error('sync_checkpoint_lock_failed', __('Synchronization checkpoint lock is unavailable.', 'nmkr-connect'));
    if ($state === 'checkpoint_persistence_failed') return new WP_Error('sync_checkpoint_persistence_failed', __('Synchronization checkpoint could not be persisted.', 'nmkr-connect'));
    return new WP_Error('sync_owner_mismatch', __('Synchronization ownership no longer matches this worker.', 'nmkr-connect'));
}

/** Finalize an exact worker halt only at the top-level orchestrator. */
function nmkr_handle_sync_worker_halt($halt, $run_id, $sync_stats_id) {
    if (!is_wp_error($halt)) return false;
    if ($halt->get_error_code() !== 'sync_stop_requested') return $halt;
    $owner = nmkr_get_sync_owner();
    if (!is_array($owner) || !hash_equals((string) ($owner['run_id'] ?? ''), (string) $run_id)
        || ($owner['mode'] ?? '') !== 'direct' || ($owner['state'] ?? '') !== 'stop_requested'
        || (int) ($owner['sync_stats_id'] ?? 0) !== (int) $sync_stats_id) return new WP_Error('sync_owner_mismatch', __('Synchronization ownership no longer matches this worker.', 'nmkr-connect'));
    $finalizing = nmkr_transition_sync_owner($run_id, 'stop_requested', 'finalizing', $sync_stats_id);
    if (!nmkr_sync_owner_transition_succeeded($finalizing)) return new WP_Error('sync_owner_mismatch', __('Synchronization ownership no longer matches this worker.', 'nmkr-connect'));
    $final = nmkr_sync_data_complete(false, __('Synchronization stopped by user.', 'nmkr-connect'), array('outcome' => 'stopped'));
    return is_array($final) ? $final : new WP_Error('sync_stopped_finalization_pending', __('Synchronization stop finalization remains pending.', 'nmkr-connect'));
}

/**
 * Count the total number of sync steps before starting the sync process
 * 
 * This function calculates the total steps needed for the complete sync:
 * - 1 step per project
 * - 1 step per token 
 * - 1 step per token detail
 * 
 * @param array $projects Array of project data from NMKR API
 * @return int Total number of sync steps
 */
function nmkr_count_sync_steps($projects, $run_id = '', $sync_stats_id = 0) {
    try {
        if (!is_array($projects)) {
            nmkr_log_data_sync('Invalid projects data for step counting: expected array, got ' . gettype($projects), 'warning');
            return 0;
        }
        
        $total_steps = 0;
        
        // 1 step per project
        $total_steps += count($projects);
        
        // Count tokens for each project (requires API calls)
        foreach ($projects as $project) {
            $halt = nmkr_sync_worker_checkpoint($run_id, $sync_stats_id, 'between_step_count_projects');
            if (is_wp_error($halt)) return $halt;
            $project_uid = isset($project['project_uid']) ? $project['project_uid'] : 
                          (isset($project['uid']) ? $project['uid'] : null);
            
            if (!$project_uid) {
                nmkr_log_data_sync('Missing project UID when counting steps for project: ' . json_encode($project), 'warning');
                continue;
            }
            
            try {
                // Fetch tokens to count them
                $tracking = nmkr_start_performance_tracking('fetch_tokens_for_count_' . $project_uid);
                $halt = nmkr_sync_worker_checkpoint($run_id, $sync_stats_id, 'before_step_count_token_list_request');
                if (is_wp_error($halt)) return $halt;
                $tokens = nmkr_connect_fetch_nfts_by_project($project_uid);
                $halt = nmkr_sync_worker_checkpoint($run_id, $sync_stats_id, 'after_step_count_token_list_request');
                if (is_wp_error($halt)) return $halt;
                nmkr_end_performance_tracking($tracking);
                
                if (is_wp_error($tokens)) {
                    nmkr_log_data_sync('Failed to fetch tokens for step counting in project ' . $project_uid . ': ' . $tokens->get_error_message(), 'warning');
                    continue;
                }
                
                if (is_array($tokens)) {
                    $token_count = count($tokens);
                    // 1 step per token + 1 step per token detail
                    $total_steps += ($token_count * 2);
                    
                    nmkr_log_data_sync('Project ' . $project_uid . ' has ' . $token_count . ' tokens (' . ($token_count * 2) . ' steps)');
                }
                
            } catch (Exception $e) {
                nmkr_log_data_sync('Exception while counting steps for project ' . $project_uid . ': ' . $e->getMessage(), 'warning');
                continue;
            }
        }
        
        nmkr_log_data_sync('Total sync steps calculated: ' . $total_steps);
        return $total_steps;
        
    } catch (Exception $e) {
        nmkr_log_data_sync('Critical error counting sync steps: ' . $e->getMessage(), 'error');
        return 0;
    }
}

/**
 * Sync all projects from NMKR API
 * 
 * Fetches all projects from the NMKR API, stores each project to the local database,
 * logs success/failure per project, and updates sync progress.
 * 
 * @param array &$sync_log Reference to sync log array
 * @param int &$completed_steps Reference to completed steps counter
 * @param int $total_steps Total number of sync steps
 * @return array Array of project UIDs on success, empty array on failure
 */
function nmkr_sync_projects(&$sync_log, &$completed_steps, $total_steps, $run_id = '', $sync_stats_id = 0) {
    try {
        $sync_log[] = 'Starting project synchronization';
        
        // Fetch projects from API with comprehensive error handling
        try {
            $tracking = nmkr_start_performance_tracking('fetch_projects');
            $halt = nmkr_sync_worker_checkpoint($run_id, $sync_stats_id, 'before_projects_request'); if (is_wp_error($halt)) return $halt;
            $projects = nmkr_connect_fetch_projects();
            $halt = nmkr_sync_worker_checkpoint($run_id, $sync_stats_id, 'after_projects_request'); if (is_wp_error($halt)) return $halt;
            nmkr_end_performance_tracking($tracking);
            
            if (is_wp_error($projects)) {
                $error_message = $projects->get_error_message();
                $sync_log[] = 'ERROR: Failed to fetch projects from API: ' . $error_message;
                nmkr_log_data_sync('API error fetching projects: ' . $error_message, 'error');
                return array();
            }
            
            // Validate projects response
            if (!is_array($projects)) {
                $error_message = 'Invalid projects response from API: expected array, got ' . gettype($projects);
                $sync_log[] = 'ERROR: ' . $error_message;
                nmkr_log_data_sync($error_message, 'error');
                return array();
            }
            
            if (empty($projects)) {
                $sync_log[] = 'WARNING: No projects returned from API';
                nmkr_log_data_sync('Warning: No projects returned from API', 'warning');
                return array();
            }
            
        } catch (Exception $e) {
            $error_message = 'Critical error during project fetching: ' . $e->getMessage();
            $sync_log[] = 'ERROR: ' . $error_message;
            nmkr_log_data_sync($error_message, 'error', array(
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ));
            return array();
        }
        
        // Validate and process individual projects
        $valid_projects = array();
        $project_uids = array();
        
        foreach ($projects as $index => $project) {
            if (!is_array($project)) {
                $sync_log[] = 'WARNING: Invalid project data at index ' . $index . ': expected array, got ' . gettype($project);
                nmkr_log_data_sync('Invalid project data at index ' . $index . ': expected array, got ' . gettype($project), 'warning');
                continue;
            }
            
            // Check for required fields
            $required_fields = ['projectname'];
            $is_valid = true;
            
            foreach ($required_fields as $field) {
                if (!isset($project[$field]) || $project[$field] === null || $project[$field] === '') {
                    $sync_log[] = 'WARNING: Invalid project data at index ' . $index . ': missing or empty required field "' . $field . '"';
                    nmkr_log_data_sync('Invalid project data at index ' . $index . ': missing or empty required field "' . $field . '"', 'warning');
                    $is_valid = false;
                    break;
                }
            }
            
            if ($is_valid) {
                $valid_projects[] = $project;
            }
        }
        
        $sync_log[] = 'Found ' . count($valid_projects) . ' valid projects (filtered from ' . count($projects) . ' total)';
        nmkr_log_data_sync('Found ' . count($valid_projects) . ' valid projects (filtered from ' . count($projects) . ' total)');
        
        // Use the global total steps passed from main sync function
        // (No override - respect the calculated total including tokens and details)
        
        // Stage 1: Project Processing
        update_option('nmkr_sync_total_items', $total_steps);
        update_option('nmkr_sync_current_count', $completed_steps);
        
        // Initialize progress with global total steps
        nmkr_update_sync_progress($completed_steps, $total_steps, 'Starting project synchronization');
        
        $project_count = 0;
        $successful_projects = 0;
        $failed_projects = 0;
        
        foreach ($valid_projects as $project) {
            $halt = nmkr_sync_worker_checkpoint($run_id, $sync_stats_id, 'between_projects'); if (is_wp_error($halt)) return $halt;
            $project_name = isset($project['projectname']) ? $project['projectname'] : 'Unknown Project';
            $project_uid = isset($project['uid']) ? $project['uid'] : 
                          (isset($project['uid']) ? $project['uid'] : null);
            
            nmkr_update_sync_progress($completed_steps, $total_steps, '🗂️ Processing Project: ' . $project_name);
            
            // Validate project UID
            if (!$project_uid) {
                $sync_log[] = 'ERROR: Missing project unique identifier in project data';
                nmkr_log_data_sync('Missing project unique identifier in project data', 'error', array('project' => $project));
                $failed_projects++;
                $completed_steps++; // Increment for failed projects to maintain progress
                $project_count++;
                continue;
            }
            
            try {
                $halt = nmkr_sync_worker_checkpoint($run_id, $sync_stats_id, 'before_project_write'); if (is_wp_error($halt)) return $halt;
                // Store project in database
                $store_project_tracking = nmkr_start_performance_tracking('store_project_' . $project_uid);
                $store_result = nmkr_store_project($project);
                $store_project_performance = nmkr_end_performance_tracking($store_project_tracking);
                
                if ($store_result === false) {
                    throw new Exception('Database insertion failed for project');
                }
                
                $sync_log[] = 'SUCCESS: Stored project "' . $project['projectname'] . '" (UID: ' . $project_uid . ')';
                $project_uids[] = $project_uid;
                $successful_projects++;
                $completed_steps++;
                
            } catch (Exception $e) {
                $error_message = 'Failed to store project "' . $project['projectname'] . '": ' . $e->getMessage();
                $sync_log[] = 'ERROR: ' . $error_message;
                nmkr_log_data_sync($error_message, 'error', array(
                    'project_uid' => $project_uid,
                    'project_name' => $project['projectname']
                ));
                $failed_projects++;
                $completed_steps++; // Increment for failed projects to maintain progress
            }
            
            $project_count++;
        }
        
        
        $sync_log[] = 'Project synchronization complete. Success: ' . $successful_projects . ', Failed: ' . $failed_projects;
        nmkr_log_data_sync('Project synchronization complete. Success: ' . $successful_projects . ', Failed: ' . $failed_projects);
        
        return $project_uids;
        
    } catch (Exception $e) {
        $error_message = 'Critical error in project synchronization: ' . $e->getMessage();
        $sync_log[] = 'ERROR: ' . $error_message;
        nmkr_log_data_sync($error_message, 'error', array(
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ));
        return array();
    }
}

/**
 * Fetch token UIDs for a specific project
 * 
 * Fetches token UIDs for a specific project via NMKR API and returns them
 * for later processing. This function only handles the API call and basic
 * validation, not storage.
 * 
 * @param string $project_uid The project UID to fetch tokens for
 * @param array &$sync_log Reference to sync log array
 * @return array Array containing token UIDs and basic token data
 */
function nmkr_fetch_tokens_for_project($project_uid, &$sync_log, $completed_steps = 0, $total_steps = 1, $run_id = '', $sync_stats_id = 0) {
    try {
        $sync_log[] = 'Starting token UID fetching for project: ' . $project_uid;
        nmkr_update_sync_progress($completed_steps, $total_steps, '🔍 Fetching Token UIDs for Project: ' . $project_uid);
        
        // Fetch tokens for this project
        try {
            $tracking = nmkr_start_performance_tracking('fetch_tokens_for_project_' . $project_uid);
            $halt = nmkr_sync_worker_checkpoint($run_id, $sync_stats_id, 'before_token_list_request'); if (is_wp_error($halt)) return $halt;
            $tokens = nmkr_connect_fetch_nfts_by_project($project_uid);
            $halt = nmkr_sync_worker_checkpoint($run_id, $sync_stats_id, 'after_token_list_request'); if (is_wp_error($halt)) return $halt;
            nmkr_end_performance_tracking($tracking);
            
            if (is_wp_error($tokens)) {
                $error_message = $tokens->get_error_message();
                $sync_log[] = 'ERROR: Failed to fetch tokens for project ' . $project_uid . ': ' . $error_message;
                nmkr_log_data_sync('API error fetching tokens for project ' . $project_uid . ': ' . $error_message, 'error');
                return array(
                    'token_uids' => array(),
                    'tokens_data' => array(),
                    'successful_fetches' => 0,
                    'failed_fetches' => 0
                );
            }
            
            // Validate tokens response structure
            if (!is_array($tokens)) {
                $error_message = 'Invalid tokens response from API for project ' . $project_uid . ': expected array, got ' . gettype($tokens);
                $sync_log[] = 'ERROR: ' . $error_message;
                nmkr_log_data_sync($error_message, 'error');
                return array(
                    'token_uids' => array(),
                    'tokens_data' => array(),
                    'successful_fetches' => 0,
                    'failed_fetches' => 0
                );
            }
            
        } catch (Exception $e) {
            $error_message = 'Critical error fetching tokens for project ' . $project_uid . ': ' . $e->getMessage();
            $sync_log[] = 'ERROR: ' . $error_message;
            nmkr_log_data_sync($error_message, 'error', array(
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ));
            return array(
                'token_uids' => array(),
                'tokens_data' => array(),
                'successful_fetches' => 0,
                'failed_fetches' => 0
            );
        }
        
        if (empty($tokens)) {
            $sync_log[] = 'INFO: No tokens found for project ' . $project_uid;
            nmkr_log_data_sync('No tokens found for project ' . $project_uid, 'info');
            return array(
                'token_uids' => array(),
                'tokens_data' => array(),
                'successful_fetches' => 0,
                'failed_fetches' => 0
            );
        }
        
        // Validate and process individual tokens
        $valid_tokens = array();
        $token_uids = array();
        $failed_fetches = 0;
        
        foreach ($tokens as $token_index => $token) {
            if (!is_array($token)) {
                $sync_log[] = 'WARNING: Invalid token data at index ' . $token_index . ' for project ' . $project_uid . ': expected array, got ' . gettype($token);
                if (!nmkr_should_throttle_logs()) {
                    nmkr_log_data_sync('Invalid token data at index ' . $token_index . ' for project ' . $project_uid . ': expected array, got ' . gettype($token), 'warning');
                }
                $failed_fetches++;
                continue;
            }
            
            // Check for token UID (required for processing)
            $token_uid = isset($token['uid']) ? $token['uid'] : 
                        (isset($token['token_uid']) ? $token['token_uid'] : null);
            
            if (!$token_uid || $token_uid === '') {
                $sync_log[] = 'WARNING: Invalid token data at index ' . $token_index . ' for project ' . $project_uid . ': missing token UID';
                if (!nmkr_should_throttle_logs()) {
                    nmkr_log_data_sync('Invalid token data at index ' . $token_index . ' for project ' . $project_uid . ': missing token UID', 'warning');
                }
                $failed_fetches++;
                continue;
            }
            
            $valid_tokens[] = $token;
            $token_uids[] = $token_uid;
        }
        
        $sync_log[] = '✅ Fetch complete (project ' . $project_uid . '): ' . count($valid_tokens) . ' valid tokens fetched (filtered from ' . count($tokens) . ' total)';
        nmkr_log_data_sync('✅ Fetch complete (project ' . $project_uid . '): ' . count($valid_tokens) . ' valid tokens fetched', 'info');
        
        // Return token UIDs and data for later processing
        return array(
            'token_uids' => $token_uids,
            'tokens_data' => $valid_tokens,
            'successful_fetches' => count($valid_tokens),
            'failed_fetches' => $failed_fetches
        );
        
    } catch (Exception $e) {
        $error_message = 'Critical error in token fetching for project ' . $project_uid . ': ' . $e->getMessage();
        $sync_log[] = 'ERROR: ' . $error_message;
        nmkr_log_data_sync($error_message, 'error', array(
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ));
        return array(
            'token_uids' => array(),
            'tokens_data' => array(),
            'successful_fetches' => 0,
            'failed_fetches' => 0
        );
    }
}

/**
 * Sync tokens for a specific project
 * 
 * Processes and stores basic token data for a specific project. This function
 * now only handles token storage, not fetching.
 * 
 * @param string $project_uid The project UID to process tokens for
 * @param array $tokens_data Array of token data to process
 * @param array &$sync_log Reference to sync log array
 * @param int &$completed_steps Reference to completed steps counter
 * @param int $total_steps Total number of sync steps
 * @return array Array containing processing results
 */
function nmkr_sync_tokens($project_uid, $tokens_data, &$sync_log, &$completed_steps, $total_steps, $run_id = '', $sync_stats_id = 0) {
    try {
        $sync_log[] = 'Starting token processing for project: ' . $project_uid;
        nmkr_update_sync_progress($completed_steps, $total_steps, '🪙 Processing Token Data for Project: ' . $project_uid);
        
        if (empty($tokens_data)) {
            $sync_log[] = 'INFO: No tokens to process for project ' . $project_uid;
            nmkr_log_data_sync('No tokens to process for project ' . $project_uid, 'info');
            return array(
                'token_uids' => array(),
                'successful_tokens' => 0,
                'failed_tokens' => 0,
                'skipped_tokens' => 0
            );
        }
        
        // Process and collect each token UID (no storage here)
        $token_uids = array();
        $successful_tokens = 0;
        $failed_tokens = 0;
        $skipped_tokens = 0;
        
        foreach ($tokens_data as $token_index => $token) {
            $halt = nmkr_sync_worker_checkpoint($run_id, $sync_stats_id, 'between_tokens'); if (is_wp_error($halt)) return $halt;
            // Validate that $token is an array
            if (!is_array($token)) {
                $skipped_tokens++;
                $sync_log[] = 'WARNING: Invalid token data at index ' . $token_index . ' for project ' . $project_uid . ': expected array, got ' . gettype($token);
                if (!nmkr_should_throttle_logs()) {
                    nmkr_log_data_sync('Invalid token data at index ' . $token_index . ' for project ' . $project_uid . ': expected array, got ' . gettype($token), 'warning');
                }
                continue;
            }
            
            // Validate required fields with fallback logic
            $token_uid = null;
            if (isset($token['uid']) && !empty($token['uid'])) {
                $token_uid = $token['uid'];
            } elseif (isset($token['token_uid']) && !empty($token['token_uid'])) {
                $token_uid = $token['token_uid'];
            }
            
            // Skip tokens without a valid UID
            if (!$token_uid) {
                $skipped_tokens++;
                $sync_log[] = 'WARNING: Token at index ' . $token_index . ' for project ' . $project_uid . ': missing required field "uid" or "token_uid"';
                if (!nmkr_should_throttle_logs()) {
                    nmkr_log_data_sync('Token at index ' . $token_index . ' for project ' . $project_uid . ': missing required field "uid" or "token_uid"', 'warning');
                }
                continue;
            }
            
            // Validate other critical fields that nmkr_store_token() expects
            $required_fields = ['id', 'name'];
            $missing_fields = array();
            
            foreach ($required_fields as $field) {
                if (!isset($token[$field]) || $token[$field] === null || $token[$field] === '') {
                    $missing_fields[] = $field;
                }
            }
            
            // Skip tokens with missing critical fields
            if (!empty($missing_fields)) {
                $skipped_tokens++;
                $sync_log[] = 'WARNING: Token ' . $token_uid . ' for project ' . $project_uid . ': missing critical fields: ' . implode(', ', $missing_fields);
                if (!nmkr_should_throttle_logs()) {
                    nmkr_log_data_sync('Token ' . $token_uid . ' for project ' . $project_uid . ': missing critical fields: ' . implode(', ', $missing_fields), 'warning');
                }
                continue;
            }
            
            try {
                // Only collect token UID for later detail processing
                $token_uids[] = $token_uid;
                $successful_tokens++;
                $sync_log[] = '✅ Token UID collected (UID ' . $token_uid . ')';
                if (!nmkr_should_throttle_logs()) {
                    nmkr_log_data_sync('✅ Token UID collected (UID ' . $token_uid . ')', 'info');
                }
            } catch (Exception $e) {
                $failed_tokens++;
                $sync_log[] = 'ERROR: Exception collecting token UID ' . $token_uid . ' for project ' . $project_uid . ': ' . $e->getMessage();
                nmkr_log_data_sync('Exception collecting token UID ' . $token_uid . ' for project ' . $project_uid . ': ' . $e->getMessage(), 'error');
            }
        }
        
        $sync_log[] = 'Token UID collection complete for project ' . $project_uid . ': ' . $successful_tokens . ' tokens collected, ' . $failed_tokens . ' failed, ' . $skipped_tokens . ' skipped';
        nmkr_log_data_sync('Token UID collection complete for project ' . $project_uid . ': ' . $successful_tokens . ' tokens collected, ' . $skipped_tokens . ' skipped', 'info');
        
        // Return only token UIDs for later detail processing
        return array(
            'token_uids' => $token_uids,
            'successful_tokens' => $successful_tokens,
            'failed_tokens' => $failed_tokens,
            'skipped_tokens' => $skipped_tokens
        );
        
    } catch (Exception $e) {
        $error_message = 'Critical error in token processing for project ' . $project_uid . ': ' . $e->getMessage();
        $sync_log[] = 'ERROR: ' . $error_message;
        nmkr_log_data_sync($error_message, 'error', array(
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ));
        return array(
            'token_uids' => array(),
            'successful_tokens' => 0,
            'failed_tokens' => 0,
            'skipped_tokens' => 0
        );
    }
}

/**
 * Sync token details for a specific token
 * 
 * Fetches metadata for a specific token using NMKR API, stores it using database functions,
 * handles null/empty/invalid responses gracefully, and logs issues clearly.
 * 
 * @param string $token_uid The token UID to fetch details for
 * @param string $project_uid The project UID associated with the token
 * @param array &$sync_log Reference to sync log array
 * @param int &$completed_steps Reference to completed steps counter
 * @param int $total_steps Total number of sync steps
 * @return true|WP_Error True on success, WP_Error on failure or validation issues
 */
function nmkr_sync_token_details($token_uid, $project_uid, &$sync_log, &$completed_steps, $total_steps, $token = null, $run_id = '', $sync_stats_id = 0) {
    try {
        nmkr_update_sync_progress($completed_steps, $total_steps, 'Fetching details for token: ' . $token_uid);
        
        // Fetch token details from API
        try {
            $tracking = nmkr_start_performance_tracking('fetch_token_details_' . $token_uid);
            $halt = nmkr_sync_worker_checkpoint($run_id, $sync_stats_id, 'before_token_detail_request'); if (is_wp_error($halt)) return $halt;
            $details = nmkr_connect_fetch_nft_details($token_uid);
            $halt = nmkr_sync_worker_checkpoint($run_id, $sync_stats_id, 'after_token_detail_request'); if (is_wp_error($halt)) return $halt;
            nmkr_end_performance_tracking($tracking);

            // Handle validation failure as a special case
            if (is_wp_error($details) && $details->get_error_code() === 'validation_failure') {
                // Do not increment $completed_steps here; handled in the retry loop for skipped tokens
                return $details;
            }
            
            if (is_wp_error($details)) {
                $error_message = $details->get_error_message();
                $sync_log[] = 'ERROR: Failed to fetch details for token ' . $token_uid . ': ' . $error_message;
                nmkr_log_data_sync('API error fetching details for token ' . $token_uid . ': ' . $error_message, 'error');
                // FIXED: Don't increment completed_steps on error - let retry loop handle it
                return new WP_Error('api_error', $error_message);
            }
            
            // Handle null/empty responses gracefully
            if ($details === null || $details === '') {
                $sync_log[] = 'WARNING: Empty details response for token ' . $token_uid;
                nmkr_log_data_sync('Empty details response for token ' . $token_uid, 'warning');
                // FIXED: Don't increment completed_steps on error - let retry loop handle it
                return new WP_Error('empty_details', 'Empty details response');
            }
            
            // Validate details structure
            if (!is_array($details) && !is_object($details)) {
                $sync_log[] = 'WARNING: Invalid details response for token ' . $token_uid . ': expected array or object, got ' . gettype($details);
                if (!nmkr_should_throttle_logs()) {
                    nmkr_log_data_sync('Invalid details response for token ' . $token_uid . ': expected array or object, got ' . gettype($details), 'warning');
                }
                // FIXED: Don't increment completed_steps on error - let retry loop handle it
                return new WP_Error('invalid_details', 'Invalid details response: expected array or object, got ' . gettype($details));
            }
            
            // NEW: Store token in main table only after successful details fetch
            if (is_array($details) && !empty($details)) {
                // Merge $token and $details if $token is provided
                $merged_token_data = is_array($token) ? array_merge($token, $details) : $details;
                $halt = nmkr_sync_worker_checkpoint($run_id, $sync_stats_id, 'before_token_write'); if (is_wp_error($halt)) return $halt;
                $store_token_result = nmkr_store_token($merged_token_data, $project_uid);
                if ($store_token_result === true) {
                    $sync_log[] = 'SUCCESS: Stored token in main table for UID: ' . $token_uid;
                    nmkr_log_data_sync('Stored token in main table for UID: ' . $token_uid, 'info');
                } else {
                    $sync_log[] = 'ERROR: Failed to store token in main table for UID: ' . $token_uid;
                    nmkr_log_data_sync('Failed to store token in main table for UID: ' . $token_uid, 'error');
                }
            }
        } catch (Exception $e) {
            $error_message = 'Critical error fetching details for token ' . $token_uid . ': ' . $e->getMessage();
            $sync_log[] = 'ERROR: ' . $error_message;
            nmkr_log_data_sync($error_message, 'error', array(
                'token_uid' => $token_uid,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ));
            // FIXED: Don't increment completed_steps on error - let retry loop handle it
            return new WP_Error('exception', $error_message);
        }
        
        // Store token details in database
        try {
            $halt = nmkr_sync_worker_checkpoint($run_id, $sync_stats_id, 'before_token_detail_write'); if (is_wp_error($halt)) return $halt;
            $store_result = nmkr_store_token_details($token_uid, $details);
            
            if ($store_result === false) {
                throw new Exception('Database insertion failed for token details');
            }
            
            $sync_log[] = 'SUCCESS: Stored details for token UID: ' . $token_uid;
            nmkr_update_sync_progress($completed_steps, $total_steps, 'Processing token details - Token: ' . $token_uid);
            return true;
        } catch (Exception $e) {
            $error_message = 'Failed to store details for token ' . $token_uid . ': ' . $e->getMessage();
            $sync_log[] = 'ERROR: ' . $error_message;
            nmkr_log_data_sync($error_message, 'error', array(
                'token_uid' => $token_uid
            ));
            // FIXED: Don't increment completed_steps on error - let retry loop handle it
            return new WP_Error('store_failed', $error_message);
        }
    } catch (Exception $e) {
        $error_message = 'Critical error in token details synchronization for token ' . $token_uid . ': ' . $e->getMessage();
        $sync_log[] = 'ERROR: ' . $error_message;
        nmkr_log_data_sync($error_message, 'error', array(
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ));
        // FIXED: Don't increment completed_steps on error - let retry loop handle it
        return new WP_Error('critical_error', $error_message);
    }
}

/**
 * Main function to handle the synchronization process
 * 
 * ** REFACTORED MODULAR ORCHESTRATOR **
 * 
 * This function serves as the main orchestrator that calls the modular functions
 * in sequence: projects → tokens → token details. It wraps everything in try/catch
 * and returns a structured response.
 * 
 * ** ENHANCED ERROR HANDLING DOCUMENTATION **
 * 
 * This function retains all enhanced error handling from previous versions including:
 * 
 * 1. API Configuration Validation:
 *    - Missing API key detection
 *    - Configuration access failures
 * 
 * 2. Sync Data Initialization:
 *    - Database connection failures
 *    - Sync stats creation failures
 *    - Data structure validation
 * 
 * 3. Modular Function Error Handling:
 *    - Project synchronization failures
 *    - Token synchronization failures
 *    - Token details synchronization failures
 * 
 * 4. Critical Error Cleanup:
 *    - Automatic sync state reset
 *    - Scheduled job cleanup
 *    - Frontend status updates
 * 
 * @return array|string|WP_Error Result of the sync operation
 */
function nmkr_sync_data($run_id = '') {
    $sync_stats_id = null;
    $sync_log = array();
    $completed_steps = 0;
    $total_steps = 0;
    $business_data_complete = false;

    if (!nmkr_is_valid_sync_run_id($run_id) || !nmkr_sync_owner_matches($run_id, 'running', 0)) {
        return new WP_Error('sync_owner_mismatch', 'Synchronization owner no longer matches this worker.');
    }
    
    // Initialize tracking variables for final summary
    $sync_start_time = microtime(true);
    $total_successful_tokens = 0;
    $total_skipped_tokens = 0;
    $total_failed_tokens = 0;

    try {
        // ** ENHANCED ERROR HANDLING: API Configuration Validation **
        try {
            $options = get_option('nmkr_connect_options');
            $api_key = isset($options['api_key']) ? $options['api_key'] : '';
            if (empty($api_key)) {
                $error_details = NMKR_Sync_Common_Errors::apiKeyMissing();
                $error = new WP_Error($error_details['code'], $error_details['message']);
                $sync_log[] = 'ERROR: ' . $error_details['formatted_display'];
                nmkr_log_data_sync('Sync failed: ' . $error_details['message'], 'error');
                
                // Update sync status for frontend
                nmkr_cleanup_failed_direct_sync($run_id, 0, $error_details['formatted_display']);
                
                if (defined('DOING_AJAX') && DOING_AJAX) {
                    return array(
                        'success' => false,
                        'message' => $error_details['message'],
                        'error_code' => $error_details['code'],
                        'formatted_display' => $error_details['formatted_display'],
                        'log' => $sync_log
                    );
                }
                return $error;
            }
        } catch (Exception $e) {
            $error_msg = 'Failed to validate API configuration: ' . $e->getMessage();
            $sync_log[] = 'ERROR: ' . $error_msg;
            nmkr_log_data_sync('Critical error during API configuration check: ' . $e->getMessage(), 'error');
            
            nmkr_cleanup_failed_direct_sync($run_id, 0, $error_msg);
            
            if (defined('DOING_AJAX') && DOING_AJAX) {
                return array(
                    'success' => false,
                    'message' => $error_msg,
                    'error_code' => 'config_validation_failed',
                    'log' => $sync_log
                );
            }
            return new WP_Error('config_validation_failed', $error_msg);
        }
        
        // ** ENHANCED ERROR HANDLING: Sync Data Initialization **
        try {
            // Generate correlation ID for this sync
            $correlation_id = nmkr_generate_correlation_id();
            
            // Record sync initialization in the sync_stats table
            $sync_stats_id = nmkr_save_sync_stats([
                'run_id' => $run_id,
                'sync_type' => 'full_sync',
                'start_time' => nmkr_get_timestamp(),
                'status' => 'initializing',
                'items_processed' => 0,
                'items_successful' => 0,
                'items_failed' => 0
            ]);
            
            if (!$sync_stats_id) {
                throw new Exception('Failed to create sync statistics record');
            }
            $bound_owner = nmkr_bind_exact_sync_history_owner($run_id, $sync_stats_id);
            if (!nmkr_sync_owner_transition_succeeded($bound_owner)) {
                return nmkr_handle_sync_owner_binding_failure($bound_owner, $run_id, $sync_stats_id);
            }
        } catch (Exception $e) {
            $error_msg = 'Failed to initialize synchronization: ' . $e->getMessage();
            $sync_log[] = 'ERROR: ' . $error_msg;
            nmkr_log_data_sync('Critical error during sync initialization: ' . $e->getMessage(), 'error');
            
            if (nmkr_sync_owner_matches($run_id, 'running', $sync_stats_id ?: 0)) {
                nmkr_cleanup_failed_direct_sync($run_id, $sync_stats_id ?: 0, $error_msg);
            }
            
            if (defined('DOING_AJAX') && DOING_AJAX) {
                return array(
                    'success' => false,
                    'message' => $error_msg,
                    'error_code' => 'initialization_failed',
                    'log' => $sync_log
                );
            }
            return new WP_Error('initialization_failed', $error_msg);
        }
        
        // Seed live stats transient before polling begins
        set_transient(
            'nmkr_current_sync_stats_live',
            [
                'start_time'     => microtime(true),
                'request_count'  => 0,
                'total_api_time' => 0,
                'request_times'  => [],
                'memory_usage'   => memory_get_peak_usage(true) / 1024
            ],
            NMKR_SYNC_TRANSIENT_TTL
        );
        
        // Set sync in progress flag
        update_option('nmkr_sync_in_progress', true);
        set_transient('nmkr_sync_in_progress', true, NMKR_SYNC_TRANSIENT_TTL);
        
        // Store sync data with correlation ID
        $sync_data = array(
            'run_id' => $run_id,
            'correlation_id' => $correlation_id,
            'start_time' => nmkr_get_timestamp(),
            'status' => 'initializing',
            'api_key_configured' => true,
            'sync_stats_id' => $sync_stats_id,
            'last_update_time' => time(),
            'all_tokens' => array(),
            'tracking' => null
        );
        if (!in_array(($bound_owner['state'] ?? ''), array('running', 'stop_requested'), true) || !nmkr_save_sync_data($sync_data)) {
            throw new Exception('Failed to persist run-owned synchronization state');
        }
        // Stop can win after history binding but before initial runtime state.
        // Persist that minimum state first, then hand off exactly once.
        if (($bound_owner['state'] ?? '') === 'stop_requested') {
            $finalizing = nmkr_transition_sync_owner($run_id, 'stop_requested', 'finalizing', $sync_stats_id);
            if (nmkr_sync_owner_transition_succeeded($finalizing)) {
                return nmkr_sync_data_complete(false, __('Synchronization stopped by user.', 'nmkr-connect'), array('outcome' => 'stopped'));
            }
            return new WP_Error('sync_owner_mismatch', 'Synchronization ownership no longer matches this worker.');
        }
        
        // Start performance tracking
        $tracking = nmkr_start_performance_tracking('sync_data');
        $sync_data['tracking'] = $tracking;
        nmkr_save_sync_data($sync_data);
        
        // Clear any previous errors - will be updated once total steps are known
        update_option('nmkr_sync_error', '');
        update_option('nmkr_sync_total_items', 0);
        update_option('nmkr_sync_current_count', 0);
        update_option('nmkr_sync_start_time', time());
        
        // Initialize sync heartbeat to indicate backend activity
        nmkr_update_sync_heartbeat();
        
        // Stage 1: INITIALIZING
        $sync_log[] = 'Initialization complete - starting sync process';
        nmkr_log_data_sync('Starting sync process');
        
        try {
            // First, we need to fetch projects to count total steps
            $halt = nmkr_sync_worker_checkpoint($run_id, $sync_stats_id, 'before_projects_request');
            if (is_wp_error($halt)) return nmkr_handle_sync_worker_halt($halt, $run_id, $sync_stats_id);
            $projects = nmkr_connect_fetch_projects();
            $halt = nmkr_sync_worker_checkpoint($run_id, $sync_stats_id, 'after_projects_request');
            if (is_wp_error($halt)) return nmkr_handle_sync_worker_halt($halt, $run_id, $sync_stats_id);
            
            if (is_wp_error($projects)) {
                throw new Exception('Failed to fetch projects for step calculation: ' . $projects->get_error_message());
            }
            
            if (!is_array($projects)) {
                throw new Exception('Invalid projects response for step calculation: expected array, got ' . gettype($projects));
            }
            
            // Calculate total steps
            $total_steps = nmkr_count_sync_steps($projects, $run_id, $sync_stats_id);
            if (is_wp_error($total_steps)) return nmkr_handle_sync_worker_halt($total_steps, $run_id, $sync_stats_id);
            
            if ($total_steps <= 0) {
                $sync_log[] = 'WARNING: No sync steps calculated - projects may be empty';
                $total_steps = 1; // Prevent division by zero
            }
            
            $sync_log[] = 'Total sync steps calculated: ' . $total_steps;
            
            // Reset progress and clear any previous errors - now that total steps are known
            nmkr_update_sync_progress(0, $total_steps, 'Preparing to fetch project data');
            
            // Initialize progress with proper total steps
            nmkr_update_sync_progress(0, $total_steps, '⏳ Starting synchronization process');
            
        } catch (Exception $e) {
            $error_msg = 'Failed to calculate sync steps: ' . $e->getMessage();
            $sync_log[] = 'ERROR: ' . $error_msg;
            throw new Exception($error_msg);
        }
        
        // Complete FETCHING_PROJECTS stage
        nmkr_update_sync_progress(
            $completed_steps, 
            $total_steps,
            "📥 Projects fetched successfully"
        );
        
        // Step 1: Synchronize projects
        $sync_log[] = 'Step 1: Starting project synchronization';
        $project_uids = nmkr_sync_projects($sync_log, $completed_steps, $total_steps, $run_id, $sync_stats_id);
        if (is_wp_error($project_uids)) return nmkr_handle_sync_worker_halt($project_uids, $run_id, $sync_stats_id);
        
        if (empty($project_uids)) {
            $error_msg = 'No projects synchronized successfully';
            $sync_log[] = 'ERROR: ' . $error_msg;
            throw new Exception($error_msg);
        }
        
        $sync_log[] = 'Step 1 complete: ' . count($project_uids) . ' projects synchronized';
        
        // Step 2: Fetch token UIDs for each project
        $sync_log[] = 'Step 2: Starting token UID fetching';
        $token_project_map = array(); // NEW: Single structured array instead of separate arrays
        $all_tokens_data = array();
        $total_projects = count($project_uids);
        $total_tokens = 0; // Initialize total tokens counter
        $token_details_synced = 0; // Initialize token details counter
        
        // Step 3a: Fetch token UIDs for all projects
        nmkr_update_sync_progress($completed_steps, $total_steps, 'Preparing to fetch tokens');
        
        foreach ($project_uids as $i => $project_uid) {
            // Update progress while fetching tokens for each project
            nmkr_update_sync_progress($completed_steps, $total_steps, 'Fetching tokens for project ' . ($i + 1));
            
            $fetch_result = nmkr_fetch_tokens_for_project($project_uid, $sync_log, $completed_steps, $total_steps, $run_id, $sync_stats_id);
            if (is_wp_error($fetch_result)) return nmkr_handle_sync_worker_halt($fetch_result, $run_id, $sync_stats_id);
            
            if (!empty($fetch_result['token_uids'])) {
                // NEW: Build structured map with explicit project associations
                foreach ($fetch_result['token_uids'] as $token_uid) {
                    $token_project_map[] = array(
                        'token_uid' => $token_uid,
                        'project_uid' => $project_uid
                    );
                    // Increment completed steps for each token fetched (1 step per token)
                    $completed_steps++;
                }
                $all_tokens_data = array_merge($all_tokens_data, $fetch_result['tokens_data']);
                // Increment total tokens by the number of tokens fetched for this project
                $total_tokens += count($fetch_result['token_uids']);
            }
        }
        
        // Token fetching complete
        nmkr_update_sync_progress($completed_steps, $total_steps, 'Token fetching complete');
        
        // Step 3: Process and store token data
        $sync_log[] = 'Step 3: Starting token data processing';
        
        // Step 3b: Process token data by project
        nmkr_update_sync_progress($completed_steps, $total_steps, 'Preparing to process tokens');
        
        foreach ($project_uids as $i => $project_uid) {
            // Update progress while processing tokens for each project
            nmkr_update_sync_progress($completed_steps, $total_steps, 'Processing tokens for project ' . ($i + 1));
            
            // Get tokens data for this project
            $project_tokens_data = array();
            foreach ($all_tokens_data as $token_data) {
                $token_project_uid = isset($token_data['project_uid']) ? $token_data['project_uid'] : 
                                   (isset($token_data['projectUid']) ? $token_data['projectUid'] : null);
                if ($token_project_uid === $project_uid) {
                    $project_tokens_data[] = $token_data;
                }
            }
            
            $token_result = nmkr_sync_tokens($project_uid, $project_tokens_data, $sync_log, $completed_steps, $total_steps, $run_id, $sync_stats_id);
            if (is_wp_error($token_result)) return nmkr_handle_sync_worker_halt($token_result, $run_id, $sync_stats_id);
        }
        
        // Token processing complete
        nmkr_update_sync_progress($completed_steps, $total_steps, 'Token processing complete');
        
        // Step 4: Synchronize token details
        $sync_log[] = 'Step 4: Starting token details synchronization';
        $successful_details = 0;
        $skipped_details = 0;
        $failed_details = 0;
        $total_successful_tokens = 0;
        $total_failed_tokens = 0;
        $total_skipped_tokens = 0;
        
        // Begin token details synchronization
        nmkr_update_sync_progress($completed_steps, $total_steps, 'Starting token details synchronization');
        
        foreach ($token_project_map as $token_index => $entry) {
            $token_uid = $entry['token_uid'];
            $project_uid = $entry['project_uid'];
            // Find the original $token object for this $token_uid
            $token = null;
            foreach ($all_tokens_data as $candidate_token) {
                $candidate_uid = isset($candidate_token['uid']) ? $candidate_token['uid'] : (isset($candidate_token['token_uid']) ? $candidate_token['token_uid'] : null);
                if ($candidate_uid === $token_uid) {
                    $token = $candidate_token;
                    break;
                }
            }
            $result = nmkr_sync_token_details($token_uid, $project_uid, $sync_log, $completed_steps, $total_steps, $token, $run_id, $sync_stats_id);
            if (is_wp_error($result) && in_array($result->get_error_code(), array('sync_stop_requested', 'sync_owner_mismatch', 'sync_checkpoint_lock_failed', 'sync_checkpoint_persistence_failed'), true)) return nmkr_handle_sync_worker_halt($result, $run_id, $sync_stats_id);
            
            // Handle successful result
            if ($result === true) {
                $successful_details++;
                $total_successful_tokens++;
                $token_details_synced++; // Increment token details counter
                $completed_steps++; // CRITICAL FIX: Increment for successful tokens
            }
            // Handle WP_Error results
            else if (is_wp_error($result)) {
                $error_code = $result->get_error_code();
                if ($error_code === 'validation_failure') {
                    $sync_log[] = '⏭️ Skipped token ' . $token_uid . ': ' . $result->get_error_message();
                    nmkr_log_data_sync('⏭️ Skipped token ' . $token_uid . ': ' . $result->get_error_message(), 'info');
                    $skipped_details++;
                    $total_skipped_tokens++;
                    $completed_steps++; // Increment for skipped tokens
                } else {
                    $sync_log[] = 'ERROR: Failed to fetch details for token ' . $token_uid . ': ' . $result->get_error_message();
                    nmkr_log_data_sync('API error fetching details for token ' . $token_uid . ': ' . $result->get_error_message(), 'error');
                    $failed_details++;
                    $total_failed_tokens++;
                    $completed_steps++; // Increment for failed tokens
                }
            }
            // Unexpected return type
            else {
                $sync_log[] = 'ERROR: Unexpected return type from nmkr_sync_token_details for token ' . $token_uid . ': ' . gettype($result);
                nmkr_log_data_sync('Unexpected return type from nmkr_sync_token_details for token ' . $token_uid . ': ' . gettype($result), 'error');
                $failed_details++;
                $total_failed_tokens++;
                $completed_steps++; // Increment for unexpected results
            }
        }
        
        // Token details synchronization complete
        nmkr_update_sync_progress($completed_steps, $total_steps, 'Token details synchronization complete');
        
        $sync_log[] = 'Step 4 complete: ' . $successful_details . ' token details synchronized out of ' . count($token_project_map) . ' tokens';
        
        // Step 5: Finalizing with smooth progress updates
        $sync_log[] = 'Step 5: Starting finalization process';
        
        nmkr_update_sync_progress($total_steps, $total_steps, '✨ Finalizing synchronization');
        
        $sync_log[] = 'Step 5 complete: Finalization finished';
        
        // The last safe boundary is before any success-only finalization state.
        $halt = nmkr_sync_worker_checkpoint($run_id, $sync_stats_id, 'before_completed_finalization');
        if (is_wp_error($halt)) return nmkr_handle_sync_worker_halt($halt, $run_id, $sync_stats_id);
        // Mark sync near completion - all work is done but final flags not yet set
        update_option('nmkr_sync_near_completion', true);
        nmkr_log_data_sync('Marked sync as near completion - all work finished, finalizing flags', 'info');
        
        // Complete sync
        $performance = nmkr_end_performance_tracking($tracking);
        // Note: Metrics saving is handled by the authoritative path below
        
        // Build final metrics; the canonical finalizer owns every durable
        // completion write and all active-state cleanup.
        $live = get_transient('nmkr_current_sync_stats_live');
        if (!empty($live)) {
            // Get computed performance data
            $performance_data = nmkr_get_sync_stats();
            
            // Merge computed performance metrics into live stats with proper field mapping
            if ($performance_data) {
                $live['total_sync_duration'] = $performance_data['total_duration'] ?? 0;
                $live['average_response_time'] = $performance_data['average_time'] ?? 0;
                $live['memory_usage'] = $performance_data['memory_used'] ?? 0;
                $live['api_requests'] = $performance_data['request_count'] ?? 0;
                // total_api_time, total_projects, total_tokens already in live stats
            }
            
            // Ensure we have the totals from the actual sync
            $live['total_projects'] = count($project_uids);
            $live['total_tokens'] = count($token_project_map);
            
        } else {
            nmkr_log_data_sync('⚠️ No live sync statistics found for final metrics save.', 'warning');
        }
        
        $business_data_complete = true;
        $final = array(
            'run_id' => $run_id,
            'metrics' => nmkr_build_final_sync_metrics($live, nmkr_get_sync_data(), array(
                'total_projects' => count($project_uids),
                'total_tokens' => count($token_project_map),
            )),
            'items_processed' => $total_tokens,
            'items_successful' => $total_successful_tokens,
            'items_failed' => $total_failed_tokens,
            'items_skipped' => $total_skipped_tokens,
            'token_details_synced' => $token_details_synced,
        );
        // Persist exact terminalization input before ownership handoff. A
        // resume callback may safely claim an exact still-running owner.
        $prepared = nmkr_prepare_sync_finalization($sync_stats_id, $final, nmkr_get_sync_data());
        if (!is_array($prepared)) {
            throw new Exception('Failed to persist synchronization finalization handoff');
        }
        $finalizing_owner = nmkr_transition_sync_owner($run_id, 'running', 'finalizing', $sync_stats_id);
        if (!nmkr_sync_owner_transition_succeeded($finalizing_owner)) {
            throw new Exception('Synchronization owner changed before finalization');
        }
        $terminal = nmkr_sync_data_complete(true, '', $prepared, false, true);
        if (!is_array($terminal) || !in_array($terminal['status'] ?? '', array('completed', 'success'), true)) {
            throw new Exception('Canonical synchronization finalization failed');
        }

        // Log comprehensive final summary
        nmkr_log_sync_summary($sync_log, $project_uids, $token_project_map, $total_successful_tokens, $total_skipped_tokens, $total_failed_tokens, $token_details_synced, $sync_start_time);
        
        $sync_log[] = 'SUCCESS: Complete synchronization finished successfully';
        nmkr_log_data_sync('Complete synchronization finished successfully');
        
        // Return structured response
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return array(
                'success' => true,
                'message' => 'Sync process completed successfully.',
                'log' => $sync_log,
                'progress' => 100,
                'performance' => $performance
            );
        }
        
        return 'Sync process completed successfully.';
        
    } catch (Exception $e) {
        // ** ENHANCED ERROR HANDLING: Critical Error Cleanup **
        $error_msg = 'Critical error during synchronization: ' . $e->getMessage();
        $sync_log[] = 'CRITICAL ERROR: ' . $error_msg;
        nmkr_log_data_sync($error_msg, 'error', array(
            'exception' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ));

        $resume_data = nmkr_get_sync_data();
        if (is_array($resume_data) && (string) ($resume_data['run_id'] ?? '') === (string) $run_id
            && (int) ($resume_data['sync_stats_id'] ?? 0) === (int) $sync_stats_id
            && nmkr_is_sync_terminal_status($resume_data['status'] ?? '') && nmkr_get_sync_owner() === false
            && nmkr_finish_ownerless_terminal_cleanup($resume_data, (string) ((get_option(nmkr_sync_finalization_resume_key($sync_stats_id), array())['outcome'] ?? 'failed')), get_option(nmkr_sync_finalization_resume_key($sync_stats_id), false))) {
            return defined('DOING_AJAX') && DOING_AJAX
                ? array('success' => true, 'message' => 'Sync process completed successfully.', 'log' => $sync_log, 'progress' => 100)
                : 'Sync process completed successfully.';
        }

        if (!nmkr_sync_owner_matches($run_id, null, $sync_stats_id ?: 0)) {
            return new WP_Error('sync_owner_mismatch', 'Synchronization owner changed; stale worker stopped.');
        }

        // Durable success evidence makes this a resumable finalization, not a
        // business-data failure. Preserve active/finalizing state so a retry
        // can finish history verification and cleanup without relabeling it.
        if ($sync_stats_id && nmkr_sync_finalization_handoff_pending($run_id, $sync_stats_id)
            && ($business_data_complete || (function_exists('nmkr_sync_has_committed_success')
            && nmkr_sync_has_committed_success($sync_stats_id)))) {
            if (is_array($resume_data) && (int) ($resume_data['sync_stats_id'] ?? 0) === (int) $sync_stats_id) {
                $resume_data['status'] = 'finalizing';
                $resume_data['completed'] = false;
                nmkr_save_sync_data($resume_data);
            }
            update_option('nmkr_sync_in_progress', true);
            set_transient('nmkr_sync_in_progress', true, NMKR_SYNC_TRANSIENT_TTL);
            return new WP_Error('sync_finalization_pending', 'Synchronization data was saved; terminal cleanup remains pending.');
        }
        $handoff_record = $sync_stats_id > 0 ? get_option(nmkr_sync_finalization_resume_key($sync_stats_id), false) : false;
        if ($business_data_complete && nmkr_is_valid_sync_finalization_record($handoff_record, $run_id, $sync_stats_id)) {
            if (is_array($resume_data) && (int) ($resume_data['sync_stats_id'] ?? 0) === (int) $sync_stats_id) {
                $resume_data['status'] = 'finalizing';
                $resume_data['completed'] = false;
                nmkr_save_sync_data($resume_data);
            }
            nmkr_set_sync_finalization_error();
            return new WP_Error('sync_finalization_unavailable', 'Synchronization data was saved, but no executable finalization retry is scheduled.');
        }
        
        // Update sync stats with critical failure
        if ($sync_stats_id) {
            try {
                nmkr_update_sync_stats($sync_stats_id, [
                    'status' => 'failed',
                    'error_message' => $error_msg,
                    'end_time' => nmkr_get_timestamp(),
                    'items_processed' => isset($total_tokens) ? $total_tokens : 0,
                    'items_successful' => isset($total_successful_tokens) ? $total_successful_tokens : 0,
                    'items_failed' => isset($total_failed_tokens) ? $total_failed_tokens : 0,
                    'items_skipped' => isset($total_skipped_tokens) ? $total_skipped_tokens : 0,
                    'token_details_synced' => isset($token_details_synced) ? $token_details_synced : 0
                ]);
            } catch (Exception $stats_error) {
                nmkr_log_data_sync('Failed to update sync stats during error cleanup: ' . $stats_error->getMessage(), 'error');
            }
        }
        
        // Ensure sync flags are cleared
        try {
            update_option('nmkr_sync_in_progress', false);
            delete_transient('nmkr_sync_in_progress');
            update_option('nmkr_sync_error', $error_msg);
            nmkr_update_sync_progress($completed_steps, $total_steps, '❌ Synchronization Failed');
            
            // Clear any scheduled batch processing
            wp_clear_scheduled_hook('nmkr_process_batch_hook');
            
        } catch (Exception $cleanup_error) {
            nmkr_log_data_sync('Failed to cleanup sync state during error handling: ' . $cleanup_error->getMessage(), 'error');
        }
        
        // Clear sync data to prevent stuck state
        try {
            if (nmkr_sync_owner_matches($run_id, null, $sync_stats_id ?: 0)) {
                nmkr_clear_sync_data();
                $released = nmkr_release_sync_owner($run_id, $sync_stats_id ?: 0, 'running');
                if ($released !== true) {
                    update_option('nmkr_sync_status', 'failed_cleanup_pending');
                }
            }
        } catch (Exception $clear_error) {
            nmkr_log_data_sync('Failed to clear sync data during error cleanup: ' . $clear_error->getMessage(), 'error');
        }
        
        // At the end of the function, after sync finishes (success or error), delete the transient
        // --- Robustify: Delete performance stats transient on completion or error ---
        delete_transient('nmkr_current_sync_stats_live');
        delete_transient('nmkr_current_sync_stats_summary');
        // --- End Robustify ---

        if (defined('DOING_AJAX') && DOING_AJAX) {
            return array(
                'success' => false,
                'message' => $error_msg,
                'error_code' => 'critical_sync_failure',
                'log' => $sync_log,
                'progress' => ($total_steps > 0 ? (int)round(($completed_steps / $total_steps) * 100) : 0)
            );
        }
        
        return new WP_Error('critical_sync_failure', $error_msg);
    }
}

/**
 * Convert memory limit string to bytes
 * 
 * @param string $memory_limit Memory limit string (e.g. '128M', '1G')
 * @return int Memory limit in bytes
 */
function nmkr_convert_to_bytes($memory_limit) {
    $unit = strtolower(substr($memory_limit, -1));
    $value = (int) substr($memory_limit, 0, -1);
    
    switch ($unit) {
        case 'g':
            $value *= 1024;
        case 'm':
            $value *= 1024;
        case 'k':
            $value *= 1024;
    }
    
    return $value;
}

/**
 * Start a manual synchronization process
 * 
 * @return array Result of the sync operation
 */
function nmkr_start_sync() {
    // Retained only for compatibility. Direct synchronization must begin at
    // the authorized AJAX admission boundary so it has an exact run owner.
    return new WP_Error('legacy_sync_start_unavailable', __('Legacy synchronization start is unavailable. Use the authorized synchronization start action.', 'nmkr-connect'));
}

/**
 * Log comprehensive sync summary
 * 
 * @param array &$sync_log Reference to sync log array
 * @param array $project_uids Array of project UIDs that were processed
 * @param array $token_project_map Array of token-project mappings that were processed
 * @param int $successful_tokens Number of successfully processed tokens
 * @param int $skipped_tokens Number of skipped tokens
 * @param int $failed_tokens Number of failed tokens
 * @param int $successful_details Number of successfully synchronized token details
 * @param float $sync_start_time Sync start time for duration calculation
 * @return void
 */
function nmkr_log_sync_summary(&$sync_log, $project_uids, $token_project_map, $successful_tokens, $skipped_tokens, $failed_tokens, $successful_details, $sync_start_time) {
    $sync_duration = microtime(true) - $sync_start_time;
    $total_projects = count($project_uids);
    $total_tokens = count($token_project_map);
    
    // Validate that counters add up correctly
    $calculated_total = $successful_tokens + $skipped_tokens + $failed_tokens;
    if ($calculated_total !== $total_tokens) {
        $sync_log[] = 'WARNING: Token counter mismatch - calculated: ' . $calculated_total . ', actual: ' . $total_tokens;
        nmkr_log_data_sync('Token counter mismatch detected - calculated: ' . $calculated_total . ', actual: ' . $total_tokens, 'warning');
    }
    
    // Calculate duration
    $duration_seconds = (float) $sync_duration;
    $duration_minutes = (int) floor($duration_seconds / 60);
    $duration_remaining_seconds = (int) floor(fmod($duration_seconds, 60));
    $duration_formatted = sprintf('%dm %02ds', $duration_minutes, $duration_remaining_seconds);
    
    // Create the summary message
    $summary_lines = array(
        '[NMKR Connect Sync] ✅ Token sync summary:',
        '- Total projects: ' . $total_projects,
        '- Total tokens: ' . $total_tokens,
        '- Successfully synced: ' . $successful_tokens,
        '- Skipped (validation): ' . $skipped_tokens,
        '- Failed after retries: ' . $failed_tokens,
        '- Token details synced: ' . $successful_details,
        '- Sync duration: ' . $duration_formatted
    );
    
    // Add to sync log
    foreach ($summary_lines as $line) {
        $sync_log[] = $line;
    }
    
    // Log to data sync system
    $summary_message = implode("\n", $summary_lines);
    nmkr_log_data_sync($summary_message, 'info');
}

// Hook for batch processing
add_action('nmkr_process_batch_hook', 'nmkr_process_next_batch');

// Note: Background sync execution hook is defined in nmkr-sync-ajax-handlers.php
// add_action('nmkr_execute_sync_background', 'nmkr_execute_sync_background_job');
