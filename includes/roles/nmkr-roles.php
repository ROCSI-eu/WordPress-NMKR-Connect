<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * NMKR roles & capabilities (foundation).
 */
if ( ! defined( 'NMKR_CAPS_VERSION' ) ) { define( 'NMKR_CAPS_VERSION', 1 ); }

/**
 * Single source of truth for NMKR capabilities, roles and labels.
 *
 * @return array{caps: string[], roles: array<string, array<string,bool>>, labels: array<string,string>}
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
        'nmkr-admin' => array(
            'read'                 => true,
            'nmkr_access_plugin'   => true,
            'nmkr_view_dashboard'  => true,
            'nmkr_view_projects'   => true,
            'nmkr_view_shortcodes' => true,
            'nmkr_view_analytics'  => true,
            'nmkr_manage_settings' => true,
            'nmkr_manage_sync'     => true,
        ),

        // Restricted to Projects / Shortcodes / Analytics
        'nmkr-marketing' => array(
            'read'                 => true,
            'nmkr_access_plugin'   => true,
            'nmkr_view_projects'   => true,
            'nmkr_view_shortcodes' => true,
            'nmkr_view_analytics'  => true,
            // no dashboard/settings/sync caps
        ),
    );

    // Role display labels (translatable)
    $labels = array(
        'nmkr-admin'     => __( 'NMKR Admin', 'nmkr-connect' ),
        'nmkr-marketing' => __( 'NMKR Marketing', 'nmkr-connect' ),
    );

    return array( 'caps' => $caps, 'roles' => $roles, 'labels' => $labels );
}

/**
 * Create/update roles and grant caps (idempotent).
 * Called on plugin activation and via an admin_init safety-net.
 */
function nmkr_roles_install_caps() {
    $spec  = nmkr_roles_caps_spec();
    $caps  = $spec['caps'];
    $roles = $spec['roles'];
    $labels = isset( $spec['labels'] ) ? $spec['labels'] : array();

    // Ensure WP Administrator retains all NMKR caps
    if ( $admin = get_role( 'administrator' ) ) {
        foreach ( $caps as $cap ) {
            if ( ! $admin->has_cap( $cap ) ) {
                $admin->add_cap( $cap );
            }
        }
    }

    // Create or update custom roles (additive only) and enforce labels
    foreach ( $roles as $role_key => $role_caps ) {
        $label = isset( $labels[ $role_key ] ) ? $labels[ $role_key ] : ucwords( str_replace( '-', ' ', $role_key ) );
        $role  = get_role( $role_key );

        if ( ! $role ) {
            add_role( $role_key, $label, $role_caps );
            $role = get_role( $role_key );
        } else {
            // Ensure label is correct in roles option.
            $wp_roles = wp_roles(); // WP_Roles
            if ( isset( $wp_roles->roles[ $role_key ]['name'] ) && $wp_roles->roles[ $role_key ]['name'] !== $label ) {
                $wp_roles->roles[ $role_key ]['name'] = $label;
                $wp_roles->role_names[ $role_key ]     = $label;
                update_option( $wp_roles->role_key, $wp_roles->roles );
            }
        }

        // Ensure intended caps are present (additive; do not remove).
        if ( $role ) {
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

    // Also enforce role labels on every admin request
    $labels  = isset( $spec['labels'] ) ? $spec['labels'] : array();
    $wp_roles = wp_roles();

    foreach ( $labels as $role_key => $label ) {
        if ( isset( $wp_roles->roles[ $role_key ]['name'] ) && $wp_roles->roles[ $role_key ]['name'] !== $label ) {
            $wp_roles->roles[ $role_key ]['name'] = $label;
            $wp_roles->role_names[ $role_key ]     = $label;
            update_option( $wp_roles->role_key, $wp_roles->roles );
        }
    }
}



/** Remove only roles and capabilities installed by NMKR Connect. */
function nmkr_roles_uninstall_caps() {
    $spec        = nmkr_roles_caps_spec();
    $owned_roles = array_keys( $spec['roles'] );

    // Remove plugin-owned role assignments before deleting their definitions so
    // reinstalling the plugin cannot restore privileges from stale user meta.
    $user_ids = get_users(
        array(
            'role__in' => $owned_roles,
            'fields'   => 'ID',
        )
    );
    foreach ( $user_ids as $user_id ) {
        $user = new WP_User( $user_id );
        foreach ( $owned_roles as $role_key ) {
            if ( in_array( $role_key, (array) $user->roles, true ) ) {
                $user->remove_role( $role_key );
            }
        }
    }

    foreach ( $owned_roles as $role_key ) {
        remove_role( $role_key );
    }
    foreach ( wp_roles()->roles as $role_key => $unused ) {
        $role = get_role( $role_key );
        if ( ! $role ) { continue; }
        foreach ( $spec['caps'] as $cap ) {
            $role->remove_cap( $cap );
        }
    }
    delete_option( 'nmkr_caps_version' );
}
