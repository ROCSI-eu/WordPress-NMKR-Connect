jQuery(document).ready(function($) {
    // Cache nonce values ONCE to avoid repeated DOM queries
    const syncNonce = $('#nmkr-sync-nonce').val();
    const dashboardNonce = $('#nmkr-dashboard-nonce').val();
    const canManageSync = Boolean(window.nmkrDashboardConfig && window.nmkrDashboardConfig.canManageSync);

    // Keep observation polling available while suppressing manager-only telemetry writes.
    $.ajaxPrefilter(function(options, originalOptions, jqXHR) {
        const requestData = originalOptions.data || options.data;
        const action = typeof requestData === 'string'
            ? new URLSearchParams(requestData).get('action')
            : requestData && requestData.action;

        if (!canManageSync && action === 'nmkr_store_active_metrics') {
            jqXHR.abort();
        }
    });

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
            nonce: dashboardNonce,
            _: Date.now()
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
            console.error('API status check failed (HTTP ' + (xhr.status || 0) + ').');
            $('#api-status').html('⚠️ Unable to check API status. Please try refreshing the page.');

            // Log API status check error
            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'nmkr_store_active_metrics',
                    nonce: dashboardNonce,
                    metrics: { ui_log: "UI: Displaying 'Unable to check API status' error message to user" },
                    log_type: 'error',
                    _: Date.now()
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
                log_type: 'debug',
                _: Date.now()
            }
        });

        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'nmkr_check_api_status',
                nonce: dashboardNonce,
                _: Date.now()
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
                            log_type: 'debug',
                            _: Date.now()
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
                            log_type: 'debug',
                            _: Date.now()
                        }
                    });
                }
            },
            error: function(xhr) {
                console.error('API status refresh failed (HTTP ' + (xhr.status || 0) + ').');
                $('#api-status').html('⚠️ Unable to check API status. Please try refreshing the page.');

                // Log API status refresh error
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'nmkr_store_active_metrics',
                        nonce: dashboardNonce,
                        metrics: { ui_log: "UI: Displaying 'Unable to check API status' error message after refresh attempt" },
                        log_type: 'error',
                        _: Date.now()
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
                log_type: 'debug',
                _: Date.now()
            }
        });

        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'nmkr_get_sync_statistics',
                request_type: 'completed',
                force_refresh: true,
                nonce: dashboardNonce,
                _: Date.now()
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
                    const latestRun = response.data.latest_run || {};
                    const latestValue = value => value === null || typeof value === 'undefined' ? '—' : String(value);
                    $('#latest-run-terminal-result').text(latestValue(latestRun.terminal_result));
                    $('#latest-run-status').text(latestValue(latestRun.status));
                    $('#latest-run-processed').text(latestValue(latestRun.items_processed));
                    $('#latest-run-successful').text(latestValue(latestRun.items_successful));
                    $('#latest-run-failed').text(latestValue(latestRun.items_failed));
                    $('#latest-run-skipped').text(latestValue(latestRun.items_skipped));
                    $('#latest-run-token-details').text(latestValue(latestRun.token_details_synced));

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
                            log_type: 'debug',
                            _: Date.now()
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
                            log_type: 'warning',
                            _: Date.now()
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
                        log_type: 'error',
                        _: Date.now()
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
                nonce: dashboardNonce,
                _: Date.now()
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
                    log_type: 'debug',
                    _: Date.now()
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
            nonce: dashboardNonce,
            _: Date.now()
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
                        log_type: 'info',
                        _: Date.now()
                    }
                });

                // Show active sync metrics container
                $('#active-sync-metrics').show();

                // Kick off polling if available
                if (window.NMKRProgress && typeof window.NMKRProgress.startPolling === 'function') {
                    window.NMKRProgress.startPolling();
                }
            }

            // Show recovered note if server indicates recent recovery
            try {
                if (response && response.data && response.data.last_result === 'recovered_stale' && response.data.last_recovery_at > 0) {
                    const t = new Date(response.data.last_recovery_at * 1000);
                    const note = document.getElementById('nmkr-recovered-note');
                    if (note) {
                        note.textContent = 'Recovered from stale sync at ' + t.toLocaleString();
                        note.style.display = 'block';
                    }
                }
            } catch (e) {}
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
                log_type: 'info',
                _: Date.now()
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
                nonce: syncNonce,
                _: Date.now()
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
                            log_type: 'error',
                            _: Date.now()
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
                        log_type: 'error',
                        _: Date.now()
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
                nonce: button.data('nonce'),
                _: Date.now()
            },
            success: function(response) {
                if (response.success) {
                    // Show success message
                    showLogMessage('All logs cleared successfully', 'success');
                    // Refresh visible log sections without reloading the dashboard page.
                    ['sync', 'api', 'ui', 'performance'].forEach(updateLogSection);
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
                log_type: logType,
                _: Date.now()
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
