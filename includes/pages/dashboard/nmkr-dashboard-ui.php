<?php
/**
 * NMKR Connect Dashboard UI
 *
 * UI components and rendering functions for the dashboard.
 *
 * @package NMKR_Connect
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render the dashboard CSS styles
 */
function nmkr_render_dashboard_styles() {
    ?>
    <style>
        /* Global Panel Styling */
        .nmkr-dashboard {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .nmkr-dashboard .panel {
            background: #fff;
            border: 1px solid #e5e5e5;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            border-radius: 6px;
            padding: 20px;
            margin-bottom: 25px;
            position: relative;
            overflow: hidden;
        }
        
        .nmkr-dashboard .panel h2 {
            margin-top: 0;
            padding-bottom: 15px;
            border-bottom: 1px solid #f0f0f1;
        }
        
        .nmkr-dashboard .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .nmkr-dashboard .panel-section {
            padding: 15px 0;
            border-top: 1px solid #f0f0f1;
        }
        
        .nmkr-dashboard .panel-section:first-child {
            border-top: none;
            padding-top: 0;
        }
        
        .nmkr-dashboard .panel-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding-top: 15px;
        }
        
        .nmkr-dashboard .center-text {
            text-align: center;
        }
        
        .nmkr-dashboard .panel-description {
            color: #666;
            margin-bottom: 15px;
            text-align: center;
        }
        
        /* Panel description specific styling overrides */
        .nmkr-dashboard .panel-description.legend-title {
            font-weight: bold;
            text-align: left;
            margin-bottom: 5px !important;
        }
        
        /* Status Colors */
        .nmkr-dashboard .status-excellent {
            color: #46b450;
        }
        
        .nmkr-dashboard .status-good {
            color: #ffb900;
        }
        
        .nmkr-dashboard .status-warning {
            color: #f56e28;
        }
        
        .nmkr-dashboard .status-critical {
            color: #dc3232;
        }
        
        .nmkr-dashboard .status-neutral {
            color: #666;
        }

        /* API status loading animation */
        .api-status-loading {
            display: inline-block;
            position: relative;
            color: #666;
        }
        
        .api-status-loading:after {
            content: '...';
            position: absolute;
            width: 20px;
            text-align: left;
            animation: dots 1.5s infinite;
        }
        
        @keyframes dots {
            0%, 20% { content: '.'; }
            40% { content: '..'; }
            60%, 100% { content: '...'; }
        }
        
        /* API Status Panel specific styling */
        .api-status-panel #api-status {
            margin-bottom: 20px;
            min-height: 30px; /* Add minimum height to prevent layout shifts */
        }
        
        /* Ensure consistency in panel heights during loading */
        .api-status-panel {
            min-height: 180px;
        }
        
        /* Sync Controls Styling */
        .sync-data .sync-controls {
            display: flex;
            flex-direction: column;
            gap: 15px;
            min-height: 120px;
            margin-bottom: 160px; /* Space for active metrics when they appear */
            position: relative;
        }

        /* Info Box Styling */
        .nmkr-info-box {
            display: flex;
            align-items: center;
            background-color: #f0f6fc;
            border-left: 4px solid #2271b1;
            padding: 12px 15px;
            margin-bottom: 20px;
            border-radius: 2px;
        }
        
        .nmkr-info-box .dashicons {
            font-size: 24px;
            color: #2271b1;
            margin-right: 12px;
        }
        
        .nmkr-info-box p {
            margin: 0;
            color: #50575e;
            font-size: 14px;
        }
        
        /* Sync Statistics Panel Styling */
        .sync-statistics .sync-stats {
            text-align: left;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .sync-statistics .sync-stats p {
            margin: 8px 0;
            color: #50575e;
        }
        
        .sync-statistics .performance-metrics {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e5e5e5;
        }
        
        .sync-statistics .performance-metrics p {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 8px 0;
        }
        
        .sync-statistics .performance-metrics span {
            font-family: monospace;
            background: #f0f0f1;
            padding: 2px 8px;
            border-radius: 3px;
        }
        
        .sync-statistics .performance-legend {
            margin-top: 15px;
            padding: 10px;
            background-color: #f9f9f9;
            border-radius: 4px;
            font-size: 0.85em;
        }
        
        .sync-statistics .legend-title {
            font-weight: bold;
            margin-bottom: 5px !important;
        }
        
        .sync-statistics .legend-item {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 5px;
        }
        
        .sync-statistics .legend-dot {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 2px;
        }
        
        .sync-statistics .legend-dot.status-excellent {
            background-color: #46b450;
        }
        
        .sync-statistics .legend-dot.status-good {
            background-color: #ffb900;
        }
        
        .sync-statistics .legend-dot.status-warning {
            background-color: #f56e28;
        }
        
        .sync-statistics .legend-dot.status-critical {
            background-color: #dc3232;
        }
        
        .sync-statistics .legend-desc {
            margin-top: 5px !important;
            color: #666 !important;
            line-height: 1.4;
        }
        
        .sync-statistics .stats-separator {
            margin: 15px 0 10px;
            border: 0;
            height: 1px;
            background-color: #e5e5e5;
        }

        /* Sync Controls Styling */
        .sync-data .sync-controls {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .sync-data .sync-buttons {
            display: flex;
            justify-content: center;
            gap: 10px;
        }
        
        .sync-data #nmkr-sync-progress-container {
            margin-top: 15px;
            background-color: #f0f0f1;
            border-radius: 4px;
            overflow: hidden;
            height: 24px;
        }
        
        .sync-data #nmkr-sync-progress-bar {
            height: 100%;
            background-color: #2271b1;
            color: white;
            text-align: center;
            line-height: 24px;
            transition: width 0.3s ease;
        }
        
        .sync-data .sync-status-message {
            text-align: center;
            margin-top: 10px;
            min-height: 20px;
            display: block; /* Ensure it takes up space even when empty */
        }
        
        /* Style for the inline phase label next to the status message */
        #status-message .sync-phase-label {
            display: inline-block;
            margin-left: 0.5em;
            font-size: 1rem;
            font-weight: 500;
            color: #444; /* adjust as needed to match your theme */
            vertical-align: middle;
        }
        
        .sync-data .button-danger {
            background-color: #dc3232;
            border-color: #b32d2e;
            color: white;
        }
        
        .sync-data .button-danger:hover {
            background-color: #b32d2e;
            border-color: #b32d2e;
        }
        
        /* Active Sync Metrics Styling */
        .sync-data #active-sync-metrics {
            background-color: #f9f9f9;
            border-radius: 6px;
            padding: 10px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            margin-top: 20px;
            position: relative;
            width: calc(100% - 20px); /* Account for padding */
        }
        
        .sync-data #active-sync-metrics .panel-description {
            margin: 0 0 10px 0;
            font-size: 1.1em;
            font-weight: bold;
        }
        
        .sync-data .active-metrics-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            margin-top: 10px;
        }
        
        .sync-data .active-metric {
            display: flex;
            flex-direction: column;
            align-items: center;
            background: rgba(0,0,0,0.02);
            padding: 8px 12px;
            border-radius: 4px;
            border: 1px solid #e5e5e5;
        }
        
        .sync-data .active-metric .label {
            margin-bottom: 5px;
            font-size: 13px;
        }
        
        .sync-data .active-metric .value {
            font-family: monospace;
            background: #fff;
            padding: 3px 8px;
            border-radius: 3px;
            border: 1px solid #e5e5e5;
            font-weight: bold;
            min-width: 60px;
            text-align: center;
            display: inline-block;
        }
        
        /* Debug Logs Panel Styling */
        .debug-logs-panel .log-section {
            margin-bottom: 15px;
        }
        
        .debug-logs-panel .log-section details {
            border: 1px solid #e5e5e5;
            border-radius: 4px;
            background: #f9f9f9;
        }
        
        .debug-logs-panel .log-section summary {
            padding: 12px 15px;
            cursor: pointer;
            font-weight: bold;
            background: #f0f0f1;
            border-bottom: 1px solid #e5e5e5;
            user-select: none;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .debug-logs-panel .log-section summary:hover {
            background: #e5e5e5;
        }
        
        .debug-logs-panel .log-section[open] summary {
            border-bottom: 1px solid #e5e5e5;
        }
        
        .debug-logs-panel .log-section summary .log-section-title {
            transition: all 0.2s ease;
        }
        
        .debug-logs-panel .log-entry-count {
            background: #666;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: normal;
        }
        
        .debug-logs-panel .log-entries {
            max-height: 400px;
            overflow-y: auto;
            padding: 0;
        }
        
        .debug-logs-panel .log-entry {
            padding: 10px 15px;
            border-bottom: 1px solid #e5e5e5;
            font-size: 0.9em;
        }
        
        .debug-logs-panel .log-entry:last-child {
            border-bottom: none;
        }
        
        .debug-logs-panel .log-entry .log-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .debug-logs-panel .log-entry .log-timestamp {
            color: #666;
            font-family: monospace;
            font-size: 0.85em;
        }
        
        .debug-logs-panel .log-entry .log-type {
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 0.8em;
            text-transform: uppercase;
            font-weight: bold;
        }
        
        .debug-logs-panel .log-entry .log-type.info {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .debug-logs-panel .log-entry .log-type.debug {
            background: #e2e3e5;
            color: #383d41;
        }
        
        .debug-logs-panel .log-entry .log-type.warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .debug-logs-panel .log-entry .log-type.error {
            background: #f8d7da;
            color: #721c24;
        }
        
        .debug-logs-panel .log-entry .log-message {
            margin: 5px 0;
            color: #333;
        }
        
        .debug-logs-panel .log-entry .log-data {
            background: #f8f9fa;
            padding: 8px;
            border-radius: 3px;
            border-left: 3px solid #dee2e6;
            margin-top: 8px;
            font-family: monospace;
            font-size: 0.8em;
            white-space: pre-wrap;
            word-break: break-all;
            color: #495057;
        }
        
        .debug-logs-panel .no-logs {
            padding: 20px;
            text-align: center;
            color: #666;
            font-style: italic;
        }
        
        /* Debug Logs Filter Controls */
        .debug-logs-panel .filter-controls {
            background: #ffffff;
            border: 1px solid #c3c4c7;
            border-radius: 6px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
        }
        
        .debug-logs-panel .filter-controls fieldset {
            border: none;
            margin: 0;
            padding: 0;
        }
        
        .debug-logs-panel .filter-controls legend {
            font-weight: 600;
            font-size: 14px;
            color: #1d2327;
            margin-bottom: 15px;
            padding: 0;
        }
        
        .debug-logs-panel .filter-controls .filter-fields-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
            justify-items: center;
        }
        
        .debug-logs-panel .filter-controls .filter-group {
            display: flex;
            flex-direction: column;
            width: 100%;
            max-width: 220px;
        }
        
        .debug-logs-panel .filter-controls .filter-group label {
            font-weight: 600;
            margin-bottom: 6px;
            font-size: 13px;
            color: #1d2327;
            line-height: 1.4;
        }
        
        .debug-logs-panel .filter-controls input[type="text"],
        .debug-logs-panel .filter-controls select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #8c8f94;
            border-radius: 4px;
            font-size: 14px;
            background: #ffffff;
            box-sizing: border-box;
            line-height: 1.4;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }
        
        .debug-logs-panel .filter-controls input[type="text"]:focus,
        .debug-logs-panel .filter-controls select:focus {
            border-color: #2271b1;
            box-shadow: 0 0 0 1px #2271b1;
            outline: none;
        }
        
        .debug-logs-panel .filter-controls input[type="text"]::placeholder {
            color: #888;
            font-style: italic;
            opacity: 1;
        }
        
        .debug-logs-panel .filter-controls .filter-actions {
            text-align: center;
            padding-top: 10px;
            border-top: 1px solid #f0f0f1;
        }
        
        .debug-logs-panel .filter-controls .clear-filters-btn {
            padding: 8px 16px;
            background: #f6f7f7;
            border: 1px solid #c3c4c7;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            color: #50575e;
            text-decoration: none;
            display: inline-block;
            transition: all 0.15s ease-in-out;
        }
        
        .debug-logs-panel .filter-controls .clear-filters-btn:hover {
            background: #f0f0f1;
            border-color: #8c8f94;
            color: #1d2327;
        }
        
        .debug-logs-panel .filter-controls .clear-filters-btn:focus {
            border-color: #2271b1;
            box-shadow: 0 0 0 1px #2271b1;
            outline: none;
        }
        
        /* Responsive adjustments */
        @media (max-width: 782px) {
            .debug-logs-panel .filter-controls .filter-fields-container {
                grid-template-columns: 1fr;
                gap: 12px;
                justify-items: stretch;
            }
            
            .debug-logs-panel .filter-controls .filter-group {
                max-width: none;
            }
            
            .debug-logs-panel .filter-controls {
                padding: 15px;
            }
        }
        
        @media (min-width: 783px) and (max-width: 1200px) {
            .debug-logs-panel .filter-controls .filter-fields-container {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        .debug-logs-panel .filter-note {
            font-size: 0.9em;
            color: #666;
            margin-bottom: 15px;
            padding: 8px 12px;
            background: #f0f6fc;
            border-left: 3px solid #2271b1;
            border-radius: 2px;
        }
        
        /* Hidden log entries during filtering */
        .debug-logs-panel .log-entry.nmkr-filtered-hidden {
            display: none;
        }
        
        /* Show "no results" message when all entries are filtered out */
        .debug-logs-panel .no-filtered-results {
            display: none;
            padding: 20px;
            text-align: center;
            color: #666;
            font-style: italic;
        }
        
        .debug-logs-panel .no-filtered-results.show {
            display: block;
        }
        
        /* Clear Logs Controls Styling */
        .debug-logs-panel .clear-logs-controls {
            text-align: center;
            margin: 20px 0;
            padding: 15px;
            border-top: 1px solid #f0f0f1;
        }
        
        .debug-logs-panel .clear-all-logs-btn {
            background: #dc3232;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: background-color 0.2s ease;
        }
        
        .debug-logs-panel .clear-all-logs-btn:hover:not(.disabled) {
            background: #a00;
        }
        
        .debug-logs-panel .clear-all-logs-btn:focus:not(.disabled) {
            box-shadow: 0 0 0 2px #007cba;
            outline: none;
        }
        
        .debug-logs-panel .clear-all-logs-btn.disabled {
            background: #ccc;
            color: #999;
            cursor: not-allowed;
            opacity: 0.6;
        }
        
        /* Individual Section Clear Buttons */
        .debug-logs-panel .log-section summary {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .debug-logs-panel .clear-section-logs-btn {
            background: #dc3232;
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 11px;
            font-weight: 500;
            margin-left: 10px;
            transition: background-color 0.2s ease;
            flex-shrink: 0;
        }
        
        .debug-logs-panel .clear-section-logs-btn:hover:not(.disabled) {
            background: #a00;
        }
        
        .debug-logs-panel .clear-section-logs-btn:focus:not(.disabled) {
            box-shadow: 0 0 0 2px #007cba;
            outline: none;
        }
        
        .debug-logs-panel .clear-section-logs-btn.disabled {
            background: #ccc;
            color: #999;
            cursor: not-allowed;
            opacity: 0.6;
        }
        
        .debug-logs-panel .log-section summary .log-section-title {
            flex-grow: 1;
        }
        
        .debug-logs-panel .log-section summary .log-entry-count {
            margin-right: auto;
        }
    </style>
    <?php
}

/**
 * Render the API status panel
 */
function nmkr_render_api_status_panel() {
    ?>
    <!-- API Status Panel -->
    <div class="panel api-status-panel">
        <h2 class="center-text">API Connection Status</h2>
        <div class="nmkr-info-box">
            <span class="dashicons dashicons-info"></span>
            <p>Check the current status of your connection to the NMKR API.</p>
        </div>
        
        <div id="api-status" class="api-status center-text">
            <span class="status-indicator checking">Checking connection...</span>
        </div>
        
        <div class="api-actions center-text">
            <button id="refresh-api-status" class="button button-primary">Refresh Status</button>
        </div>
    </div>
    <?php
}

/**
 * Render the Sync Data Panel
 * 
 * @param string $dashboard_nonce The nonce for dashboard operations
 */
function nmkr_render_sync_data_panel($dashboard_nonce) {
    ?>
    <!-- Data Synchronization Panel -->
    <div class="panel sync-data" role="region" aria-label="Data Synchronization Controls">
        <h2 class="center-text">Data Synchronization</h2>
        <div class="nmkr-info-box">
            <span class="dashicons dashicons-database-import"></span>
            <p>Synchronize and update your NMKR projects, tokens, and token details to maintain current data in the dashboard.</p>
        </div>
        
        <div class="sync-controls panel-section">
            <div class="sync-buttons">
                <button id="nmkr-sync-button" class="button button-primary" disabled aria-label="Start Data Synchronization">
                    Start Synchronization
                </button>
                <button id="nmkr-stop-sync-button" class="button button-danger" style="display:none;" aria-label="Stop Data Synchronization">
                    Stop Synchronization
                </button>
            </div>
            
            <input type="hidden" id="nmkr-sync-nonce" value="<?php echo wp_create_nonce('nmkr_sync_nonce'); ?>">
            <input type="hidden" id="nmkr-dashboard-nonce" value="<?php echo $dashboard_nonce; ?>">
            
            <div id="nmkr-sync-progress-container" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" style="display: none;">
                <div id="nmkr-sync-progress-bar" style="width: 0%;">0%</div>
            </div>
            
            <div id="status-message" aria-live="polite" class="sync-status-message">
                <span id="nmkr-sync-phase-label" class="sync-phase-label">Waiting for synchronization to start…</span>
            </div>
            
            <div id="active-sync-metrics" style="display: none; text-align: center; margin-top: 15px; font-size: 14px;">
                <h4 class="panel-description">Active Sync Metrics</h4>
                <div class="active-metrics-container">
                    <!-- Progress Metrics -->
                    <div class="active-metric">
                        <span class="label"><strong>🗂️ Projects synced:</strong></span>
                        <span class="value total-projects-active">0</span>
                    </div>
                    <div class="active-metric">
                        <span class="label"><strong>🪙 Tokens synced:</strong></span>
                        <span class="value total-tokens-active">0</span>
                    </div>
                    <div class="active-metric">
                        <span class="label"><strong>⏱️ Sync duration:</strong></span>
                        <span class="value total-sync-duration-active">0s</span>
                    </div>
                    
                    <!-- Performance Metrics -->
                    <div class="active-metric">
                        <span class="label"><strong>🔌 Total API time:</strong></span>
                        <span class="value total-api-time-active">0.00s</span>
                    </div>
                    <div class="active-metric">
                        <span class="label"><strong>⚡ Response time:</strong></span>
                        <span class="value avg-response-time status-excellent">0.00ms</span>
                    </div>
                    <div class="active-metric">
                        <span class="label"><strong>📊 API requests:</strong></span>
                        <span class="value api-requests">0</span>
                    </div>
                    <div class="active-metric">
                        <span class="label"><strong>🗄️ Memory:</strong></span>
                        <span class="value memory-usage status-excellent">0.00MB</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Render the Sync Statistics Panel
 *
 * @param array $initial_stats Initial statistics data
 */
function nmkr_render_sync_statistics_panel($initial_stats) {
    ?>
    <!-- Synchronization Statistics Panel -->
    <div class="panel sync-statistics" role="region" aria-label="Synchronization Statistics">
        <h2 class="center-text">Previous Synchronization Statistics</h2>
        <div class="nmkr-info-box">
            <span class="dashicons dashicons-chart-bar"></span>
            <p>View detailed statistics about previous synchronization processes.</p>
        </div>
        
        <div class="sync-stats panel-section">
            <!-- Last Sync Time -->
            <div class="last-sync-time">
                <p><strong>🕒 Last synced at:</strong> <span id="last-synced"><?php echo esc_html($initial_stats['last_sync_time']); ?></span></p>
            </div>
            
            <!-- Sync Metrics -->
            <div id="performance-stats" class="performance-metrics">
                <p class="total-projects"><strong>🗂️ Total projects:</strong> <span id="total-projects"><?php echo esc_html($initial_stats['total_projects']); ?></span></p>
                <p class="total-tokens"><strong>🪙 Total tokens:</strong> <span id="total-tokens"><?php echo esc_html($initial_stats['total_tokens']); ?></span></p>
                <p class="total-time"><strong>⏱️ Total sync duration:</strong> <span id="total-sync-time"><?php echo esc_html($initial_stats['total_sync_duration']); ?></span></p>
                <p class="api-time"><strong>🔌 Total API time:</strong> <span id="total-api-time"><?php echo esc_html($initial_stats['total_api_time']); ?></span></p>
                <p class="avg-time"><strong>⚡ Average response time:</strong> <span id="avg-response-time" class="<?php echo esc_attr($initial_stats['response_time_class']); ?>"><?php echo esc_html($initial_stats['average_response_time']); ?></span></p>
                <p class="request-count"><strong>📊 API requests:</strong> <span id="request-count"><?php echo esc_html($initial_stats['api_requests']); ?></span></p>
                <p class="memory-used"><strong>🗄️ Memory usage:</strong> <span id="memory-usage" class="<?php echo esc_attr($initial_stats['memory_class']); ?>"><?php echo esc_html($initial_stats['memory_usage']); ?></span></p>
            </div>
            
            <!-- Performance Legend -->
            <div id="performance-legend" class="performance-legend panel-section">
                <p class="panel-description legend-title">Performance Indicators:</p>
                <div class="legend-item">
                    <span class="legend-dot status-excellent"></span> Excellent
                    <span class="legend-dot status-good"></span> Good
                    <span class="legend-dot status-warning"></span> Warning
                    <span class="legend-dot status-critical"></span> Critical
                </div>
                <p class="legend-desc">
                    <small>Response Time: &lt;500ms (Excellent), &lt;1000ms (Good), &lt;2000ms (Warning), &gt;2000ms (Critical)</small><br>
                    <small>Memory Usage: &lt;50MB (Excellent), &lt;100MB (Good), &lt;200MB (Warning), &gt;200MB (Critical)</small>
                </p>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Render the Debug Logs Panel
 * Only displayed if log_to_dashboard option is enabled
 */
function nmkr_render_debug_logs_panel() {
    // Check if log_to_dashboard is enabled
    $options = get_option('nmkr_connect_options');
    $log_to_dashboard = !empty($options['log_to_dashboard']);
    
    if (!$log_to_dashboard) {
        return; // Don't render the panel if logging to dashboard is disabled
    }
    
    // Get logs from WordPress options (using configured retention limit)
    $sync_logs = get_option('nmkr_sync_logs', array());
    $api_logs = get_option('nmkr_api_logs', array());
    $ui_logs = get_option('nmkr_ui_logs', array());
    $performance_logs = get_option('nmkr_performance_logs', array());
    
    $retention_limit = nmkr_get_log_retention_limit();
    
    ?>
    <!-- Debug Logs Panel -->
    <a id="nmkr-debug-logs"></a>
    <div class="panel debug-logs-panel" role="region" aria-label="Debug Logs">
        <h2 class="center-text">Debug Logs (Last <?php echo esc_html($retention_limit); ?> Entries)</h2>
                 <div class="nmkr-info-box">
             <span class="dashicons dashicons-text-page"></span>
             <p>View recent debug logs from synchronization, API, UI, and performance monitoring. This is a static view that shows logs captured at page load time.</p>
         </div>
         
         <div class="filter-note">
             💡 <strong>Tip:</strong> Search across all log entries or use the dropdowns to filter by specific criteria. Filters work together to narrow down results.
         </div>
         
         <!-- Filter Controls -->
         <div class="filter-controls">
             <fieldset>
                 <legend>🔍 Filter Debug Logs</legend>
                 
                 <div class="filter-fields-container">
                     <div class="filter-group">
                         <label for="nmkr-log-search">Search Messages</label>
                         <input type="text" id="nmkr-log-search" placeholder="Type to search messages..." />
                     </div>
                     <div class="filter-group">
                         <label for="nmkr-log-type-filter">Filter by Type</label>
                         <select id="nmkr-log-type-filter">
                             <option value="">All Types</option>
                             <option value="info">Info</option>
                             <option value="debug">Debug</option>
                             <option value="warning">Warning</option>
                             <option value="error">Error</option>
                         </select>
                     </div>
                     <div class="filter-group">
                         <label for="nmkr-log-category-filter">Filter by Category</label>
                         <select id="nmkr-log-category-filter">
                             <option value="">All Categories</option>
                             <option value="sync">Sync</option>
                             <option value="api">API</option>
                             <option value="ui">UI</option>
                             <option value="performance">Performance</option>
                         </select>
                     </div>
                 </div>
                 
                 <div class="filter-actions">
                     <button type="button" class="clear-filters-btn" id="nmkr-clear-filters">Clear All Filters</button>
                 </div>
             </fieldset>
         </div>
         
         <!-- Clear All Logs Button -->
         <div class="clear-logs-controls">
             <?php 
             $sync_in_progress = get_option('nmkr_sync_in_progress', false);
             $disabled_attr = $sync_in_progress ? 'disabled' : '';
             $disabled_class = $sync_in_progress ? 'disabled' : '';
             $tooltip_text = $sync_in_progress ? 'Logs cannot be cleared while sync is in progress.' : 'Clear all debug logs (sync, API, UI, and performance logs)';
             ?>
             <button type="button" 
                     class="clear-all-logs-btn <?php echo esc_attr($disabled_class); ?>" 
                     id="nmkr-clear-all-logs"
                     <?php echo $disabled_attr; ?>
                     title="<?php echo esc_attr($tooltip_text); ?>"
                     data-nonce="<?php echo wp_create_nonce('nmkr_clear_logs_nonce'); ?>">
                 🧹 Clear All Logs
             </button>
         </div>
         
         <div class="panel-section">
            <!-- Sync Logs Section -->
            <div class="log-section">
                                 <details>
                     <summary>
                         <span class="log-section-title">▶ Sync Logs</span>
                         <span class="log-entry-count"><?php echo count($sync_logs); ?> entries</span>
                         <?php 
                         $sync_in_progress = get_option('nmkr_sync_in_progress', false);
                         $disabled_attr = $sync_in_progress ? 'disabled' : '';
                         $disabled_class = $sync_in_progress ? 'disabled' : '';
                         $tooltip_text = $sync_in_progress ? 'Logs cannot be cleared while sync is in progress.' : 'Clear sync logs';
                         ?>
                         <button type="button" 
                                 class="clear-section-logs-btn <?php echo esc_attr($disabled_class); ?>" 
                                 data-log-type="sync"
                                 <?php echo $disabled_attr; ?>
                                 title="<?php echo esc_attr($tooltip_text); ?>"
                                 data-nonce="<?php echo wp_create_nonce('nmkr_clear_logs_nonce'); ?>">
                             🧹 Clear Logs
                         </button>
                     </summary>
                    <div class="log-entries">
                                                 <?php if (empty($sync_logs)): ?>
                             <div class="no-logs">No sync logs available.</div>
                         <?php else: ?>
                             <div class="no-filtered-results">No logs match the current filters.</div>
                             <?php foreach ($sync_logs as $log): ?>
                                 <div class="log-entry nmkr-log-entry" 
                                      data-type="<?php echo esc_attr(strtolower($log['type'])); ?>" 
                                      data-category="sync" 
                                      data-message="<?php echo esc_attr(strtolower($log['message'])); ?>">
                                     <div class="log-header">
                                         <span class="log-timestamp"><?php echo esc_html($log['timestamp']); ?></span>
                                         <span class="log-type <?php echo esc_attr($log['type']); ?>"><?php echo esc_html($log['type']); ?></span>
                                     </div>
                                     <div class="log-message"><?php echo esc_html($log['message']); ?></div>
                                     <?php if (!empty($log['data'])): ?>
                                         <div class="log-data"><?php echo esc_html(is_array($log['data']) ? print_r($log['data'], true) : $log['data']); ?></div>
                                     <?php endif; ?>
                                 </div>
                             <?php endforeach; ?>
                         <?php endif; ?>
                    </div>
                </details>
            </div>
            
            <!-- API Logs Section -->
            <div class="log-section">
                                 <details>
                     <summary>
                         <span class="log-section-title">▶ API Logs</span>
                         <span class="log-entry-count"><?php echo count($api_logs); ?> entries</span>
                         <?php 
                         $sync_in_progress = get_option('nmkr_sync_in_progress', false);
                         $disabled_attr = $sync_in_progress ? 'disabled' : '';
                         $disabled_class = $sync_in_progress ? 'disabled' : '';
                         $tooltip_text = $sync_in_progress ? 'Logs cannot be cleared while sync is in progress.' : 'Clear API logs';
                         ?>
                         <button type="button" 
                                 class="clear-section-logs-btn <?php echo esc_attr($disabled_class); ?>" 
                                 data-log-type="api"
                                 <?php echo $disabled_attr; ?>
                                 title="<?php echo esc_attr($tooltip_text); ?>"
                                 data-nonce="<?php echo wp_create_nonce('nmkr_clear_logs_nonce'); ?>">
                             🧹 Clear Logs
                         </button>
                     </summary>
                    <div class="log-entries">
                                                 <?php if (empty($api_logs)): ?>
                             <div class="no-logs">No API logs available.</div>
                         <?php else: ?>
                             <div class="no-filtered-results">No logs match the current filters.</div>
                             <?php foreach ($api_logs as $log): ?>
                                 <div class="log-entry nmkr-log-entry" 
                                      data-type="<?php echo esc_attr(strtolower($log['type'])); ?>" 
                                      data-category="api" 
                                      data-message="<?php echo esc_attr(strtolower($log['message'])); ?>">
                                     <div class="log-header">
                                         <span class="log-timestamp"><?php echo esc_html($log['timestamp']); ?></span>
                                         <span class="log-type <?php echo esc_attr($log['type']); ?>"><?php echo esc_html($log['type']); ?></span>
                                     </div>
                                     <div class="log-message"><?php echo esc_html($log['message']); ?></div>
                                     <?php if (!empty($log['data'])): ?>
                                         <div class="log-data"><?php echo esc_html(is_array($log['data']) ? print_r($log['data'], true) : $log['data']); ?></div>
                                     <?php endif; ?>
                                 </div>
                             <?php endforeach; ?>
                         <?php endif; ?>
                    </div>
                </details>
            </div>
            
            <!-- UI Logs Section -->
            <div class="log-section">
                                 <details>
                     <summary>
                         <span class="log-section-title">▶ UI Logs</span>
                         <span class="log-entry-count"><?php echo count($ui_logs); ?> entries</span>
                         <?php 
                         $sync_in_progress = get_option('nmkr_sync_in_progress', false);
                         $disabled_attr = $sync_in_progress ? 'disabled' : '';
                         $disabled_class = $sync_in_progress ? 'disabled' : '';
                         $tooltip_text = $sync_in_progress ? 'Logs cannot be cleared while sync is in progress.' : 'Clear UI logs';
                         ?>
                         <button type="button" 
                                 class="clear-section-logs-btn <?php echo esc_attr($disabled_class); ?>" 
                                 data-log-type="ui"
                                 <?php echo $disabled_attr; ?>
                                 title="<?php echo esc_attr($tooltip_text); ?>"
                                 data-nonce="<?php echo wp_create_nonce('nmkr_clear_logs_nonce'); ?>">
                             🧹 Clear Logs
                         </button>
                     </summary>
                    <div class="log-entries">
                                                 <?php if (empty($ui_logs)): ?>
                             <div class="no-logs">No UI logs available.</div>
                         <?php else: ?>
                             <div class="no-filtered-results">No logs match the current filters.</div>
                             <?php foreach ($ui_logs as $log): ?>
                                 <div class="log-entry nmkr-log-entry" 
                                      data-type="<?php echo esc_attr(strtolower($log['type'])); ?>" 
                                      data-category="ui" 
                                      data-message="<?php echo esc_attr(strtolower($log['message'])); ?>">
                                     <div class="log-header">
                                         <span class="log-timestamp"><?php echo esc_html($log['timestamp']); ?></span>
                                         <span class="log-type <?php echo esc_attr($log['type']); ?>"><?php echo esc_html($log['type']); ?></span>
                                     </div>
                                     <div class="log-message"><?php echo esc_html($log['message']); ?></div>
                                     <?php if (!empty($log['data'])): ?>
                                         <div class="log-data"><?php echo esc_html(is_array($log['data']) ? print_r($log['data'], true) : $log['data']); ?></div>
                                     <?php endif; ?>
                                 </div>
                             <?php endforeach; ?>
                         <?php endif; ?>
                    </div>
                </details>
            </div>
            
            <!-- Performance Logs Section -->
            <div class="log-section">
                                 <details>
                     <summary>
                         <span class="log-section-title">▶ Performance Logs</span>
                         <span class="log-entry-count"><?php echo count($performance_logs); ?> entries</span>
                         <?php 
                         $sync_in_progress = get_option('nmkr_sync_in_progress', false);
                         $disabled_attr = $sync_in_progress ? 'disabled' : '';
                         $disabled_class = $sync_in_progress ? 'disabled' : '';
                         $tooltip_text = $sync_in_progress ? 'Logs cannot be cleared while sync is in progress.' : 'Clear performance logs';
                         ?>
                         <button type="button" 
                                 class="clear-section-logs-btn <?php echo esc_attr($disabled_class); ?>" 
                                 data-log-type="performance"
                                 <?php echo $disabled_attr; ?>
                                 title="<?php echo esc_attr($tooltip_text); ?>"
                                 data-nonce="<?php echo wp_create_nonce('nmkr_clear_logs_nonce'); ?>">
                             🧹 Clear Logs
                         </button>
                     </summary>
                    <div class="log-entries">
                                                 <?php if (empty($performance_logs)): ?>
                             <div class="no-logs">No performance logs available.</div>
                         <?php else: ?>
                             <div class="no-filtered-results">No logs match the current filters.</div>
                             <?php foreach ($performance_logs as $log): ?>
                                 <?php 
                                 $message = $log['message'] ?? ($log['operation'] ?? 'Performance data');
                                 $type = $log['type'] ?? 'info';
                                 ?>
                                 <div class="log-entry nmkr-log-entry" 
                                      data-type="<?php echo esc_attr(strtolower($type)); ?>" 
                                      data-category="performance" 
                                      data-message="<?php echo esc_attr(strtolower($message)); ?>">
                                     <div class="log-header">
                                         <span class="log-timestamp"><?php echo esc_html($log['timestamp'] ?? 'N/A'); ?></span>
                                         <span class="log-type <?php echo esc_attr($type); ?>"><?php echo esc_html($type); ?></span>
                                     </div>
                                     <div class="log-message"><?php echo esc_html($message); ?></div>
                                    <?php if (!empty($log['data']) || !empty($log['duration']) || !empty($log['memory_used'])): ?>
                                        <div class="log-data"><?php 
                                            // Format performance log data
                                            $perf_data = array();
                                            if (isset($log['operation'])) $perf_data['Operation'] = $log['operation'];
                                            if (isset($log['duration'])) $perf_data['Duration'] = $log['duration'] . 'ms';
                                            if (isset($log['memory_used'])) $perf_data['Memory'] = $log['memory_used'] . 'MB';
                                            if (isset($log['request_count'])) $perf_data['API Requests'] = $log['request_count'];
                                            if (isset($log['average_time'])) $perf_data['Avg Response Time'] = $log['average_time'] . 'ms';
                                            if (!empty($log['data'])) $perf_data['Additional Data'] = $log['data'];
                                            
                                            echo esc_html(print_r($perf_data, true));
                                        ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </details>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Render the dashboard JavaScript
 *
 * @param string $dashboard_nonce The nonce for dashboard operations
 */
function nmkr_render_dashboard_scripts($dashboard_nonce) {
    ?>
    <script>
        jQuery(document).ready(function($) {
            // Cache nonce values ONCE to avoid repeated DOM queries
            const syncNonce = $('#nmkr-sync-nonce').val();
            const dashboardNonce = $('#nmkr-dashboard-nonce').val();
            
            // Set default display while loading
            $('#api-status').html('<span class="api-status-loading">Checking connection...</span>');
            $('#nmkr-sync-button').prop('disabled', true);
            
            
            // Check API status on page load
            $.ajax({
                url: ajaxurl,
                method: 'POST',
                dataType: 'json',
                data: { 
                    action: 'nmkr_check_api_status',
                    nonce: dashboardNonce
                },
                timeout: 10000, // 10 second timeout
                success: function(response) {
                    $('#api-status').html(response.data.message);
                    
                    // Enable or disable sync button based on API status
                    if (response.data.connected === true) {
                        $('#nmkr-sync-button').prop('disabled', false);
                        
                    } else {
                        $('#nmkr-sync-button').prop('disabled', true);
                        
                    }
                },
                error: function(xhr) {
                    console.error('API status check error:', xhr.responseText);
                    $('#api-status').html('⚠️ Unable to check API status. Please try refreshing the page.');
                    
                    // Log API status check error
                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: { 
                            action: 'nmkr_store_active_metrics',
                            nonce: dashboardNonce,
                            metrics: { ui_log: "UI: Displaying 'Unable to check API status' error message to user" },
                            log_type: 'error'
                        }
                    });
                }
            });
            
            // Refresh API status when refresh button is clicked
            $('#refresh-api-status').on('click', function() {
                $('#api-status').html('<span class="api-status-loading">Checking connection...</span>');
                
                // Log refresh button click
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: { 
                        action: 'nmkr_store_active_metrics',
                        nonce: dashboardNonce,
                        metrics: { ui_log: "UI: User clicked 'Refresh Status' button - showing loading state" },
                        log_type: 'debug'
                    }
                });
                
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: { 
                        action: 'nmkr_check_api_status',
                        nonce: dashboardNonce 
                    },
                    timeout: 10000, // 10 second timeout
                    success: function(response) {
                        $('#api-status').html(response.data.message);
                        
                        // Enable or disable sync button based on API status
                        if (response.data.connected === true) {
                            $('#nmkr-sync-button').prop('disabled', false);
                            
                            // Log sync button enabled after refresh
                            $.ajax({
                                url: ajaxurl,
                                method: 'POST',
                                data: { 
                                    action: 'nmkr_store_active_metrics',
                                    nonce: dashboardNonce,
                                    metrics: { ui_log: "UI: Enabled 'Start Synchronization' button after status refresh" },
                                    log_type: 'debug'
                                }
                            });
                        } else {
                            $('#nmkr-sync-button').prop('disabled', true);
                            
                            // Log sync button disabled after refresh
                            $.ajax({
                                url: ajaxurl,
                                method: 'POST',
                                data: { 
                                    action: 'nmkr_store_active_metrics',
                                    nonce: dashboardNonce,
                                    metrics: { ui_log: "UI: Disabled 'Start Synchronization' button after status refresh" },
                                    log_type: 'debug'
                                }
                            });
                        }
                    },
                    error: function(xhr) {
                        console.error('API status refresh error:', xhr.responseText);
                        $('#api-status').html('⚠️ Unable to check API status. Please try refreshing the page.');
                        
                        // Log API status refresh error
                        $.ajax({
                            url: ajaxurl,
                            method: 'POST',
                            data: { 
                                action: 'nmkr_store_active_metrics',
                                nonce: dashboardNonce,
                                metrics: { ui_log: "UI: Displaying 'Unable to check API status' error message after refresh attempt" },
                                log_type: 'error'
                            }
                        });
                    }
                });
            });
            
            // Function to refresh completed sync metrics only (for the statistics panel)
            function refreshCompletedMetrics() {
                // Log metrics refresh
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: { 
                        action: 'nmkr_store_active_metrics',
                        nonce: dashboardNonce,
                        metrics: { ui_log: "UI: Refreshing completed sync statistics display" },
                        log_type: 'debug'
                    }
                });
                
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'nmkr_get_sync_statistics',
                        request_type: 'completed',
                        force_refresh: true,
                        nonce: dashboardNonce
                    },
                    success: function(response) {
                        if (response.success) {
                            // Update displayed statistics
                            $('#last-synced').text(response.data.last_sync_time);
                            $('#total-projects').text(response.data.total_projects);
                            $('#total-tokens').text(response.data.total_tokens);
                            $('#total-sync-time').text(response.data.total_sync_duration);
                            $('#total-api-time').text(response.data.total_api_time);
                            $('#request-count').text(response.data.api_requests);
                            
                            // Update with classes for color coding
                            $('#avg-response-time')
                                .text(response.data.average_response_time)
                                .removeClass()
                                .addClass(response.data.response_time_class);
                            
                            $('#memory-usage')
                                .text(response.data.memory_usage)
                                .removeClass()
                                .addClass(response.data.memory_class);
                                
                            // Log successful statistics update
                            $.ajax({
                                url: ajaxurl,
                                method: 'POST',
                                data: { 
                                    action: 'nmkr_store_active_metrics',
                                    nonce: dashboardNonce,
                                    metrics: { ui_log: "UI: Successfully updated completed sync statistics display" },
                                    log_type: 'debug'
                                }
                            });
                        } else {
                            // Log failed statistics update
                            $.ajax({
                                url: ajaxurl,
                                method: 'POST',
                                data: { 
                                    action: 'nmkr_store_active_metrics',
                                    nonce: dashboardNonce,
                                    metrics: { ui_log: "UI: Failed to update completed sync statistics display" },
                                    log_type: 'warning'
                                }
                            });
                        }
                    },
                    error: function() {
                        // Log AJAX error for statistics refresh
                        $.ajax({
                            url: ajaxurl,
                            method: 'POST',
                            data: { 
                                action: 'nmkr_store_active_metrics',
                                nonce: dashboardNonce,
                                metrics: { ui_log: "UI: AJAX error when refreshing completed sync statistics" },
                                log_type: 'error'
                            }
                        });
                    }
                });
            }
            
            // Function to refresh active sync metrics
            function refreshActiveMetrics() {
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'nmkr_get_sync_statistics',
                        request_type: 'active',
                        force_refresh: true,
                        nonce: dashboardNonce
                    },
                    success: function(response) {
                        if (response.success) {
                            // Ensure we have valid data
                            if (!response.data) {
                                return;
                            }
                            
                            const data = response.data;
                            
                            // Show the active metrics container
                            if ($('#active-sync-metrics').is(':hidden')) {
                                $('#active-sync-metrics').show();
                                
                            }
                            
                            // Update all 7 active sync metrics
                            
                            // Progress metrics (newly added)
                            $('#active-sync-metrics .total-projects-active').text(data.total_projects || '0');
                            $('#active-sync-metrics .total-tokens-active').text(data.total_tokens || '0');
                            
                            // Format sync duration
                            var syncDuration = data.total_sync_duration || 0;
                            var formattedDuration = syncDuration < 60 ? 
                                parseFloat(syncDuration).toFixed(1) + 's' : 
                                Math.floor(syncDuration / 60) + 'm ' + Math.floor(syncDuration % 60) + 's';
                            $('#active-sync-metrics .total-sync-duration-active').text(formattedDuration);
                            
                            // Format total API time
                            var apiTime = data.total_api_time || 0;
                            var formattedApiTime = parseFloat(apiTime).toFixed(2) + 's';
                            $('#active-sync-metrics .total-api-time-active').text(formattedApiTime);
                            
                            // Performance metrics (existing, enhanced)
                            var avgResponseElement = $('#active-sync-metrics .avg-response-time');
                            var avgResponseTime = data.average_response_time || 0;
                            var formattedAvgResponse = avgResponseTime < 1 ? 
                                (avgResponseTime * 1000).toFixed(2) + 'ms' : 
                                avgResponseTime.toFixed(2) + 's';
                            
                            avgResponseElement
                                .text(formattedAvgResponse)
                                .removeClass()
                                .addClass(data.response_time_class || 'status-excellent');
                                
                            // Update API requests count
                            var apiRequests = data.api_requests || 0;
                            $('#active-sync-metrics .api-requests').text(apiRequests.toLocaleString());
                            
                            // Update memory usage with appropriate formatting and styling
                            var memoryElement = $('#active-sync-metrics .memory-usage');
                            var memoryUsage = data.memory_usage || 0;
                            var formattedMemory = parseFloat(memoryUsage).toFixed(2) + 'MB';
                            
                            memoryElement
                                .text(formattedMemory)
                                .removeClass()
                                .addClass(data.memory_class || 'status-excellent');
                            
                        } else {
                        }
                    },
                    error: function() {
                        // AJAX error for metrics refresh - removed excessive logging
                    }
                });
            }
            
            // Logging throttling mechanism
            let lastLogTime = 0;
            let logQueue = [];
            const LOG_THROTTLE_INTERVAL = 5000; // 5 seconds between log batches
            const LOG_BATCH_SIZE = 5; // Maximum logs per batch
            
            function throttledLog(logData) {
                logQueue.push(logData);
                
                // Only process if enough time has passed and we have logs to send
                const now = Date.now();
                if (now - lastLogTime >= LOG_THROTTLE_INTERVAL && logQueue.length > 0) {
                    // Process up to LOG_BATCH_SIZE logs
                    const logsToSend = logQueue.splice(0, LOG_BATCH_SIZE);
                    lastLogTime = now;
                    
                    // Send batched logs with summary
                    const logMessages = logsToSend.map(log => log.message).join('; ');
                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: { 
                            action: 'nmkr_store_active_metrics',
                            nonce: dashboardNonce,
                            metrics: { ui_log: "UI: Batched logs (" + logsToSend.length + " entries): " + logMessages },
                            log_type: 'debug'
                        }
                    });
                }
            }
            
            // Check if sync is in progress on page load
            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: { 
                    action: 'nmkr_check_api_status',
                    nonce: dashboardNonce 
                },
                success: function(response) {
                    if (response.data.sync_in_progress) {
                        // Show sync in progress UI
                        $('#nmkr-sync-button').hide();
                        $('#nmkr-stop-sync-button').show();
                        $('#nmkr-sync-progress-container').show();
                        
                        // Log sync in progress UI update
                        $.ajax({
                            url: ajaxurl,
                            method: 'POST',
                            data: { 
                                action: 'nmkr_store_active_metrics',
                                nonce: dashboardNonce,
                                metrics: { ui_log: "UI: Showing sync in progress state on page load - displaying stop button and progress bar" },
                                log_type: 'info'
                            }
                        });
                        
                        // Show active sync metrics container
                        $('#active-sync-metrics').show();
                    }
                }
            });
            
            // Handle stop sync button click
            $('#nmkr-stop-sync-button').on('click', function() {
                $(this).prop('disabled', true);
                
                // Log stop attempt (essential - use direct logging for critical events)
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: { 
                        action: 'nmkr_store_active_metrics',
                        nonce: dashboardNonce,
                        metrics: { ui_log: "UI: User stopped synchronization" },
                        log_type: 'info'
                    }
                });
                
                $('#status-message').text('⏹️ Stopping Synchronization...');
                
                // Hide active metrics container
                $('#active-sync-metrics').hide();
                
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    dataType: 'json',
                    data: { 
                        action: 'nmkr_stop_sync',
                        nonce: syncNonce
                    },
                    timeout: 15000,
                    success: function(response) {
                        if (response.success) {
                            $('#status-message').text('✅ Synchronization stopped successfully');
                            
                            // Clean up UI state
                            $('#nmkr-sync-progress-container, #active-sync-metrics').hide();
                            $('#nmkr-stop-sync-button').hide();
                            $('#nmkr-sync-button').show().prop('disabled', false);
                            
                            // Sync stopped successfully - removed excessive logging
                            
                            // After the sync is stopped, refresh the completed metrics once to show latest data
                            setTimeout(refreshCompletedMetrics, 1500);
                        } else {
                            // Handle error
                            $('#status-message').text('❌ Failed to stop synchronization: ' + (response.data ? response.data.message : 'Unknown error'));
                            // Don't call teardownSyncUI() here - keep sync button hidden since stop failed
                            $('#nmkr-stop-sync-button').prop('disabled', false);
                            
                            // Keep polling active since sync is likely still running on server
                            // Show active metrics again since sync is still running
                            $('#active-sync-metrics').show();
                            
                            // Log critical stop failure (essential)
                            $.ajax({
                                url: ajaxurl,
                                method: 'POST',
                                data: { 
                                    action: 'nmkr_store_active_metrics',
                                    nonce: dashboardNonce,
                                    metrics: { ui_log: "CRITICAL: Failed to stop sync - " + (response.data ? response.data.message : 'Unknown error') },
                                    log_type: 'error'
                                }
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        // Handle AJAX error
                        $('#status-message').text('❌ Network error stopping synchronization: ' + error);
                        // Don't call teardownSyncUI() here - keep sync button hidden since stop failed
                        $('#nmkr-stop-sync-button').prop('disabled', false);
                        
                        // Keep polling active since sync is likely still running on server
                        // Show active metrics again since sync is still running
                        $('#active-sync-metrics').show();
                        
                        // Log critical network error (essential)
                        $.ajax({
                            url: ajaxurl,
                            method: 'POST',
                            data: { 
                                action: 'nmkr_store_active_metrics',
                                nonce: dashboardNonce,
                                metrics: { ui_log: "CRITICAL: Network error stopping sync - " + error },
                                log_type: 'error'
                            }
                        });
                    }
                });
            });
            
            // When the sync completes (handled by nmkr-sync-progress.js), it should call a refresh of completed metrics
            // This can be done by adding a refresh call in the corresponding event handling code
            // Custom event handler for sync completion (namespaced to avoid conflicts)
            $(document).on('nmkr:sync:completed', function() {
                // Clean up UI state
                $('#nmkr-sync-progress-container, #active-sync-metrics').hide();
                $('#nmkr-stop-sync-button').hide();
                $('#nmkr-sync-button').show().prop('disabled', false);
                
                // Refresh completed metrics after sync finishes
                setTimeout(refreshCompletedMetrics, 500);
            });
            
            // Custom event handler for sync stop (namespaced to avoid conflicts)
            $(document).on('nmkr:sync:stopped', function() {
                // Clean up UI state
                $('#nmkr-sync-progress-container, #active-sync-metrics').hide();
                $('#nmkr-stop-sync-button').hide();
                $('#nmkr-sync-button').show().prop('disabled', false);
                
                // Refresh completed metrics after sync stops
                setTimeout(refreshCompletedMetrics, 500);
            });
            
            // Custom event handlers for progress and status updates (namespaced)
            // Note: These events are triggered by external sync progress components
            $(document).on('nmkr:sync:progress', function(e, progressData) {
                if (progressData && typeof progressData.percentage !== 'undefined') {
                    $('#nmkr-sync-progress-bar')
                        .css('width', progressData.percentage + '%')
                        .text(progressData.percentage + '%')
                        .attr('aria-valuenow', progressData.percentage);
                }
            });
            
            $(document).on('nmkr:sync:status', function(e, statusData) {
                if (statusData && statusData.message) {
                    $('#status-message').text(statusData.message);
                }
            });
            
            // Debug logs collapsible sections enhancement
            $('.debug-logs-panel details').on('toggle', function() {
                var $summary = $(this).find('summary .log-section-title');
                if (this.open) {
                    $summary.text('▼ ' + $summary.text().replace('▶ ', ''));
                } else {
                    $summary.text('▶ ' + $summary.text().replace('▼ ', ''));
                }
            });
            
            // Debug logs filtering functionality (plain JavaScript)
            const searchInput = document.getElementById('nmkr-log-search');
            const typeFilter = document.getElementById('nmkr-log-type-filter');
            const categoryFilter = document.getElementById('nmkr-log-category-filter');
            const clearFiltersBtn = document.getElementById('nmkr-clear-filters');
            
            if (searchInput && typeFilter && categoryFilter && clearFiltersBtn) {
                // Store original entry counts for section headers
                const originalCounts = {};
                document.querySelectorAll('.debug-logs-panel .log-section').forEach(function(section) {
                    const countBadge = section.querySelector('.log-entry-count');
                    if (countBadge) {
                        const category = section.querySelector('.log-entries').getAttribute('data-category') || 
                                       section.querySelector('.nmkr-log-entry')?.getAttribute('data-category') || 'unknown';
                        originalCounts[category] = countBadge.textContent;
                    }
                });
                
                function applyFilters() {
                    const searchTerm = searchInput.value.toLowerCase().trim();
                    const selectedType = typeFilter.value.toLowerCase();
                    const selectedCategory = categoryFilter.value.toLowerCase();
                    
                    // Get all log entries
                    const allLogEntries = document.querySelectorAll('.debug-logs-panel .nmkr-log-entry');
                    let totalVisible = 0;
                    
                    // Track visible counts per section
                    const visibleCounts = { sync: 0, api: 0, ui: 0, performance: 0 };
                    
                    allLogEntries.forEach(function(entry) {
                        const entryType = entry.getAttribute('data-type') || '';
                        const entryCategory = entry.getAttribute('data-category') || '';
                        const entryMessage = entry.getAttribute('data-message') || '';
                        
                        let shouldShow = true;
                        
                        // Apply search filter
                        if (searchTerm && !entryMessage.includes(searchTerm) && 
                            !entryType.includes(searchTerm) && !entryCategory.includes(searchTerm)) {
                            shouldShow = false;
                        }
                        
                        // Apply type filter
                        if (selectedType && entryType !== selectedType) {
                            shouldShow = false;
                        }
                        
                        // Apply category filter
                        if (selectedCategory && entryCategory !== selectedCategory) {
                            shouldShow = false;
                        }
                        
                        // Show or hide the entry
                        if (shouldShow) {
                            entry.classList.remove('nmkr-filtered-hidden');
                            visibleCounts[entryCategory]++;
                            totalVisible++;
                        } else {
                            entry.classList.add('nmkr-filtered-hidden');
                        }
                    });
                    
                    // Update section counts and show/hide "no results" messages
                    document.querySelectorAll('.debug-logs-panel .log-section').forEach(function(section) {
                        const logEntries = section.querySelector('.log-entries');
                        const noResultsMsg = section.querySelector('.no-filtered-results');
                        const countBadge = section.querySelector('.log-entry-count');
                        
                        // Determine section category
                        let sectionCategory = '';
                        const firstEntry = section.querySelector('.nmkr-log-entry');
                        if (firstEntry) {
                            sectionCategory = firstEntry.getAttribute('data-category');
                        }
                        
                        const visibleInSection = visibleCounts[sectionCategory] || 0;
                        const hasAnyEntries = section.querySelectorAll('.nmkr-log-entry').length > 0;
                        
                        // Update count badge
                        if (countBadge && sectionCategory) {
                            countBadge.textContent = visibleInSection + ' entries';
                        }
                        
                        // Show/hide "no results" message
                        if (noResultsMsg) {
                            if (hasAnyEntries && visibleInSection === 0) {
                                noResultsMsg.classList.add('show');
                            } else {
                                noResultsMsg.classList.remove('show');
                            }
                        }
                    });
                }
                
                function clearFilters() {
                    searchInput.value = '';
                    typeFilter.value = '';
                    categoryFilter.value = '';
                    
                    // Show all entries
                    document.querySelectorAll('.debug-logs-panel .nmkr-log-entry').forEach(function(entry) {
                        entry.classList.remove('nmkr-filtered-hidden');
                    });
                    
                    // Hide all "no results" messages
                    document.querySelectorAll('.debug-logs-panel .no-filtered-results').forEach(function(msg) {
                        msg.classList.remove('show');
                    });
                    
                    // Restore original counts
                    document.querySelectorAll('.debug-logs-panel .log-section').forEach(function(section) {
                        const countBadge = section.querySelector('.log-entry-count');
                        const firstEntry = section.querySelector('.nmkr-log-entry');
                        if (countBadge && firstEntry) {
                            const category = firstEntry.getAttribute('data-category');
                            if (originalCounts[category]) {
                                countBadge.textContent = originalCounts[category];
                            }
                        }
                    });
                }
                
                // Add event listeners
                searchInput.addEventListener('input', applyFilters);
                typeFilter.addEventListener('change', applyFilters);
                categoryFilter.addEventListener('change', applyFilters);
                clearFiltersBtn.addEventListener('click', clearFilters);
            }
            
            // Clear logs functionality
            // Clear All Logs button
            $('#nmkr-clear-all-logs').on('click', function() {
                if ($(this).hasClass('disabled')) return;
                
                if (!confirm('Are you sure you want to clear all debug logs? This action cannot be undone.')) {
                    return;
                }
                
                const button = $(this);
                const originalText = button.text();
                
                button.prop('disabled', true).text('Clearing...');
                
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'nmkr_clear_all_logs',
                        nonce: button.data('nonce')
                    },
                    success: function(response) {
                        if (response.success) {
                            // Show success message
                            showLogMessage('All logs cleared successfully', 'success');
                            // Reload the debug logs panel after a short delay
                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                        } else {
                            showLogMessage(response.data.message || 'Failed to clear logs', 'error');
                        }
                    },
                    error: function() {
                        showLogMessage('Network error occurred while clearing logs', 'error');
                    },
                    complete: function() {
                        button.prop('disabled', false).text(originalText);
                    }
                });
            });
            
            // Clear Section Logs buttons
            $('.clear-section-logs-btn').on('click', function(e) {
                e.stopPropagation(); // Prevent accordion toggle
                
                if ($(this).hasClass('disabled')) return;
                
                const logType = $(this).data('log-type');
                const logTypeName = logType.charAt(0).toUpperCase() + logType.slice(1);
                
                if (!confirm('Are you sure you want to clear all ' + logTypeName + ' logs? This action cannot be undone.')) {
                    return;
                }
                
                const button = $(this);
                const originalText = button.text();
                
                button.prop('disabled', true).text('Clearing...');
                
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'nmkr_clear_section_logs',
                        nonce: button.data('nonce'),
                        log_type: logType
                    },
                    success: function(response) {
                        if (response.success) {
                            // Show success message
                            showLogMessage(response.data.message || (logTypeName + ' logs cleared successfully'), 'success');
                            // Update the specific section
                            updateLogSection(logType);
                        } else {
                            showLogMessage(response.data.message || 'Failed to clear ' + logTypeName.toLowerCase() + ' logs', 'error');
                        }
                    },
                    error: function() {
                        showLogMessage('Network error occurred while clearing ' + logTypeName.toLowerCase() + ' logs', 'error');
                    },
                    complete: function() {
                        button.prop('disabled', false).text(originalText);
                    }
                });
            });
            
            // Helper function to show temporary messages
            function showLogMessage(message, type) {
                const messageEl = $('<div class="nmkr-log-message ' + type + '">' + message + '</div>');
                messageEl.css({
                    position: 'fixed',
                    top: '20px',
                    right: '20px',
                    padding: '10px 15px',
                    borderRadius: '4px',
                    color: 'white',
                    fontWeight: 'bold',
                    zIndex: 9999,
                    backgroundColor: type === 'success' ? '#46b450' : '#dc3232'
                });
                
                $('body').append(messageEl);
                
                setTimeout(function() {
                    messageEl.fadeOut(300, function() {
                        $(this).remove();
                    });
                }, 3000);
            }
            
            // Helper function to update a specific log section after clearing
            function updateLogSection(logType) {
                const section = $('[data-log-type="' + logType + '"]').closest('.log-section');
                const entryCount = section.find('.log-entry-count');
                const logEntries = section.find('.log-entries');
                const noLogsDiv = logEntries.find('.no-logs');
                
                // Update entry count
                entryCount.text('0 entries');
                
                // Clear all log entries and show "no logs" message
                logEntries.find('.nmkr-log-entry').remove();
                if (noLogsDiv.length === 0) {
                    logEntries.prepend('<div class="no-logs">No ' + logType + ' logs available.</div>');
                } else {
                    noLogsDiv.show();
                }
                
                // Hide any "no filtered results" messages
                logEntries.find('.no-filtered-results').removeClass('show');
            }
            
            // Function to update clear logs button states based on sync status
            function updateClearLogButtonStates(syncInProgress) {
                const clearAllBtn = $('#nmkr-clear-all-logs');
                const clearSectionBtns = $('.clear-section-logs-btn');
                const tooltip = syncInProgress ? 
                    'Logs cannot be cleared while sync is in progress.' : 
                    'Click to clear logs';
                
                if (syncInProgress) {
                    clearAllBtn.addClass('disabled').prop('disabled', true);
                    clearSectionBtns.addClass('disabled').prop('disabled', true);
                } else {
                    clearAllBtn.removeClass('disabled').prop('disabled', false);
                    clearSectionBtns.removeClass('disabled').prop('disabled', false);
                }
                
                // Update tooltips
                clearAllBtn.attr('title', syncInProgress ? 
                    'Logs cannot be cleared while sync is in progress.' : 
                    'Clear all debug logs (sync, API, UI, and performance logs)');
                
                clearSectionBtns.each(function() {
                    const logType = $(this).data('log-type');
                    $(this).attr('title', syncInProgress ? 
                        'Logs cannot be cleared while sync is in progress.' : 
                        'Clear ' + logType + ' logs');
                });
            }
            
            // Listen for sync progress updates to update button states
            $(document).on('sync_progress_update', function(e, progressData) {
                if (progressData && typeof progressData.sync_in_progress !== 'undefined') {
                    updateClearLogButtonStates(progressData.sync_in_progress);
                }
            });
            
            // Also listen for sync completion/error to re-enable buttons
            $(document).on('sync_completed sync_error sync_stopped', function() {
                updateClearLogButtonStates(false);
            });
        });
    </script>
    <?php
}  