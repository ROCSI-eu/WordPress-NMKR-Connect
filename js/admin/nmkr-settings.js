document.addEventListener('DOMContentLoaded', function() {
    const profileSelect = document.getElementById('nmkr_sync_profile');
    const batchSizeInput = document.getElementById('nmkr_sync_batch_size');
    const batchDelayInput = document.getElementById('nmkr_sync_batch_delay');
    const initialIntervalInput = document.querySelector('input[name="nmkr_connect_options[sync_initial_interval]"]');
    const maxIntervalInput = document.querySelector('input[name="nmkr_connect_options[sync_max_interval]"]');
    const intervalIncreaseInput = document.querySelector('input[name="nmkr_connect_options[sync_interval_increase]"]');
    const intervalDecreaseInput = document.querySelector('input[name="nmkr_connect_options[sync_interval_decrease]"]');
    const maxErrorsInput = document.querySelector('input[name="nmkr_connect_options[sync_max_errors]"]');

    if (!profileSelect || !batchSizeInput || !batchDelayInput || !initialIntervalInput ||
        !maxIntervalInput || !intervalIncreaseInput || !intervalDecreaseInput || !maxErrorsInput) {
        return;
    }

    // Define the profiles - keep in sync with PHP function nmkr_get_sync_profiles()
    const profiles = {
        'light': {
            batch_size: 3,
            batch_delay: 3,
            initial_interval: 2000,
            max_interval: 20000,
            interval_increase: 1.5,
            interval_decrease: 0.7,
            max_errors: 5
        },
        'balanced': {
            batch_size: 5,
            batch_delay: 2,
            initial_interval: 1000,
            max_interval: 30000,
            interval_increase: 2.0,
            interval_decrease: 0.5,
            max_errors: 3
        },
        'aggressive': {
            batch_size: 10,
            batch_delay: 1,
            initial_interval: 500,
            max_interval: 10000,
            interval_increase: 2.5,
            interval_decrease: 0.3,
            max_errors: 2
        }
    };

    // Function to apply profile settings
    function applyProfile(profile) {
        if (profile === 'custom') {
            return; // Don't change anything for custom profile
        }

        const settings = profiles[profile];
        if (settings) {
            batchSizeInput.value = settings.batch_size;
            batchDelayInput.value = settings.batch_delay;
            initialIntervalInput.value = settings.initial_interval;
            maxIntervalInput.value = settings.max_interval;
            intervalIncreaseInput.value = settings.interval_increase;
            intervalDecreaseInput.value = settings.interval_decrease;
            maxErrorsInput.value = settings.max_errors;
        }
    }

    // Function to check if current settings match a profile
    function detectCurrentProfile() {
        // If no profile is selected or custom is selected, check if settings match any profile
        if (profileSelect.value === 'custom' || !profileSelect.value) {
            for (const profile in profiles) {
                const settings = profiles[profile];
                const matchesProfile =
                    parseInt(batchSizeInput.value) === settings.batch_size &&
                    parseInt(batchDelayInput.value) === settings.batch_delay &&
                    parseInt(initialIntervalInput.value) === settings.initial_interval &&
                    parseInt(maxIntervalInput.value) === settings.max_interval &&
                    parseFloat(intervalIncreaseInput.value) === settings.interval_increase &&
                    parseFloat(intervalDecreaseInput.value) === settings.interval_decrease &&
                    parseInt(maxErrorsInput.value) === settings.max_errors;

                if (matchesProfile) {
                    profileSelect.value = profile;
                    return;
                }
            }
            // If no match found, set to custom
            profileSelect.value = 'custom';
        }
    }

    // Handle profile selection change
    profileSelect.addEventListener('change', function() {
        const selectedProfile = this.value;
        if (selectedProfile !== 'custom') {
            if (confirm('Changing the synchronization profile will update all settings below. Continue?')) {
                applyProfile(selectedProfile);
            } else {
                // Revert to custom if user cancels
                this.value = 'custom';
            }
        }
    });

    // Set to custom if any individual settings are changed
    const allInputs = [batchSizeInput, batchDelayInput, initialIntervalInput,
                     maxIntervalInput, intervalIncreaseInput,
                     intervalDecreaseInput, maxErrorsInput];

    allInputs.forEach(input => {
        if (input) {
            input.addEventListener('change', function() {
                profileSelect.value = 'custom';
            });
        }
    });

    // On page load, detect the current profile or apply the selected profile
    if (profileSelect.value !== 'custom') {
        // If a non-custom profile is selected, apply its settings
        applyProfile(profileSelect.value);
    } else {
        // Otherwise, try to detect if current settings match a profile
        detectCurrentProfile();
    }
});

