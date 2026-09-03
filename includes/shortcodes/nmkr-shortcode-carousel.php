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
        return '<p>' . esc_html__( 'No projects available. Please synchronize with NMKR Studio first.', 'nmkr-connect' ) . '</p>';
    }

    // Optional attribute to lock selection
    $allow_user_select = ! isset( $atts['allow_user_select'] ) || $atts['allow_user_select'] !== '0';

    // Table names with dynamic prefix
    $projects_table = $wpdb->prefix . 'nmkr_projects';

    // Fetch selected project details
    $selected_project = $wpdb->get_row($wpdb->prepare("SELECT * FROM $projects_table WHERE project_uid = %s", $active_project_uid));

    if (!$selected_project) {
        return '<p>' . esc_html__( 'Project not found.', 'nmkr-connect' ) . '</p>';
    }

    // Get project counters
    $counters = nmkr_get_project_counters( $active_project_uid );

    // Fetch all projects for selector
    $projects = $wpdb->get_results("SELECT * FROM $projects_table");

    // Handle token search/filter
    $search_query = isset($_GET['search_token']) ? sanitize_text_field($_GET['search_token']) : '';
    $filter_minted = isset($_GET['filter_minted']) ? sanitize_text_field($_GET['filter_minted']) : '';

    // Get tokens using joined query
    $tokens = nmkr_get_project_tokens_joined( $active_project_uid );
    if ( empty( $tokens ) ) {
        return '<p>' . esc_html__( 'No tokens found for this project.', 'nmkr-connect' ) . '</p>';
    }

    // Enqueue frontend analytics scaffold
    nmkr_enqueue_analytics_frontend();

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
    
    $output .= '
    <style>
        /* Custom styles for Carousel View */
        .nmkr-project-details {
            border: 1px solid #ddd;
            background-color: #f9f9f9;
            padding: 20px;
            margin-bottom: 20px;
            text-align: center;
        }

        .nmkr-project-details h2 {
            margin-bottom: 10px;
            font-size: 24px;
        }

        .nmkr-project-details p {
            margin-bottom: 5px;
        }

        .nmkr-project-counters {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 15px;
            margin: 15px 0;
            font-size: 14px;
        }
        .nmkr-counter-item {
            background-color: #fff;
            padding: 8px 15px;
            border-radius: 5px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .nmkr-subtitle-pane {
            text-align: center;
            margin-bottom: 20px;
        }

        /* Horizontal carousel styles */
        .nmkr-carousel-wrapper {
            position: relative; /* Added relative positioning to the wrapper */
        }

        .nmkr-carousel-container {
            display: flex;
            overflow-x: scroll;
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none;  /* IE 10+ */
            scroll-behavior: smooth;
            padding: 10px;
            gap: 20px;
        }

        /* Hide scrollbar for Webkit browsers */
        .nmkr-carousel-container::-webkit-scrollbar {
            display: none;
        }

        .nmkr-token {
            border: 1px solid #ccc;
            padding: 20px;
            text-align: center;
            background-color: #fff;
            min-width: 250px;
            transition: box-shadow 0.3s ease;
        }

        .nmkr-token:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .nmkr-token img {
            max-width: 100%;
            height: auto;
            margin-bottom: 10px;
            cursor: pointer;
        }



        /* Carousel arrow buttons */
        .carousel-prev, .carousel-next {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background-color: rgba(0, 0, 0, 0.5);
            color: white;
            border: none;
            font-size: 24px;
            padding: 12px;
            border-radius: 50%;
            cursor: pointer;
            z-index: 1; /* Ensures the arrows are above the carousel items */
        }

        .carousel-prev:hover, .carousel-next:hover {
            background-color: rgba(0, 0, 0, 0.8);
            box-shadow: 0px 0px 5px rgba(0, 0, 0, 0.5);
        }

        .carousel-prev {
            left: 0;
        }

        .carousel-next {
            right: 0;
        }

        /* Filter Form Styles */
        .nmkr-token-filter {
            display: flex;
            flex-direction: column; /* Stack items vertically */
            align-items: center;
            gap: 20px;
            margin: 20px 0 15px 0;
            background-color: #f9f9f9;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 10px;
            max-width: 1000px;
            margin-left: auto;
            margin-right: auto;
        }

        .nmkr-token-filter label {
            font-weight: bold;
            display: block; /* Ensure label appears above the input */
            text-align: left; /* Align labels to the left */
            width: 100%; /* Full width for better layout */
        }

        .nmkr-token-filter input,
        .nmkr-token-filter select {
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #ccc;
            font-size: 14px;
            width: 100%;
        }

        .filter-button {
            background-color: #0073aa;
            color: #fff;
            padding: 12px 30px;
            border-radius: 5px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            border: none;
            transition: background-color 0.3s ease;
            width: 100%; /* Full-width button to match the rest */
        }

        .filter-button:hover {
            background-color: #005e8c;
        }
        
        /* Price Badge Styling */
        .nmkr-token-price {
            margin: 10px 0;
        }
        
        .nmkr-price-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            margin-right: 8px;
        }
        
        .nmkr-price-ada {
            background-color: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }
        
        .nmkr-price-sol {
            background-color: #e3f2fd;
            color: #1565c0;
            border: 1px solid #bbdefb;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .nmkr-token-filter {
                flex-direction: column;
                gap: 10px;
            }

            .nmkr-token-filter label {
                text-align: left;
            }

            .nmkr-token-filter input,
            .nmkr-token-filter select,
            .filter-button {
                width: 100%;
            }
        }
    </style>

    <script>
        function scrollCarousel(direction) {
            const container = document.querySelector(".nmkr-carousel-container");
            const scrollAmount = container.offsetWidth / 2;
            if (direction === "left") {
                container.scrollBy({ left: -scrollAmount, behavior: "smooth" });
            } else {
                container.scrollBy({ left: scrollAmount, behavior: "smooth" });
            }
        }

        document.addEventListener("DOMContentLoaded", function() {
            // Auto-scroll logic
            let autoScroll = setInterval(function() {
                scrollCarousel("right");
            }, 5000);

            document.querySelector(".nmkr-carousel-container").addEventListener("mouseover", function() {
                clearInterval(autoScroll);
            });

            document.querySelector(".nmkr-carousel-container").addEventListener("mouseout", function() {
                autoScroll = setInterval(function() {
                    scrollCarousel("right");
                }, 5000);
            });
        });
    </script>
    ';

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
        $output .= '<div class="nmkr-counter-item"><strong>' . esc_html__('Total','nmkr-connect') . ':</strong> ' . esc_html($counters->total_tokens) . '</div>';
        $output .= '<div class="nmkr-counter-item"><strong>' . esc_html__('Minted','nmkr-connect') . ':</strong> ' . esc_html($counters->minted_count) . '</div>';
        $output .= '<div class="nmkr-counter-item"><strong>' . esc_html__('Sold','nmkr-connect') . ':</strong> ' . esc_html($counters->sold_count) . '</div>';
        $output .= '<div class="nmkr-counter-item"><strong>' . esc_html__('Reserved','nmkr-connect') . ':</strong> ' . esc_html($counters->reserved_active_count) . '</div>';
        $output .= '<div class="nmkr-counter-item"><strong>' . esc_html__('Available','nmkr-connect') . ':</strong> ' . esc_html($counters->available_count) . '</div>';
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
                    . ' aria-label="' . esc_attr__( 'Buy token with NMKR Pay', 'nmkr-connect' ) . '"'
                    . ' data-nmkr-evt="click" data-nmkr-cta="buy" data-nmkr-shortcode="carousel"'
                    . ' data-nmkr-project-uid="' . esc_attr($active_project_uid) . '"'
                    . ' data-nmkr-token-uid="' . esc_attr(!empty($token->token_uid) ? $token->token_uid : '') . '"'
                    . ' data-nmkr-id="carousel:' . esc_attr(!empty($token->token_uid) ? $token->token_uid : $active_project_uid) . '"'
                    . '>'
                    . '<span aria-hidden="true">💳</span> '
                    . esc_html__( 'Buy with NMKR Pay', 'nmkr-connect' )
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
