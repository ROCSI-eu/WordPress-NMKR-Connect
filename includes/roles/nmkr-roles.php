<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * NMKR roles & capabilities (foundation).
 */
define( 'NMKR_CAPS_VERSION', 1 );

/**
 * Single source of truth for NMKR capabilities and roles.
 *
 * @return array{caps: string[], roles: array<string, array<string,bool>>}
 */
function nmkr_roles_caps_spec() {
    $caps = array(
        'nmkr_access_plugin',     // See NMKR top-level menu
        'nmkr_view_dashboard',    // Dashboard page + read-only dashboard AJAX
        'nmkr_view_projects',     // NFT Projects page
        'nmkr_view_shortcodes',   // Shortcodes page
        'nmkr_view_analytics',    // Analytics UI + admin analytics AJAX
        'nmkr_manage_settings',   // Settings → NMKR Connect
        'nmkr_manage_sync',       // Start/stop/restart/cleanup sync & privileged ops
    );

    // Role capability maps
    $roles = array(
        // Full NMKR access (does NOT imply site-wide admin like manage_options)
        'nmkr-admin' => array_fill_keys( $caps, true ),

        // Restricted to Projects / Shortcodes / Analytics
        'nmkr-marketing' => array(
            'nmkr_access_plugin'   => true,
            'nmkr_view_projects'   => true,
            'nmkr_view_shortcodes' => true,
            'nmkr_view_analytics'  => true,
        ),
    );

    return array( 'caps' => $caps, 'roles' => $roles );
}

/**
 * Create/update roles and grant caps (idempotent).
 * Called on plugin activation and via an admin_init safety-net.
 */
function nmkr_roles_install_caps() {
    $spec  = nmkr_roles_caps_spec();
    $caps  = $spec['caps'];
    $roles = $spec['roles'];

    // Ensure WP Administrator retains all NMKR caps
    if ( $admin = get_role( 'administrator' ) ) {
        foreach ( $caps as $cap ) {
            if ( ! $admin->has_cap( $cap ) ) {
                $admin->add_cap( $cap );
            }
        }
    }

    // Create or update custom roles (additive only)
    foreach ( $roles as $role_key => $role_caps ) {
        $role = get_role( $role_key );
        if ( ! $role ) {
            add_role( $role_key, ucwords( str_replace( '-', ' ', $role_key ) ), $role_caps );
        } else {
            foreach ( $role_caps as $cap => $grant ) {
                if ( $grant && ! $role->has_cap( $cap ) ) {
                    $role->add_cap( $cap );
                }
            }
        }
    }

    update_option( 'nmkr_caps_version', NMKR_CAPS_VERSION, false );
}

/**
 * Safety net to ensure caps/roles after updates (in case activation didn't run).
 */
function nmkr_roles_ensure_caps() {
    $current = (int) get_option( 'nmkr_caps_version', 0 );
    if ( $current < NMKR_CAPS_VERSION ) {
        nmkr_roles_install_caps();
        return;
    }

    // Always ensure Administrator keeps all NMKR caps
    $spec = nmkr_roles_caps_spec();
    if ( $admin = get_role( 'administrator' ) ) {
        foreach ( $spec['caps'] as $cap ) {
            if ( ! $admin->has_cap( $cap ) ) {
                $admin->add_cap( $cap );
            }
        }
    }
}