document.addEventListener('DOMContentLoaded', function() {
    const debugCheckbox = document.getElementById('nmkr_debug_enabled');
    const logToDebugFileCheckbox = document.getElementById('nmkr_log_to_debug_file');
    const logToDashboardCheckbox = document.getElementById('nmkr_log_to_dashboard');
    const syncDebugCheckbox = document.getElementById('nmkr_sync_debug_enabled');
    const apiDebugCheckbox = document.getElementById('nmkr_api_debug_enabled');
    const uiDebugCheckbox = document.getElementById('nmkr_ui_debug_enabled');
    const performanceDebugCheckbox = document.getElementById('nmkr_performance_debug_enabled');
    const logThrottleCheckbox = document.getElementById('nmkr_log_throttle_enabled');
    const logRetentionLimitInput = document.getElementById('nmkr_log_retention_limit');
    const dashboardSyncLoggingStatus = document.getElementById('nmkr-dashboard-sync-logging-status');
    const dashboardSyncLoggingStatusText = document.getElementById('nmkr-dashboard-sync-logging-status-text');
    const dashboardSyncLoggingActiveText = window.nmkrSettingsConfig.i18n.dashboardSyncLoggingActive;
    const dashboardSyncLoggingInactiveText = window.nmkrSettingsConfig.i18n.dashboardSyncLoggingInactive;

    if (!debugCheckbox || !logToDebugFileCheckbox || !logToDashboardCheckbox ||
        !syncDebugCheckbox || !apiDebugCheckbox || !uiDebugCheckbox ||
        !performanceDebugCheckbox || !logThrottleCheckbox || !logRetentionLimitInput ||
        !dashboardSyncLoggingStatus || !dashboardSyncLoggingStatusText) {
        return;
    }

    // Function to update the state of all dependent checkboxes
    function updateDependentCheckboxes() {
        if (debugCheckbox.checked) {
            // Enable destination toggles when master debug is enabled
            logToDebugFileCheckbox.disabled = false;
            logToDashboardCheckbox.disabled = false;

            // Enable log retention limit input when debug is enabled
            logRetentionLimitInput.disabled = false;

            // Check if at least one destination is selected
            const hasDestination = logToDebugFileCheckbox.checked || logToDashboardCheckbox.checked;

            if (hasDestination) {
                // Enable log type checkboxes when master debug is on AND at least one destination is selected
                syncDebugCheckbox.disabled = false;
                apiDebugCheckbox.disabled = false;
                uiDebugCheckbox.disabled = false;
                performanceDebugCheckbox.disabled = false;
                logThrottleCheckbox.disabled = false;
            } else {
                // Disable and uncheck log type checkboxes when no destination is selected
                syncDebugCheckbox.disabled = true;
                syncDebugCheckbox.checked = false;
                apiDebugCheckbox.disabled = true;
                apiDebugCheckbox.checked = false;
                uiDebugCheckbox.disabled = true;
                uiDebugCheckbox.checked = false;
                performanceDebugCheckbox.disabled = true;
                performanceDebugCheckbox.checked = false;
                logThrottleCheckbox.disabled = true;
                logThrottleCheckbox.checked = false;
            }
        } else {
            // If master debug is disabled, disable and uncheck all dependent options
            logToDebugFileCheckbox.disabled = true;
            logToDebugFileCheckbox.checked = false;
            logToDashboardCheckbox.disabled = true;
            logToDashboardCheckbox.checked = false;

            // Disable log retention limit input when debug is disabled
            logRetentionLimitInput.disabled = true;

            syncDebugCheckbox.disabled = true;
            syncDebugCheckbox.checked = false;
            apiDebugCheckbox.disabled = true;
            apiDebugCheckbox.checked = false;
            uiDebugCheckbox.disabled = true;
            uiDebugCheckbox.checked = false;
            performanceDebugCheckbox.disabled = true;
            performanceDebugCheckbox.checked = false;
            logThrottleCheckbox.disabled = true;
            logThrottleCheckbox.checked = false;
        }

        const dashboardSyncLoggingActive = debugCheckbox.checked && logToDashboardCheckbox.checked && syncDebugCheckbox.checked;
        dashboardSyncLoggingStatus.classList.toggle('notice-success', dashboardSyncLoggingActive);
        dashboardSyncLoggingStatus.classList.toggle('notice-warning', !dashboardSyncLoggingActive);
        dashboardSyncLoggingStatusText.textContent = dashboardSyncLoggingActive
            ? dashboardSyncLoggingActiveText
            : dashboardSyncLoggingInactiveText;
    }

    // Initial state
    updateDependentCheckboxes();

    // Add event listeners for changes
    debugCheckbox.addEventListener('change', updateDependentCheckboxes);
    logToDebugFileCheckbox.addEventListener('change', updateDependentCheckboxes);
    logToDashboardCheckbox.addEventListener('change', updateDependentCheckboxes);
    syncDebugCheckbox.addEventListener('change', updateDependentCheckboxes);
});

