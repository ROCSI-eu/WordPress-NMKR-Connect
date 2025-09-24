<?php
// Include necessary functions from split files
require_once plugin_dir_path(dirname(dirname(dirname(__FILE__)))) . 'includes/api/nmkr-api-functions.php';
require_once plugin_dir_path(dirname(dirname(dirname(__FILE__)))) . 'includes/synchronization/nmkr-sync-core.php';
require_once plugin_dir_path(dirname(dirname(dirname(__FILE__)))) . 'includes/synchronization/nmkr-sync-batch-processing.php';
require_once plugin_dir_path(dirname(dirname(dirname(__FILE__)))) . 'includes/synchronization/nmkr-sync-progress-tracking.php';
require_once plugin_dir_path(dirname(dirname(dirname(__FILE__)))) . 'includes/synchronization/nmkr-sync-error-handling.php';
require_once plugin_dir_path(dirname(dirname(dirname(__FILE__)))) . 'includes/synchronization/nmkr-sync-ajax-handlers.php';
require_once plugin_dir_path(dirname(dirname(dirname(__FILE__)))) . 'includes/database/nmkr-database-functions.php';
require_once plugin_dir_path(dirname(dirname(dirname(__FILE__)))) . 'includes/helpers/nmkr-performance-functions.php';
require_once plugin_dir_path(dirname(dirname(dirname(__FILE__)))) . 'includes/helpers/nmkr-utility-functions.php';
require_once plugin_dir_path(dirname(dirname(dirname(__FILE__)))) . 'includes/helpers/nmkr-media-helpers.php';
require_once plugin_dir_path(dirname(dirname(dirname(__FILE__)))) . 'includes/helpers/nmkr-availability.php';
require_once plugin_dir_path(dirname(dirname(dirname(__FILE__)))) . 'includes/helpers/nmkr-project-stats.php';
require_once plugin_dir_path(dirname(dirname(dirname(__FILE__)))) . 'includes/helpers/nmkr-lightbox.php';

