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
        return '<p>' . esc_html__( 'No projects available. Please synchronize with NMKR Studio first.', 'rocsi-connector-for-nmkr' ) . '</p>';
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
        return '<p>' . esc_html__( 'Selected project not found.', 'rocsi-connector-for-nmkr' ) . '</p>';
    }

    // Counters from the centralized helper
    $counters = nmkr_get_project_counters( $active_project_uid );

    // Enqueue frontend analytics scaffold
    nmkr_enqueue_analytics_frontend();
    nmkr_connect_enqueue_style_asset( 'nmkr-shortcode-project', 'css/nmkr-shortcode-project.css' );

    // Initialize output with project details
    $output = '';

    // Print buy button styles once
    if ( function_exists('nmkr_print_buy_button_styles_once') ) {
        nmkr_print_buy_button_styles_once();
    }

    // Render project selector if allowed
    if ( $allow_user_select ) {
        $projects = $wpdb->get_results( "SELECT project_uid, project_name, project_url FROM {$projects_table} ORDER BY created_at DESC" );
        $output .= nmkr_render_project_selector_simple( $projects, $active_project_uid );
    }

    $output .= '<div class="nmkr-single-project"'
        . ' data-nmkr-evt="view"'
        . ' data-nmkr-shortcode="project"'
        . ' data-nmkr-project-uid="' . esc_attr($active_project_uid) . '"'
        . ' data-nmkr-id="project:' . esc_attr($active_project_uid) . '"'
        . '>
        <h4>' . esc_html($project->project_name) . '</h4>';

    // --- BEGIN: normalized project logo for [nmkr-project] ---
    $logo = nmkr_get_project_logo_url( $project );
    if ( empty( $logo ) ) {
        $logo = plugins_url( 'images/placeholder.png', NMKR_CONNECT_PLUGIN_FILE );
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
    $output .= '<strong>' . esc_html__('Total','rocsi-connector-for-nmkr') . ':</strong> ' . esc_html($counters->total_tokens);
    $output .= '</div>';
    $output .= '<div class="nmkr-project-meta-item">';
    $output .= '<strong>' . esc_html__('Minted','rocsi-connector-for-nmkr') . ':</strong> ' . esc_html($counters->minted_count);
    $output .= '</div>';
    $output .= '<div class="nmkr-project-meta-item">';
    $output .= '<strong>' . esc_html__('Sold','rocsi-connector-for-nmkr') . ':</strong> ' . esc_html($counters->sold_count);
    $output .= '</div>';
    $output .= '<div class="nmkr-project-meta-item">';
    $output .= '<strong>' . esc_html__('Reserved','rocsi-connector-for-nmkr') . ':</strong> ' . esc_html($counters->reserved_active_count);
    $output .= '</div>';
    $output .= '<div class="nmkr-project-meta-item">';
    $output .= '<strong>' . esc_html__('Available','rocsi-connector-for-nmkr') . ':</strong> ' . esc_html($counters->available_count);
    $output .= '</div>';
    $output .= '</div>';

    // Featured token using the same token helpers
    $featured = nmkr_get_first_token_for_project( $active_project_uid, true );
    if ( $featured ) {
        $status_label = nmkr_token_status_label( $featured );
        $buyable      = nmkr_token_is_buyable( $featured );

        $output .= '<div class="nmkr-featured-token">';
        $output .= '<h5>' . esc_html__( 'Featured Token', 'rocsi-connector-for-nmkr' ) . '</h5>';

        // Token image (opens lightbox)
        $output .= nmkr_get_token_image_markup( $featured, $featured->token_name, '' );

        $output .= '<h6>' . esc_html( $featured->token_name ) . '</h6>';

        // Status + buy button
        $output .= '<div class="nmkr-status">' . esc_html( $status_label ) . '</div>';
        if ( $buyable && ! empty( $featured->payment_gateway_link ) ) {
            $output .= '<a href="' . esc_url( $featured->payment_gateway_link ) . '"'
                    . ' class="nmkr-buy-button"'
                    . ' target="_blank" rel="noopener noreferrer"'
                    . ' aria-label="' . esc_attr__( 'Buy token with NMKR Pay', 'rocsi-connector-for-nmkr' ) . '"'
                    . ' data-nmkr-evt="click" data-nmkr-cta="buy" data-nmkr-shortcode="project"'
                    . ' data-nmkr-project-uid="' . esc_attr($active_project_uid) . '"'
                    . ' data-nmkr-token-uid="' . esc_attr(!empty($featured->token_uid) ? $featured->token_uid : '') . '"'
                    . ' data-nmkr-id="project:' . esc_attr($active_project_uid) . '"'
                    . '>'
                    . '<span aria-hidden="true">💳</span> '
                    . esc_html__( 'Buy with NMKR Pay', 'rocsi-connector-for-nmkr' )
                    . '</a>';
        }
        $output .= '</div>';
    }

    // Project links - always use project_url (not website)
    $output .= '<div class="nmkr-project-links">';
    if (!empty($project->policy_id)) {
        $output .= '<a href="' . esc_url( 'https://cardanoscan.io/tokenPolicy/' . rawurlencode( (string) $project->policy_id ) ) . '" class="nmkr-project-link" target="_blank">View on CardanoScan</a>';
    }
    if ( ! empty( $project->project_url ) ) {
        $output .= '<a class="nmkr-project-link" target="_blank" href="' . esc_url( $project->project_url ) . '">' . esc_html__( 'Website', 'rocsi-connector-for-nmkr' ) . '</a>';
    }
    if (!empty($project->twitter_handle)) {
        $output .= '<a href="' . esc_url( 'https://twitter.com/' . rawurlencode( ltrim( (string) $project->twitter_handle, '@' ) ) ) . '" class="nmkr-project-link" target="_blank">Twitter</a>';
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
