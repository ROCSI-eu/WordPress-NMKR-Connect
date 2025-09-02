<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Include new helpers
require_once dirname(__FILE__,2) . '/helpers/nmkr-availability.php';
require_once dirname(__FILE__,2) . '/helpers/nmkr-lightbox.php';
require_once dirname(__FILE__,2) . '/helpers/nmkr-projects-util.php';
require_once dirname(__FILE__,2) . '/helpers/nmkr-ui-helpers.php';

// Shortcode function to display a single token with details
function nmkr_shortcode_token($atts) {
    // Check if the user has the necessary plan (Starter or above)
    if (!wnc_fs()->can_use_premium_code()) {
        return '<p>This feature is only available in the Starter plan and above. <a href="' . esc_url(wnc_fs()->get_upgrade_url()) . '">Upgrade Now</a></p>';
    }

    global $wpdb;

    // Attributes for token selection
    $atts = shortcode_atts(
        array(
            'token_uid' => '',
        ), $atts, 'nmkr_shortcode_token'
    );

    // Parameterless mode: If token_uid is missing, resolve the latest project → pick its first buyable token
    if ( empty( $atts['token_uid'] ) ) {
        $active_project_uid = nmkr_get_active_project_uid_single( $atts );
        if ( empty( $active_project_uid ) ) {
            return '<p>' . esc_html__( 'No projects available. Please synchronize with NMKR Studio first.', 'nmkr-connect' ) . '</p>';
        }
        $token_obj = nmkr_get_first_token_for_project( $active_project_uid, true );
        if ( ! $token_obj ) {
            return '<p>' . esc_html__( 'No tokens found for the selected project.', 'nmkr-connect' ) . '</p>';
        }
        $atts['token_uid'] = $token_obj->token_uid;
    }

    // Table names with dynamic prefix
    $tokens_table = $wpdb->prefix . 'nmkr_tokens';

    // Single token fetch must join details
    $t  = $wpdb->prefix . 'nmkr_tokens';
    $td = $wpdb->prefix . 'nmkr_token_details';
    $token = $wpdb->get_row( $wpdb->prepare(
        "SELECT t.*, td.* FROM {$t} t LEFT JOIN {$td} td ON td.token_uid = t.token_uid WHERE t.token_uid = %s",
        $atts['token_uid']
    ));
    if ( ! $token ) {
        return '<p>' . esc_html__( 'Token not found.', 'nmkr-connect' ) . '</p>';
    }

    // Initialize output with token details
    $output = '';
    
    // Print buy button styles once
    if ( function_exists('nmkr_print_buy_button_styles_once') ) {
        nmkr_print_buy_button_styles_once();
    }
    
    // Enqueue frontend analytics scaffold
    nmkr_enqueue_analytics_frontend();

    $output .= '
    <style>
        .nmkr-single-token {
            text-align: center;
            margin: 20px auto;
            border: 1px solid #ddd;
            padding: 20px;
            background-color: #f9f9f9;
            max-width: 600px;
        }
        .nmkr-token-image {
            max-width: 300px;
            height: auto;
            border-radius: 8px;
            margin: 10px 0;
            cursor: pointer;
        }
        .nmkr-token-meta {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 15px;
            margin: 20px 0;
        }
        .nmkr-token-meta-item {
            background-color: #fff;
            padding: 8px 15px;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .nmkr-token-status {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.9em;
            margin: 5px 0;
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
            padding: 10px 20px;
            background-color: #2196f3;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 15px;
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
    </style>
    
    <div class="nmkr-single-token" data-nmkr-evt="view" data-nmkr-shortcode="token" data-nmkr-token-uid="' . esc_attr($token->token_uid) . '" data-nmkr-id="token:' . esc_attr($token->token_uid) . '">
        <h3>' . esc_html($token->token_name) . '</h3>';

    // --- BEGIN: normalized main token image for [nmkr-token] ---
    
    // Resolve image URL via helper (gateway_link → re-based HTTPS; or ipfs_link; or metadata.image).
    $img = nmkr_get_token_image_url( $token );
    
    // Fallback to bundled placeholder if nothing resolves.
    if ( empty( $img ) ) {
        $img = plugins_url( 'images/placeholder.jpg', NMKR_CONNECT_PLUGIN_FILE );
    }
    
    // Alt text preference order.
    $alt = '';
    if ( ! empty( $token->token_name ) ) {
        $alt = $token->token_name;
    } elseif ( ! empty( $token->asset_name ) ) {
        $alt = $token->asset_name;
    } else {
        $alt = 'Token';
    }
    
    // Display token image with lightbox functionality
    $output .= '<img src="' . esc_url( $img ) . '" alt="' . esc_attr( $alt ) . '" class="nmkr-token-image" loading="lazy" decoding="async" onclick="openLightbox(this.src)">';
    
    // --- END: normalized main token image for [nmkr-token] ---

    // Token metadata
    $output .= '<div class="nmkr-token-meta">';
    
    // Token status using helper
    $status_label = nmkr_token_status_label( $token );
    $status_class = strtolower($status_label);
    $output .= '<div class="nmkr-token-meta-item"><span class="nmkr-token-status ' . $status_class . '">' . esc_html($status_label) . '</span></div>';
    
    // Token price
    $price_html = nmkr_render_token_price_badges( $token );
    if ( $price_html ) { $output .= $price_html; }
    
    // Token series
    if (!empty($token->series)) {
        $output .= '<div class="nmkr-token-meta-item"><strong>Series:</strong> ' . esc_html($token->series) . '</div>';
    }
    
    // Token asset
    if (!empty($token->asset_name)) {
        $output .= '<div class="nmkr-token-meta-item"><strong>Asset:</strong> ' . esc_html($token->asset_name) . '</div>';
    }
    
    $output .= '</div>';

    // Token actions using helper
    $buyable = nmkr_token_is_buyable( $token );
    if ( $buyable ) {
        if (!empty($token->payment_gateway_link)) {
            $output .= '<a href="' . esc_url($token->payment_gateway_link) . '"'
                . ' class="nmkr-buy-button"'
                . ' target="_blank" rel="noopener noreferrer"'
                . ' aria-label="' . esc_attr__( 'Buy token with NMKR Pay', 'nmkr-connect' ) . '"'
                . ' data-nmkr-evt="click" data-nmkr-cta="buy" data-nmkr-shortcode="token"'
                . ' data-nmkr-token-uid="' . esc_attr($token->token_uid) . '"'
                . ' data-nmkr-id="token:' . esc_attr($token->token_uid) . '"'
                . '>'
                . '<span aria-hidden="true">💳</span> '
                . esc_html__( 'Buy with NMKR Pay', 'nmkr-connect' )
                . '</a>';
        }
    }

    $output .= '</div>';

    // Print lightbox once
    if ( function_exists('nmkr_print_lightbox_once') ) { 
        nmkr_print_lightbox_once(); 
    }

    return $output;
}

// Register the shortcode
add_shortcode('nmkr-token', 'nmkr_shortcode_token');
?>