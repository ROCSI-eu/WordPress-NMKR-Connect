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
 * Enqueue the dashboard CSS styles
 */
function nmkr_render_dashboard_styles() {
    nmkr_connect_enqueue_style_asset( 'nmkr-dashboard', 'css/admin/nmkr-dashboard.css' );
}

/**
 * Render the API status panel
 */
function nmkr_render_api_status_panel() {
    ?>
    <section class="panel api-status-panel nmkr-dashboard-status-card" aria-labelledby="nmkr-api-status-title">
        <div class="nmkr-dashboard-card-heading">
            <div>
                <p class="nmkr-dashboard-card-kicker">Connection</p>
                <h2 id="nmkr-api-status-title">NMKR API</h2>
            </div>
            <span class="dashicons dashicons-admin-links" aria-hidden="true"></span>
        </div>

        <p class="nmkr-dashboard-card-help">Confirm that the plugin can reach NMKR before starting a synchronization.</p>

        <div id="api-status" class="api-status" role="status" aria-live="polite" aria-atomic="true">
            <span class="status-indicator checking">Checking connection...</span>
        </div>

        <div class="api-actions">
            <button id="refresh-api-status" class="button button-secondary">Refresh Status</button>
        </div>
    </section>
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
    <section class="panel sync-data nmkr-dashboard-status-card nmkr-dashboard-sync-card" role="region" aria-labelledby="nmkr-sync-title">
        <div class="nmkr-dashboard-card-heading">
            <div>
                <p class="nmkr-dashboard-card-kicker">Synchronization</p>
                <h2 id="nmkr-sync-title">Data Sync</h2>
            </div>
            <span class="dashicons dashicons-update" aria-hidden="true"></span>
        </div>

        <p class="nmkr-dashboard-card-help">Update locally stored projects, tokens, and token details from NMKR.</p>

        <div id="nmkr-recovered-note" class="notice notice-info is-dismissible" style="display:none"></div>

        <div class="sync-controls">
            <div class="sync-buttons">
                <?php if ( $can_manage_sync ) : ?>
                <button id="nmkr-sync-button" class="button button-primary" disabled aria-label="Start Data Synchronization">
                    Start Synchronization
                </button>
                <button id="nmkr-stop-sync-button" class="button button-danger" style="display:none;" aria-label="Stop Data Synchronization">
                    Stop Synchronization
                </button>
                <?php else : ?>
                <p class="nmkr-dashboard-view-only-note"><span class="dashicons dashicons-lock" aria-hidden="true"></span> Synchronization controls are read-only for your account.</p>
                <?php endif; ?>
            </div>

            <input type="hidden" id="nmkr-sync-nonce" value="<?php echo esc_attr( wp_create_nonce( 'nmkr_sync_nonce' ) ); ?>">
            <input type="hidden" id="nmkr-dashboard-nonce" value="<?php echo esc_attr( $dashboard_nonce ); ?>">

            <div id="nmkr-sync-progress-container" role="progressbar" aria-label="Synchronization progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" style="display: none;">
                <div id="nmkr-sync-progress-bar" style="width: 0%;">0%</div>
            </div>

            <div id="status-message" role="status" aria-live="polite" aria-atomic="true" class="sync-status-message">
                <span id="nmkr-sync-phase-label" class="sync-phase-label">Ready to synchronize.</span>
            </div>

            <div id="active-sync-metrics" class="nmkr-active-sync-metrics" style="display: none;">
                <div class="nmkr-active-sync-heading">
                    <h3>Active synchronization</h3>
                    <p>Live progress for the current run.</p>
                </div>

                <div class="active-metrics-container nmkr-active-metrics-summary">
                    <div class="active-metric">
                        <span class="label">Projects synced</span>
                        <span class="value total-projects-active">0</span>
                    </div>
                    <div class="active-metric">
                        <span class="label">Tokens synced</span>
                        <span class="value total-tokens-active">0</span>
                    </div>
                    <div class="active-metric">
                        <span class="label">Sync duration</span>
                        <span class="value total-sync-duration-active">0s</span>
                    </div>
                </div>

                <details class="nmkr-active-performance">
                    <summary>Live performance details</summary>
                    <div class="active-metrics-container">
                        <div class="active-metric">
                            <span class="label">Total API time</span>
                            <span class="value total-api-time-active">0.00s</span>
                        </div>
                        <div class="active-metric">
                            <span class="label">Response time</span>
                            <span class="value avg-response-time status-excellent">0.00ms</span>
                        </div>
                        <div class="active-metric">
                            <span class="label">API requests</span>
                            <span class="value api-requests">0</span>
                        </div>
                        <div class="active-metric">
                            <span class="label">Memory</span>
                            <span class="value memory-usage status-excellent">0.00MB</span>
                        </div>
                    </div>
                </details>
            </div>
        </div>
    </section>
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
    $has_sync_history = 'No synchronization done yet' !== $initial_stats['last_sync_time'];
    ?>
    <section class="panel sync-statistics nmkr-dashboard-summary-panel" role="region" aria-labelledby="nmkr-sync-summary-title">
        <div class="nmkr-dashboard-section-heading">
            <div>
                <p class="nmkr-dashboard-card-kicker">Latest synchronized state</p>
                <h2 id="nmkr-sync-summary-title">Synchronization Summary</h2>
            </div>
            <p><?php echo esc_html( $has_sync_history ? 'Review the latest run outcome and synchronized totals.' : 'No completed synchronization has been recorded yet.' ); ?></p>
        </div>

        <div class="nmkr-summary-grid">
            <div class="nmkr-summary-card nmkr-summary-card-wide">
                <span class="nmkr-summary-label">Last synchronized</span>
                <strong id="last-synced"><?php echo esc_html($initial_stats['last_sync_time']); ?></strong>
            </div>
            <div class="nmkr-summary-card">
                <span class="nmkr-summary-label">Latest result</span>
                <strong id="latest-run-terminal-result"><?php echo esc_html($latest_run['terminal_result'] ?? '—'); ?></strong>
            </div>
            <div class="nmkr-summary-card">
                <span class="nmkr-summary-label">Lifecycle</span>
                <strong id="latest-run-status"><?php echo esc_html($latest_run['status'] ?? '—'); ?></strong>
            </div>
            <div class="nmkr-summary-card">
                <span class="nmkr-summary-label">Projects</span>
                <strong id="total-projects"><?php echo esc_html($initial_stats['total_projects']); ?></strong>
            </div>
            <div class="nmkr-summary-card">
                <span class="nmkr-summary-label">Tokens</span>
                <strong id="total-tokens"><?php echo esc_html($initial_stats['total_tokens']); ?></strong>
            </div>
        </div>

        <details id="latest-run-result" class="nmkr-dashboard-details">
            <summary>Latest run details</summary>
            <div class="nmkr-detail-grid">
                <div><span>Processed</span><strong id="latest-run-processed"><?php echo esc_html($display_counter('items_processed')); ?></strong></div>
                <div><span>Successful</span><strong id="latest-run-successful"><?php echo esc_html($display_counter('items_successful')); ?></strong></div>
                <div><span>Failed</span><strong id="latest-run-failed"><?php echo esc_html($display_counter('items_failed')); ?></strong></div>
                <div><span>Skipped</span><strong id="latest-run-skipped"><?php echo esc_html($display_counter('items_skipped')); ?></strong></div>
                <div><span>Token details synced</span><strong id="latest-run-token-details"><?php echo esc_html($display_counter('token_details_synced')); ?></strong></div>
            </div>
        </details>

        <details id="nmkr-performance-details" class="nmkr-dashboard-details nmkr-performance-details">
            <summary>Performance diagnostics</summary>
            <div id="performance-stats" class="nmkr-detail-grid nmkr-performance-grid">
                <div class="total-time"><span>Total sync duration</span><strong id="total-sync-time"><?php echo esc_html($initial_stats['total_sync_duration']); ?></strong></div>
                <div class="api-time"><span>Total API time</span><strong id="total-api-time"><?php echo esc_html($initial_stats['total_api_time']); ?></strong></div>
                <div class="avg-time"><span>Average response time</span><strong id="avg-response-time" class="<?php echo esc_attr($initial_stats['response_time_class']); ?>"><?php echo esc_html($initial_stats['average_response_time']); ?></strong></div>
                <div class="request-count"><span>API requests</span><strong id="request-count"><?php echo esc_html($initial_stats['api_requests']); ?></strong></div>
                <div class="memory-used"><span>Memory usage</span><strong id="memory-usage" class="<?php echo esc_attr($initial_stats['memory_class']); ?>"><?php echo esc_html($initial_stats['memory_usage']); ?></strong></div>
            </div>

            <div id="performance-legend" class="performance-legend">
                <p class="legend-title">Performance thresholds</p>
                <div class="legend-item" aria-label="Performance status thresholds">
                    <span><span class="legend-dot status-excellent" aria-hidden="true"></span> Excellent</span>
                    <span><span class="legend-dot status-good" aria-hidden="true"></span> Good</span>
                    <span><span class="legend-dot status-warning" aria-hidden="true"></span> Warning</span>
                    <span><span class="legend-dot status-critical" aria-hidden="true"></span> Critical</span>
                </div>
                <p class="legend-desc">
                    <small>Response time: &lt;0.5s Excellent, &lt;1s Good, &lt;2s Warning, ≥2s Critical.</small><br>
                    <small>Memory usage: &lt;50MB Excellent, &lt;100MB Good, &lt;200MB Warning, ≥200MB Critical.</small>
                </p>
            </div>
        </details>
    </section>
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
    <details class="nmkr-dashboard-diagnostics">
        <summary>
            <span>
                <strong>Diagnostics</strong>
                <small>Debug logs and troubleshooting details</small>
            </span>
            <span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
        </summary>
        <div class="panel debug-logs-panel" role="region" aria-label="Debug Logs">
            <div class="nmkr-dashboard-section-heading">
                <div>
                    <p class="nmkr-dashboard-card-kicker">Troubleshooting</p>
                    <h2>Debug Logs</h2>
                </div>
                <p>Showing up to <?php echo esc_html($retention_limit); ?> retained entries per log stream.</p>
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
    </details>
    <?php
}

/**
 * Enqueue the dashboard JavaScript
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
