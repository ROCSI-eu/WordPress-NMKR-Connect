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
$GLOBALS['nmkr_style_calls'] = array();
$GLOBALS['nmkr_current_user_caps'] = array( 'nmkr_access_plugin' => true );

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

function nmkr_connect_enqueue_style_asset( $handle, $relative_path, $dependencies = array() ) {
    $GLOBALS['nmkr_style_calls'][] = array(
        'handle'       => $handle,
        'relative_path' => $relative_path,
        'dependencies' => $dependencies,
    );
}

function current_user_can( $capability ) {
    return ! empty( $GLOBALS['nmkr_current_user_caps'][ $capability ] );
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
nmkr_admin_menu_assert( 'dashicons-admin-generic' !== $menu['icon_url'], 'generic gear icon must not return' );
nmkr_admin_menu_assert( 'dashicons-admin-links' === $menu['icon_url'], 'semantic links Dashicon remains the no-CSS fallback' );

nmkr_connect_enqueue_admin_menu_icon_styles();
nmkr_admin_menu_assert( 1 === count( $GLOBALS['nmkr_style_calls'] ), 'custom menu icon stylesheet must enqueue for authorized users' );
nmkr_admin_menu_assert( 'nmkr-admin-menu-icon' === $GLOBALS['nmkr_style_calls'][0]['handle'], 'custom menu icon stylesheet handle must remain stable' );
nmkr_admin_menu_assert( 'css/admin/nmkr-admin-menu-icon.css' === $GLOBALS['nmkr_style_calls'][0]['relative_path'], 'custom menu icon stylesheet path must remain local' );

$GLOBALS['nmkr_current_user_caps']['nmkr_access_plugin'] = false;
nmkr_connect_enqueue_admin_menu_icon_styles();
nmkr_admin_menu_assert( 1 === count( $GLOBALS['nmkr_style_calls'] ), 'menu icon stylesheet must not enqueue for users who cannot see the plugin menu' );
$GLOBALS['nmkr_current_user_caps']['nmkr_access_plugin'] = true;

$icon_css = file_get_contents( __DIR__ . '/../css/admin/nmkr-admin-menu-icon.css' );
$icon_svg = file_get_contents( __DIR__ . '/../images/nmkr-admin-menu-icon.svg' );
nmkr_admin_menu_assert( false !== strpos( $icon_css, '#toplevel_page_nmkr-connect-dashboard' ), 'menu icon CSS must remain scoped to the plugin top-level menu' );
nmkr_admin_menu_assert( false !== strpos( $icon_css, 'nmkr-admin-menu-icon.svg' ), 'menu icon CSS must reference the local custom SVG' );
nmkr_admin_menu_assert( false !== strpos( $icon_css, 'currentColor' ), 'menu icon must inherit WordPress admin color states' );
nmkr_admin_menu_assert( false !== strpos( $icon_css, '-webkit-mask:' ) && false !== strpos( $icon_css, 'mask:' ), 'menu icon must use the local SVG as a monochrome mask' );
nmkr_admin_menu_assert( false !== strpos( $icon_svg, 'viewBox="0 0 20 20"' ), 'custom menu icon must be authored at the WordPress admin glyph viewBox' );
nmkr_admin_menu_assert( false === strpos( $icon_css, 'http://' ) && false === strpos( $icon_css, 'https://' ), 'menu icon CSS must not introduce external requests' );
nmkr_admin_menu_assert( false === strpos( $icon_svg, '<image' ) && false === strpos( $icon_svg, '<script' ) && false === strpos( $icon_svg, 'href=' ) && false === strpos( $icon_svg, 'url(' ), 'menu icon SVG must contain no external/resource-loading primitives' );

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
