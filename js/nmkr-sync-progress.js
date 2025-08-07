'use strict';

jQuery(document).ready(function($) {
    // Consolidated polling state variables
    let pollTimeoutId;
    let isFetching = false;
    let hasError = false;
    let startXhr, pollXhr, stopXhr;
    let syncInProgress = false;
    
    // Get settings from WordPress (unified object)
    const options = nmkrSyncProgress.options || {};
    
    // Initialize from Synchronization Profile settings
    const unifiedPollingInterval = options.sync_initial_interval || 2000;    // e.g. 1000 ms
    let currentPollingInterval = unifiedPollingInterval;
    const minPollingInterval = options.sync_initial_interval || 2000;        // lower bound
    const maxPollingInterval = options.sync_max_interval || 2000;            // upper bound
    const increaseFactor = options.sync_interval_increase || 1.0;           // e.g. 2.0×
    const decreaseFactor = options.sync_interval_decrease || 1.0;           // e.g. 0.5×
    let lastStepsCompleted = 0;
    
    // Get sync profile settings
    const batchSize = options.batch_size || 10;
    
    const progressBar = $('#nmkr-sync-progress-bar');
    const progressContainer = $('#nmkr-sync-progress-container');
    const syncButton = $('#nmkr-sync-button');
    const stopSyncButton = $('#nmkr-stop-sync-button');
    const statusMessage = $('#status-message');
    const lastSynced = $('#last-synced');
    const totalSyncTime = $('#total-sync-time');
    const totalApiTime = $('#total-api-time');
    const requestCount = $('#request-count');
    const avgResponseTime = $('#avg-response-time');
    const memoryUsage = $('#memory-usage');
    const totalProjects = $('#total-projects');
    const totalTokens = $('#total-tokens');
    const activeSyncMetrics = $('#active-sync-metrics');
    const syncDataPanel = $('.sync-data.panel');
    const syncNonce = $('#nmkr-sync-nonce').val();

    // Function to update button state based on sync status
    function updateButtonState() {
        if (syncInProgress) {
            syncButton.hide();
            stopSyncButton.show();
        } else {
            syncButton.show();
            stopSyncButton.hide();
        }
    }

    // Unified error handling function for sync operations
    function handleError(reason) {
        // Set error state flags
        hasError = true;
        isFetching = false;
        syncInProgress = false;
        
        // Update button state on error
        updateButtonState();
        
        // Update error message display
        const errorMessage = reason || 'Unknown error occurred';
        $('#status-message').text('❌ Synchronization error: ' + errorMessage);
        $('#nmkr-sync-error').text('Sync failed: ' + errorMessage).show();
        
        // Ensure sync button is enabled
        syncButton.prop('disabled', false);
        
        // Perform UI teardown (includes stopping polling)
        teardownSyncUI();
    }

    // DRY cleanup helper to avoid code duplication
    function teardownSyncUI() {
        // Reset sync state flags immediately
        syncInProgress = false;
        
        // Abort any in-flight XHR requests before stopping polling
        if (pollXhr && pollXhr.readyState !== 4) {
            pollXhr.abort();
            pollXhr = null;
        }
        
        // Abort start sync request if still in progress
        if (startXhr && startXhr.readyState !== 4) {
            startXhr.abort();
            startXhr = null;
        }
        
        // Abort stop sync request if still in progress
        if (stopXhr && stopXhr.readyState !== 4) {
            stopXhr.abort();
            stopXhr = null;
        }
        
        stopPolling();
        $('#nmkr-sync-progress-container, #active-sync-metrics').hide();
        $('#nmkr-stop-sync-button').hide();
        $('#nmkr-sync-button').show().prop('disabled', false);
    }

    // Handle sync button click - moved from dashboard UI
    syncButton.off('click').on('click', function() {
        // Reset error state immediately when starting fresh sync
        hasError = false;
        
        // Prevent double-clicks by immediately disabling the button
        syncButton.prop('disabled', true);
        
        // Immediately queue background sync with explicit timeout
        startXhr = $.ajax({
            url: nmkrSyncProgress.ajax_url,
            method: 'POST',
            data: {
                action: 'nmkr_start_sync',
                nonce: nmkrSyncProgress.nonce
            },
            timeout: 60000
        })
        .done(function(response) {
            if (response.success) {
                // Begin polling live metrics
                startSyncPolling();
            } else {
                handleError(response.data?.message || 'unknown error');
            }
        })
        .fail(function(xhr, status) {
            if (status === 'abort') return;
            handleError(status || 'network error');
        });
    });

    // Handle stop sync button click
    stopSyncButton.off('click').on('click', function() {
        stopSyncButton.prop('disabled', true);
        stopXhr = $.post(nmkrSyncProgress.ajax_url, {
            action: 'nmkr_stop_sync',
            nonce: nmkrSyncProgress.nonce
        })
        .fail(function(xhr, status) {
            if (status === 'abort') return;
            // Even if stop fails, we should still teardown the UI
            console.warn('Stop sync request failed:', status);
        })
        .always(() => {
            teardownSyncUI();
        });
        $('#status-message').text('⏹️ Stopping Synchronization…');
    });

    function fetchProgress() {
      if (isFetching || hasError) return;
      isFetching = true;
      pollXhr = $.ajax({
        url: nmkrSyncProgress.ajax_url,
        method: 'POST',
        dataType: 'json',
        timeout: 60000,
        data: {
          action: 'nmkr_sync_progress',
          nonce: nmkrSyncProgress.nonce
        }
      })
      .done(response => {
        try {
          if (response.success && response.data) {
            const { progress, current_item, in_progress, error, live_metrics } = response.data;
            
            // Guard against undefined progress - use 0 if not a valid number
            const validProgress = (typeof progress === 'number' && !isNaN(progress)) ? progress : 0;
            
            // Update progress bar using helper function
            updateProgressBar(validProgress);
            
            // Update current item status  
            if (current_item) {
              $('#status-message').html('<div class="status-header">' + current_item + '</div>');
            }
            
            // Handle error state
            if (error && error.trim() !== '') {
              handleError(error);
              return;
            }
            
            // Update live metrics if available
            if (live_metrics) {
              updateActiveMetrics(live_metrics);
            }
            
            // Only treat 100% as final complete when the backend is no longer in_progress
            if (validProgress === 100 && !in_progress) {
              stopPolling();
              handleComplete();
              return;
            }
            
            // Adaptive polling interval logic based on progress changes
            if (validProgress > lastStepsCompleted) {
              currentPollingInterval = Math.max(
                currentPollingInterval * decreaseFactor,
                minPollingInterval
              );
              lastStepsCompleted = validProgress;
            } else {
              currentPollingInterval = Math.min(
                currentPollingInterval * increaseFactor,
                maxPollingInterval
              );
            }
            
            // Schedule next poll only on success with proper bounds
            const nextInterval = Math.min(
              Math.max(currentPollingInterval, minPollingInterval),
              maxPollingInterval
            );
            pollTimeoutId = setTimeout(fetchProgress, nextInterval);
          } else {
            // Handle unsuccessful response
            handleError('Invalid response from server');
            return;
          }
        } catch (e) {
          handleError('Invalid JSON');
          return;
        }
      })
      .fail((jqXHR, status) => {
        if (status === 'abort') {
          // intentional cancel — do nothing
          return;
        }
        if (status === 'timeout' && pollXhr) {
          pollXhr.abort();
        }
        handleError(status);
      })
      .always(() => {
        isFetching = false;
      });
    }

    function startSyncPolling() {
      // Reset error state when starting fresh sync
      hasError = false;
      syncInProgress = true;
      
      // Update button state immediately
      updateButtonState();
      
      // Reset adaptive polling state
      lastStepsCompleted = 0;
      currentPollingInterval = minPollingInterval;
      stopPolling();
      
      // Hide start button and show stop button immediately
      syncButton.hide();
      stopSyncButton.show();
      
      // Set initial status message
      statusMessage.text('⏳ Initializing synchronization...');
      
      // Show progress container and metrics
      progressContainer.show();
      activeSyncMetrics.show();
      
      // Reset metrics to zero before starting
      prerenderActiveMetrics();
      
      // Note: Live metrics are now embedded in progress response via updateActiveMetrics()
      // No need for separate refreshActiveMetrics interval
      
      fetchProgress();
    }


    function handleComplete() {
      syncInProgress = false;
      
      // Update button state on completion
      updateButtonState();
      
      $('#nmkr-sync-complete').show();
      syncButton.prop('disabled', false);
    }

    if (nmkrSyncProgress.resume) {
      $('#nmkr-sync-phase-label').text('Synchronization in progress…');
      startSyncPolling();
    }
    
    // Ensure timeout is cleared when page is unloaded
    $(window).on('beforeunload', () => {
        if (pollXhr && pollXhr.readyState !== 4) pollXhr.abort();
        if (startXhr && startXhr.readyState !== 4) startXhr.abort();
        if (stopXhr && stopXhr.readyState !== 4) stopXhr.abort();
        stopPolling();
    });



    // Helper function to stop polling
    function stopPolling() {
        if (pollTimeoutId) {
            clearTimeout(pollTimeoutId);
            pollTimeoutId = null;
        }
        isFetching = false;
    }







    // HTML escaping function to prevent XSS
    const escapeHtml = (str) => {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    };

    // Utility function to safely handle numeric values
    const safeNumber = (value, defaultValue = 0) => {
        const num = Number(value);
        return Number.isFinite(num) ? num : defaultValue;
    };

    // Utility function to update color classes
    const updateColorClass = ($element, newClass) => {
        const colorClasses = ['status-excellent', 'status-good', 'status-warning', 'status-critical'];
        $element.removeClass(colorClasses.join(' '));
        if (newClass) {
            $element.addClass(newClass);
        }
    };

    // Hide progress container initially
    progressContainer.hide();
    
    // Add compact class to the sync data panel when not syncing
    syncDataPanel.addClass('compact');

    // Add styles for status messages and error handling
    const style = document.createElement('style');
    style.textContent = `
        .status-message {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .status-error {
            color: #dc3232;
        }
        
        .status-success {
            color: #46b450;
        }
        
        .status-info {
            color: #0073aa;
        }
        
<<<<<<< HEAD
        .status-warning {
            color: #ffb900;
=======
        .status-neutral {
            color: #666;
>>>>>>> development
        }
        
        .status-excellent {
            color: #46b450;
            font-weight: 600;
        }
        
        .status-good {
            color: #ffb900;
            font-weight: 600;
        }
        
        .status-warning {
            color: #f56e28;
            font-weight: 600;
        }
        
        .status-critical {
            color: #dc3232;
            font-weight: 600;
        }
        
        .status-details {
            margin: 8px 0;
            color: #666;
            font-size: 0.9em;
        }
        
        .error-suggestion {
            color: #666;
            font-style: italic;
        }
        
        .error-context {
            color: #666;
            font-family: monospace;
        }
        
        .performance-info {
            margin-top: 8px;
            color: #666;
            font-size: 0.9em;
        }
        
        #batch-details {
            margin-top: 16px;
            padding: 12px;
            background: #f0f0f1;
            border-radius: 4px;
            font-size: 0.9em;
        }
        
        #batch-details h4 {
            margin: 0 0 8px 0;
            color: #1d2327;
        }
        
        #batch-details ul {
            margin: 0;
            padding-left: 20px;
        }
        
        #batch-details li {
            margin: 4px 0;
        }
        
        #status-message {
            margin-bottom: 16px;
        }
        
        @keyframes progress-bar-stripes {
            from { background-position: 1rem 0; }
            to { background-position: 0 0; }
        }
        
        /* Loading dots animation for early stages */
        .loading-dots {
            animation: loading-dots 1.5s infinite;
            opacity: 0.7;
        }
        
        @keyframes loading-dots {
            0%, 20% { opacity: 0.3; }
            50% { opacity: 1; }
            100% { opacity: 0.3; }
        }
        
        /* Enhanced stage type styling */
        .status-header.status-info {
            color: #0073aa;
        }
        
        .status-header.status-success {
            color: #46b450;
        }
        
        .status-header.status-error {
            color: #dc3232;
        }
        
        .status-header.status-warning {
            color: #ffb900;
        }
        
        .status-header.status-stopping {
            color: #f56e28;
            font-weight: 600;
        }
        
        /* Last update timestamp styling */
        .last-update-time {
            font-size: 0.85em;
            color: #666;
            margin-top: 4px;
            font-style: italic;
        }
        
        .progress-details {
            margin: 12px 0;
            padding: 8px 12px;
            background: #fbfbfb;
            border-radius: 4px;
            font-size: 0.95em;
            line-height: 1.5;
            color: #333;
            border: none;
        }
        
        .progress-stage {
            font-weight: 500;
            color: #1d2327;
            margin-bottom: 4px;
        }
        
        .progress-current {
            color: #50575e;
            margin-bottom: 4px;
        }
        
        .progress-counts {
            margin: 12px 0;
            padding: 8px;
            background: #f8f9fa;
            border-radius: 4px;
            font-size: 0.9em;
        }
        
        .projects-progress,
        .tokens-progress {
            margin-bottom: 4px;
            color: #50575e;
        }
        
        .projects-progress {
            font-weight: 500;
            color: #1d2327;
        }
        
        .tokens-progress {
            color: #50575e;
        }
        
        .batch-details {
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid #e2e4e7;
        }
        
        .batch-progress {
            font-weight: 500;
            color: #1d2327;
            margin-bottom: 4px;
        }
        
        .remaining-projects,
        .remaining-tokens,
        .current-project,
        .current-token {
            color: #50575e;
            margin-bottom: 4px;
        }
        
        .current-project,
        .current-token {
            font-style: italic;
        }
        
        /* Panel size adjustments */
        .sync-data.panel.compact {
            padding-top: 20px;
            padding-bottom: 20px;
            transition: padding 0.3s ease;
        }
        
        .sync-data.panel {
            padding-top: 30px;
            padding-bottom: 30px;
            transition: padding 0.3s ease;
        }
        
        /* Enhanced progress display styles */
        .stage-counts {
            font-size: 0.9em;
            margin-top: 4px;
            color: #333;
            font-weight: 500;
            padding: 4px 0;
        }
        
        .overall-progress {
            background-color: #f0f6fc;
            padding: 8px 12px;
            border-radius: 4px;
            margin-top: 8px;
            border-left: 4px solid #0073aa;
            font-size: 0.95em;
            display: inline-block;
            margin-bottom: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        
        .count-info {
            color: #666;
            font-size: 0.9em;
        }
        
        #status-message strong {
            color: #1d2327;
        }
    `;
    document.head.appendChild(style);

    // Function to format duration
    const formatDuration = (duration, showUnit = true) => {
        if (duration === undefined || duration === null) return '-';
        return showUnit ? `${safeNumber(duration).toFixed(2)}s` : safeNumber(duration).toFixed(2);
    };

    // Function to get response time color class
    const getResponseTimeColorClass = (time) => {
        if (time === undefined || time === null) return 'status-neutral';
        if (time < 0.5) return 'status-excellent';
        if (time < 1) return 'status-good';
        if (time < 2) return 'status-warning';
        return 'status-critical';
    };

    // Function to format average response time
    const formatAverageResponseTime = (time) => {
        if (time === undefined || time === null) return '-';
        const seconds = safeNumber(time);
        return seconds < 1 ? 
            `${(seconds * 1000).toFixed(2)}ms` : 
            `${seconds.toFixed(2)}s`;
    };

    // Function to get color class based on memory usage (in MB)
    const getMemoryColorClass = (mb) => {
        const memory = safeNumber(mb);
        if (memory < 50) {
            return 'status-excellent'; // Green
        } else if (memory < 100) {
            return 'status-good'; // Yellow
        } else if (memory < 200) {
            return 'status-warning'; // Orange
        } else {
            return 'status-critical'; // Red
        }
    };

    // Function to format memory size
    const formatMemory = (mb) => {
        const memory = safeNumber(mb);
        if (memory < 1) return (memory * 1024).toFixed(2) + 'KB';
        return memory.toFixed(2) + 'MB';
    };

    // Function to format duration with minutes and seconds for longer times
    const formatLongDuration = (seconds) => {
        const time = safeNumber(seconds);
        if (time < 0) return '0s';
        if (time < 60) return `${time.toFixed(1)}s`;
        const minutes = Math.floor(time / 60);
        const remainingSeconds = time % 60;
        return `${minutes}m ${remainingSeconds.toFixed(0)}s`;
    };

    // Function to format count numbers
    const formatCount = (count) => {
        const num = safeNumber(count);
        return num ? num.toLocaleString() : '0';
    };

    // Helper function to prerender all 7 active sync metrics with professional zero values
    const prerenderActiveMetrics = () => {
        if (activeSyncMetrics.length > 0) {
            activeSyncMetrics.find('.total-projects-active').text('0');
            activeSyncMetrics.find('.total-tokens-active').text('0');
            activeSyncMetrics.find('.total-sync-duration-active').text('0s');
            activeSyncMetrics.find('.total-api-time-active').text('0.00s');
            activeSyncMetrics.find('.avg-response-time').text('0.00ms').removeClass().addClass('status-excellent');
            activeSyncMetrics.find('.api-requests').text('0');
            activeSyncMetrics.find('.memory-usage').text('0.00MB').removeClass().addClass('status-excellent');
            console.log('🎨 Prerendered all 7 active sync metrics with professional zero values');
        }
    };

    // Reset performance stats when starting new sync
    const resetPerformanceStats = () => {
        // Don't reset last sync time during reset
        // lastSynced.text('No synchronization done yet');
        
        // Reset metrics
        totalProjects.text('-');
        totalTokens.text('-');
        totalSyncTime.text('-');
        totalApiTime.text('-');
        avgResponseTime.text('-').removeClass().addClass('status-neutral');
        requestCount.text('-');
        memoryUsage.text('-').removeClass().addClass('status-neutral');
        
        // Hide the active sync response time
        activeSyncMetrics.hide();
    };

    // Function to update last sync time from server
    const updateLastSyncTime = (type = 'automatic', callback = null) => {
        $.ajax({
            url: nmkrSyncProgress.ajax_url,
            type: 'POST',
            data: {
                action: 'nmkr_get_sync_statistics',
                type: type,
                request_type: 'completed', // Always request completed stats, never active ones
                force_refresh: type === 'manual_stop' || type === 'sync_completed',
                nonce: nmkrSyncProgress.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    const stats = response.data;
                    
                    // Always update last sync time, as this is always from completed syncs
                    lastSynced.text(escapeHtml(stats.last_sync_time));
                    
                    // Only update the statistics panel if we're not in an active sync or if near completion
                    // This prevents active metrics from leaking into completed statistics
                    if (!syncInProgress || type === 'sync_completed' || type === 'near_completion') {
                        // Update all historical statistics in the panel - these are always from completed syncs
                        totalProjects.text(escapeHtml(stats.total_projects));
                        totalTokens.text(escapeHtml(stats.total_tokens));
                        totalSyncTime.text(escapeHtml(stats.total_sync_duration));
                        totalApiTime.text(escapeHtml(stats.total_api_time));
                        avgResponseTime.text(escapeHtml(stats.average_response_time));
                        requestCount.text(escapeHtml(stats.api_requests));
                        memoryUsage.text(escapeHtml(stats.memory_usage));
                        
                        // Update color classes for performance indicators
                        updateColorClass(avgResponseTime, getResponseTimeColorClass(safeNumber(stats.average_response_time)));
                        updateColorClass(memoryUsage, getMemoryColorClass(safeNumber(stats.memory_usage)));
                        
                        // Hide the active sync metrics when not in active sync
                        activeSyncMetrics.hide();
                    }
                    
                    // Execute callback if provided
                    if (callback && typeof callback === 'function') {
                        callback(stats);
                    }
                }
            },
            error: function(xhr, status, error) {
                // Only update on error if not in active synchronization
                if (!syncInProgress) {
                    lastSynced.text('Error retrieving synchronization data');
                }
                
                // Execute callback even on error
                if (callback && typeof callback === 'function') {
                    callback(null);
                }
            }
        });
    };

    // Function to update performance statistics
    const updatePerformanceStats = (performanceData, batchInfo = {}, isInitializing = false) => {
        // If initializing, prerender all 7 metrics with proper default values
        if (isInitializing) {
            // During initialization, show the active metrics container and prerender all values
            if (syncInProgress) {
                activeSyncMetrics.show();
                prerenderActiveMetrics();
            }
            // Do not update the historical statistics panel during initialization
            return;
        }

        // --- Robustify: Show warning if performanceData is missing or null ---
        if (!performanceData) {
            // If stats are seeded pre-poll, this should never trigger. Silently return.
            return;
        }
        // --- End Robustify ---

        // Log data for debugging
        if (window.console && window.console.debug) {
            console.debug('Performance Stats:', performanceData);
            console.debug('Batch Info:', batchInfo);
        }

        // During active sync, only update the active metrics section, never the statistics panel
        if (syncInProgress) {
            // Only update the active sync metrics display, never the statistics panel
            activeSyncMetrics.show();
            
            // Create an object to collect all metrics to store in the transient
            const metricsToStore = {
                // Progress metrics
                total_projects: 0,
                total_tokens: 0,
                total_sync_duration: 0,
                // Performance metrics
                total_api_time: 0,
                average_response_time: 0,
                api_requests: 0,
                memory_usage: 0
            };
            
            // Update progress metrics (newly added to active panel)
            if (performanceData.total_projects !== undefined) {
                const projectCount = safeNumber(performanceData.total_projects);
                activeSyncMetrics.find('.total-projects-active').text(formatCount(projectCount));
                metricsToStore.total_projects = projectCount;
            }
            
            if (performanceData.total_tokens !== undefined) {
                const tokenCount = safeNumber(performanceData.total_tokens);
                activeSyncMetrics.find('.total-tokens-active').text(formatCount(tokenCount));
                metricsToStore.total_tokens = tokenCount;
            }
            
            if (performanceData.total_duration !== undefined) {
                const duration = safeNumber(performanceData.total_duration);
                activeSyncMetrics.find('.total-sync-duration-active').text(formatLongDuration(duration));
                metricsToStore.total_sync_duration = duration;
            }
            
            if (performanceData.total_api_time !== undefined) {
                const apiTime = safeNumber(performanceData.total_api_time);
                activeSyncMetrics.find('.total-api-time-active').text(formatDuration(apiTime));
                metricsToStore.total_api_time = apiTime;
            }
            
            // Update performance metrics (existing, enhanced)
            if (performanceData.average_time !== undefined) {
                const avgTime = safeNumber(performanceData.average_time);
                const formattedAvgTime = formatAverageResponseTime(avgTime);
                const colorClass = getResponseTimeColorClass(avgTime);
                
                activeSyncMetrics.find('.avg-response-time')
                    .text(formattedAvgTime)
                    .removeClass()
                    .addClass(colorClass);
                
                metricsToStore.average_response_time = avgTime;
            }
            
            if (performanceData.request_count !== undefined) {
                const requestCount = safeNumber(performanceData.request_count);
                activeSyncMetrics.find('.api-requests').text(formatCount(requestCount));
                metricsToStore.api_requests = requestCount;
            }
            
            if (performanceData.memory_used !== undefined) {
                const memoryValue = safeNumber(performanceData.memory_used);
                const formattedMemory = formatMemory(memoryValue);
                const memoryClass = getMemoryColorClass(memoryValue);
                
                activeSyncMetrics.find('.memory-usage')
                    .text(formattedMemory)
                    .removeClass()
                    .addClass(memoryClass);
                
                metricsToStore.memory_usage = memoryValue;
            }
            
            // Make sure all 7 metrics are visible in the UI with appropriate defaults
            activeSyncMetrics.find('.total-projects-active, .total-tokens-active, .total-sync-duration-active, .total-api-time-active, .avg-response-time, .api-requests, .memory-usage').each(function() {
                const $this = $(this);
                if (!$this.text() || $this.text() === '-') {
                    if ($this.hasClass('total-projects-active') || $this.hasClass('total-tokens-active') || $this.hasClass('api-requests')) {
                        $this.text('0');
                    } else if ($this.hasClass('total-sync-duration-active')) {
                        $this.text('0s');
                    } else if ($this.hasClass('total-api-time-active')) {
                        $this.text('0.00s');
                    } else if ($this.hasClass('avg-response-time')) {
                        $this.text('0.00ms').removeClass().addClass('status-excellent');
                        if (metricsToStore.average_response_time === 0) {
                            metricsToStore.average_response_time = 0.01; // Minimal default value
                        }
                    } else if ($this.hasClass('memory-usage')) {
                        $this.text('0.00MB').removeClass().addClass('status-excellent');
                    }
                }
            });
            
            // NOTE: Live metrics are now embedded in progress response
            // No need to store separately since nmkr_sync_progress_handler includes live_metrics
            
            // IMPORTANT: Never update the main statistics panel during an active sync
            return;
        }
        
        // Below this point, we're not in an active sync, so we can update the statistics panel
        // with completed sync data only

        // Update total sync time
        if (performanceData.total_duration !== undefined) {
            totalSyncTime.text(formatDuration(safeNumber(performanceData.total_duration), true));
        }

        // Update total API time
        if (performanceData.total_api_time !== undefined) {
            totalApiTime.text(formatDuration(safeNumber(performanceData.total_api_time), true));
        }

        // Update request count
        if (performanceData.request_count !== undefined) {
            requestCount.text(safeNumber(performanceData.request_count));
        }

        // Update average response time with color coding
        if (performanceData.average_time !== undefined) {
            const avgTime = safeNumber(performanceData.average_time);
            const formattedAvgTime = formatAverageResponseTime(avgTime);
            const colorClass = getResponseTimeColorClass(avgTime);
            
            avgResponseTime.text(formattedAvgTime);
            updateColorClass(avgResponseTime, colorClass);
        }

        // Update memory usage with color coding
        if (performanceData.memory_used !== undefined) {
            const memoryValue = safeNumber(performanceData.memory_used);
            memoryUsage.text(formatMemory(memoryValue));
            updateColorClass(memoryUsage, getMemoryColorClass(memoryValue));
        }

        // Update total projects
        if (batchInfo.total_projects !== undefined) {
            totalProjects.text(safeNumber(batchInfo.total_projects));
        }

        // Update total tokens
        if (batchInfo.total_tokens !== undefined) {
            totalTokens.text(safeNumber(batchInfo.total_tokens));
        }
    };
    
    // Function to update active metrics display during sync
    const updateActiveMetrics = (liveMetrics) => {
        if (!syncInProgress || !liveMetrics) return;
        
        // Update progress metrics using consistent class selectors
        activeSyncMetrics.find('.total-projects-active').text(liveMetrics.total_projects || 0);
        activeSyncMetrics.find('.total-tokens-active').text(liveMetrics.total_tokens || 0);
        activeSyncMetrics.find('.total-sync-duration-active').text((liveMetrics.total_sync_duration || 0).toFixed(1) + 's');
        
        // Update performance metrics using consistent class selectors
        activeSyncMetrics.find('.total-api-time-active').text((liveMetrics.total_api_time || 0).toFixed(2) + 's');
        activeSyncMetrics.find('.avg-response-time').text((liveMetrics.average_response_time || 0).toFixed(2) + 'ms');
        activeSyncMetrics.find('.api-requests').text(liveMetrics.api_requests || 0);
        activeSyncMetrics.find('.memory-usage').text((liveMetrics.memory_usage || 0).toFixed(1) + 'MB');
        
        // Show the active metrics panel
        activeSyncMetrics.show();
    };


    // Function to format error details with additional context
    const formatErrorDetails = (error) => {
        let details = '';
        
        // Handle string-based errors
        if (typeof error === 'string') {
            return escapeHtml(error);
        }
        
        // Handle object-based errors
        if (error && typeof error === 'object') {
            if (error.message) {
                details = escapeHtml(error.message);
            }
            if (error.code) {
                details += ` (Error Code: ${escapeHtml(error.code)})`;
            }
            if (error.suggestion) {
                details += `<br><small class="error-suggestion">Suggestion: ${escapeHtml(error.suggestion)}</small>`;
            }
            if (error.context) {
                details += `<br><small class="error-context">Context: ${escapeHtml(error.context)}</small>`;
            }
        }
        
        return details;
    };



    // Function to display status message with performance metrics and last update timestamp
    const displayStatusMessage = (message, status, performanceData = null, progressDetails = null, lastUpdateTime = null) => {
        message = escapeHtml(message);
        
        // Prepare performance stats if data is provided
        let performanceHtml = '';
        if (performanceData) {
            updatePerformanceStats(performanceData);
            
            // Only show detailed performance in the status message if needed
            if (options.show_performance_in_status) {
                performanceHtml = '<div class="performance">' +
                    '<span>Duration: ' + formatDuration(safeNumber(performanceData.total_duration), true) + '</span>' +
                    '<span>API Time: ' + formatDuration(safeNumber(performanceData.total_api_time), true) + '</span>' +
                    '<span>Avg Response: ' + formatAverageResponseTime(safeNumber(performanceData.average_time)) + '</span>' +
                    '</div>';
            }
        }
        
        // Prepare last update timestamp if provided
        let timestampHtml = '';
        if (lastUpdateTime && status === 'info') {
            // Only show timestamp for active syncs to reassure users
            const timeElapsed = formatTimeElapsed(lastUpdateTime);
            timestampHtml = '<div class="last-update-time">Last update: ' + timeElapsed + '</div>';
        }
        
        // Add progress details if provided
        let progressHtml = '';
        if (progressDetails) {
            // Handle different types of progress details
            if (typeof progressDetails === 'string') {
                // String format - direct HTML content, don't escape
                progressHtml = '<div class="progress-details">' + progressDetails + '</div>';
            } else {
                // Object format - formatted message
                progressHtml = '<div class="progress-details">' + escapeHtml(JSON.stringify(progressDetails)) + '</div>';
            }
        }
        
        // Special handling for stopping sync
        if (progressContainer.hasClass('stopping') && status === 'info') {
            // If we're in stopping state and this is an info message, update with cleaning info
            statusMessage.html(
                '<div class="status-header status-' + status + '">' + message + '</div>' +
                (progressHtml ? progressHtml : '') +
                (performanceHtml ? performanceHtml : '')
            );
            return;
        }
        
        // Show error message with details
        if (status === 'error') {
            statusMessage.html(
                '<div class="status-header status-error">' + message + '</div>' +
                (progressHtml ? '<div class="error-details">' + progressHtml + '</div>' : '') +
                (timestampHtml ? timestampHtml : '') +
                (performanceHtml ? performanceHtml : '')
            );
        } else if (status === 'success') {
            statusMessage.html(
                '<div class="status-header status-success">' + message + '</div>' +
                (progressHtml ? progressHtml : '') +
                (timestampHtml ? timestampHtml : '') +
                (performanceHtml ? performanceHtml : '')
            );
        } else if (status === 'stopping') {
            statusMessage.html(
                '<div class="status-header status-stopping">' + message + '</div>' +
                (progressHtml ? progressHtml : '') +
                (timestampHtml ? timestampHtml : '') +
                (performanceHtml ? performanceHtml : '')
            );
        } else {
            // For regular info messages, display the full status message
            statusMessage.html(
                '<div class="status-header status-' + status + '">' + message + '</div>' +
                (progressHtml ? progressHtml : '') +
                (timestampHtml ? timestampHtml : '') +
                (performanceHtml ? performanceHtml : '')
            );
        }
    };

    // Function to update progress bar with enhanced visual feedback
    const updateProgressBar = (progress, isError = false) => {
        // Ensure progress never exceeds 100%
        progress = Math.min(100, Math.max(0, progress));
        
        // Don't reset progress to 0 on error if we have a valid progress value
        if (isError && progress > 0) {
            const roundedProgress = Math.round(progress);
            progressBar.css('width', roundedProgress + '%').text(roundedProgress + '%');
            progressContainer.attr('aria-valuenow', progress);
            progressBar.css('background-color', '#dc3232');
            return;
        }
        
        const roundedProgress = Math.round(progress);
        progressBar.css('width', roundedProgress + '%').text(roundedProgress + '%');
        progressContainer.attr('aria-valuenow', progress);
        
        // Enhanced color scheme and animation
        if (isError) {
            progressBar.css('background-color', '#dc3232');
        } else if (progress === 100) {
            progressBar.css('background-color', '#46b450');
        } else if (progress === 0) {
            progressBar.css('background-color', '#4caf50');
        } else {
            // In-progress state: blue with subtle animation
            progressBar.css('background-color', '#0073aa');
            progressBar.css('background-image', 'linear-gradient(45deg, rgba(255,255,255,0.15) 25%, transparent 25%, transparent 50%, rgba(255,255,255,0.15) 50%, rgba(255,255,255,0.15) 75%, transparent 75%, transparent)');
            progressBar.css('background-size', '1rem 1rem');
            progressBar.css('animation', 'progress-bar-stripes 1s linear infinite');
        }
    };

    // Function to display error message
    const displayError = (message, errorDetails = null, performanceData = null, progressDetails = null, lastUpdateTime = null) => {
        displayStatusMessage(message, 'error', errorDetails, progressDetails, lastUpdateTime);
        updateProgressBar(0, true);
    };

    // Function to display success message
    const displaySuccess = (message, performanceData = null, progressDetails = null, forceComplete = true, lastUpdateTime = null) => {
        displayStatusMessage(message, 'success', performanceData, progressDetails, lastUpdateTime);
        
        // Only set progress to 100% if forceComplete is true
        if (forceComplete) {
            updateProgressBar(100);
            // Add a small delay to ensure the success message is displayed
            // Only hide the progress bar after a brief moment
            if (progressContainer.is(':visible')) {
                setTimeout(() => {
                    progressContainer.hide();
                    // Clear progress details
                    statusMessage.find('.progress-details').remove();
                    // Hide active sync response time
                    activeSyncMetrics.hide();
                }, 250);
            }
        }
        
        // Add compact class back to sync data panel when sync is complete
        syncDataPanel.addClass('compact');
        
        // Update last sync time first, then trigger the event
        // This ensures the stats panel has the latest data before any event handlers run
        updateLastSyncTime('sync_completed', function() {
            // Trigger an event to notify other scripts that sync has completed
            // This will allow other components to refresh their displays
            $(document).trigger('nmkr_sync_completed');
        });
    };

    // Function to display info message 
    const displayInfo = (message, performanceData = null, progressDetails = null, lastUpdateTime = null) => {
        // Simplified info display without stage detection
        let stageType = 'info';
        
        // Check if this is a stopping synchronization message
        if (message && (message.includes('Stopping synchronization') || message.includes('⏹️'))) {
            message = message.includes('⏹️') ? message : '⏹️ ' + message;
            stageType = 'stopping';
        }
        
        displayStatusMessage(message, stageType, performanceData, progressDetails, lastUpdateTime);
    };

    // Function to display warning message
    const displayWarning = (message, warningDetails = null, performanceData = null, progressDetails = null) => {
        // Use orange warning color
        let performanceHtml = '';
        if (performanceData) {
            updatePerformanceStats(performanceData);
            // Only show detailed performance in the warning message if needed
            if (options.show_performance_in_status) {
                performanceHtml = '<div class="performance">' +
                    '<span>Duration: ' + formatDuration(safeNumber(performanceData.total_duration), true) + '</span>' +
                    '<span>API Time: ' + formatDuration(safeNumber(performanceData.total_api_time), true) + '</span>' +
                    '<span>Avg Response: ' + formatAverageResponseTime(safeNumber(performanceData.average_time)) + '</span>' +
                    '</div>';
            }
        }
        
        const warningHtml = '<div class="status-header status-warning">' + escapeHtml(message) + '</div>' +
            (warningDetails ? '<div class="warning-details">' + escapeHtml(warningDetails) + '</div>' : '') +
            (progressDetails ? '<div class="progress-details">' + progressDetails + '</div>' : '') +
            performanceHtml;
        
        statusMessage.html(warningHtml);
        
        // Change progress bar color to orange to indicate warning state
        progressBar.css('background-color', '#ffb900');
    };


    


    // End of functions - closing the IIFE
});
