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
    nmkr_connect_enqueue_style_asset( 'nmkr-dashboard', 'css/admin/nmkr-dashboard.css' );
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
 * @param string $dashboard_nonce The nonce for dashboard operations.
 * @param bool   $can_manage_sync Whether the current user may mutate synchronization state.
 */
function nmkr_render_sync_data_panel($dashboard_nonce, $can_manage_sync) {
    ?>
    <!-- Data Synchronization Panel -->
    <div class="panel sync-data" role="region" aria-label="Data Synchronization Controls">
        <h2 class="center-text">Data Synchronization</h2>
        <div id="nmkr-recovered-note" class="notice notice-info is-dismissible" style="display:none"></div>
        <div class="nmkr-info-box">
            <span class="dashicons dashicons-database-import"></span>
            <p>Synchronize and update your NMKR projects, tokens, and token details to maintain current data in the dashboard.</p>
        </div>

        <div class="sync-controls panel-section">
            <div class="sync-buttons">
                <?php if ( $can_manage_sync ) : ?>
                <button id="nmkr-sync-button" class="button button-primary" disabled aria-label="Start Data Synchronization">
                    Start Synchronization
                </button>
                <button id="nmkr-stop-sync-button" class="button button-danger" style="display:none;" aria-label="Stop Data Synchronization">
                    Stop Synchronization
                </button>
                <?php endif; ?>
            </div>

            <input type="hidden" id="nmkr-sync-nonce" value="<?php echo esc_attr( wp_create_nonce( 'nmkr_sync_nonce' ) ); ?>">
            <input type="hidden" id="nmkr-dashboard-nonce" value="<?php echo esc_attr( $dashboard_nonce ); ?>">

            <div id="nmkr-sync-progress-container" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" style="display: none;">
                <div id="nmkr-sync-progress-bar" style="width: 0%;">0%</div>
            </div>

            <div id="status-message" aria-live="polite" class="sync-status-message">
                <span id="nmkr-sync-phase-label" class="sync-phase-label"></span>
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
    $latest_run = is_array($initial_stats['latest_run'] ?? null) ? $initial_stats['latest_run'] : array();
    $display_counter = function ($key) use ($latest_run) {
        return array_key_exists($key, $latest_run) && $latest_run[$key] !== null ? (string) $latest_run[$key] : '—';
    };
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

            <div id="latest-run-result" class="performance-metrics">
                <p><strong>Latest run result:</strong> <span id="latest-run-terminal-result"><?php echo esc_html($latest_run['terminal_result'] ?? '—'); ?></span></p>
                <p><strong>Lifecycle status:</strong> <span id="latest-run-status"><?php echo esc_html($latest_run['status'] ?? '—'); ?></span></p>
                <p><strong>Processed:</strong> <span id="latest-run-processed"><?php echo esc_html($display_counter('items_processed')); ?></span></p>
                <p><strong>Successful:</strong> <span id="latest-run-successful"><?php echo esc_html($display_counter('items_successful')); ?></span></p>
                <p><strong>Failed:</strong> <span id="latest-run-failed"><?php echo esc_html($display_counter('items_failed')); ?></span></p>
                <p><strong>Skipped:</strong> <span id="latest-run-skipped"><?php echo esc_html($display_counter('items_skipped')); ?></span></p>
                <p><strong>Token details synced:</strong> <span id="latest-run-token-details"><?php echo esc_html($display_counter('token_details_synced')); ?></span></p>
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
                    <small>Response Time: &lt;0.5s (Excellent), &lt;1s (Good), &lt;2s (Warning), &gt;2s (Critical)</small><br>
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
function nmkr_render_debug_logs_panel($can_manage_sync) {
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

         <?php if ( $can_manage_sync ) : ?>
         <!-- Clear All Logs Button -->
         <div class="clear-logs-controls">
             <?php
             $sync_in_progress = get_option('nmkr_sync_in_progress', false);
             $disabled_class = $sync_in_progress ? 'disabled' : '';
             $tooltip_text = $sync_in_progress ? 'Logs cannot be cleared while sync is in progress.' : 'Clear all debug logs (sync, API, UI, and performance logs)';
             ?>
             <button type="button"
                     class="clear-all-logs-btn <?php echo esc_attr($disabled_class); ?>"
                     id="nmkr-clear-all-logs"
                     <?php disabled( $sync_in_progress, true ); ?>
                     title="<?php echo esc_attr($tooltip_text); ?>"
                     data-nonce="<?php echo esc_attr( wp_create_nonce( 'nmkr_clear_logs_nonce' ) ); ?>">
                 🧹 Clear All Logs
             </button>
         </div>
         <?php endif; ?>

         <div class="panel-section">
            <!-- Sync Logs Section -->
            <div class="log-section">
                                 <details>
                     <summary>
                         <span class="log-section-title">▶ Sync Logs</span>
                         <span class="log-entry-count"><?php echo count($sync_logs); ?> entries</span>
                         <?php
                         $sync_in_progress = get_option('nmkr_sync_in_progress', false);
                         $disabled_class = $sync_in_progress ? 'disabled' : '';
                         $tooltip_text = $sync_in_progress ? 'Logs cannot be cleared while sync is in progress.' : 'Clear sync logs';
                         ?>
                         <?php if ( $can_manage_sync ) : ?>
                         <button type="button"
                                 class="clear-section-logs-btn <?php echo esc_attr($disabled_class); ?>"
                                 data-log-type="sync"
                                 <?php disabled( $sync_in_progress, true ); ?>
                                 title="<?php echo esc_attr($tooltip_text); ?>"
                                 data-nonce="<?php echo esc_attr( wp_create_nonce( 'nmkr_clear_logs_nonce' ) ); ?>">
                             🧹 Clear Logs
                         </button>
                         <?php endif; ?>
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
                                         <div class="log-data"><?php echo esc_html(nmkr_format_log_value($log['data'])); ?></div>
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
                         $disabled_class = $sync_in_progress ? 'disabled' : '';
                         $tooltip_text = $sync_in_progress ? 'Logs cannot be cleared while sync is in progress.' : 'Clear API logs';
                         ?>
                         <?php if ( $can_manage_sync ) : ?>
                         <button type="button"
                                 class="clear-section-logs-btn <?php echo esc_attr($disabled_class); ?>"
                                 data-log-type="api"
                                 <?php disabled( $sync_in_progress, true ); ?>
                                 title="<?php echo esc_attr($tooltip_text); ?>"
                                 data-nonce="<?php echo esc_attr( wp_create_nonce( 'nmkr_clear_logs_nonce' ) ); ?>">
                             🧹 Clear Logs
                         </button>
                         <?php endif; ?>
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
                                         <div class="log-data"><?php echo esc_html(nmkr_format_log_value($log['data'])); ?></div>
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
                         $disabled_class = $sync_in_progress ? 'disabled' : '';
                         $tooltip_text = $sync_in_progress ? 'Logs cannot be cleared while sync is in progress.' : 'Clear UI logs';
                         ?>
                         <?php if ( $can_manage_sync ) : ?>
                         <button type="button"
                                 class="clear-section-logs-btn <?php echo esc_attr($disabled_class); ?>"
                                 data-log-type="ui"
                                 <?php disabled( $sync_in_progress, true ); ?>
                                 title="<?php echo esc_attr($tooltip_text); ?>"
                                 data-nonce="<?php echo esc_attr( wp_create_nonce( 'nmkr_clear_logs_nonce' ) ); ?>">
                             🧹 Clear Logs
                         </button>
                         <?php endif; ?>
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
                                         <div class="log-data"><?php echo esc_html(nmkr_format_log_value($log['data'])); ?></div>
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
                         $disabled_class = $sync_in_progress ? 'disabled' : '';
                         $tooltip_text = $sync_in_progress ? 'Logs cannot be cleared while sync is in progress.' : 'Clear performance logs';
                         ?>
                         <?php if ( $can_manage_sync ) : ?>
                         <button type="button"
                                 class="clear-section-logs-btn <?php echo esc_attr($disabled_class); ?>"
                                 data-log-type="performance"
                                 <?php disabled( $sync_in_progress, true ); ?>
                                 title="<?php echo esc_attr($tooltip_text); ?>"
                                 data-nonce="<?php echo esc_attr( wp_create_nonce( 'nmkr_clear_logs_nonce' ) ); ?>">
                             🧹 Clear Logs
                         </button>
                         <?php endif; ?>
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

                                            echo esc_html(nmkr_format_log_value($perf_data));
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
function nmkr_render_dashboard_scripts($dashboard_nonce, $can_manage_sync) {
    nmkr_connect_enqueue_script_asset(
        'nmkr-dashboard',
        'js/admin/nmkr-dashboard.js',
        array( 'jquery', 'nmkr-sync-progress' ),
        true
    );
    wp_add_inline_script(
        'nmkr-dashboard',
        'window.nmkrDashboardConfig = ' . wp_json_encode(
            array( 'canManageSync' => (bool) $can_manage_sync )
        ) . ';',
        'before'
    );
}
