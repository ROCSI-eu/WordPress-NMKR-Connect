<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Include new helpers
require_once dirname(__FILE__,2) . '/helpers/nmkr-availability.php';
require_once dirname(__FILE__,2) . '/helpers/nmkr-project-stats.php';
require_once dirname(__FILE__,2) . '/helpers/nmkr-lightbox.php';
require_once dirname(__FILE__,2) . '/helpers/nmkr-projects-util.php';
require_once dirname(__FILE__,2) . '/helpers/nmkr-ui-helpers.php';

// Shortcode function to display NMKR projects and tokens in a carousel view with project details, search, filter, and a dropdown for project selection
function nmkr_shortcode_carousel($atts) {

    global $wpdb;

    // Attributes for project selection
    $atts = shortcode_atts(
        array(
            'project_uid' => '',
            'allow_user_select' => '1',
        ), $atts, 'nmkr_shortcode_carousel'
    );

    // Resolve the active project
    $active_project_uid = nmkr_get_active_project_uid_single( $atts );
    if ( empty( $active_project_uid ) ) {
        return '<p>' . esc_html__( 'No projects available. Please synchronize with NMKR Studio first.', 'rocsi-connector-for-nmkr' ) . '</p>';
    }

    // Optional attribute to lock selection
    $allow_user_select = ! isset( $atts['allow_user_select'] ) || $atts['allow_user_select'] !== '0';

    // Table names with dynamic prefix
    $projects_table = $wpdb->prefix . 'nmkr_projects';

    // Fetch selected project details
    $selected_project = $wpdb->get_row($wpdb->prepare("SELECT * FROM $projects_table WHERE project_uid = %s", $active_project_uid));

    if (!$selected_project) {
        return '<p>' . esc_html__( 'Project not found.', 'rocsi-connector-for-nmkr' ) . '</p>';
    }

    // Get project counters
    $counters = nmkr_get_project_counters( $active_project_uid );

    // Fetch all projects for selector
    $projects = $wpdb->get_results("SELECT * FROM $projects_table");

    // Handle token search/filter
    $search_query = isset( $_GET['search_token'] )
        ? sanitize_text_field( wp_unslash( $_GET['search_token'] ) )
        : '';
    $filter_minted = isset( $_GET['filter_minted'] )
        ? sanitize_text_field( wp_unslash( $_GET['filter_minted'] ) )
        : '';

    // Get tokens using joined query
    $tokens = nmkr_get_project_tokens_joined( $active_project_uid );
    if ( empty( $tokens ) ) {
        return '<p>' . esc_html__( 'No tokens found for this project.', 'rocsi-connector-for-nmkr' ) . '</p>';
    }

    // Enqueue frontend analytics scaffold
    nmkr_enqueue_analytics_frontend();
    nmkr_connect_enqueue_style_asset( 'nmkr-shortcode-carousel', 'css/nmkr-shortcode-carousel.css' );
    nmkr_connect_enqueue_script_asset( 'nmkr-carousel', 'js/nmkr-carousel.js', array(), true );

    // Apply filters if needed
    if (!empty($search_query) || $filter_minted !== '') {
        $filtered_tokens = [];
        foreach ($tokens as $token) {
            $matches_search = empty($search_query) ||
                stripos($token->token_name, $search_query) !== false ||
                (isset($token->title) && stripos($token->title, $search_query) !== false);

            $matches_minted = $filter_minted === '' ||
                (isset($token->minted) && (int)$token->minted === (int)$filter_minted);

            if ($matches_search && $matches_minted) {
                $filtered_tokens[] = $token;
            }
        }
        $tokens = $filtered_tokens;
    }

    // Initialize output
    $output = '';

    // Print buy button styles once
    if ( function_exists('nmkr_print_buy_button_styles_once') ) {
        nmkr_print_buy_button_styles_once();
    }

    // Render project selector if allowed
    if ( $allow_user_select ) {
        $output .= nmkr_render_project_selector_simple( $projects, $active_project_uid );
    }

    // Token search and filter form
    $output .= '<form method="get" class="nmkr-token-filter">';
    $output .= '<input type="hidden" name="nmkr_project" value="' . esc_attr($active_project_uid) . '">';
    $output .= '<div style="width:100%;">';
    $output .= '<label for="search_token">Search Token Name: </label>';
    $output .= '<input type="text" name="search_token" id="search_token" value="' . esc_attr($search_query) . '" placeholder="Enter token name"></div>';
    $output .= '<div style="width:100%;">';
    $output .= '<label for="filter_minted">Filter by Minted Status: </label>';
    $output .= '<select name="filter_minted" id="filter_minted">';
    $output .= '<option value="">-- All Tokens --</option>';
    $output .= '<option value="1" ' . selected($filter_minted, '1', false) . '>Minted</option>';
    $output .= '<option value="0" ' . selected($filter_minted, '0', false) . '>Not Minted</option>';
    $output .= '</select></div>';
    $output .= '<button type="submit" class="filter-button">Filter Tokens</button>';
    $output .= '</form>';

    // Display selected project details with counters
    if ($selected_project) {
        $output .= '<div class="nmkr-project-details">';
        $output .= '<h2>Project: ' . esc_html($selected_project->project_name) . '</h2>';
        if (!empty($selected_project->description)) {
            $output .= '<p>' . esc_html($selected_project->description) . '</p>';
        }
        $output .= '<p>Policy ID: <a href="https://cardanoscan.io/tokenPolicy/' . esc_html($selected_project->policy_id) . '" target="_blank">' . esc_html($selected_project->policy_id) . '</a></p>';

        // Project counters
        $output .= '<div class="nmkr-project-counters">';
        $output .= '<div class="nmkr-counter-item"><strong>' . esc_html__('Total','rocsi-connector-for-nmkr') . ':</strong> ' . esc_html($counters->total_tokens) . '</div>';
        $output .= '<div class="nmkr-counter-item"><strong>' . esc_html__('Minted','rocsi-connector-for-nmkr') . ':</strong> ' . esc_html($counters->minted_count) . '</div>';
        $output .= '<div class="nmkr-counter-item"><strong>' . esc_html__('Sold','rocsi-connector-for-nmkr') . ':</strong> ' . esc_html($counters->sold_count) . '</div>';
        $output .= '<div class="nmkr-counter-item"><strong>' . esc_html__('Reserved','rocsi-connector-for-nmkr') . ':</strong> ' . esc_html($counters->reserved_active_count) . '</div>';
        $output .= '<div class="nmkr-counter-item"><strong>' . esc_html__('Available','rocsi-connector-for-nmkr') . ':</strong> ' . esc_html($counters->available_count) . '</div>';
        $output .= '</div>';

        if (!empty($selected_project->project_url)) {
            $output .= '<p>Website: <a href="' . esc_url($selected_project->project_url) . '" target="_blank">' . esc_html($selected_project->project_url) . '</a></p>';
        }
        if (!empty($selected_project->twitter_handle)) {
            $output .= '<p>Twitter: <a href="https://twitter.com/' . esc_attr(ltrim($selected_project->twitter_handle, '@')) . '" target="_blank">@' . esc_html(ltrim($selected_project->twitter_handle, '@')) . '</a></p>';
        }
        $output .= '<p>Blockchain: Cardano</p>';
        $output .= '</div>';
    }

    // Subtitle pane for tokens
    $output .= '<div class="nmkr-subtitle-pane">';
    $output .= '<h3>Tokens for Project: ' . esc_html($selected_project->project_name) . '</h3>';
    $output .= '</div>';

    // Token carousel with navigation buttons
    if ($tokens) {
        $output .= '<div class="nmkr-carousel-wrapper">';
        $output .= '<button class="carousel-prev" onclick="scrollCarousel(\'left\')">‹</button>';
        $output .= '<div class="nmkr-carousel-container">';
        foreach ($tokens as $token) {

            // --- BEGIN: normalized slide image for [nmkr-carousel] ---
            $alt = '';
            if ( ! empty( $token->name ) ) {
                $alt = $token->name;
            } elseif ( ! empty( $token->asset_name ) ) {
                $alt = $token->asset_name;
            } else {
                $alt = 'Token';
            }
            // --- END: normalized slide image for [nmkr-carousel] ---

            $output .= '<div class="nmkr-token"'
                . ' data-nmkr-evt="view"'
                . ' data-nmkr-shortcode="carousel"'
                . ' data-nmkr-project-uid="' . esc_attr($active_project_uid) . '"'
                . ' data-nmkr-token-uid="' . esc_attr(!empty($token->token_uid) ? $token->token_uid : '') . '"'
                . ' data-nmkr-id="carousel:' . esc_attr(!empty($token->token_uid) ? $token->token_uid : $active_project_uid) . '"'
                . '>';
            $output .= nmkr_get_token_image_markup( $token, $alt, 'token-image', 'style="max-width: 100%; height: auto; margin-bottom: 10px;"' );
            $output .= '<h4>' . esc_html($token->token_name) . '</h4>';
            $price_html = nmkr_render_token_price_badges( $token );
            if ( $price_html ) { $output .= $price_html; }

            // Use helper functions for status and buyable logic
            $status_label = nmkr_token_status_label( $token );
            $buyable = nmkr_token_is_buyable( $token );

            $output .= '<p><strong>Status:</strong> ' . esc_html($status_label) . '</p>';

            if ( $buyable ) {
                $output .= '<a href="' . esc_url($token->payment_gateway_link) . '"'
                    . ' class="nmkr-buy-button"'
                    . ' target="_blank" rel="noopener noreferrer"'
                    . ' aria-label="' . esc_attr__( 'Buy token with NMKR Pay', 'rocsi-connector-for-nmkr' ) . '"'
                    . ' data-nmkr-evt="click" data-nmkr-cta="buy" data-nmkr-shortcode="carousel"'
                    . ' data-nmkr-project-uid="' . esc_attr($active_project_uid) . '"'
                    . ' data-nmkr-token-uid="' . esc_attr(!empty($token->token_uid) ? $token->token_uid : '') . '"'
                    . ' data-nmkr-id="carousel:' . esc_attr(!empty($token->token_uid) ? $token->token_uid : $active_project_uid) . '"'
                    . '>'
                    . '<span aria-hidden="true">💳</span> '
                    . esc_html__( 'Buy with NMKR Pay', 'rocsi-connector-for-nmkr' )
                    . '</a>';
            }
            $output .= '</div>';
        }
        $output .= '</div>'; // Close carousel container
        $output .= '<button class="carousel-next" onclick="scrollCarousel(\'right\')">›</button>';
        $output .= '</div>'; // Close carousel wrapper
    } else {
        $output .= '<p>No tokens available for this project.</p>';
    }

    // Print lightbox once
    if ( function_exists('nmkr_print_lightbox_once') ) {
        nmkr_print_lightbox_once();
    }

    return $output;
}

// Register the shortcode
add_shortcode('nmkr-carousel', 'nmkr_shortcode_carousel');
?>