document.addEventListener('DOMContentLoaded', function() {
    const toggleButton = document.getElementById('toggle_api_key_visibility');
    const apiKeyInput = document.getElementById('nmkr_api_key');

    if (!toggleButton || !apiKeyInput) {
        return;
    }

    toggleButton.addEventListener('click', function() {
        if (apiKeyInput.type === 'password') {
            apiKeyInput.type = 'text';
            toggleButton.textContent = 'Hide';
        } else {
            apiKeyInput.type = 'password';
            toggleButton.textContent = 'Show';
        }
    });
});

jQuery(document).ready(function($) {
    $('#nmkr-reset-defaults').on('click', function() {
        if (confirm('Are you sure you want to reset all settings to their default values?')) {
            // Store current API key
            const currentApiKey = $('#nmkr_api_key').val();

            // Reset Sync Settings
            $('#nmkr_sync_profile').val('balanced');
            $('#nmkr_sync_batch_size').val('5');
            $('#nmkr_sync_batch_delay').val('2');
            $('input[name="nmkr_connect_options[sync_initial_interval]"]').val('1000');
            $('input[name="nmkr_connect_options[sync_max_interval]"]').val('30000');
            $('input[name="nmkr_connect_options[sync_interval_increase]"]').val('2.0');
            $('input[name="nmkr_connect_options[sync_interval_decrease]"]').val('0.5');
            $('input[name="nmkr_connect_options[sync_max_errors]"]').val('3');

            // Reset Debug Settings - ensure they are disabled by default
            $('#nmkr_debug_enabled').prop('checked', false);
            $('#nmkr_log_to_debug_file').prop('checked', false).prop('disabled', true);
            $('#nmkr_log_to_dashboard').prop('checked', false).prop('disabled', true);
            $('#nmkr_api_debug_enabled').prop('checked', false).prop('disabled', true);
            $('#nmkr_sync_debug_enabled').prop('checked', false).prop('disabled', true);
            $('#nmkr_ui_debug_enabled').prop('checked', false).prop('disabled', true);
            $('#nmkr_performance_debug_enabled').prop('checked', false).prop('disabled', true);
            $('#nmkr_log_throttle_enabled').prop('checked', false).prop('disabled', true);
            $('#nmkr_log_retention_limit').val('100').prop('disabled', true);

            // Reset Analytics & Privacy Settings
            $('#nmkr_analytics_mode').val('off');
            $('#nmkr_ga4_measurement_id').val('');
            $('#nmkr_ga4_api_secret').val('');
            $('#nmkr_analytics_retention_days').val('90');
            $('#nmkr_analytics_track_logged_in').prop('checked', false);
            $('#nmkr_analytics_require_consent').prop('checked', true);
            $('#nmkr_analytics_sample_rate').val('1');
            $('#nmkr_analytics_remove_on_uninstall').prop('checked', true);
            $('#nmkr_analytics_debug').prop('checked', false);

            // Restore API key
            $('#nmkr_api_key').val(currentApiKey);

            // Show success message
            alert('Settings have been reset to defaults. Click "Save Settings" to apply.');
        }
    });
});
