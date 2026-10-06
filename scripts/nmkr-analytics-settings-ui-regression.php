<?php
/**
 * Source-level regression for the Analytics + Settings admin facelift.
 */

$root = dirname( __DIR__ );

function nmkr_analytics_settings_ui_assert( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

$analytics_page = file_get_contents( $root . '/includes/pages/analytics/nmkr-analytics-admin.php' );
$analytics_js   = file_get_contents( $root . '/js/admin/nmkr-analytics-dashboard.js' );
$analytics_css  = file_get_contents( $root . '/css/admin/nmkr-analytics-dashboard.css' );
$settings_core  = file_get_contents( $root . '/includes/pages/settings/nmkr-settings-core.php' );
$settings_js    = file_get_contents( $root . '/js/admin/nmkr-settings.js' );
$settings_css   = file_get_contents( $root . '/css/admin/nmkr-settings.css' );
$bootstrap      = file_get_contents( $root . '/rocsi-connector-for-nmkr.php' );

$analytics_contracts = array(
    "current_user_can( 'nmkr_view_analytics' )" => 'Analytics capability remains nmkr_view_analytics',
    'id="nmkr-analytics-filters"' => 'Analytics filters runtime target remains stable',
    'id="nmkr-analytics-kpis"' => 'Analytics KPI runtime target remains stable',
    'id="nmkr-analytics-charts"' => 'Analytics charts runtime target remains stable',
    'id="nmkr-analytics-tables"' => 'Analytics tables runtime target remains stable',
    'id="nmkr-analytics-exports"' => 'Analytics export runtime target remains stable',
    'id="nmkr-analytics-state"' => 'Analytics has an accessible report-state region',
    'data-analytics-mode=' => 'Analytics exposes the configured mode to the page shell',
    'Analytics collection is off.' => 'Analytics off state is intentional',
    'GA4-only analytics is active.' => 'Analytics GA4-only state is intentional',
    'configuration is incomplete' => 'Analytics GA4 incomplete state is intentional',
);
foreach ( $analytics_contracts as $needle => $message ) {
    nmkr_analytics_settings_ui_assert( false !== strpos( $analytics_page, $needle ), $message );
}

foreach ( array(
    'Filters will appear here',
    'KPI cards (Views, Clicks, CTR) will render here.',
    'Exports (CSV / JSON) will be available here.',
    'placeholder; wired in PR-',
) as $placeholder ) {
    nmkr_analytics_settings_ui_assert(
        false === strpos( $analytics_page, $placeholder ),
        'released Analytics UI contains no implementation placeholder copy: ' . $placeholder
    );
}

nmkr_analytics_settings_ui_assert(
    false !== strpos( $analytics_js, "setReportState('loading'" )
        && false !== strpos( $analytics_js, "setReportState('error'" )
        && false !== strpos( $analytics_js, "hasReportData ? 'ready' : 'empty'" ),
    'Analytics JS distinguishes loading, error, populated, and empty report states'
);

$analytics_actions = array(
    'nmkr_analytics_kpis',
    'nmkr_analytics_timeseries',
    'nmkr_analytics_top_projects',
    'nmkr_analytics_top_tokens',
    'nmkr_analytics_export',
);
foreach ( $analytics_actions as $action ) {
    nmkr_analytics_settings_ui_assert(
        false !== strpos( $analytics_js, $action ),
        'Analytics runtime action remains present: ' . $action
    );
}

$settings_contracts = array(
    "register_setting(\n        'nmkr_connect_settings_group',\n        'nmkr_connect_options',\n        'nmkr_connect_sanitize_options'" => 'Settings option name and sanitizer remain unchanged',
    "return 'nmkr_manage_settings';" => 'Settings save capability remains nmkr_manage_settings',
    "current_user_can( 'nmkr_manage_settings' )" => 'Settings page capability remains nmkr_manage_settings',
    '<form action="options.php" method="post"' => 'Settings still submit through options.php',
    "settings_fields('nmkr_connect_settings_group')" => 'Settings nonce/options group remains native',
    'id="nmkr-settings-api"' => 'primary API setup group is present',
    'id="nmkr-settings-sync"' => 'synchronization group is present',
    'id="nmkr-settings-sync-advanced"' => 'advanced synchronization disclosure is present',
    'id="nmkr-settings-analytics"' => 'analytics/privacy group is present',
    'id="nmkr-settings-diagnostics"' => 'diagnostics disclosure is present',
    'id="nmkr-reset-defaults"' => 'reset action remains present',
);
foreach ( $settings_contracts as $needle => $message ) {
    nmkr_analytics_settings_ui_assert( false !== strpos( $settings_core, $needle ), $message );
}

$settings_field_ids = array(
    'nmkr_api_key',
    'nmkr_sync_profile',
    'nmkr_sync_batch_size',
    'nmkr_sync_batch_delay',
    'nmkr_sync_initial_interval',
    'nmkr_sync_max_interval',
    'nmkr_sync_interval_increase',
    'nmkr_sync_interval_decrease',
    'nmkr_sync_max_errors',
    'nmkr_debug_enabled',
    'nmkr_log_to_debug_file',
    'nmkr_log_to_dashboard',
    'nmkr_api_debug_enabled',
    'nmkr_sync_debug_enabled',
    'nmkr_ui_debug_enabled',
    'nmkr_performance_debug_enabled',
    'nmkr_log_throttle_enabled',
    'nmkr_log_retention_limit',
    'nmkr_analytics_mode',
    'nmkr_ga4_measurement_id',
    'nmkr_ga4_api_secret',
    'nmkr_analytics_retention_days',
    'nmkr_analytics_track_logged_in',
    'nmkr_analytics_require_consent',
    'nmkr_analytics_sample_rate',
    'nmkr_analytics_remove_on_uninstall',
    'nmkr_analytics_debug',
);
foreach ( $settings_field_ids as $field_id ) {
    nmkr_analytics_settings_ui_assert(
        false !== strpos( $settings_core, "'" . $field_id . "'" ),
        'registered Settings field remains present: ' . $field_id
    );
}

nmkr_analytics_settings_ui_assert(
    false !== strpos( $settings_js, "if (confirm('Are you sure you want to reset all settings to their default values?')" )
        && false !== strpos( $settings_js, "$('#nmkr_api_key').val(currentApiKey);" ),
    'Reset confirmation and API-key preservation remain unchanged'
);

nmkr_analytics_settings_ui_assert(
    false !== strpos( $bootstrap, "'css/admin/nmkr-settings.css'" ),
    'Settings stylesheet is scoped through the existing admin enqueue path'
);
nmkr_analytics_settings_ui_assert(
    false === strpos( $bootstrap, "wp_style_add_data('nmkr-analytics-dashboard', 'rtl', 'replace')" ),
    'Analytics no longer replaces the complete facelift stylesheet with the stale legacy RTL file'
);

$presentation_contracts = array(
    array( $analytics_css, '.nmkr-analytics-wrap', 'Analytics CSS is page scoped' ),
    array( $analytics_css, '@media (max-width: 782px)', 'Analytics covers WordPress narrow-admin widths' ),
    array( $settings_css, '.nmkr-settings-wrap', 'Settings CSS is page scoped' ),
    array( $settings_css, '@media (max-width: 782px)', 'Settings covers WordPress narrow-admin widths' ),
    array( $settings_css, '@media (prefers-reduced-motion: reduce)', 'Settings disclosure respects reduced motion' ),
);
foreach ( $presentation_contracts as $contract ) {
    nmkr_analytics_settings_ui_assert(
        false !== strpos( $contract[0], $contract[1] ),
        $contract[2]
    );
}

echo "Analytics + Settings admin UI regression: PASS\n";
