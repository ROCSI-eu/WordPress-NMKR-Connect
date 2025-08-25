<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Shortcode function to display a single project's details
function nmkr_shortcode_project($atts) {
    // Check if the user has the necessary plan (Starter or above)
    if (!wnc_fs()->can_use_premium_code()) {
        return '<p>This feature is only available in the Starter plan and above. <a href="' . esc_url(wnc_fs()->get_upgrade_url()) . '">Upgrade Now</a></p>';
    }

    global $wpdb;

    // Attributes for project selection
    $atts = shortcode_atts(
        array(
            'project_uid' => '',
        ), $atts, 'nmkr_shortcode_project'
    );

    // Table name with dynamic prefix
    $projects_table = $wpdb->prefix . 'nmkr_projects';

    // Fetch the selected project from the database
    if (!empty($atts['project_uid'])) {
        $project = $wpdb->get_row($wpdb->prepare("SELECT * FROM $projects_table WHERE project_uid = %s", $atts['project_uid']));
    }

    if (!$project) {
        return '<p>Project not found or invalid project UID.</p>';
    }

    // Initialize output with project details
    $output = '
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
    </style>
    
    <div class="nmkr-single-project">
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
/>';
    }

    // Display project description if available
    if (!empty($project->description)) {
        $output .= '<p>' . esc_html($project->description) . '</p>';
    }

    // Project metadata
    $output .= '<div class="nmkr-project-meta">';
    $output .= '<div class="nmkr-project-meta-item">';
    $output .= '<strong>Total Tokens:</strong> ' . esc_html($project->total_tokens);
    $output .= '</div>';
    $output .= '<div class="nmkr-project-meta-item">';
    $output .= '<strong>Available:</strong> ' . esc_html($project->free);
    $output .= '</div>';
    $output .= '<div class="nmkr-project-meta-item">';
    $output .= '<strong>Minted:</strong> ' . esc_html($project->minted);
    $output .= '</div>';
    $output .= '</div>';

    // Project links
    $output .= '<div class="nmkr-project-links">';
    if (!empty($project->policy_id)) {
        $output .= '<a href="https://cardanoscan.io/tokenPolicy/' . esc_html($project->policy_id) . '" class="nmkr-project-link" target="_blank">View on CardanoScan</a>';
    }
    if (!empty($project->website)) {
        $output .= '<a href="' . esc_url($project->website) . '" class="nmkr-project-link" target="_blank">Website</a>';
    }
    if (!empty($project->twitter_handle)) {
        $output .= '<a href="https://twitter.com/' . esc_attr(ltrim($project->twitter_handle, '@')) . '" class="nmkr-project-link" target="_blank">Twitter</a>';
    }
    $output .= '</div>';

    $output .= '</div>';

    return $output;
}

// Register the shortcode
add_shortcode('nmkr-project', 'nmkr_shortcode_project');
?>