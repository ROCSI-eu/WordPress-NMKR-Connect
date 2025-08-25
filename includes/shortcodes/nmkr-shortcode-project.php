<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Include new helpers
require_once dirname(__FILE__,2) . '/helpers/nmkr-availability.php';
require_once dirname(__FILE__,2) . '/helpers/nmkr-project-stats.php';
require_once dirname(__FILE__,2) . '/helpers/nmkr-projects-util.php';
require_once dirname(__FILE__,2) . '/helpers/nmkr-lightbox.php';
require_once dirname(__FILE__,2) . '/helpers/nmkr-ui-helpers.php';

// Shortcode function to display a single project's details
function nmkr_shortcode_project($atts) {
    // Check if the user has the necessary plan (Starter or above)
    if (!wnc_fs()->can_use_premium_code()) {
        return '<p>' . esc_html__( 'This feature is only available in the Starter plan or above.', 'nmkr-connect' ) . ' ' .
               '<a href="' . esc_url( wnc_fs()->get_upgrade_url() ) . '">' . esc_html__( 'Upgrade Now', 'nmkr-connect' ) . '</a></p>';
    }

    // Attributes for project selection
    $atts = shortcode_atts(
        array(
            'project_uid' => '',
            'allow_user_select' => '1',
        ), $atts, 'nmkr_shortcode_project'
    );

    // Resolve the active project
    $active_project_uid = nmkr_get_active_project_uid_single( $atts );
    if ( empty( $active_project_uid ) ) {
        return '<p>' . esc_html__( 'No projects available. Please synchronize with NMKR Studio first.', 'nmkr-connect' ) . '</p>';
    }

    // Optional attribute to lock selection
    $allow_user_select = ! isset( $atts['allow_user_select'] ) || $atts['allow_user_select'] !== '0';

    // Fetch the selected project record
    global $wpdb;
    $projects_table = $wpdb->prefix . 'nmkr_projects';
    $project = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$projects_table} WHERE project_uid = %s",
        $active_project_uid
    ) );

    if ( ! $project ) {
        return '<p>' . esc_html__( 'Selected project not found.', 'nmkr-connect' ) . '</p>';
    }

    // Counters from the centralized helper
    $counters = nmkr_get_project_counters( $active_project_uid );

    // Initialize output with project details
    $output = '';
    
    // Print buy button styles once
    if ( function_exists('nmkr_print_buy_button_styles_once') ) {
        nmkr_print_buy_button_styles_once();
    }
    
    $output .= '
    <style>
        .nmkr-single-project {
            text-align: center;
            margin: 20px auto;
            border: 1px solid #ddd;
            padding: 20px;
            background-color: #f9f9f9;
            max-width: 800px;
        }
        .nmkr-single-project h4 {
            margin-bottom: 15px;
            font-size: 24px;
        }
        .nmkr-single-project p {
            margin-bottom: 10px;
        }
        .nmkr-project-logo {
            max-width: 200px;
            height: auto;
            margin: 10px 0;
            cursor: pointer;
        }
        .nmkr-project-meta {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 20px;
            margin: 20px 0;
        }
        .nmkr-project-meta-item {
            background-color: #fff;
            padding: 10px 20px;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .nmkr-project-links {
            margin-top: 20px;
        }
        .nmkr-project-link {
            display: inline-block;
            padding: 10px 20px;
            background-color: #2196f3;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 5px;
            transition: background-color 0.3s ease;
        }
        .nmkr-project-link:hover {
            background-color: #1976d2;
        }
        .nmkr-featured-token {
            margin-top: 20px;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            border: 1px solid #ddd;
        }
        .nmkr-featured-token img {
            max-width: 150px;
            height: auto;
            border-radius: 4px;
            margin-bottom: 10px;
            cursor: pointer;
        }
        .nmkr-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.9em;
            margin: 10px 0;
            background-color: #e3f2fd;
            color: #1565c0;
        }
        .nmkr-btn {
            display: inline-block;
            padding: 8px 16px;
            background-color: #11F250;
            color: black;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 10px;
            font-weight: bold;
            transition: background-color 0.3s ease;
        }
        .nmkr-btn:hover {
            background-color: #0edf47;
        }
    </style>';

    // Render project selector if allowed
    if ( $allow_user_select ) {
        $projects = $wpdb->get_results( "SELECT project_uid, project_name, project_url FROM {$projects_table} ORDER BY created_at DESC" );
        $output .= nmkr_render_project_selector_simple( $projects, $active_project_uid );
    }
    
    $output .= '<div class="nmkr-single-project">
        <h4>' . esc_html($project->project_name) . '</h4>';

    // --- BEGIN: normalized project logo for [nmkr-project] ---
    $logo = nmkr_get_project_logo_url( $project );
    if ( empty( $logo ) ) {
        $logo = plugins_url( 'images/placeholder.jpg', NMKR_CONNECT_PLUGIN_FILE );
    }

    $alt = '';
    if ( ! empty( $project->project_name ) ) {
        $alt = $project->project_name . ' logo';
    } elseif ( ! empty( $project->name ) ) {
        $alt = $project->name . ' logo';
    } else {
        $alt = 'Project logo';
    }
    // --- END: normalized project logo for [nmkr-project] ---

    // Display project logo if available
    if (!empty($project->project_logo)) {
        $output .= '<img
  src="' . esc_url( $logo ) . '"
  alt="' . esc_attr( $alt ) . '"
  loading="lazy"
  decoding="async"
  class="nmkr-project-logo"
  onclick="if(window.openLightbox){openLightbox(this.src)}"
/>';
    }

    // Display project description if available
    if (!empty($project->description)) {
        $output .= '<p>' . esc_html($project->description) . '</p>';
    }

    // Project metadata with computed counters from centralized helper
    $output .= '<div class="nmkr-project-meta">';
    $output .= '<div class="nmkr-project-meta-item">';
    $output .= '<strong>' . esc_html__('Total','nmkr-connect') . ':</strong> ' . esc_html($counters->total_tokens);
    $output .= '</div>';
    $output .= '<div class="nmkr-project-meta-item">';
    $output .= '<strong>' . esc_html__('Minted','nmkr-connect') . ':</strong> ' . esc_html($counters->minted_count);
    $output .= '</div>';
    $output .= '<div class="nmkr-project-meta-item">';
    $output .= '<strong>' . esc_html__('Sold','nmkr-connect') . ':</strong> ' . esc_html($counters->sold_count);
    $output .= '</div>';
    $output .= '<div class="nmkr-project-meta-item">';
    $output .= '<strong>' . esc_html__('Reserved','nmkr-connect') . ':</strong> ' . esc_html($counters->reserved_active_count);
    $output .= '</div>';
    $output .= '<div class="nmkr-project-meta-item">';
    $output .= '<strong>' . esc_html__('Available','nmkr-connect') . ':</strong> ' . esc_html($counters->available_count);
    $output .= '</div>';
    $output .= '</div>';

    // Featured token using the same token helpers
    $featured = nmkr_get_first_token_for_project( $active_project_uid, true );
    if ( $featured ) {
        $status_label = nmkr_token_status_label( $featured );
        $buyable      = nmkr_token_is_buyable( $featured );

        $output .= '<div class="nmkr-featured-token">';
        $output .= '<h5>' . esc_html__( 'Featured Token', 'nmkr-connect' ) . '</h5>';
        
        // Token image (opens lightbox)
        $img = nmkr_get_token_image_url( $featured );
        if ( empty( $img ) ) {
            $img = plugins_url( 'images/placeholder.jpg', NMKR_CONNECT_PLUGIN_FILE );
        }
        $output .= '<img src="' . esc_url( $img ) . '" alt="' . esc_attr( $featured->token_name ) . '" onclick="openLightbox(this.src)" />';
        
        $output .= '<h6>' . esc_html( $featured->token_name ) . '</h6>';
        
        // Status + buy button
        $output .= '<div class="nmkr-status">' . esc_html( $status_label ) . '</div>';
        if ( $buyable && ! empty( $featured->payment_gateway_link ) ) {
            $output .= '<a href="' . esc_url( $featured->payment_gateway_link ) . '"'
                    . ' class="nmkr-buy-button"'
                    . ' target="_blank" rel="noopener noreferrer"'
                    . ' aria-label="' . esc_attr__( 'Buy token with NMKR Pay', 'nmkr-connect' ) . '">'
                    . '<span aria-hidden="true">💳</span> '
                    . esc_html__( 'Buy with NMKR Pay', 'nmkr-connect' )
                    . '</a>';
        }
        $output .= '</div>';
    }

    // Project links - always use project_url (not website)
    $output .= '<div class="nmkr-project-links">';
    if (!empty($project->policy_id)) {
        $output .= '<a href="https://cardanoscan.io/tokenPolicy/' . esc_html($project->policy_id) . '" class="nmkr-project-link" target="_blank">View on CardanoScan</a>';
    }
    if ( ! empty( $project->project_url ) ) {
        $output .= '<a class="nmkr-project-link" target="_blank" href="' . esc_url( $project->project_url ) . '">' . esc_html__( 'Website', 'nmkr-connect' ) . '</a>';
    }
    if (!empty($project->twitter_handle)) {
        $output .= '<a href="https://twitter.com/' . esc_attr(ltrim($project->twitter_handle, '@')) . '" class="nmkr-project-link" target="_blank">Twitter</a>';
    }
    $output .= '</div>';

    $output .= '</div>';

    // Print lightbox once at the end
    if ( function_exists('nmkr_print_lightbox_once') ) { 
        nmkr_print_lightbox_once(); 
    }

    return $output;
}

// Register the shortcode
add_shortcode('nmkr-project', 'nmkr_shortcode_project');
?>