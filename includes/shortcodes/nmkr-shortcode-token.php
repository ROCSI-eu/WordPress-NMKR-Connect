<?php
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

    // Table names with dynamic prefix
    $tokens_table = $wpdb->prefix . 'nmkr_tokens';

    // Fetch the selected token from the database
    if (!empty($atts['token_uid'])) {
        $token = $wpdb->get_row($wpdb->prepare(
            "SELECT t.*, td.* 
             FROM $tokens_table t 
             LEFT JOIN {$wpdb->prefix}nmkr_token_details td ON t.token_uid = td.token_uid 
             WHERE t.token_uid = %s", 
            $atts['token_uid']
        ));
    }

    if (!$token) {
        return '<p>Token not found or invalid token UID.</p>';
    }

    // Initialize output with token details
    $output = '
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
    </script>
    
    <div class="nmkr-single-token">
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
    $output .= '<img src="' . esc_url( $img ) . '" alt="' . esc_attr( $alt ) . '" class="nmkr-token-image" loading="lazy" decoding="async" onclick="openLightbox(\'' . esc_url( $img ) . '\')">';
    
    // --- END: normalized main token image for [nmkr-token] ---

    // Token metadata
    $output .= '<div class="nmkr-token-meta">';
    
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
    $output .= '<div class="nmkr-token-meta-item"><span class="nmkr-token-status ' . $status_class . '">' . $status_text . '</span></div>';
    
    // Token price
    if (!empty($token->price)) {
        $output .= '<div class="nmkr-token-meta-item"><strong>Price:</strong> ' . esc_html($token->price) . ' ADA</div>';
    }
    
    // Token series
    if (!empty($token->series)) {
        $output .= '<div class="nmkr-token-meta-item"><strong>Series:</strong> ' . esc_html($token->series) . '</div>';
    }
    
    // Token asset
    if (!empty($token->asset_name)) {
        $output .= '<div class="nmkr-token-meta-item"><strong>Asset:</strong> ' . esc_html($token->asset_name) . '</div>';
    }
    
    $output .= '</div>';

    // Token actions
    if (!$token->minted && empty($token->reserved_until) && empty($token->sell_date)) {
        if (!empty($token->payment_gateway_link)) {
            $output .= '<a href="' . esc_url($token->payment_gateway_link) . '" class="nmkr-token-button" target="_blank">Purchase</a>';
        }
    }

    $output .= '</div>';

    // Lightbox container
    $output .= '
    <div id="lightbox" class="lightbox" onclick="closeLightbox()">
        <a href="#" class="close-lightbox">&times;</a>
        <img src="" alt="Token Image">
    </div>';

    return $output;
}

// Register the shortcode
add_shortcode('nmkr-token', 'nmkr_shortcode_token');
?>