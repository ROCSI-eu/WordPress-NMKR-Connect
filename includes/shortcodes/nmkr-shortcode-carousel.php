<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once dirname( __FILE__, 2 ) . '/helpers/nmkr-availability.php';
require_once dirname( __FILE__, 2 ) . '/helpers/nmkr-project-stats.php';
require_once dirname( __FILE__, 2 ) . '/helpers/nmkr-lightbox.php';
require_once dirname( __FILE__, 2 ) . '/helpers/nmkr-projects-util.php';
require_once dirname( __FILE__, 2 ) . '/helpers/nmkr-ui-helpers.php';

function nmkr_shortcode_carousel( $atts ) {
    global $wpdb;

    $atts = shortcode_atts(
        array(
            'project_uid'       => '',
            'allow_user_select' => '1',
        ),
        $atts,
        'nmkr_shortcode_carousel'
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
    $selected_project = $wpdb->get_row(
        $wpdb->prepare( "SELECT * FROM {$projects_table} WHERE project_uid = %s", $active_project_uid )
    );

    if ( ! $selected_project ) {
        return nmkr_render_shortcode_state(
            __( 'Project not found.', 'rocsi-connector-for-nmkr' ),
            'warning'
        );
    }

    $counters = nmkr_get_project_counters( $active_project_uid );

    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned table identifier.
    $projects = $wpdb->get_results( "SELECT * FROM {$projects_table}" );

    $search_query = isset( $_GET['search_token'] )
        ? sanitize_text_field( wp_unslash( $_GET['search_token'] ) )
        : '';
    $filter_minted = isset( $_GET['filter_minted'] )
        ? sanitize_text_field( wp_unslash( $_GET['filter_minted'] ) )
        : '';

    $tokens = nmkr_get_project_tokens_joined( $active_project_uid );
    if ( empty( $tokens ) ) {
        return nmkr_render_shortcode_state(
            __( 'No tokens found for this project.', 'rocsi-connector-for-nmkr' )
        );
    }

    nmkr_enqueue_analytics_frontend();
    nmkr_connect_enqueue_style_asset(
        'nmkr-shortcode-carousel',
        'css/nmkr-shortcode-carousel.css',
        array( 'nmkr-shortcode-foundation' )
    );
    nmkr_connect_enqueue_script_asset( 'nmkr-carousel', 'js/nmkr-carousel.js', array(), true );

    $filters_active = '' !== $search_query || '' !== $filter_minted;
    if ( $filters_active ) {
        $filtered_tokens = array();
        foreach ( $tokens as $token ) {
            $matches_search = empty( $search_query )
                || stripos( (string) $token->token_name, $search_query ) !== false
                || ( isset( $token->title ) && stripos( (string) $token->title, $search_query ) !== false );

            $matches_minted = '' === $filter_minted
                || ( isset( $token->minted ) && (int) $token->minted === (int) $filter_minted );

            if ( $matches_search && $matches_minted ) {
                $filtered_tokens[] = $token;
            }
        }
        $tokens = $filtered_tokens;
    }

    if ( function_exists( 'nmkr_print_buy_button_styles_once' ) ) {
        nmkr_print_buy_button_styles_once();
    }

    static $instance = 0;
    $instance++;
    $carousel_id = 'nmkr-carousel-' . $instance;

    $output = '<div class="nmkr-shortcode nmkr-shortcode-carousel">';

    if ( $allow_user_select ) {
        $output .= nmkr_render_project_selector_simple( $projects, $active_project_uid );
    }

    $output .= '<form method="get" class="nmkr-token-filter">';
    $output .= '<input type="hidden" name="nmkr_project" value="' . esc_attr( $active_project_uid ) . '">';
    $output .= '<label class="nmkr-search-field"><span>' . esc_html__( 'Search tokens', 'rocsi-connector-for-nmkr' ) . '</span>';
    $output .= '<input type="search" name="search_token" class="nmkr-search-input" value="' . esc_attr( $search_query ) . '" placeholder="' . esc_attr__( 'Token name', 'rocsi-connector-for-nmkr' ) . '"></label>';
    $output .= '<label class="nmkr-filter-field"><span>' . esc_html__( 'Minted status', 'rocsi-connector-for-nmkr' ) . '</span>';
    $output .= '<select name="filter_minted" class="nmkr-filter-select">';
    $output .= '<option value="">' . esc_html__( 'All statuses', 'rocsi-connector-for-nmkr' ) . '</option>';
    $output .= '<option value="1" ' . selected( $filter_minted, '1', false ) . '>' . esc_html__( 'Minted', 'rocsi-connector-for-nmkr' ) . '</option>';
    $output .= '<option value="0" ' . selected( $filter_minted, '0', false ) . '>' . esc_html__( 'Not minted', 'rocsi-connector-for-nmkr' ) . '</option>';
    $output .= '</select></label>';
    $output .= '<button type="submit" class="filter-button">' . esc_html__( 'Apply filters', 'rocsi-connector-for-nmkr' ) . '</button>';
    $output .= '</form>';

    $output .= '<section class="nmkr-project-details" aria-label="' . esc_attr__( 'Selected project', 'rocsi-connector-for-nmkr' ) . '">';
    $output .= '<div class="nmkr-project-heading"><h2>' . esc_html( $selected_project->project_name ) . '</h2>';
    if ( ! empty( $selected_project->description ) ) {
        $output .= '<p class="nmkr-project-description">' . esc_html( $selected_project->description ) . '</p>';
    }
    $output .= '</div>';

    $output .= '<div class="nmkr-project-counters" aria-label="' . esc_attr__( 'Project token statistics', 'rocsi-connector-for-nmkr' ) . '">';
    $output .= '<div class="nmkr-counter-item"><strong>' . esc_html__( 'Total', 'rocsi-connector-for-nmkr' ) . '</strong>' . esc_html( $counters->total_tokens ) . '</div>';
    $output .= '<div class="nmkr-counter-item"><strong>' . esc_html__( 'Minted', 'rocsi-connector-for-nmkr' ) . '</strong>' . esc_html( $counters->minted_count ) . '</div>';
    $output .= '<div class="nmkr-counter-item"><strong>' . esc_html__( 'Sold', 'rocsi-connector-for-nmkr' ) . '</strong>' . esc_html( $counters->sold_count ) . '</div>';
    $output .= '<div class="nmkr-counter-item"><strong>' . esc_html__( 'Reserved', 'rocsi-connector-for-nmkr' ) . '</strong>' . esc_html( $counters->reserved_active_count ) . '</div>';
    $output .= '<div class="nmkr-counter-item"><strong>' . esc_html__( 'Available', 'rocsi-connector-for-nmkr' ) . '</strong>' . esc_html( $counters->available_count ) . '</div>';
    $output .= '</div>';

    $output .= '<div class="nmkr-project-external">';
    if ( ! empty( $selected_project->policy_id ) ) {
        $output .= '<a href="' . esc_url( 'https://cardanoscan.io/tokenPolicy/' . rawurlencode( (string) $selected_project->policy_id ) ) . '" target="_blank" rel="noopener noreferrer">'
            . esc_html__( 'View on CardanoScan', 'rocsi-connector-for-nmkr' )
            . '<span class="screen-reader-text"> ' . esc_html__( '(opens in a new tab)', 'rocsi-connector-for-nmkr' ) . '</span></a>';
    }
    if ( ! empty( $selected_project->project_url ) ) {
        $output .= '<a href="' . esc_url( $selected_project->project_url ) . '" target="_blank" rel="noopener noreferrer">'
            . esc_html__( 'Project website', 'rocsi-connector-for-nmkr' )
            . '<span class="screen-reader-text"> ' . esc_html__( '(opens in a new tab)', 'rocsi-connector-for-nmkr' ) . '</span></a>';
    }
    if ( ! empty( $selected_project->twitter_handle ) ) {
        $output .= '<a href="' . esc_url( 'https://twitter.com/' . rawurlencode( ltrim( (string) $selected_project->twitter_handle, '@' ) ) ) . '" target="_blank" rel="noopener noreferrer">'
            . esc_html__( 'Twitter', 'rocsi-connector-for-nmkr' )
            . '<span class="screen-reader-text"> ' . esc_html__( '(opens in a new tab)', 'rocsi-connector-for-nmkr' ) . '</span></a>';
    }
    $output .= '</div></section>';

    if ( empty( $tokens ) ) {
        $output .= '<div class="nmkr-shortcode-state nmkr-shortcode-state-empty" role="status"><p>'
            . esc_html__( 'No tokens match the current filters.', 'rocsi-connector-for-nmkr' )
            . '</p></div>';
    } else {
        $output .= '<div class="nmkr-carousel-heading"><h3>'
            . esc_html__( 'Project tokens', 'rocsi-connector-for-nmkr' )
            . '</h3><p>'
            . esc_html__( 'Use the arrow buttons, keyboard focus, trackpad, or touch to browse.', 'rocsi-connector-for-nmkr' )
            . '</p></div>';

        $output .= '<div class="nmkr-carousel-wrapper">';
        $output .= '<button type="button" class="nmkr-carousel-control carousel-prev" data-nmkr-carousel-direction="prev" aria-controls="' . esc_attr( $carousel_id ) . '" aria-label="' . esc_attr__( 'Previous tokens', 'rocsi-connector-for-nmkr' ) . '">‹</button>';
        $output .= '<div id="' . esc_attr( $carousel_id ) . '" class="nmkr-carousel-container" tabindex="0" role="region" aria-label="' . esc_attr__( 'Scrollable token carousel', 'rocsi-connector-for-nmkr' ) . '">';

        foreach ( $tokens as $token ) {
            $token_uid = ! empty( $token->token_uid ) ? $token->token_uid : '';
            $alt = ! empty( $token->token_name )
                ? $token->token_name
                : ( ! empty( $token->asset_name ) ? $token->asset_name : __( 'Token', 'rocsi-connector-for-nmkr' ) );

            $output .= '<article class="nmkr-token"'
                . ' data-nmkr-evt="view"'
                . ' data-nmkr-shortcode="carousel"'
                . ' data-nmkr-project-uid="' . esc_attr( $active_project_uid ) . '"'
                . ' data-nmkr-token-uid="' . esc_attr( $token_uid ) . '"'
                . ' data-nmkr-id="carousel:' . esc_attr( $token_uid ? $token_uid : $active_project_uid ) . '"'
                . '>';

            $output .= '<div class="nmkr-token-media">';
            $output .= nmkr_get_token_image_markup( $token, $alt, 'token-image' );
            $output .= '</div><div class="nmkr-carousel-card-body">';
            $output .= '<h4>' . esc_html( $alt ) . '</h4>';

            $status_label = nmkr_token_status_label( $token );
            $status_class = strtolower( $status_label );
            $output .= '<span class="nmkr-token-status ' . esc_attr( $status_class ) . '">'
                . '<span class="screen-reader-text">' . esc_html__( 'Status:', 'rocsi-connector-for-nmkr' ) . ' </span>'
                . esc_html( $status_label )
                . '</span>';

            $price_html = nmkr_render_token_price_badges( $token );
            if ( $price_html ) {
                $output .= $price_html;
            }

            $buyable = nmkr_token_is_buyable( $token );
            if ( $buyable && ! empty( $token->payment_gateway_link ) ) {
                $output .= '<a href="' . esc_url( $token->payment_gateway_link ) . '"'
                    . ' class="nmkr-buy-button"'
                    . ' target="_blank" rel="noopener noreferrer"'
                    . ' aria-label="' . esc_attr__( 'Buy token with NMKR Pay', 'rocsi-connector-for-nmkr' ) . '"'
                    . ' data-nmkr-evt="click" data-nmkr-cta="buy" data-nmkr-shortcode="carousel"'
                    . ' data-nmkr-project-uid="' . esc_attr( $active_project_uid ) . '"'
                    . ' data-nmkr-token-uid="' . esc_attr( $token_uid ) . '"'
                    . ' data-nmkr-id="carousel:' . esc_attr( $token_uid ? $token_uid : $active_project_uid ) . '"'
                    . '><span aria-hidden="true">💳</span> '
                    . esc_html__( 'Buy with NMKR Pay', 'rocsi-connector-for-nmkr' )
                    . '</a>';
            }

            $output .= '</div></article>';
        }

        $output .= '</div>';
        $output .= '<button type="button" class="nmkr-carousel-control carousel-next" data-nmkr-carousel-direction="next" aria-controls="' . esc_attr( $carousel_id ) . '" aria-label="' . esc_attr__( 'Next tokens', 'rocsi-connector-for-nmkr' ) . '">›</button>';
        $output .= '</div>';
    }

    $output .= '</div>';

    if ( function_exists( 'nmkr_print_lightbox_once' ) ) {
        nmkr_print_lightbox_once();
    }

    return $output;
}

add_shortcode('nmkr-carousel', 'nmkr_shortcode_carousel');
