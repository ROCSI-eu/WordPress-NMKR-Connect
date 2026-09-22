<?php
/**
 * Public-safe regression for the ROCSI Connector for NMKR admin menu hierarchy.
 */

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/../' );
}

$GLOBALS['nmkr_menu_calls'] = array();
$GLOBALS['nmkr_submenu_calls'] = array();
$GLOBALS['nmkr_actions'] = array();

function add_action( $hook, $callback, $priority = 10 ) {
    $GLOBALS['nmkr_actions'][] = array( $hook, $callback, $priority );
}

function add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $callback = '', $icon_url = '', $position = null ) {
    $GLOBALS['nmkr_menu_calls'][] = array(
        'page_title' => $page_title,
        'menu_title' => $menu_title,
        'capability' => $capability,
        'menu_slug'  => $menu_slug,
        'callback'   => $callback,
        'icon_url'   => $icon_url,
        'position'   => $position,
    );
    return 'toplevel_page_' . $menu_slug;
}

function add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback = '' ) {
    $GLOBALS['nmkr_submenu_calls'][] = array(
        'parent_slug' => $parent_slug,
        'page_title'  => $page_title,
        'menu_title'  => $menu_title,
        'capability'  => $capability,
        'menu_slug'   => $menu_slug,
        'callback'    => $callback,
    );
    return $parent_slug . '_page_' . $menu_slug;
}

function __( $text ) {
    return $text;
}

function nmkr_admin_menu_assert( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

require_once __DIR__ . '/../includes/menus/nmkr-admin-menu.php';

nmkr_connect_admin_menu();

nmkr_admin_menu_assert( 1 === count( $GLOBALS['nmkr_menu_calls'] ), 'one top-level menu must be registered' );

$menu = $GLOBALS['nmkr_menu_calls'][0];
nmkr_admin_menu_assert( 'nmkr_access_plugin' === $menu['capability'], 'top-level capability must remain nmkr_access_plugin' );
nmkr_admin_menu_assert( 'nmkr-connect-dashboard' === $menu['menu_slug'], 'top-level slug must remain stable' );
nmkr_admin_menu_assert( 'nmkr_connect_dashboard_page' === $menu['callback'], 'top-level callback must remain stable' );
nmkr_admin_menu_assert( 81 === $menu['position'], 'top-level menu must be placed below core Settings at position 81' );
nmkr_admin_menu_assert( $menu['position'] > 80, 'top-level menu must no longer compete with high-position core navigation' );

$expected = array(
    array( 'nmkr-connect-dashboard', 'nmkr_view_dashboard', 'nmkr-connect-dashboard', 'nmkr_connect_dashboard_page' ),
    array( 'nmkr-connect-dashboard', 'nmkr_view_projects', 'nmkr-connect-projects', 'nmkr_connect_projects_page' ),
    array( 'nmkr-connect-dashboard', 'nmkr_view_shortcodes', 'nmkr-connect-shortcodes', 'nmkr_display_shortcodes_page' ),
    array( 'nmkr-connect-dashboard', 'nmkr_view_analytics', 'nmkr-connect-analytics', 'nmkr_connect_analytics_page' ),
);

nmkr_admin_menu_assert( count( $expected ) === count( $GLOBALS['nmkr_submenu_calls'] ), 'submenu count must remain stable' );

foreach ( $expected as $index => $contract ) {
    $submenu = $GLOBALS['nmkr_submenu_calls'][ $index ];
    nmkr_admin_menu_assert( $contract[0] === $submenu['parent_slug'], 'submenu parent must remain stable at index ' . $index );
    nmkr_admin_menu_assert( $contract[1] === $submenu['capability'], 'submenu capability must remain stable at index ' . $index );
    nmkr_admin_menu_assert( $contract[2] === $submenu['menu_slug'], 'submenu slug/order must remain stable at index ' . $index );
    nmkr_admin_menu_assert( $contract[3] === $submenu['callback'], 'submenu callback must remain stable at index ' . $index );
}

$settings_source = file_get_contents( __DIR__ . '/../includes/pages/settings/nmkr-settings-core.php' );
nmkr_admin_menu_assert( false !== strpos( $settings_source, "add_options_page(" ), 'settings page must remain under core Settings' );
nmkr_admin_menu_assert( false !== strpos( $settings_source, "'nmkr_manage_settings'" ), 'settings capability must remain nmkr_manage_settings' );
nmkr_admin_menu_assert( false !== strpos( $settings_source, "'nmkr-connect-settings'" ), 'settings page slug must remain stable' );

echo "Admin menu placement regression: PASS\n";
