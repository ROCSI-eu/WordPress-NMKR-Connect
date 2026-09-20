<?php
/**
 * Connector for NMKR - Analytics Admin Page (Phase C · PR-1)
 * Shell UI: header, filters placeholder, KPI/cards placeholder, charts/table placeholders.
 */

if (!defined('ABSPATH')) { exit; }

function nmkr_connect_analytics_page() {
    if ( ! current_user_can( 'nmkr_view_analytics' ) ) {
        if ( function_exists( 'nmkr_render_access_denied_page' ) ) {
            nmkr_render_access_denied_page( __( 'Analytics', 'connector-for-nmkr' ) );
            return;
        }
        wp_die( esc_html__( 'Access denied.', 'connector-for-nmkr' ) );
    }

    ?>
    <div class="wrap nmkr-analytics-wrap">
        <h1><?php echo esc_html__( 'Analytics', 'connector-for-nmkr' ); ?></h1>

        <!-- Filters Bar (placeholder; wired in PR-4) -->
        <div id="nmkr-analytics-filters" class="nmkr-analytics-section nmkr-filters" aria-label="<?php echo esc_attr__( 'Filters', 'connector-for-nmkr' ); ?>">
            <p><?php echo esc_html__( 'Filters will appear here (date range, shortcode type, search).', 'connector-for-nmkr' ); ?></p>
        </div>

        <!-- KPI Cards (placeholder; wired in PR-4) -->
        <div id="nmkr-analytics-kpis" class="nmkr-analytics-section" aria-live="polite">
            <p><?php echo esc_html__( 'KPI cards (Views, Clicks, CTR) will render here.', 'connector-for-nmkr' ); ?></p>
        </div>

        <!-- Charts Row (placeholder; wired in PR-4) -->
        <div id="nmkr-analytics-charts" class="nmkr-analytics-section">
            <div class="nmkr-analytics-chart" id="nmkr-chart-views"></div>
            <div class="nmkr-analytics-chart" id="nmkr-chart-clicks"></div>
        </div>

        <!-- Tables Row (placeholder; wired in PR-5) -->
        <div id="nmkr-analytics-tables" class="nmkr-analytics-section">
            <div class="nmkr-analytics-table" id="nmkr-top-projects"></div>
            <div class="nmkr-analytics-table" id="nmkr-top-tokens"></div>
        </div>

        <!-- Export Bar (placeholder; wired in PR-6) -->
        <div id="nmkr-analytics-exports" class="nmkr-analytics-section">
            <p><?php echo esc_html__( 'Exports (CSV / JSON) will be available here.', 'connector-for-nmkr' ); ?></p>
        </div>
    </div>
    <?php
}


