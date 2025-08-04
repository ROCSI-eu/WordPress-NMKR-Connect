<?php
// Function to render the NMKR Shortcodes page
function nmkr_display_shortcodes_page() {
    ?>
    <div class="wrap nmkr-dashboard">
        <h1 class="center-text">NMKR Connect - Shortcodes</h1>

        <div class="panel">
            <h2 class="center-text">Available Shortcodes</h2>
            <div class="nmkr-info-box">
                <span class="dashicons dashicons-shortcode"></span>
                <p>Below are the shortcodes available in the NMKR Connect plugin. You can use these shortcodes to display NFT projects and tokens on your site.</p>
            </div>
        </div>

        <?php if ( wnc_fs()->can_use_premium_code() || wnc_fs()->is_plan( 'free' ) ) : ?>
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
        <?php else : ?>
            <!-- Free plan users only see grid and list with limited access message -->
            <div class="panel">
                <h2 class="center-text">[nmkr-grid]</h2>
                <div class="panel-section">
                    <p class="center-text"><em>This feature is available in the Free plan.</em></p>
                </div>
            </div>

            <div class="panel">
                <h2 class="center-text">[nmkr-token-list]</h2>
                <div class="panel-section">
                    <p class="center-text"><em>This feature is available in the Free plan.</em></p>
                </div>
            </div>
        <?php endif; ?>

        <?php if ( wnc_fs()->is_plan( 'starter' ) || wnc_fs()->can_use_premium_code() ) : ?>
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
        <?php else : ?>
            <!-- Starter and above features with limited access message -->
            <div class="panel">
                <h2 class="center-text">[nmkr-carousel]</h2>
                <div class="panel-section">
                    <p class="center-text"><em>This feature is available in the Starter plan and above.</em></p>
                </div>
            </div>

            <div class="panel">
                <h2 class="center-text">[nmkr-token]</h2>
                <div class="panel-section">
                    <p class="center-text"><em>This feature is available in the Starter plan and above.</em></p>
                </div>
            </div>

            <div class="panel">
                <h2 class="center-text">[nmkr-project]</h2>
                <div class="panel-section">
                    <p class="center-text"><em>This feature is available in the Starter plan and above.</em></p>
                </div>
            </div>
        <?php endif; ?>

        <style>
            /* Global Panel Styling */
            .nmkr-dashboard {
                max-width: 800px;
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
            
            /* Code Styling */
            code {
                background-color: #f4f4f4;
                padding: 4px 8px;
                border-radius: 4px;
                font-size: 13px;
                color: #333;
                border: 1px solid #e5e5e5;
                display: inline-block;
                margin: 2px 0;
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
        </style>
    </div>
    <?php
}