function nmkr_connect_projects_page() {
    if ( ! current_user_can( 'nmkr_view_projects' ) ) {
        if ( function_exists( 'nmkr_render_access_denied_page' ) ) {
            nmkr_render_access_denied_page( __( 'NFT Projects', 'nmkr-connect' ) );
            return;
        }
        wp_die( esc_html__( 'Access denied.', 'nmkr-connect' ) );
    }
    global $wpdb;
    $projects_table = $wpdb->prefix . 'nmkr_projects';
    $tokens_table = $wpdb->prefix . 'nmkr_tokens';

    // Fetch projects from the database
    $projects = $wpdb->get_results("SELECT * FROM $projects_table");

    // Handle project selection
    $selected_project_uid = isset($_POST['project_uid']) ? sanitize_text_field($_POST['project_uid']) : '';

    // Handle token search/filter
    $search_query = isset($_POST['search_token']) ? sanitize_text_field($_POST['search_token']) : '';
    $filter_minted = isset($_POST['filter_minted']) ? sanitize_text_field($_POST['filter_minted']) : '';

    ?>
    <div class="wrap nmkr-dashboard">
        <h1 class="center-text">NMKR Projects and Tokens</h1>

        <div class="panel">
            <h2 class="center-text">Your Projects</h2>
            <div class="nmkr-info-box">
                <span class="dashicons dashicons-portfolio"></span>
                <p>Select a project to view its tokens and details. Projects are synchronized from the NMKR Studio platform.</p>
            </div>

            <!-- Project Selector Form -->
            <form method="post" class="project-selector-form panel-section">
                <div class="form-group">
                    <label for="project_uid">Select a Project to View Tokens:</label>
                    <select name="project_uid" id="project_uid" onchange="this.form.submit()">
                        <option value="">-- Select a Project --</option>
                        <?php foreach ($projects as $project): ?>
                            <option value="<?php echo esc_attr($project->project_uid); ?>" <?php selected($selected_project_uid, $project->project_uid); ?>>
                                <?php echo esc_html($project->project_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>

        <?php
        // If a project is selected, display its details and tokens
        if ($selected_project_uid):
            $selected_project = $wpdb->get_row($wpdb->prepare("SELECT * FROM $projects_table WHERE project_uid = %s", $selected_project_uid));
            if ($selected_project):
                // Get project counters
                $counters = nmkr_get_project_counters( $selected_project->project_uid );
        ?>
            <div class="panel">
                <h2 class="center-text"><?php echo esc_html($selected_project->project_name); ?></h2>
                <div class="nmkr-info-box">
                    <span class="dashicons dashicons-info"></span>
                    <p><?php echo esc_html($selected_project->description); ?></p>
                </div>
                
                <div class="project-details panel-section">
                    <p><strong>🌐 Website:</strong> <a href="<?php echo esc_url($selected_project->project_url); ?>" target="_blank"><?php echo esc_html($selected_project->project_url); ?></a></p>
                    <p><strong>🪙 Total Tokens:</strong> <?php echo esc_html($counters->total_tokens); ?></p>
                    <p><strong>✅ Available Tokens:</strong> <?php echo esc_html($counters->available_count); ?></p>
                </div>

                <!-- Optional: Display detailed counters -->
                <div class="project-stats panel-section">
                    <p class="nmkr-stats">
                        <strong><?php esc_html_e('Total','nmkr-connect'); ?>:</strong> <?php echo esc_html($counters->total_tokens); ?> ·
                        <strong><?php esc_html_e('Minted','nmkr-connect'); ?>:</strong> <?php echo esc_html($counters->minted_count); ?> ·
                        <strong><?php esc_html_e('Sold','nmkr-connect'); ?>:</strong> <?php echo esc_html($counters->sold_count); ?> ·
                        <strong><?php esc_html_e('Reserved','nmkr-connect'); ?>:</strong> <?php echo esc_html($counters->reserved_active_count); ?> ·
                        <strong><?php esc_html_e('Available','nmkr-connect'); ?>:</strong> <?php echo esc_html($counters->available_count); ?>
                    </p>
                </div>

                <!-- Token Search and Filter Form -->
                <form method="post" class="token-filter-form panel-section">
                    <input type="hidden" name="project_uid" value="<?php echo esc_attr($selected_project_uid); ?>">
                    <h3 class="center-text">Filter Tokens</h3>
                    <div class="filters-container">
                        <div class="form-group">
                            <label for="search_token">Search Token Name:</label>
                            <input type="text" name="search_token" id="search_token" value="<?php echo esc_attr($search_query); ?>" placeholder="Enter token name">
                        </div>
                        <div class="form-group">
                            <label for="filter_minted">Filter by Minted Status:</label>
                            <select name="filter_minted" id="filter_minted">
                                <option value="">-- All Tokens --</option>
                                <option value="1" <?php selected($filter_minted, '1'); ?>>Minted</option>
                                <option value="0" <?php selected($filter_minted, '0'); ?>>Not Minted</option>
                            </select>
                        </div>
                        <button type="submit" class="button button-primary">Apply Filter</button>
                    </div>
                </form>

                <!-- Token List -->
                <?php
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

                if (!empty($tokens)):
                ?>
                <div class="panel-section">
                    <h3 class="center-text">Project Tokens</h3>
                    <div class="token-grid">
                        <?php foreach ($tokens as $token): ?>
                            <div class="token-item">
                                <div class="token-image">
                                    <?php
                                    // --- BEGIN: normalized token preview (admin) ---
                                    $img = nmkr_get_token_image_url( $token );
                                    if ( empty( $img ) ) {
                                        $img = plugins_url( 'images/placeholder.png', dirname(__FILE__) );
                                    }

                                    $t_alt = '';
                                    if ( ! empty( $token->token_name ) ) {
                                        $t_alt = $token->token_name;
                                    } elseif ( ! empty( $token->asset_name ) ) {
                                        $t_alt = $token->asset_name;
                                    } else {
                                        $t_alt = 'Token';
                                    }
                                    // --- END: normalized token preview (admin) ---
                                    ?>
                                    <img
                                      src="<?php echo esc_url( $img ); ?>"
                                      alt="<?php echo esc_attr( $t_alt ); ?>"
                                      width="150"
                                      loading="lazy"
                                      decoding="async"
                                      onclick="if(window.openLightbox){openLightbox(this.src)}"
                                      style="cursor: pointer;"
                                    />
                                </div>
                                <div class="token-details">
                                    <p class="token-name"><strong><?php echo esc_html($token->token_name); ?></strong></p>
                                    <p class="token-status-container"><strong>Status:</strong> 
                                        <?php
                                        // Use helper function for status
                                        $status_label = nmkr_token_status_label( $token );
                                        $status_class = 'status-' . strtolower($status_label);
                                        echo '<span class="token-status ' . $status_class . '">' . esc_html($status_label) . '</span>';
                                        ?>
                                    </p>
                                    <?php
                                    $price_html = nmkr_render_token_price_badges( $token );
                                    if ( $price_html ) {
                                        echo $price_html; // safe, generated markup
                                    }
                                    ?>
                                    <?php if (!empty($token->series)): ?>
                                        <p><strong>Series:</strong> <?php echo esc_html($token->series); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($token->asset_name)): ?>
                                        <p><strong>Asset:</strong> <?php echo esc_html($token->asset_name); ?></p>
                                    <?php endif; ?>
                                    <?php 
                                    // Use helper function for buyable logic
                                    $buyable = nmkr_token_is_buyable( $token );
                                    if ( $buyable && !empty($token->payment_gateway_link) ): 
                                    ?>
                                        <a class="button button-primary" href="<?php echo esc_url($token->payment_gateway_link); ?>" target="_blank">Buy Now</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="panel-section">
                    <p class="center-text">No tokens found for this project.</p>
                </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="panel">
                <p class="center-text">Project not found.</p>
            </div>
        <?php endif; ?>
        <?php endif; ?>

        <!-- Styling for Projects Page -->
        <style>
            /* Global Panel Styling */
            .nmkr-dashboard {
                max-width: 1200px;
                margin: 0 auto;
            }
            
            .nmkr-dashboard .panel {
                background: #fff;
                border: 1px solid #e5e5e5;
                box-shadow: 0 1px 3px rgba(0,0,0,0.05);
                border-radius: 6px;
                padding: 20px;
                margin-bottom: 25px;
                position: relative;
                overflow: hidden;
            }
            
            .nmkr-dashboard .panel h2 {
                margin-top: 0;
                padding-bottom: 15px;
                border-bottom: 1px solid #f0f0f1;
            }
            
            .nmkr-dashboard .panel-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 15px;
            }
            
            .nmkr-dashboard .panel-section {
                padding: 15px 0;
                border-top: 1px solid #f0f0f1;
            }
            
            .nmkr-dashboard .panel-section:first-child {
                border-top: none;
                padding-top: 0;
            }
            
            .nmkr-dashboard .center-text {
                text-align: center;
            }
            
            /* Info Box Styling */
            .nmkr-info-box {
                display: flex;
                align-items: center;
                background-color: #f0f6fc;
                border-left: 4px solid #2271b1;
                padding: 12px 15px;
                margin-bottom: 20px;
                border-radius: 2px;
            }
            
            .nmkr-info-box .dashicons {
                font-size: 24px;
                color: #2271b1;
                margin-right: 12px;
            }
            
            .nmkr-info-box p {
                margin: 0;
                color: #50575e;
                font-size: 14px;
            }
            
            /* Status Colors */
            .nmkr-dashboard .status-excellent {
                color: #46b450;
            }
            
            .nmkr-dashboard .status-good {
                color: #ffb900;
            }
            
            .nmkr-dashboard .status-warning {
                color: #f56e28;
            }
            
            .nmkr-dashboard .status-critical {
                color: #dc3232;
            }
            
            .nmkr-dashboard .status-neutral {
                color: #666;
            }
            
            /* Project Form Styling */
            .project-selector-form {
                display: flex;
                justify-content: center;
                align-items: center;
            }
            
            .form-group {
                margin-bottom: 15px;
            }
            
            .form-group label {
                display: block;
                margin-bottom: 5px;
                font-weight: 500;
            }
            
            .form-group select, 
            .form-group input {
                width: 100%;
                max-width: 400px;
                padding: 8px 12px;
                border-radius: 4px;
                border: 1px solid #ddd;
            }
            
            /* Project Details */
            .project-details {
                text-align: center;
            }
            
            .project-details p {
                margin: 5px 0;
            }

            /* Project Stats */
            .project-stats {
                text-align: center;
                background-color: #f8f9fa;
                padding: 15px;
                border-radius: 4px;
            }
            
            .nmkr-stats {
                margin: 0;
                font-size: 14px;
            }
            
            /* Token Filter Form */
            .token-filter-form {
                text-align: center;
            }
            
            .filters-container {
                display: flex;
                flex-wrap: wrap;
                justify-content: center;
                gap: 15px;
                margin-top: 15px;
            }
            
            .filters-container .form-group {
                flex: 1;
                min-width: 200px;
                max-width: 300px;
            }
            
            /* Token Grid Styling */
            .token-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                gap: 20px;
                margin-top: 20px;
            }
            
            .token-item {
                border: 1px solid #e5e5e5;
                border-radius: 6px;
                overflow: hidden;
                transition: transform 0.2s, box-shadow 0.2s;
                background: #fff;
                box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            }
            
            .token-item:hover {
                transform: translateY(-5px);
                box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            }
            
            .token-image {
                text-align: center;
                padding: 10px;
                background-color: #f8f9fa;
                border-bottom: 1px solid #e5e5e5;
            }
            
            .token-image img {
                max-width: 100%;
                height: auto;
                object-fit: contain;
                border-radius: 4px;
            }
            
            .token-details {
                padding: 15px;
            }
            
            .token-details p {
                margin: 8px 0;
                font-size: 14px;
            }
            
            .token-name {
                font-size: 16px !important;
                margin-top: 0 !important;
                margin-bottom: 10px !important;
            }
            
            .token-status {
                display: inline-block;
                padding: 2px 8px;
                border-radius: 12px;
                font-size: 12px;
                background-color: #f0f0f1;
            }
            
            .button-primary {
                display: inline-block;
                margin-top: 10px;
                background-color: #2271b1;
                color: #fff;
                text-decoration: none;
                padding: 6px 12px;
                border-radius: 4px;
                border: none;
                cursor: pointer;
                font-size: 14px;
                transition: background-color 0.2s;
            }
            
            .button-primary:hover {
                background-color: #135e96;
                color: #fff;
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
        </style>
    </div>
    <?php
    
    // Print lightbox once
    nmkr_print_lightbox_once();
}
?>