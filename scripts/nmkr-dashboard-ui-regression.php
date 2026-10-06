<?php
/**
 * Source-level regression for the Dashboard operator-first UI contract.
 */

$root = dirname( __DIR__ );

function nmkr_dashboard_ui_assert( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

$core = file_get_contents( $root . '/includes/pages/dashboard/nmkr-dashboard-core.php' );
$ui   = file_get_contents( $root . '/includes/pages/dashboard/nmkr-dashboard-ui.php' );
$css  = file_get_contents( $root . '/css/admin/nmkr-dashboard.css' );

$core_contracts = array(
    'nmkr-dashboard-header'      => 'Dashboard has an operator-oriented page header',
    'nmkr-dashboard-primary-grid'=> 'connection and sync controls share the primary operational area',
    'nmkr_render_api_status_panel();' => 'API status remains in the Dashboard flow',
    'nmkr_render_sync_data_panel($dashboard_nonce, $can_manage_sync);' => 'sync controls remain in the Dashboard flow',
    'nmkr_render_sync_statistics_panel($initial_stats);' => 'sync statistics remain in the Dashboard flow',
);
foreach ( $core_contracts as $needle => $message ) {
    nmkr_dashboard_ui_assert( false !== strpos( $core, $needle ), $message );
}

$ui_contracts = array(
    'id="api-status"'                    => 'API status target remains stable',
    'role="status" aria-live="polite"'   => 'Dashboard status output is announced accessibly',
    'id="nmkr-sync-button"'              => 'start synchronization control remains stable',
    'id="nmkr-stop-sync-button"'         => 'stop synchronization control remains stable',
    'id="nmkr-sync-progress-container"'  => 'progressbar target remains stable',
    'aria-label="Synchronization progress"' => 'progressbar has an accessible name',
    'id="status-message" role="status"'  => 'sync status is exposed as a live status region',
    'nmkr-active-metrics-summary'        => 'active run summary is separated from performance diagnostics',
    'Live performance details'           => 'active performance telemetry is progressively disclosed',
    'nmkr-summary-grid'                  => 'latest synchronized state uses a scannable summary grid',
    'id="nmkr-sync-summary-message"'      => 'summary guidance has a stable refresh target',
    'id="latest-run-result"'             => 'latest run detail target remains stable',
    'id="performance-stats"'             => 'performance statistics target remains stable',
    'id="nmkr-performance-details"'      => 'performance diagnostics are progressively disclosed',
    'id="nmkr-debug-logs" class="nmkr-dashboard-diagnostics"' => 'debug-log deep link targets the diagnostics disclosure',
);
foreach ( $ui_contracts as $needle => $message ) {
    nmkr_dashboard_ui_assert( false !== strpos( $ui, $needle ), $message );
}

nmkr_dashboard_ui_assert(
    false === strpos( $ui, 'style="display: none; text-align: center; margin-top: 15px; font-size: 14px;"' ),
    'active metrics no longer rely on legacy inline layout styling'
);

nmkr_dashboard_ui_assert(
    false === strpos( $css, 'margin-bottom: 160px' ),
    'Dashboard no longer reserves a fixed 160px gap for active metrics'
);

$css_contracts = array(
    'max-width: 1180px;'                     => 'Dashboard uses a wider operational canvas',
    '.nmkr-dashboard-primary-grid'           => 'primary operational grid is styled',
    '.nmkr-summary-grid'                     => 'summary cards are responsive',
    '.nmkr-dashboard-diagnostics'            => 'diagnostics disclosure is styled',
    '@media (max-width: 782px)'               => 'WordPress narrow-admin breakpoint is covered',
    '@media (max-width: 480px)'               => 'small mobile layout is covered',
    '@media (prefers-reduced-motion: reduce)' => 'reduced-motion behavior is covered',
);
foreach ( $css_contracts as $needle => $message ) {
    nmkr_dashboard_ui_assert( false !== strpos( $css, $needle ), $message );
}

echo "Dashboard UI regression: PASS\n";
