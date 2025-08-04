<?php

function nmkr_connect_admin_menu() {
    // Main menu page for NMKR Connect
    add_menu_page(
        'NMKR Connect Dashboard',            // Page title
        'NMKR Connect',                      // Menu title
        'manage_options',                    // Capability
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
        'manage_options',                    // Capability
        'nmkr-connect-dashboard',            // Menu slug (same as main to avoid duplicating page)
        'nmkr_connect_dashboard_page'        // Callback function to show the Dashboard page
    );

    // Submenu for NFT Projects
    add_submenu_page(
        'nmkr-connect-dashboard',            // Parent slug
        'NFT Projects',                      // Page title
        'NFT Projects',                      // Menu title
        'manage_options',                    // Capability
        'nmkr-connect-projects',             // Menu slug
        'nmkr_connect_projects_page'         // Callback function for the NFT Projects page
    );

    // Submenu for Shortcodes
    add_submenu_page(
        'nmkr-connect-dashboard',            // Parent slug
        'Shortcodes',                        // Page title
        'Shortcodes',                        // Menu title
        'manage_options',                    // Capability
        'nmkr-connect-shortcodes',           // Menu slug
        'nmkr_display_shortcodes_page'       // Callback function for the Shortcodes page
    );
}
add_action('admin_menu', 'nmkr_connect_admin_menu');