<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Shortcode function to display NMKR projects and tokens in a carousel view with project details, search, filter, and a dropdown for project selection
function nmkr_shortcode_carousel($atts) {
    // Check if the user has the necessary plan (Starter or above)
    if (!wnc_fs()->can_use_premium_code()) {
        return '<p>This feature is only available in the Starter plan and above. <a href="' . esc_url(wnc_fs()->get_upgrade_url()) . '">Upgrade Now</a></p>';
    }

    global $wpdb;

    // Table names with dynamic prefix
    $projects_table = $wpdb->prefix . 'nmkr_projects';
    $tokens_table = $wpdb->prefix . 'nmkr_tokens';

    // Fetch all projects from the database
    $projects = $wpdb->get_results("SELECT * FROM $projects_table");

    // Initialize selected project
    $selected_project_uid = isset($_GET['nmkr_project']) ? esc_attr($_GET['nmkr_project']) : $projects[0]->project_uid;

    // Fetch selected project details
    $selected_project = $wpdb->get_row($wpdb->prepare("SELECT * FROM $projects_table WHERE project_uid = %s", $selected_project_uid));

    // Handle token search/filter
    $search_query = isset($_GET['search_token']) ? sanitize_text_field($_GET['search_token']) : '';
    $filter_minted = isset($_GET['filter_minted']) ? sanitize_text_field($_GET['filter_minted']) : '';

    // Build the token query based on filters
    $token_query = "SELECT t.*, td.* 
                    FROM $tokens_table t 
                    LEFT JOIN {$wpdb->prefix}nmkr_token_details td ON t.token_uid = td.token_uid 
                    WHERE t.project_uid = %s";
    $query_params = [$selected_project_uid];

    if (!empty($search_query)) {
        $token_query .= " AND (t.token_name LIKE %s OR td.title LIKE %s)";
        $query_params[] = '%' . $wpdb->esc_like($search_query) . '%';
        $query_params[] = '%' . $wpdb->esc_like($search_query) . '%';
    }

    if ($filter_minted !== '') {
        $token_query .= " AND t.minted = %d";
        $query_params[] = (int)$filter_minted;
    }

    // Fetch tokens based on the query
    $tokens = $wpdb->get_results($wpdb->prepare($token_query, ...$query_params));

    // Initialize output
    $output = '
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

        .nmkr-buy-button {
            display: inline-block;
            padding: 10px 20px;
            background-color: #11F250;
            color: black;
            text-decoration: none !important;
            border-radius: 5px;
            margin-top: 10px;
            font-weight: bold;
        }

        .nmkr-buy-button:hover {
            text-decoration: none !important;
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

        /* Lightbox styles */
        .lightbox {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            text-align: center;
        }

        .lightbox img {
            max-width: 90%;
            max-height: 80%;
            margin-top: 5%;
            box-shadow: 0 0 10px #fff;
            border: 2px solid #fff;
        }

        .lightbox:target {
            display: block;
        }

        .close-lightbox {
            position: absolute;
            top: 10px;
            right: 20px;
            color: white;
            font-size: 30px;
            text-decoration: none;
        }

        /* Dropdown styles */
        .nmkr-project-select {
            margin-bottom: 20px;
            text-align: center;
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
        function openLightbox(imageSrc) {
            var lightbox = document.getElementById("lightbox");
            lightbox.querySelector("img").src = imageSrc;
            lightbox.style.display = "block";
        }

        function closeLightbox() {
            document.getElementById("lightbox").style.display = "none";
        }

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
    
    <div id="lightbox" class="lightbox" onclick="closeLightbox()">
        <a href="#" class="close-lightbox">&times;</a>
        <img src="" alt="Token Image">
    </div>
    ';

    // Project selection dropdown
    $output .= '<div class="nmkr-project-select">';
    $output .= '<form method="get">';
    $output .= '<label for="nmkr_project">Select Project: </label>';
    $output .= '<select id="nmkr_project" name="nmkr_project" onchange="this.form.submit()">';

    foreach ($projects as $project) {
        $output .= '<option value="' . esc_attr($project->project_uid) . '" ' . selected($project->project_uid, $selected_project_uid, false) . '>' . esc_html($project->project_name) . '</option>';
    }

    $output .= '</select>';
    $output .= '</form>';
    $output .= '</div>';

    // Token search and filter form
    $output .= '<form method="get" class="nmkr-token-filter">';
    $output .= '<input type="hidden" name="nmkr_project" value="' . esc_attr($selected_project_uid) . '">';
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

    // Display selected project details
    if ($selected_project) {
        $output .= '<div class="nmkr-project-details">';
        $output .= '<h2>Project: ' . esc_html($selected_project->project_name) . '</h2>';
        if (!empty($selected_project->description)) {
            $output .= '<p>' . esc_html($selected_project->description) . '</p>';
        }
        $output .= '<p>Policy ID: <a href="https://cardanoscan.io/tokenPolicy/' . esc_html($selected_project->policy_id) . '" target="_blank">' . esc_html($selected_project->policy_id) . '</a></p>';
        $output .= '<p>Total Tokens: ' . esc_html($selected_project->total_tokens) . '</p>';
        $output .= '<p>Available Tokens: ' . esc_html($selected_project->free) . '</p>';
        if (!empty($selected_project->website)) {
            $output .= '<p>Website: <a href="' . esc_url($selected_project->website) . '" target="_blank">' . esc_html($selected_project->website) . '</a></p>';
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
            $img = nmkr_get_token_image_url( $token );
            if ( empty( $img ) ) {
                $img = plugins_url( 'images/placeholder.jpg', NMKR_CONNECT_PLUGIN_FILE );
            }

            $alt = '';
            if ( ! empty( $token->name ) ) {
                $alt = $token->name;
            } elseif ( ! empty( $token->asset_name ) ) {
                $alt = $token->asset_name;
            } else {
                $alt = 'Token';
            }
            // --- END: normalized slide image for [nmkr-carousel] ---
            
            $output .= '<div class="nmkr-token">';
            $output .= '<img src="' . esc_url( $img ) . '" alt="' . esc_attr( $alt ) . '" class="token-image" style="max-width: 100%; height: auto; margin-bottom: 10px;" onclick="openLightbox(\'' . esc_url( $img ) . '\')" loading="lazy" decoding="async" />';
            $output .= '<h4>' . esc_html($token->token_name) . '</h4>';
            $price_html = nmkr_render_token_price_badges( $token );
            if ( $price_html ) { $output .= $price_html; }
            $output .= '<p><strong>Minted:</strong> ' . esc_html($token->minted ? 'Yes' : 'No') . '</p>';
            $output .= '<a href="' . esc_url($token->payment_gateway_link) . '" class="nmkr-buy-button"><span>💳</span> Buy with NMKR Pay</a>';
            $output .= '</div>';
        }
        $output .= '</div>'; // Close carousel container
        $output .= '<button class="carousel-next" onclick="scrollCarousel(\'right\')">›</button>';
        $output .= '</div>'; // Close carousel wrapper
    } else {
        $output .= '<p>No tokens available for this project.</p>';
    }

    return $output;
}

// Register the shortcode
add_shortcode('nmkr-carousel', 'nmkr_shortcode_carousel');
?>