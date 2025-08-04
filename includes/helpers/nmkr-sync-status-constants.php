<?php
/**
 * NMKR Connect - Sync Status Constants
 *
 * Centralized constants and utilities for consistent sync status handling.
 *
 * @package NMKR Connect
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Error message types and codes
 */
class NMKR_Sync_Error_Types {
    const API_ERROR = 'API Error';
    const CONFIGURATION_ERROR = 'Configuration Error';
    const INITIALIZATION_ERROR = 'Initialization Error';
    const BACKGROUND_JOB_ERROR = 'Background Job Error';
    const CRON_TIMEOUT = 'Cron Timeout';
    const STALL_DETECTION = 'Stall Detection';
    const CLEANUP_ERROR = 'Cleanup Error';
    const CRITICAL_ERROR = 'Critical Error';
    const VALIDATION_ERROR = 'Validation Error';
    const NETWORK_ERROR = 'Network Error';
}

/**
 * Error codes for consistent error identification
 */
class NMKR_Sync_Error_Codes {
    const API_KEY_MISSING = 'API_KEY_001';
    const API_CONNECTION_FAILED = 'API_NET_001';
    const API_INVALID_RESPONSE = 'API_RES_001';
    const INITIALIZATION_FAILED = 'INIT_001';
    const BACKGROUND_SCHEDULING_FAILED = 'BG_001';
    const STALL_NO_PROGRESS = 'STALL_001';
    const STALL_TIMEOUT = 'STALL_002';
    const CLEANUP_FAILED = 'CLEAN_001';
    const VALIDATION_FAILED = 'VAL_001';
    const CRITICAL_EXCEPTION = 'CRIT_001';
    const NETWORK_TIMEOUT = 'NET_001';
    const DATABASE_ERROR = 'DB_001';
    const PERMISSION_DENIED = 'PERM_001';
}

/**
 * Utility function to format sync error messages consistently
 *
 * @param string $type Error type from NMKR_Sync_Error_Types
 * @param string $message The error message
 * @param string $code Error code from NMKR_Sync_Error_Codes
 * @param string|null $suggestion Optional suggestion for user
 * @param string|null $context Optional technical context
 * @return array Formatted error details
 */
function nmkr_format_sync_error_message($type, $message, $code, $suggestion = null, $context = null) {
    $formatted = [
        'type' => $type,
        'message' => $message,
        'code' => $code,
        'formatted_display' => "❌ [{$type}] {$message} (Code: {$code})",
        'timestamp' => current_time('mysql')
    ];
    
    if ($suggestion) {
        $formatted['suggestion'] = $suggestion;
        $formatted['formatted_display'] .= "\n💡 Suggestion: {$suggestion}";
    }
    
    if ($context) {
        $formatted['context'] = $context;
        $formatted['formatted_display'] .= "\n🔧 Context: {$context}";
    }
    
    return $formatted;
}

/**
 * Utility function to create common sync error messages
 */
class NMKR_Sync_Common_Errors {
    /**
     * API key missing error
     */
    public static function apiKeyMissing() {
        return nmkr_format_sync_error_message(
            NMKR_Sync_Error_Types::CONFIGURATION_ERROR,
            'NMKR API key is not configured',
            NMKR_Sync_Error_Codes::API_KEY_MISSING,
            'Please configure your API key in plugin settings'
        );
    }
    
    /**
     * API connection failed error
     */
    public static function apiConnectionFailed($details = '') {
        return nmkr_format_sync_error_message(
            NMKR_Sync_Error_Types::API_ERROR,
            'Failed to connect to NMKR API',
            NMKR_Sync_Error_Codes::API_CONNECTION_FAILED,
            'Check your network connection and API key validity',
            $details
        );
    }
    
    /**
     * Sync stall detected error
     */
    public static function syncStallDetected($timeoutMinutes = 2) {
        return nmkr_format_sync_error_message(
            NMKR_Sync_Error_Types::STALL_DETECTION,
            "No progress detected in {$timeoutMinutes} minutes",
            NMKR_Sync_Error_Codes::STALL_TIMEOUT,
            'Try restarting the sync or check server logs for issues'
        );
    }
    
    /**
     * Background job error
     */
    public static function backgroundJobError($exception_message) {
        return nmkr_format_sync_error_message(
            NMKR_Sync_Error_Types::BACKGROUND_JOB_ERROR,
            'Sync threw an exception',
            NMKR_Sync_Error_Codes::CRITICAL_EXCEPTION,
            'Check error logs for detailed information',
            $exception_message
        );
    }
    
    /**
     * Cleanup failed error
     */
    public static function cleanupFailed($details = '') {
        return nmkr_format_sync_error_message(
            NMKR_Sync_Error_Types::CLEANUP_ERROR,
            'Failed to prepare for synchronization',
            NMKR_Sync_Error_Codes::CLEANUP_FAILED,
            'Try again or restart WordPress if the issue persists',
            $details
        );
    }
} 