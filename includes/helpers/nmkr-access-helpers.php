<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Render a consistent "Access Denied" admin page.
 *
 * @param string $title Page title.
 */
function nmkr_render_access_denied_page( $title = '' ) {
    if ( ! current_user_can( 'read' ) ) {
        wp_die( esc_html__( 'Access denied.', 'nmkr-connect' ) );
    }

    $title = $title ? $title : __( 'Access denied', 'nmkr-connect' );
    $admin_email = sanitize_email( get_option( 'admin_email' ) );

    echo '<div class="wrap">';
    echo '<h1>' . esc_html( $title ) . '</h1>';
    echo '<p>' . esc_html__( 'You do not have permission to view this page. If you believe this is a mistake, please contact the site administrator.', 'nmkr-connect' ) . '</p>';

    if ( $admin_email ) {
        echo '<p>' . sprintf(
            /* translators: %s: admin email address */
            esc_html__( 'Administrator contact: %s', 'nmkr-connect' ),
            '<a href="mailto:' . esc_attr( $admin_email ) . '">' . esc_html( $admin_email ) . '</a>'
        ) . '</p>';
    }

    echo '</div>';
}


