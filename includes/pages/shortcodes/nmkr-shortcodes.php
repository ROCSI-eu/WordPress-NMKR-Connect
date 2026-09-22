<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Function to render the NMKR Shortcodes page
function nmkr_display_shortcodes_page() {
    if ( ! current_user_can( 'nmkr_view_shortcodes' ) ) {
        if ( function_exists( 'nmkr_render_access_denied_page' ) ) {
            nmkr_render_access_denied_page( __( 'Shortcodes', 'rocsi-connector-for-nmkr' ) );
            return;
        }
        wp_die( esc_html__( 'Access denied.', 'rocsi-connector-for-nmkr' ) );
    }
    ?>
    <div class="wrap nmkr-dashboard">
        <h1 class="center-text">ROCSI Connector for NMKR - Shortcodes</h1>

        <div class="panel">
            <h2 class="center-text">Available Shortcodes</h2>
            <div class="nmkr-info-box">
                <span class="dashicons dashicons-shortcode"></span>
                <p>Below are the shortcodes available in the ROCSI Connector for NMKR plugin. You can use these shortcodes to display NFT projects and tokens on your site.</p>
            </div>
        </div>

            <div class="panel">
                <h2 class="center-text">[nmkr-grid]</h2>
                <div class="nmkr-info-box">
                    <span class="dashicons dashicons-grid-view"></span>
                    <p>This shortcode displays a grid of tokens.</p>
                </div>
                <div class="panel-section">
                    <p><strong>Without parameter:</strong></p>
                    <p><code>[nmkr-grid]</code><br>By default, it will display tokens from all available projects.</p>
                    <p><strong>With parameter:</strong></p>
                    <p><code>[nmkr-grid project_uid="123abc"]</code><br>Will display tokens from the project with the specified UID.</p>
                </div>
            </div>

            <div class="panel">
                <h2 class="center-text">[nmkr-token-list]</h2>
                <div class="nmkr-info-box">
                    <span class="dashicons dashicons-list-view"></span>
                    <p>This shortcode displays a list of tokens.</p>
                </div>
                <div class="panel-section">
                    <p><strong>Without parameter:</strong></p>
                    <p><code>[nmkr-token-list]</code><br>By default, it will display tokens from all available projects.</p>
                    <p><strong>With parameter:</strong></p>
                    <p><code>[nmkr-token-list project_uid="123abc"]</code><br>Will display tokens from the project with the specified UID.</p>
                </div>
            </div>

            <div class="panel">
                <h2 class="center-text">[nmkr-carousel]</h2>
                <div class="nmkr-info-box">
                    <span class="dashicons dashicons-image-rotate"></span>
                    <p>This shortcode displays tokens in a carousel format.</p>
                </div>
                <div class="panel-section">
                    <p><strong>Without parameter:</strong></p>
                    <p><code>[nmkr-carousel]</code><br>By default, it will display tokens from all available projects in a carousel.</p>
                    <p><strong>With parameter:</strong></p>
                    <p><code>[nmkr-carousel project_uid="123abc"]</code><br>Will display tokens from the project with the specified UID in a carousel.</p>
                </div>
            </div>

            <div class="panel">
                <h2 class="center-text">[nmkr-token]</h2>
                <div class="nmkr-info-box">
                    <span class="dashicons dashicons-tag"></span>
                    <p>This shortcode displays a single token's details.</p>
                </div>
                <div class="panel-section">
                    <p><strong>Without parameter:</strong></p>
                    <p><code>[nmkr-token]</code><br>This will display the first available token in the system.</p>
                    <p><strong>With parameter:</strong></p>
                    <p><code>[nmkr-token token_uid="456xyz"]</code><br>Will display details of the token with the specified UID.</p>
                </div>
            </div>

            <div class="panel">
                <h2 class="center-text">[nmkr-project]</h2>
                <div class="nmkr-info-box">
                    <span class="dashicons dashicons-portfolio"></span>
                    <p>This shortcode displays project summary details.</p>
                </div>
                <div class="panel-section">
                    <p><strong>Without parameter:</strong></p>
                    <p><code>[nmkr-project]</code><br>This will display details of the first available project in the system.</p>
                    <p><strong>With parameter:</strong></p>
                    <p><code>[nmkr-project project_uid="123abc"]</code><br>Will display details of the project with the specified UID.</p>
                </div>
            </div>


    </div>
    <?php
}
