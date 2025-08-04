'use strict';

/**
 * NMKR Connect - Sync Status Constants (JavaScript)
 *
 * Frontend utilities for sync status handling.
 */

/**
 * Enhanced error message formatter
 * @param {Object} errorData
 * @returns {string}
 */
function formatSyncErrorMessage(errorData) {
    if (typeof errorData === 'string') {
        return errorData;
    }
    
    if (errorData && typeof errorData === 'object') {
        // Check for standardized error format
        if (errorData.formatted_display) {
            return errorData.formatted_display;
        }
        
        // Fallback to constructing message
        let message = errorData.message || 'An error occurred';
        
        if (errorData.type) {
            message = `❌ [${errorData.type}] ${message}`;
        }
        
        if (errorData.code) {
            message += ` (Code: ${errorData.code})`;
        }
        
        if (errorData.suggestion) {
            message += `\n💡 Suggestion: ${errorData.suggestion}`;
        }
        
        if (errorData.context) {
            message += `\n🔧 Context: ${errorData.context}`;
        }
        
        return message;
    }
    
    return 'Unknown error occurred';
}

/**
 * Utility to format time elapsed since last update
 * @param {number} timestamp - Unix timestamp
 * @returns {string}
 */
function formatTimeElapsed(timestamp) {
    if (!timestamp || timestamp === 0) {
        return 'Never';
    }
    
    const now = Math.floor(Date.now() / 1000);
    const elapsed = now - timestamp;
    
    if (elapsed < 60) {
        return `${elapsed} second${elapsed !== 1 ? 's' : ''} ago`;
    } else if (elapsed < 3600) {
        const minutes = Math.floor(elapsed / 60);
        return `${minutes} minute${minutes !== 1 ? 's' : ''} ago`;
    } else {
        const hours = Math.floor(elapsed / 3600);
        return `${hours} hour${hours !== 1 ? 's' : ''} ago`;
    }
}

// Export for use in other files if using module system
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        formatSyncErrorMessage,
        formatTimeElapsed
    };
} 