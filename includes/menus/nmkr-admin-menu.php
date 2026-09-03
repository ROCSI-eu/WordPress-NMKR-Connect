<?php

function nmkr_connect_admin_menu() {
    // Main menu page for NMKR Connect
    add_menu_page(
        'NMKR Connect Dashboard',            // Page title
        'NMKR Connect',                      // Menu title
        'nmkr_access_plugin',                // Capability
        'nmkr-connect-dashboard',            // Menu slug
        'nmkr_connect_dashboard_page',       // Function to display the page content
        'dashicons-admin-generic',           // Icon for the menu
        6                                    // Position in the menu
    );

    // Dashboard submenu
    add_submenu_page(
        'nmkr-connect-dashboard',            // Parent slug
        'Dashboard',                         // Page title
        'Dashboard',                         // Menu title
        'nmkr_view_dashboard',               // Capability
        'nmkr-connect-dashboard',            // Menu slug (same as main to avoid duplicating page)
        'nmkr_connect_dashboard_page'        // Callback function to show the Dashboard page
    );

    // Submenu for NFT Projects
    add_submenu_page(
        'nmkr-connect-dashboard',            // Parent slug
        'NFT Projects',                      // Page title
        'NFT Projects',                      // Menu title
        'nmkr_view_projects',                // Capability
        'nmkr-connect-projects',             // Menu slug
        'nmkr_connect_projects_page'         // Callback function for the NFT Projects page
    );

    // Submenu for Shortcodes
    add_submenu_page(
        'nmkr-connect-dashboard',            // Parent slug
        'Shortcodes',                        // Page title
        'Shortcodes',                        // Menu title
        'nmkr_view_shortcodes',              // Capability
        'nmkr-connect-shortcodes',           // Menu slug
        'nmkr_display_shortcodes_page'       // Callback function for the Shortcodes page
    );

    // Submenu for Analytics
    if ( ! defined('NMKR_ANALYTICS_UI_ENABLED') || constant('NMKR_ANALYTICS_UI_ENABLED') ) {
        add_submenu_page(
            'nmkr-connect-dashboard',        // Parent slug
            __('Analytics', 'nmkr-connect'), // Page title
            __('Analytics', 'nmkr-connect'), // Menu title
            'nmkr_view_analytics',           // Capability
            'nmkr-connect-analytics',        // Menu slug
            'nmkr_connect_analytics_page'    // Callback function
        );
    }
}
add_action('admin_menu', 'nmkr_connect_admin_menu');

add_action( 'admin_init', function () {
    if ( ! is_admin() ) { return; }

    // Only act on the NMKR parent slug.
    $page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
    if ( 'nmkr-connect-dashboard' !== $page ) { return; }

    // If user can view dashboard, do nothing.
    if ( current_user_can( 'nmkr_view_dashboard' ) ) { return; }

    // Redirect to first allowed child page, else show Access Denied.
    $candidates = array(
        array( 'cap' => 'nmkr_view_projects',   'slug' => 'nmkr-connect-projects' ),
        array( 'cap' => 'nmkr_view_shortcodes', 'slug' => 'nmkr-connect-shortcodes' ),
        array( 'cap' => 'nmkr_view_analytics',  'slug' => 'nmkr-connect-analytics' ),
    );

    foreach ( $candidates as $c ) {
        if ( current_user_can( $c['cap'] ) ) {
            wp_safe_redirect( admin_url( 'admin.php?page=' . $c['slug'] ) );
            exit;
        }
    }

    if ( function_exists( 'nmkr_render_access_denied_page' ) ) {
        nmkr_render_access_denied_page( __( 'NMKR Connect', 'nmkr-connect' ) );
        exit;
    }

    // Fallback (should not happen if helper loaded)
    wp_die( esc_html__( 'Access denied.', 'nmkr-connect' ) );
}, 1 );

/**
 * Restrict visible NMKR submenus for users who cannot view the Dashboard
 * (e.g., nmkr-marketing). We allowlist only Projects, Shortcodes, Analytics.
 *
 * This runs late so the plugin controls its own submenu ordering.
 */
add_action( 'admin_menu', function () {
	if ( ! is_admin() ) {
		return;
	}

	// User must at least see the NMKR parent.
	if ( ! current_user_can( 'nmkr_access_plugin' ) ) {
		return;
	}

	// If the user can view the dashboard, we leave all submenus intact.
	if ( current_user_can( 'nmkr_view_dashboard' ) ) {
		return;
	}

	// Parent slug for NMKR menu.
	$parent = 'nmkr-connect-dashboard';

	// Only keep these submenus for restricted roles.
	$allowed = array( 'nmkr-connect-projects', 'nmkr-connect-shortcodes', 'nmkr-connect-analytics' );

	/**
	 * Filter the allowlisted submenus for restricted roles.
	 * @param string[] $allowed
	 */
	$allowed = apply_filters( 'nmkr_marketing_allowed_submenus', $allowed );

	global $submenu;

	if ( empty( $submenu[ $parent ] ) || ! is_array( $submenu[ $parent ] ) ) {
		return;
	}

	// Remove every submenu not explicitly allowed.
	foreach ( $submenu[ $parent ] as $item ) {
		// Structure: [0] => title, [1] => capability, [2] => slug, ...
		$slug = isset( $item[2] ) ? $item[2] : '';
		if ( $slug && ! in_array( $slug, $allowed, true ) ) {
			remove_submenu_page( $parent, $slug );
		}
	}
}, 100 );