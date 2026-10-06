<?php
/**
 * Source-level regression for the shared admin UI foundation.
 */

$root = dirname( __DIR__ );

function nmkr_admin_ui_foundation_assert( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

$plugin = file_get_contents( $root . '/rocsi-connector-for-nmkr.php' );
$foundation_path = $root . '/css/admin/nmkr-admin-foundation.css';
nmkr_admin_ui_foundation_assert( is_file( $foundation_path ), 'shared admin foundation stylesheet exists' );

$foundation = file_get_contents( $foundation_path );
nmkr_admin_ui_foundation_assert(
    false !== strpos( $plugin, 'function nmkr_connect_is_plugin_admin_hook' ),
    'plugin declares an explicit admin-screen scope helper'
);
nmkr_admin_ui_foundation_assert(
    false !== strpos( $plugin, "'nmkr-admin-foundation'" )
        && false !== strpos( $plugin, "'css/admin/nmkr-admin-foundation.css'" ),
    'shared admin foundation stylesheet is enqueued'
);

$screen_fragments = array(
    'nmkr-connect-dashboard',
    'nmkr-connect-projects',
    'nmkr-connect-shortcodes',
    'nmkr-connect-analytics',
    'nmkr-connect-settings',
);
foreach ( $screen_fragments as $fragment ) {
    nmkr_admin_ui_foundation_assert(
        false !== strpos( $plugin, "'{$fragment}'" ),
        "plugin screen scope includes {$fragment}"
    );
}

$screen_roots = array(
    'includes/pages/dashboard/nmkr-dashboard-core.php',
    'includes/pages/projects/nmkr-projects.php',
    'includes/pages/shortcodes/nmkr-shortcodes.php',
    'includes/pages/analytics/nmkr-analytics-admin.php',
    'includes/pages/settings/nmkr-settings-core.php',
    'includes/helpers/nmkr-access-helpers.php',
);
foreach ( $screen_roots as $relative_path ) {
    $source = file_get_contents( $root . '/' . $relative_path );
    nmkr_admin_ui_foundation_assert(
        false !== strpos( $source, 'nmkr-admin-shell' ),
        "{$relative_path} opts into the shared admin shell"
    );
}

$required_foundation_selectors = array(
    '.nmkr-admin-shell .panel',
    '.nmkr-admin-shell .panel-header',
    '.nmkr-admin-shell .panel-actions',
    '.nmkr-admin-shell .nmkr-info-box',
    '.nmkr-admin-shell .status-excellent',
    '.nmkr-admin-shell a:focus-visible',
    '@media (max-width: 782px)',
    '@media (prefers-reduced-motion: reduce)',
);
foreach ( $required_foundation_selectors as $selector ) {
    nmkr_admin_ui_foundation_assert(
        false !== strpos( $foundation, $selector ),
        "foundation includes {$selector}"
    );
}

$page_styles = array(
    'css/admin/nmkr-dashboard.css',
    'css/admin/nmkr-projects.css',
    'css/admin/nmkr-shortcodes.css',
);
$centralized_selectors = array(
    '.nmkr-dashboard .panel {',
    '.nmkr-info-box {',
    '.nmkr-dashboard .status-excellent {',
);
foreach ( $page_styles as $relative_path ) {
    $source = file_get_contents( $root . '/' . $relative_path );
    foreach ( $centralized_selectors as $selector ) {
        nmkr_admin_ui_foundation_assert(
            false === strpos( $source, $selector ),
            "{$relative_path} does not redeclare centralized selector {$selector}"
        );
    }
}

nmkr_admin_ui_foundation_assert(
    false === strpos( $plugin, "if (strpos(\$hook, 'nmkr') !== false)" ),
    'admin assets are no longer scoped by an overly broad nmkr substring check'
);

echo "Admin UI foundation regression: PASS\n";
