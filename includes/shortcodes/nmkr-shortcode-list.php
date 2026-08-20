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

// Shortcode function to display NMKR projects and tokens in a list view with project details, search, filter, and a dropdown for project selection
function nmkr_shortcode_list($atts) {
    global $wpdb;

    // Attributes for project selection
    $atts = shortcode_atts(
        array(
            'project_uid' => '',
            'allow_user_select' => '1',
        ), $atts, 'nmkr_shortcode_list'
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
        /* Custom styles for List View */
        .nmkr-project-details {
            border: 1px solid #ddd;
            background-color: #f9f9f9;
            padding: 20px;
            margin-bottom: 20px;
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
        .nmkr-token-list {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .nmkr-token-list th,
        .nmkr-token-list td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .nmkr-token-list th {
            background-color: #f5f5f5;
            font-weight: bold;
        }
        .nmkr-token-list tr:hover {
            background-color: #f9f9f9;
        }
        .nmkr-token-image {
            max-width: 100px;
            height: auto;
            border-radius: 4px;
            cursor: pointer;
        }
        .nmkr-token-status {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.9em;
        }
        .nmkr-token-status.minted {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        .nmkr-token-status.unminted {
            background-color: #fff3e0;
            color: #e65100;
        }
        .nmkr-token-status.reserved {
            background-color: #e3f2fd;
            color: #1565c0;
        }
        .nmkr-token-status.sold {
            background-color: #fbe9e7;
            color: #d84315;
        }
        .nmkr-token-button {
            display: inline-block;
            padding: 8px 16px;
            background-color: #2196f3;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            transition: background-color 0.3s ease;
        }
        .nmkr-token-button:hover {
            background-color: #1976d2;
        }
        .nmkr-token-button:disabled {
            background-color: #bdbdbd;
            cursor: not-allowed;
        }
        
        /* Price Badge Styling */
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
        .nmkr-search-filter {
            margin: 20px 0;
            text-align: center;
        }
        .nmkr-search-input {
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #ddd;
            min-width: 200px;
            margin-right: 10px;
        }
        .nmkr-filter-select {
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #ddd;
            margin-left: 10px;
        }
    </style>';

    // Render project selector if allowed
    if ( $allow_user_select ) {
        $output .= nmkr_render_project_selector_simple( $projects, $active_project_uid );
    }

    // Add search and filter
    $output .= '<div class="nmkr-search-filter">';
    $output .= '<form method="get">';
    $output .= '<input type="hidden" name="nmkr_project" value="' . esc_attr($active_project_uid) . '">';
    $output .= '<input type="text" name="search_token" class="nmkr-search-input" placeholder="Search tokens..." value="' . esc_attr($search_query) . '">';
    $output .= '<select name="filter_minted" class="nmkr-filter-select">';
    $output .= '<option value="">All Status</option>';
    $output .= '<option value="1" ' . selected($filter_minted, '1', false) . '>Minted</option>';
    $output .= '<option value="0" ' . selected($filter_minted, '0', false) . '>Unminted</option>';
    $output .= '</select>';
    $output .= '<button type="submit">Search</button>';
    $output .= '</form>';
    $output .= '</div>';

    // Add project details with counters
    $output .= '<div class="nmkr-project-details">';
    $output .= '<h2>' . esc_html($selected_project->project_name) . '</h2>';
    if (!empty($selected_project->project_logo)) {
        $output .= '<img src="' . esc_url($selected_project->project_logo) . '" alt="' . esc_attr($selected_project->project_name) . '" style="max-width: 200px; margin: 10px 0;">';
    }
    if (!empty($selected_project->description)) {
        $output .= '<p>' . esc_html($selected_project->description) . '</p>';
    }
    
    // Project counters
    $output .= '<div class="nmkr-project-counters">';
    $output .= '<div class="nmkr-counter-item"><strong>' . esc_html__('Total','nmkr-connect') . ':</strong> ' . esc_html($counters->total_tokens) . '</div>';
    $output .= '<div class="nmkr-counter-item"><strong>' . esc_html__('Minted','nmkr-connect') . ':</strong> ' . esc_html($counters->minted_count) . '</div>';
    $output .= '<div class="nmkr-counter-item"><strong>' . esc_html__('Sold','nmkr-connect') . ':</strong> ' . esc_html($counters->sold_count) . '</div>';
    $output .= '<div class="nmkr-counter-item"><strong>' . esc_html__('Reserved','nmkr-connect') . ':</strong> ' . esc_html($counters->reserved_active_count) . '</div>';
    $output .= '<div class="nmkr-counter-item"><strong>' . esc_html__('Available','nmkr-connect') . ':</strong> ' . esc_html($counters->available_count) . '</div>';
    $output .= '</div>';
    
    // Project links
    if (!empty($selected_project->project_url)) {
        $output .= '<p><strong>Website:</strong> <a href="' . esc_url($selected_project->project_url) . '" target="_blank">' . esc_html($selected_project->project_url) . '</a></p>';
    }
    $output .= '</div>';

    // Add token list
    $output .= '<table class="nmkr-token-list">';
    $output .= '<thead>';
    $output .= '<tr>';
    $output .= '<th>Image</th>';
    $output .= '<th>Name</th>';
    $output .= '<th>Status</th>';
    $output .= '<th>Price</th>';
    $output .= '<th>Series</th>';
    $output .= '<th>Asset</th>';
    $output .= '<th>Actions</th>';
    $output .= '</tr>';
    $output .= '</thead>';
    $output .= '<tbody>';

    foreach ($tokens as $token) {
        $output .= '<tr'
            . ' data-nmkr-evt="view"'
            . ' data-nmkr-shortcode="list"'
            . ' data-nmkr-project-uid="' . esc_attr($active_project_uid) . '"'
            . ' data-nmkr-token-uid="' . esc_attr(!empty($token->token_uid) ? $token->token_uid : '') . '"'
            . ' data-nmkr-id="list:' . esc_attr(!empty($token->token_uid) ? $token->token_uid : $active_project_uid) . '"'
            . '>';
        
        // Token image
        $output .= '<td>';
        
        // --- BEGIN: normalized token thumbnail for [nmkr-token-list] ---
        
        // Sensible alt text.
        $alt = '';
        if ( ! empty( $token->token_name ) ) {
            $alt = $token->token_name;
        } elseif ( ! empty( $token->asset_name ) ) {
            $alt = $token->asset_name;
        } else {
            $alt = 'Token';
        }
        
        $output .= nmkr_get_token_image_markup( $token, $alt, 'nmkr-token-image' );
        
        // --- END: normalized token thumbnail for [nmkr-token-list] ---
        
        $output .= '</td>';
        
        // Token name
        $output .= '<td>' . esc_html($token->token_name) . '</td>';
        
        // Token status using helper
        $status_label = nmkr_token_status_label( $token );
        $status_class = strtolower($status_label);
        $output .= '<td><span class="nmkr-token-status ' . $status_class . '">' . esc_html($status_label) . '</span></td>';
        
        // Token price
        $output .= '<td>';
        $output .= nmkr_render_token_price_badges( $token, false );
        $output .= '</td>';
        
        // Token series
        $output .= '<td>' . esc_html($token->series) . '</td>';
        
        // Token asset
        $output .= '<td>' . esc_html($token->asset_name) . '</td>';
        
        // Token actions using helper
        $buyable = nmkr_token_is_buyable( $token );
        $output .= '<td>';
        if ( $buyable ) {
            if (!empty($token->payment_gateway_link)) {
                $output .= '<a href="' . esc_url($token->payment_gateway_link) . '"'
                    . ' class="nmkr-buy-button"'
                    . ' target="_blank" rel="noopener noreferrer"'
                    . ' aria-label="' . esc_attr__( 'Buy token with NMKR Pay', 'nmkr-connect' ) . '"'
                    . ' data-nmkr-evt="click" data-nmkr-cta="buy" data-nmkr-shortcode="list"'
                    . ' data-nmkr-project-uid="' . esc_attr($active_project_uid) . '"'
                    . ' data-nmkr-token-uid="' . esc_attr(!empty($token->token_uid) ? $token->token_uid : '') . '"'
                    . ' data-nmkr-id="list:' . esc_attr(!empty($token->token_uid) ? $token->token_uid : $active_project_uid) . '"'
                    . '>'
                    . '<span aria-hidden="true">💳</span> '
                    . esc_html__( 'Buy with NMKR Pay', 'nmkr-connect' )
                    . '</a>';
            }
        }
        $output .= '</td>';
        
        $output .= '</tr>';
    }
    $output .= '</tbody>';
    $output .= '</table>';

    // Print lightbox once
    if ( function_exists('nmkr_print_lightbox_once') ) { 
        nmkr_print_lightbox_once(); 
    }

    return $output;
}

// Register the shortcode
add_shortcode('nmkr-token-list', 'nmkr_shortcode_list');
?>
