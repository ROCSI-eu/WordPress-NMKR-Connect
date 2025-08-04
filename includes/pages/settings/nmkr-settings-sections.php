<?php
/**
 * NMKR Connect - Settings Sections Module
 *
 * Defines all settings sections and field callbacks for the settings page.
 *
 * @package NMKR Connect
 * @subpackage Settings
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// API Settings Section Callback
function nmkr_connect_api_section_callback() {
    echo '<p>Configure your NMKR API settings below. You can get your API key from <a href="https://studio.nmkr.io/apikeys" target="_blank">NMKR Studio</a>.</p>';
}

// API Settings Field Callbacks
function nmkr_api_key_field_callback() {
    $options = get_option('nmkr_connect_options');
    $api_key = isset($options['api_key']) ? $options['api_key'] : '';
    ?>
    <input type="password" 
           name="nmkr_connect_options[api_key]" 
           id="nmkr_api_key"
           value="<?php echo esc_attr($api_key); ?>"
           class="regular-text"
    />
    <button type="button" id="toggle_api_key_visibility" class="button button-secondary">Show/Hide</button>
    <?php
}

// Synchronization Settings Section Callback
function nmkr_connect_sync_section_callback() {
    echo '<p>Configure synchronization batch processing settings below. These settings control how many tokens are processed at once and the delay between batches.</p>';
    echo '<p>To quickly configure all synchronization settings at once, use the "Synchronization Profile" dropdown below.</p>';
    echo '<div class="nmkr-profile-recommendations" style="background-color: #f8f9fa; border-left: 4px solid #0073aa; padding: 12px 16px; margin: 15px 0;">';
    echo '<h4 style="margin-top: 0; color: #0073aa;">Profile Recommendations:</h4>';
    echo '<ul style="margin-bottom: 0;">';
    echo '<li><strong>Light Profile:</strong> Recommended for shared hosting environments to reduce server load and maintain stability.</li>';
    echo '<li><strong>Balanced Profile:</strong> Optimal for most WordPress sites, offering a good compromise between performance and server resources.</li>';
    echo '<li><strong>Aggressive Profile:</strong> Suitable for dedicated servers with ample resources, can significantly speed up synchronization.</li>';
    echo '</ul>';
    echo '</div>';
}

// Synchronization Profile Field Callback
function nmkr_sync_profile_field_callback() {
    $options = get_option('nmkr_connect_options');
    $sync_profile = isset($options['sync_profile']) ? $options['sync_profile'] : 'balanced';
    $profiles = nmkr_get_sync_profiles();
    ?>
    <select name="nmkr_connect_options[sync_profile]" id="nmkr_sync_profile">
        <?php foreach ($profiles as $value => $profile) : ?>
            <option value="<?php echo esc_attr($value); ?>" <?php selected($sync_profile, $value); ?>>
                <?php echo esc_html($profile['name']); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <p class="description">Select a synchronization profile to automatically configure all settings below. Choose based on your server resources and synchronization needs.</p>
    <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            const profileSelect = document.getElementById('nmkr_sync_profile');
            const batchSizeInput = document.getElementById('nmkr_sync_batch_size');
            const batchDelayInput = document.getElementById('nmkr_sync_batch_delay');
            const initialIntervalInput = document.querySelector('input[name="nmkr_connect_options[sync_initial_interval]"]');
            const maxIntervalInput = document.querySelector('input[name="nmkr_connect_options[sync_max_interval]"]');
            const intervalIncreaseInput = document.querySelector('input[name="nmkr_connect_options[sync_interval_increase]"]');
            const intervalDecreaseInput = document.querySelector('input[name="nmkr_connect_options[sync_interval_decrease]"]');
            const maxErrorsInput = document.querySelector('input[name="nmkr_connect_options[sync_max_errors]"]');
            
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
                input.addEventListener('change', function() {
                    profileSelect.value = 'custom';
                });
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
    </script>
    <?php
}

// Batch Size Field Callback
function nmkr_sync_batch_size_field_callback() {
    $options = get_option('nmkr_connect_options');
    $batch_size = isset($options['sync_batch_size']) ? $options['sync_batch_size'] : 5;
    ?>
    <input type="number" 
           name="nmkr_connect_options[sync_batch_size]" 
           id="nmkr_sync_batch_size"
           value="<?php echo esc_attr($batch_size); ?>"
           min="1"
           max="10"
           class="small-text"
    />
    <p class="description">Number of tokens to process in each batch (1-10)</p>
    <?php
}

// Batch Delay Field Callback
function nmkr_sync_batch_delay_field_callback() {
    $options = get_option('nmkr_connect_options');
    $batch_delay = isset($options['sync_batch_delay']) ? $options['sync_batch_delay'] : 2;
    ?>
    <input type="number" 
           name="nmkr_connect_options[sync_batch_delay]" 
           id="nmkr_sync_batch_delay"
           value="<?php echo esc_attr($batch_delay); ?>"
           min="1"
           max="10"
           class="small-text"
    />
    <p class="description">Delay in seconds between processing batches (1-10)</p>
    <?php
}

// Initial Polling Interval Field Callback
function nmkr_sync_initial_interval_field_callback() {
    $options = get_option('nmkr_connect_options');
    $value = isset($options['sync_initial_interval']) ? $options['sync_initial_interval'] : 1000;
    ?>
    <input type="number" 
           name="nmkr_connect_options[sync_initial_interval]" 
           value="<?php echo esc_attr($value); ?>"
           min="100"
           max="5000"
           step="100"
           class="small-text"
    />
    <p class="description">Starting interval for polling (in milliseconds)</p>
    <?php
}

// Maximum Polling Interval Field Callback
function nmkr_sync_max_interval_field_callback() {
    $options = get_option('nmkr_connect_options');
    $value = isset($options['sync_max_interval']) ? $options['sync_max_interval'] : 30000;
    ?>
    <input type="number" 
           name="nmkr_connect_options[sync_max_interval]" 
           value="<?php echo esc_attr($value); ?>"
           min="1000"
           max="30000"
           step="1000"
           class="small-text"
    />
    <p class="description">Maximum interval for polling (in milliseconds)</p>
    <?php
}

// Interval Increase Factor Field Callback
function nmkr_sync_interval_increase_field_callback() {
    $options = get_option('nmkr_connect_options');
    $value = isset($options['sync_interval_increase']) ? $options['sync_interval_increase'] : 2.0;
    ?>
    <input type="number" 
           name="nmkr_connect_options[sync_interval_increase]" 
           value="<?php echo esc_attr($value); ?>"
           min="1.1"
           max="3"
           step="0.1"
           class="small-text"
    />
    <p class="description">Factor to multiply interval by when increasing (e.g., 2.0 = 100% increase)</p>
    <?php
}

// Interval Decrease Factor Field Callback
function nmkr_sync_interval_decrease_field_callback() {
    $options = get_option('nmkr_connect_options');
    $value = isset($options['sync_interval_decrease']) ? $options['sync_interval_decrease'] : 0.5;
    ?>
    <input type="number" 
           name="nmkr_connect_options[sync_interval_decrease]" 
           value="<?php echo esc_attr($value); ?>"
           min="0.1"
           max="0.9"
           step="0.1"
           class="small-text"
    />
    <p class="description">Factor to multiply interval by when decreasing (e.g., 0.5 = 50% decrease)</p>
    <?php
}

// Maximum Error Count Field Callback
function nmkr_sync_max_errors_field_callback() {
    $options = get_option('nmkr_connect_options');
    $value = isset($options['sync_max_errors']) ? $options['sync_max_errors'] : 3;
    ?>
    <input type="number" 
           name="nmkr_connect_options[sync_max_errors]" 
           value="<?php echo esc_attr($value); ?>"
           min="1"
           max="10"
           step="1"
           class="small-text"
    />
    <p class="description">Maximum number of consecutive errors before stopping</p>
    <?php
}

// Debug Settings Section Callback
function nmkr_connect_wp_debug_section_callback() {
    echo '<p>Configure debug logging for various plugin processes.</p>';
}

// Debug Enabled Field Callback
function nmkr_debug_enabled_field_callback() {
    $options = get_option('nmkr_connect_options');
    $debug_enabled = isset($options['debug_enabled']) ? $options['debug_enabled'] : false;
    ?>
    <input type="checkbox" 
           name="nmkr_connect_options[debug_enabled]" 
           id="nmkr_debug_enabled"
           value="1"
           <?php checked(1, $debug_enabled); ?>
    />
    <p class="description">
        Enable this to activate logging options below. You must also select at least one logging destination for logs to be written.
    </p>
    <script type="text/javascript">
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
            }
            
            // Initial state
            updateDependentCheckboxes();
            
            // Add event listeners for changes
            debugCheckbox.addEventListener('change', updateDependentCheckboxes);
            logToDebugFileCheckbox.addEventListener('change', updateDependentCheckboxes);
            logToDashboardCheckbox.addEventListener('change', updateDependentCheckboxes);
        });
    </script>
    <?php
}

// Log to Debug File Field Callback
function nmkr_log_to_debug_file_field_callback() {
    $options = get_option('nmkr_connect_options');
    $log_to_debug_file = isset($options['log_to_debug_file']) ? $options['log_to_debug_file'] : false;
    ?>
    <input type="checkbox" 
           name="nmkr_connect_options[log_to_debug_file]" 
           id="nmkr_log_to_debug_file"
           value="1"
           <?php checked(1, $log_to_debug_file); ?>
    />
    <p class="description">
        Enable this option to write debug logs to your site's debug.log file. Requires WP_DEBUG_LOG to be enabled in wp-config.php.
    </p>
    <?php
}

// Log to Dashboard Field Callback
function nmkr_log_to_dashboard_field_callback() {
    $options = get_option('nmkr_connect_options');
    $log_to_dashboard = isset($options['log_to_dashboard']) ? $options['log_to_dashboard'] : false;
    ?>
    <input type="checkbox" 
           name="nmkr_connect_options[log_to_dashboard]" 
           id="nmkr_log_to_dashboard"
           value="1"
           <?php checked(1, $log_to_dashboard); ?>
    />
    <p class="description">
        Enable this option to store debug logs in the WordPress database for viewing in the dashboard.
    </p>
    <?php if ($log_to_dashboard): ?>
        <p style="margin-top: 10px;">
            🔍 <strong>View Debug Logs:</strong>
            <a href="<?php echo admin_url('admin.php?page=nmkr-connect-dashboard#nmkr-debug-logs'); ?>">Jump to Debug Logs panel in Dashboard</a>
        </p>
    <?php endif; ?>
    <?php
}

// API Debug Enabled Field Callback
function nmkr_api_debug_enabled_field_callback() {
    $options = get_option('nmkr_connect_options');
    $api_debug_enabled = isset($options['api_debug_enabled']) ? $options['api_debug_enabled'] : false;
    $debug_enabled = isset($options['debug_enabled']) ? $options['debug_enabled'] : false;
    ?>
    <input type="checkbox" 
           name="nmkr_connect_options[api_debug_enabled]" 
           id="nmkr_api_debug_enabled"
           value="1"
           <?php checked(1, $api_debug_enabled); ?>
           <?php disabled(!$debug_enabled, true); ?>
    />
    <p class="description">
        Enable this option to write API connection status logs.
    </p>
    <?php
}

// Sync Debug Enabled Field Callback
function nmkr_sync_debug_enabled_field_callback() {
    $options = get_option('nmkr_connect_options');
    $sync_debug_enabled = isset($options['sync_debug_enabled']) ? $options['sync_debug_enabled'] : false;
    $debug_enabled = isset($options['debug_enabled']) ? $options['debug_enabled'] : false;
    ?>
    <input type="checkbox" 
           name="nmkr_connect_options[sync_debug_enabled]" 
           id="nmkr_sync_debug_enabled"
           value="1"
           <?php checked(1, $sync_debug_enabled); ?>
           <?php disabled(!$debug_enabled, true); ?>
    />
    <p class="description">
        Enable this option to write data synchronization logs.
    </p>
    <?php
}

// UI Debug Enabled Field Callback
function nmkr_ui_debug_enabled_field_callback() {
    $options = get_option('nmkr_connect_options');
    $ui_debug_enabled = isset($options['ui_debug_enabled']) ? $options['ui_debug_enabled'] : false;
    $debug_enabled = isset($options['debug_enabled']) ? $options['debug_enabled'] : false;
    ?>
    <input type="checkbox" 
           name="nmkr_connect_options[ui_debug_enabled]" 
           id="nmkr_ui_debug_enabled"
           value="1"
           <?php checked(1, $ui_debug_enabled); ?>
           <?php disabled(!$debug_enabled, true); ?>
    />
    <p class="description">
        Enable this option to write user interface status logs.
    </p>
    <?php
}

// Performance Debug Enabled Field Callback
function nmkr_performance_debug_enabled_field_callback() {
    $options = get_option('nmkr_connect_options');
    $performance_debug_enabled = isset($options['performance_debug_enabled']) ? $options['performance_debug_enabled'] : false;
    $debug_enabled = isset($options['debug_enabled']) ? $options['debug_enabled'] : false;
    ?>
    <input type="checkbox" 
           name="nmkr_connect_options[performance_debug_enabled]" 
           id="nmkr_performance_debug_enabled"
           value="1"
           <?php checked(1, $performance_debug_enabled); ?>
           <?php disabled(!$debug_enabled, true); ?>
    />
    <p class="description">
        Enable this option to write performance tracking logs.
    </p>
    <?php
}

// Log Throttle Enabled Field Callback
function nmkr_log_throttle_enabled_field_callback() {
    $options = get_option('nmkr_connect_options');
    $log_throttle_enabled = isset($options['log_throttle_enabled']) ? $options['log_throttle_enabled'] : false;
    $debug_enabled = isset($options['debug_enabled']) ? $options['debug_enabled'] : false;
    ?>
    <input type="checkbox" 
           name="nmkr_connect_options[log_throttle_enabled]" 
           id="nmkr_log_throttle_enabled"
           value="1"
           <?php checked(1, $log_throttle_enabled); ?>
           <?php disabled(!$debug_enabled, true); ?>
    />
    <p class="description">
        Enable this option to reduce repetitive logs during sync, such as retries and progress updates. Improves performance and reduces debug.log clutter.
    </p>
    <?php
}

// Log Retention Limit Field Callback
function nmkr_log_retention_limit_field_callback() {
    $options = get_option('nmkr_connect_options');
    $log_retention_limit = isset($options['log_retention_limit']) ? $options['log_retention_limit'] : 100;
    $debug_enabled = isset($options['debug_enabled']) ? $options['debug_enabled'] : false;
    ?>
    <input type="number" 
           name="nmkr_connect_options[log_retention_limit]" 
           id="nmkr_log_retention_limit"
           value="<?php echo esc_attr($log_retention_limit); ?>"
           min="1"
           max="1000"
           step="1"
           class="small-text"
           <?php disabled(!$debug_enabled, true); ?>
    />
    <p class="description">
        Specify how many log entries to keep per log type in the Dashboard. Applies only to database logs. Default is 100.
    </p>
    <?php
}

/**
 * Get synchronization profiles with their predefined settings
 * 
 * @return array An array of synchronization profiles with their settings
 */
