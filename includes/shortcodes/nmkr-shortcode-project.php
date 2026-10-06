<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once dirname( __FILE__, 2 ) . '/helpers/nmkr-availability.php';
require_once dirname( __FILE__, 2 ) . '/helpers/nmkr-project-stats.php';
require_once dirname( __FILE__, 2 ) . '/helpers/nmkr-projects-util.php';
require_once dirname( __FILE__, 2 ) . '/helpers/nmkr-lightbox.php';
require_once dirname( __FILE__, 2 ) . '/helpers/nmkr-ui-helpers.php';

function nmkr_shortcode_project( $atts ) {
    global $wpdb;

    $atts = shortcode_atts(
        array(
            'project_uid'       => '',
            'allow_user_select' => '1',
        ),
        $atts,
        'nmkr_shortcode_project'
    );

    nmkr_enqueue_shortcode_foundation();

    $active_project_uid = nmkr_get_active_project_uid_single( $atts );
    if ( empty( $active_project_uid ) ) {
        return nmkr_render_shortcode_state(
            __( 'No projects available. Please synchronize with NMKR Studio first.', 'rocsi-connector-for-nmkr' )
        );
    }

    $allow_user_select = ! isset( $atts['allow_user_select'] ) || '0' !== $atts['allow_user_select'];
    $projects_table    = $wpdb->prefix . 'nmkr_projects';

    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned table identifier; project UID is prepared.
    $project = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$projects_table} WHERE project_uid = %s",
            $active_project_uid
        )
    );

    if ( ! $project ) {
        return nmkr_render_shortcode_state(
            __( 'Selected project not found.', 'rocsi-connector-for-nmkr' ),
            'warning'
        );
    }

    $counters = nmkr_get_project_counters( $active_project_uid );

    nmkr_enqueue_analytics_frontend();
    nmkr_connect_enqueue_style_asset(
        'nmkr-shortcode-project',
        'css/nmkr-shortcode-project.css',
        array( 'nmkr-shortcode-foundation' )
    );

    if ( function_exists( 'nmkr_print_buy_button_styles_once' ) ) {
        nmkr_print_buy_button_styles_once();
    }

    $output = '<div class="nmkr-shortcode nmkr-shortcode-project">';

    if ( $allow_user_select ) {
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned table identifier.
        $projects = $wpdb->get_results( "SELECT project_uid, project_name, project_url FROM {$projects_table} ORDER BY created_at DESC" );
        $output .= nmkr_render_project_selector_simple( $projects, $active_project_uid );
    }

    $output .= '<article class="nmkr-single-project"'
        . ' data-nmkr-evt="view"'
        . ' data-nmkr-shortcode="project"'
        . ' data-nmkr-project-uid="' . esc_attr( $active_project_uid ) . '"'
        . ' data-nmkr-id="project:' . esc_attr( $active_project_uid ) . '"'
        . '>';

    $output .= '<div class="nmkr-project-profile"><div class="nmkr-project-heading">';
    $output .= '<h2>' . esc_html( $project->project_name ) . '</h2>';
    if ( ! empty( $project->description ) ) {
        $output .= '<p class="nmkr-project-description">' . esc_html( $project->description ) . '</p>';
    }
    $output .= '</div>';

    $logo = nmkr_get_project_logo_url( $project );
    if ( empty( $logo ) ) {
        $logo = plugins_url( 'images/placeholder.png', NMKR_CONNECT_PLUGIN_FILE );
    }

    $alt = ! empty( $project->project_name )
        ? $project->project_name . ' ' . __( 'logo', 'rocsi-connector-for-nmkr' )
        : __( 'Project logo', 'rocsi-connector-for-nmkr' );

    if ( ! empty( $project->project_logo ) ) {
        $output .= '<img'
            . ' src="' . esc_url( $logo ) . '"'
            . ' alt="' . esc_attr( $alt ) . '"'
            . ' loading="lazy" decoding="async"'
            . ' class="nmkr-project-logo"'
            . ' role="button" tabindex="0"'
            . ' aria-label="' . esc_attr( sprintf( __( 'Open image preview: %s', 'rocsi-connector-for-nmkr' ), $alt ) ) . '"'
            . ' data-nmkr-lightbox-trigger="1"'
            . '>';
    }
    $output .= '</div>';

    $output .= '<div class="nmkr-project-meta" aria-label="' . esc_attr__( 'Project token statistics', 'rocsi-connector-for-nmkr' ) . '">';
    $output .= '<div class="nmkr-project-meta-item"><strong>' . esc_html__( 'Total', 'rocsi-connector-for-nmkr' ) . '</strong>' . esc_html( $counters->total_tokens ) . '</div>';
    $output .= '<div class="nmkr-project-meta-item"><strong>' . esc_html__( 'Minted', 'rocsi-connector-for-nmkr' ) . '</strong>' . esc_html( $counters->minted_count ) . '</div>';
    $output .= '<div class="nmkr-project-meta-item"><strong>' . esc_html__( 'Sold', 'rocsi-connector-for-nmkr' ) . '</strong>' . esc_html( $counters->sold_count ) . '</div>';
    $output .= '<div class="nmkr-project-meta-item"><strong>' . esc_html__( 'Reserved', 'rocsi-connector-for-nmkr' ) . '</strong>' . esc_html( $counters->reserved_active_count ) . '</div>';
    $output .= '<div class="nmkr-project-meta-item"><strong>' . esc_html__( 'Available', 'rocsi-connector-for-nmkr' ) . '</strong>' . esc_html( $counters->available_count ) . '</div>';
    $output .= '</div>';

    $featured = nmkr_get_first_token_for_project( $active_project_uid, true );
    if ( $featured ) {
        $featured_name = ! empty( $featured->token_name )
            ? $featured->token_name
            : ( ! empty( $featured->asset_name ) ? $featured->asset_name : __( 'Token', 'rocsi-connector-for-nmkr' ) );
        $status_label = nmkr_token_status_label( $featured );
        $status_class = strtolower( $status_label );
        $buyable      = nmkr_token_is_buyable( $featured );

        $output .= '<section class="nmkr-featured-token" aria-label="' . esc_attr__( 'Featured token', 'rocsi-connector-for-nmkr' ) . '">';
        $output .= '<div class="nmkr-featured-token-media">';
        $output .= nmkr_get_token_image_markup( $featured, $featured_name, 'nmkr-token-image' );
        $output .= '</div><div class="nmkr-featured-token-content">';
        $output .= '<h3>' . esc_html__( 'Featured token', 'rocsi-connector-for-nmkr' ) . '</h3>';
        $output .= '<h4>' . esc_html( $featured_name ) . '</h4>';
        $output .= '<span class="nmkr-token-status ' . esc_attr( $status_class ) . '">'
            . '<span class="screen-reader-text">' . esc_html__( 'Status:', 'rocsi-connector-for-nmkr' ) . ' </span>'
            . esc_html( $status_label )
            . '</span>';

        $price_html = nmkr_render_token_price_badges( $featured );
        if ( $price_html ) {
            $output .= $price_html;
        }

        if ( $buyable && ! empty( $featured->payment_gateway_link ) ) {
            $output .= '<a href="' . esc_url( $featured->payment_gateway_link ) . '"'
                . ' class="nmkr-buy-button"'
                . ' target="_blank" rel="noopener noreferrer"'
                . ' aria-label="' . esc_attr__( 'Buy token with NMKR Pay', 'rocsi-connector-for-nmkr' ) . '"'
                . ' data-nmkr-evt="click" data-nmkr-cta="buy" data-nmkr-shortcode="project"'
                . ' data-nmkr-project-uid="' . esc_attr( $active_project_uid ) . '"'
                . ' data-nmkr-token-uid="' . esc_attr( ! empty( $featured->token_uid ) ? $featured->token_uid : '' ) . '"'
                . ' data-nmkr-id="project:' . esc_attr( $active_project_uid ) . '"'
                . '><span aria-hidden="true">💳</span> '
                . esc_html__( 'Buy with NMKR Pay', 'rocsi-connector-for-nmkr' )
                . '</a>';
        } else {
            $output .= '<p class="nmkr-token-availability">'
                . esc_html__( 'The featured token is not currently available for purchase.', 'rocsi-connector-for-nmkr' )
                . '</p>';
        }

        $output .= '</div></section>';
    } else {
        $output .= '<div class="nmkr-shortcode-state nmkr-shortcode-state-empty" role="status"><p>'
            . esc_html__( 'No featured token is available for this project.', 'rocsi-connector-for-nmkr' )
            . '</p></div>';
    }

    $output .= '<nav class="nmkr-project-links" aria-label="' . esc_attr__( 'Project links', 'rocsi-connector-for-nmkr' ) . '">';
    if ( ! empty( $project->policy_id ) ) {
        $output .= '<a href="' . esc_url( 'https://cardanoscan.io/tokenPolicy/' . rawurlencode( (string) $project->policy_id ) ) . '" class="nmkr-project-link" target="_blank" rel="noopener noreferrer">'
            . esc_html__( 'View on CardanoScan', 'rocsi-connector-for-nmkr' )
            . '<span class="screen-reader-text"> ' . esc_html__( '(opens in a new tab)', 'rocsi-connector-for-nmkr' ) . '</span></a>';
    }
    if ( ! empty( $project->project_url ) ) {
        $output .= '<a class="nmkr-project-link" target="_blank" rel="noopener noreferrer" href="' . esc_url( $project->project_url ) . '">'
            . esc_html__( 'Website', 'rocsi-connector-for-nmkr' )
            . '<span class="screen-reader-text"> ' . esc_html__( '(opens in a new tab)', 'rocsi-connector-for-nmkr' ) . '</span></a>';
    }
    if ( ! empty( $project->twitter_handle ) ) {
        $output .= '<a href="' . esc_url( 'https://twitter.com/' . rawurlencode( ltrim( (string) $project->twitter_handle, '@' ) ) ) . '" class="nmkr-project-link" target="_blank" rel="noopener noreferrer">'
            . esc_html__( 'Twitter', 'rocsi-connector-for-nmkr' )
            . '<span class="screen-reader-text"> ' . esc_html__( '(opens in a new tab)', 'rocsi-connector-for-nmkr' ) . '</span></a>';
    }
    $output .= '</nav>';

    $output .= '</article></div>';

    if ( function_exists( 'nmkr_print_lightbox_once' ) ) {
        nmkr_print_lightbox_once();
    }

    return $output;
}

add_shortcode('nmkr-project', 'nmkr_shortcode_project');
