<?php
// Shortcode function to display NMKR projects and tokens in a list view with project details, search, filter, and a dropdown for project selection
function nmkr_shortcode_list($atts) {
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
        /* Custom styles for List View */
        .nmkr-project-details {
            border: 1px solid #ddd;
            background-color: #f9f9f9;
            padding: 20px;
            margin-bottom: 20px;
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
        .nmkr-project-selector {
            margin: 20px 0;
            text-align: center;
        }
        .nmkr-project-select {
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #ddd;
            min-width: 200px;
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

    // Add project selector
    $output .= '<div class="nmkr-project-selector">';
    $output .= '<select class="nmkr-project-select" onchange="window.location.href=this.value">';
    foreach ($projects as $project) {
        $selected = ($project->project_uid === $selected_project_uid) ? 'selected' : '';
        $output .= sprintf(
            '<option value="%s" %s>%s</option>',
            add_query_arg('nmkr_project', $project->project_uid),
            $selected,
            esc_html($project->project_name)
        );
    }
    $output .= '</select>';
    $output .= '</div>';

    // Add search and filter
    $output .= '<div class="nmkr-search-filter">';
    $output .= '<form method="get">';
    $output .= '<input type="hidden" name="nmkr_project" value="' . esc_attr($selected_project_uid) . '">';
    $output .= '<input type="text" name="search_token" class="nmkr-search-input" placeholder="Search tokens..." value="' . esc_attr($search_query) . '">';
    $output .= '<select name="filter_minted" class="nmkr-filter-select">';
    $output .= '<option value="">All Status</option>';
    $output .= '<option value="1" ' . selected($filter_minted, '1', false) . '>Minted</option>';
    $output .= '<option value="0" ' . selected($filter_minted, '0', false) . '>Unminted</option>';
    $output .= '</select>';
    $output .= '<button type="submit">Search</button>';
    $output .= '</form>';
    $output .= '</div>';

    // Add project details
    $output .= '<div class="nmkr-project-details">';
    $output .= '<h2>' . esc_html($selected_project->project_name) . '</h2>';
    if (!empty($selected_project->project_logo)) {
        $output .= '<img src="' . esc_url($selected_project->project_logo) . '" alt="' . esc_attr($selected_project->project_name) . '" style="max-width: 200px; margin: 10px 0;">';
    }
    if (!empty($selected_project->description)) {
        $output .= '<p>' . esc_html($selected_project->description) . '</p>';
    }
    $output .= '<p>Total Tokens: ' . esc_html($selected_project->total_tokens) . '</p>';
    $output .= '<p>Minted: ' . esc_html($selected_project->minted) . '</p>';
    $output .= '<p>Available: ' . esc_html($selected_project->free) . '</p>';
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
        $output .= '<tr>';
        
        // Token image
        $output .= '<td>';
        if (!empty($token->ipfs_link)) {
            $output .= '<img src="' . esc_url($token->ipfs_link) . '" alt="' . esc_attr($token->token_name) . '" class="nmkr-token-image">';
        }
        $output .= '</td>';
        
        // Token name
        $output .= '<td>' . esc_html($token->token_name) . '</td>';
        
        // Token status
        $status_class = '';
        $status_text = '';
        if ($token->minted) {
            $status_class = 'minted';
            $status_text = 'Minted';
        } else if (!empty($token->reserved_until)) {
            $status_class = 'reserved';
            $status_text = 'Reserved';
        } else if (!empty($token->sell_date)) {
            $status_class = 'sold';
            $status_text = 'Sold';
        } else {
            $status_class = 'unminted';
            $status_text = 'Available';
        }
        $output .= '<td><span class="nmkr-token-status ' . $status_class . '">' . $status_text . '</span></td>';
        
        // Token price
        $output .= '<td>';
        if (!empty($token->price)) {
            $output .= esc_html($token->price) . ' ADA';
        }
        $output .= '</td>';
        
        // Token series
        $output .= '<td>' . esc_html($token->series) . '</td>';
        
        // Token asset
        $output .= '<td>' . esc_html($token->asset_name) . '</td>';
        
        // Token actions
        $output .= '<td>';
        if (!$token->minted && empty($token->reserved_until) && empty($token->sell_date)) {
            if (!empty($token->payment_gateway_link)) {
                $output .= '<a href="' . esc_url($token->payment_gateway_link) . '" class="nmkr-token-button" target="_blank">Purchase</a>';
            }
        }
        $output .= '</td>';
        
        $output .= '</tr>';
    }
    $output .= '</tbody>';
    $output .= '</table>';

    return $output;
}

// Register the shortcode
add_shortcode('nmkr-token-list', 'nmkr_shortcode_list');
?>