function nmkr_get_sync_profiles() {
    return array(
        'light' => array(
            'name' => 'Light (Less resource intensive, slower)',
            'settings' => array(
                'sync_batch_size' => 3,
                'sync_batch_delay' => 3,
                'sync_initial_interval' => 2000,
                'sync_max_interval' => 20000,
                'sync_interval_increase' => 1.5,
                'sync_interval_decrease' => 0.7,
                'sync_max_errors' => 5
            )
        ),
        'balanced' => array(
            'name' => 'Balanced (Recommended for most sites)',
            'settings' => array(
                'sync_batch_size' => 5,
                'sync_batch_delay' => 2,
                'sync_initial_interval' => 1000,
                'sync_max_interval' => 30000,
                'sync_interval_increase' => 2.0,
                'sync_interval_decrease' => 0.5,
                'sync_max_errors' => 3
            )
        ),
        'aggressive' => array(
            'name' => 'Aggressive (Resource intensive, faster)',
            'settings' => array(
                'sync_batch_size' => 10,
                'sync_batch_delay' => 1,
                'sync_initial_interval' => 500,
                'sync_max_interval' => 10000,
                'sync_interval_increase' => 2.5,
                'sync_interval_decrease' => 0.3,
                'sync_max_errors' => 2
            )
        ),
        'custom' => array(
            'name' => 'Custom (Manually configured)',
            'settings' => array()
        )
    );
} 