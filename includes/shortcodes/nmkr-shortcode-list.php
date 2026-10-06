<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once dirname( __FILE__, 2 ) . '/helpers/nmkr-availability.php';
require_once dirname( __FILE__, 2 ) . '/helpers/nmkr-project-stats.php';
require_once dirname( __FILE__, 2 ) . '/helpers/nmkr-lightbox.php';
require_once dirname( __FILE__, 2 ) . '/helpers/nmkr-projects-util.php';
require_once dirname( __FILE__, 2 ) . '/helpers/nmkr-ui-helpers.php';

function nmkr_shortcode_list( $atts ) {
    global $wpdb;

    $atts = shortcode_atts(
        array(
            'project_uid'       => '',
            'allow_user_select' => '1',
        ),
        $atts,
        'nmkr_shortcode_list'
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
        'nmkr-shortcode-list',
        'css/nmkr-shortcode-list.css',
        array( 'nmkr-shortcode-foundation' )
    );

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

    $output = '<div class="nmkr-shortcode nmkr-shortcode-list">';

    if ( $allow_user_select ) {
        $output .= nmkr_render_project_selector_simple( $projects, $active_project_uid );
    }

    $output .= '<div class="nmkr-search-filter"><form method="get">';
    $output .= '<input type="hidden" name="nmkr_project" value="' . esc_attr( $active_project_uid ) . '">';
    $output .= '<label class="nmkr-search-field"><span>' . esc_html__( 'Search tokens', 'rocsi-connector-for-nmkr' ) . '</span>';
    $output .= '<input type="search" name="search_token" class="nmkr-search-input" placeholder="' . esc_attr__( 'Token name', 'rocsi-connector-for-nmkr' ) . '" value="' . esc_attr( $search_query ) . '"></label>';
    $output .= '<label class="nmkr-filter-field"><span>' . esc_html__( 'Minted status', 'rocsi-connector-for-nmkr' ) . '</span>';
    $output .= '<select name="filter_minted" class="nmkr-filter-select">';
    $output .= '<option value="">' . esc_html__( 'All statuses', 'rocsi-connector-for-nmkr' ) . '</option>';
    $output .= '<option value="1" ' . selected( $filter_minted, '1', false ) . '>' . esc_html__( 'Minted', 'rocsi-connector-for-nmkr' ) . '</option>';
    $output .= '<option value="0" ' . selected( $filter_minted, '0', false ) . '>' . esc_html__( 'Not minted', 'rocsi-connector-for-nmkr' ) . '</option>';
    $output .= '</select></label>';
    $output .= '<button type="submit" class="nmkr-filter-submit">' . esc_html__( 'Apply filters', 'rocsi-connector-for-nmkr' ) . '</button>';
    $output .= '</form></div>';

    $output .= '<section class="nmkr-project-details" aria-label="' . esc_attr__( 'Selected project', 'rocsi-connector-for-nmkr' ) . '">';
    $output .= '<div class="nmkr-project-header"><div class="nmkr-project-heading">';
    $output .= '<h2>' . esc_html( $selected_project->project_name ) . '</h2>';
    if ( ! empty( $selected_project->description ) ) {
        $output .= '<p class="nmkr-project-description">' . esc_html( $selected_project->description ) . '</p>';
    }
    $output .= '</div>';
    if ( ! empty( $selected_project->project_logo ) ) {
        $output .= '<img src="' . esc_url( $selected_project->project_logo ) . '" alt="' . esc_attr( $selected_project->project_name ) . '" class="nmkr-project-logo" loading="lazy" decoding="async">';
    }
    $output .= '</div>';

    $output .= '<div class="nmkr-project-counters" aria-label="' . esc_attr__( 'Project token statistics', 'rocsi-connector-for-nmkr' ) . '">';
    $output .= '<div class="nmkr-counter-item"><strong>' . esc_html__( 'Total', 'rocsi-connector-for-nmkr' ) . '</strong>' . esc_html( $counters->total_tokens ) . '</div>';
    $output .= '<div class="nmkr-counter-item"><strong>' . esc_html__( 'Minted', 'rocsi-connector-for-nmkr' ) . '</strong>' . esc_html( $counters->minted_count ) . '</div>';
    $output .= '<div class="nmkr-counter-item"><strong>' . esc_html__( 'Sold', 'rocsi-connector-for-nmkr' ) . '</strong>' . esc_html( $counters->sold_count ) . '</div>';
    $output .= '<div class="nmkr-counter-item"><strong>' . esc_html__( 'Reserved', 'rocsi-connector-for-nmkr' ) . '</strong>' . esc_html( $counters->reserved_active_count ) . '</div>';
    $output .= '<div class="nmkr-counter-item"><strong>' . esc_html__( 'Available', 'rocsi-connector-for-nmkr' ) . '</strong>' . esc_html( $counters->available_count ) . '</div>';
    $output .= '</div>';

    if ( ! empty( $selected_project->project_url ) ) {
        $output .= '<div class="nmkr-project-external"><a href="' . esc_url( $selected_project->project_url ) . '" target="_blank" rel="noopener noreferrer">'
            . esc_html__( 'Project website', 'rocsi-connector-for-nmkr' )
            . '<span class="screen-reader-text"> ' . esc_html__( '(opens in a new tab)', 'rocsi-connector-for-nmkr' ) . '</span></a></div>';
    }
    $output .= '</section>';

    if ( empty( $tokens ) ) {
        $output .= '<div class="nmkr-shortcode-state nmkr-shortcode-state-empty" role="status"><p>'
            . esc_html__( 'No tokens match the current filters.', 'rocsi-connector-for-nmkr' )
            . '</p></div>';
    } else {
        $output .= '<div class="nmkr-token-list-region" role="region" tabindex="0" aria-label="' . esc_attr__( 'Token list. Scroll horizontally on narrow screens.', 'rocsi-connector-for-nmkr' ) . '">';
        $output .= '<table class="nmkr-token-list">';
        $output .= '<thead><tr>';
        $output .= '<th scope="col">' . esc_html__( 'Image', 'rocsi-connector-for-nmkr' ) . '</th>';
        $output .= '<th scope="col">' . esc_html__( 'Name', 'rocsi-connector-for-nmkr' ) . '</th>';
        $output .= '<th scope="col">' . esc_html__( 'Status', 'rocsi-connector-for-nmkr' ) . '</th>';
        $output .= '<th scope="col">' . esc_html__( 'Price', 'rocsi-connector-for-nmkr' ) . '</th>';
        $output .= '<th scope="col">' . esc_html__( 'Series', 'rocsi-connector-for-nmkr' ) . '</th>';
        $output .= '<th scope="col">' . esc_html__( 'Asset', 'rocsi-connector-for-nmkr' ) . '</th>';
        $output .= '<th scope="col">' . esc_html__( 'Actions', 'rocsi-connector-for-nmkr' ) . '</th>';
        $output .= '</tr></thead><tbody>';

        foreach ( $tokens as $token ) {
            $token_uid = ! empty( $token->token_uid ) ? $token->token_uid : '';
            $output .= '<tr'
                . ' data-nmkr-evt="view"'
                . ' data-nmkr-shortcode="list"'
                . ' data-nmkr-project-uid="' . esc_attr( $active_project_uid ) . '"'
                . ' data-nmkr-token-uid="' . esc_attr( $token_uid ) . '"'
                . ' data-nmkr-id="list:' . esc_attr( $token_uid ? $token_uid : $active_project_uid ) . '"'
                . '>';

            $alt = ! empty( $token->token_name )
                ? $token->token_name
                : ( ! empty( $token->asset_name ) ? $token->asset_name : __( 'Token', 'rocsi-connector-for-nmkr' ) );

            $output .= '<td>' . nmkr_get_token_image_markup( $token, $alt, 'nmkr-token-image' ) . '</td>';
            $output .= '<td class="nmkr-list-token-name">' . esc_html( $alt ) . '</td>';

            $status_label = nmkr_token_status_label( $token );
            $status_class = strtolower( $status_label );
            $output .= '<td><span class="nmkr-token-status ' . esc_attr( $status_class ) . '">'
                . '<span class="screen-reader-text">' . esc_html__( 'Status:', 'rocsi-connector-for-nmkr' ) . ' </span>'
                . esc_html( $status_label )
                . '</span></td>';

            $output .= '<td>' . nmkr_render_token_price_badges( $token, false ) . '</td>';
            $output .= '<td>' . esc_html( $token->series ) . '</td>';
            $output .= '<td>' . esc_html( $token->asset_name ) . '</td>';

            $buyable = nmkr_token_is_buyable( $token );
            $output .= '<td class="nmkr-list-actions">';
            if ( $buyable && ! empty( $token->payment_gateway_link ) ) {
                $output .= '<a href="' . esc_url( $token->payment_gateway_link ) . '"'
                    . ' class="nmkr-buy-button"'
                    . ' target="_blank" rel="noopener noreferrer"'
                    . ' aria-label="' . esc_attr__( 'Buy token with NMKR Pay', 'rocsi-connector-for-nmkr' ) . '"'
                    . ' data-nmkr-evt="click" data-nmkr-cta="buy" data-nmkr-shortcode="list"'
                    . ' data-nmkr-project-uid="' . esc_attr( $active_project_uid ) . '"'
                    . ' data-nmkr-token-uid="' . esc_attr( $token_uid ) . '"'
                    . ' data-nmkr-id="list:' . esc_attr( $token_uid ? $token_uid : $active_project_uid ) . '"'
                    . '><span aria-hidden="true">💳</span> '
                    . esc_html__( 'Buy with NMKR Pay', 'rocsi-connector-for-nmkr' )
                    . '</a>';
            }
            $output .= '</td></tr>';
        }

        $output .= '</tbody></table></div>';
    }

    $output .= '</div>';

    if ( function_exists( 'nmkr_print_lightbox_once' ) ) {
        nmkr_print_lightbox_once();
    }

    return $output;
}

add_shortcode('nmkr-token-list', 'nmkr_shortcode_list');
