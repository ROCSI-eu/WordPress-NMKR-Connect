<?php
/**
 * ROCSI Connector for NMKR - Analytics Admin Page.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function nmkr_connect_analytics_page() {
    if ( ! current_user_can( 'nmkr_view_analytics' ) ) {
        if ( function_exists( 'nmkr_render_access_denied_page' ) ) {
            nmkr_render_access_denied_page( __( 'Analytics', 'rocsi-connector-for-nmkr' ) );
            return;
        }
        wp_die( esc_html__( 'Access denied.', 'rocsi-connector-for-nmkr' ) );
    }

    $options        = get_option( 'nmkr_connect_options', array() );
    $analytics_mode = isset( $options['analytics_mode'] ) ? $options['analytics_mode'] : 'off';
    $mode_labels    = array(
        'off'    => __( 'Off', 'rocsi-connector-for-nmkr' ),
        'custom' => __( 'Local database', 'rocsi-connector-for-nmkr' ),
        'ga4'    => __( 'GA4 only', 'rocsi-connector-for-nmkr' ),
        'both'   => __( 'Local + GA4', 'rocsi-connector-for-nmkr' ),
    );
    $mode_label = isset( $mode_labels[ $analytics_mode ] )
        ? $mode_labels[ $analytics_mode ]
        : $mode_labels['off'];
    $ga4_required = in_array( $analytics_mode, array( 'ga4', 'both' ), true );
    $ga4_configured = ! empty( $options['nmkr_ga4_measurement_id'] ) && ! empty( $options['nmkr_ga4_api_secret'] );
    $configuration_incomplete = $ga4_required && ! $ga4_configured;

    $mode_messages = array(
        'off'    => __( 'Analytics collection is off. No new engagement events are recorded. Reports below can still reflect previously retained local data.', 'rocsi-connector-for-nmkr' ),
        'custom' => __( 'Local analytics is active. Engagement events are stored in this WordPress database subject to the configured consent and retention settings.', 'rocsi-connector-for-nmkr' ),
        'ga4'    => __( 'GA4-only analytics is active. Events are sent to the configured GA4 property and are not stored in the plugin database, so local reports may be empty.', 'rocsi-connector-for-nmkr' ),
        'both'   => __( 'Local and GA4 analytics are active. Local reports use plugin-database data; GA4 delivery follows the configured GA4 credentials and consent settings.', 'rocsi-connector-for-nmkr' ),
    );
    $mode_message = isset( $mode_messages[ $analytics_mode ] )
        ? $mode_messages[ $analytics_mode ]
        : $mode_messages['off'];
    if ( $configuration_incomplete ) {
        $mode_message = __( 'GA4 delivery is selected but configuration is incomplete. Add both a GA4 Measurement ID and API Secret in Settings before relying on GA4 delivery.', 'rocsi-connector-for-nmkr' );
    }
    ?>
    <div class="wrap nmkr-admin-shell nmkr-analytics-wrap" data-analytics-mode="<?php echo esc_attr( $analytics_mode ); ?>">
        <header class="nmkr-analytics-header">
            <div>
                <p class="nmkr-analytics-eyebrow"><?php esc_html_e( 'ROCSI Connector for NMKR', 'rocsi-connector-for-nmkr' ); ?></p>
                <h1><?php esc_html_e( 'Analytics', 'rocsi-connector-for-nmkr' ); ?></h1>
                <p class="nmkr-analytics-intro"><?php esc_html_e( 'Review shortcode engagement recorded by the plugin, filter the reporting window, and export the currently available reporting data.', 'rocsi-connector-for-nmkr' ); ?></p>
            </div>
            <?php if ( current_user_can( 'nmkr_manage_settings' ) ) : ?>
                <a class="button button-secondary" href="<?php echo esc_url( admin_url( 'options-general.php?page=nmkr-connect-settings#nmkr-settings-analytics' ) ); ?>">
                    <?php esc_html_e( 'Analytics settings', 'rocsi-connector-for-nmkr' ); ?>
                </a>
            <?php endif; ?>
        </header>

        <section class="nmkr-analytics-mode-state nmkr-analytics-mode-<?php echo esc_attr( $analytics_mode ); ?><?php echo $configuration_incomplete ? ' is-unconfigured' : ''; ?>" aria-labelledby="nmkr-analytics-mode-title">
            <div>
                <span class="nmkr-analytics-mode-label"><?php esc_html_e( 'Current mode', 'rocsi-connector-for-nmkr' ); ?></span>
                <strong id="nmkr-analytics-mode-title"><?php echo esc_html( $mode_label ); ?></strong>
            </div>
            <p><?php echo esc_html( $mode_message ); ?></p>
        </section>

        <div id="nmkr-analytics-state" class="nmkr-analytics-state is-loading" role="status" aria-live="polite" aria-atomic="true">
            <?php esc_html_e( 'Loading analytics report…', 'rocsi-connector-for-nmkr' ); ?>
        </div>

        <section class="panel nmkr-analytics-panel" aria-labelledby="nmkr-analytics-filters-title">
            <div class="nmkr-analytics-panel-heading">
                <div>
                    <p class="nmkr-analytics-kicker"><?php esc_html_e( 'Report scope', 'rocsi-connector-for-nmkr' ); ?></p>
                    <h2 id="nmkr-analytics-filters-title"><?php esc_html_e( 'Filters', 'rocsi-connector-for-nmkr' ); ?></h2>
                </div>
                <p><?php esc_html_e( 'Changing a filter refreshes local reporting data. Custom ranges are limited to 365 days.', 'rocsi-connector-for-nmkr' ); ?></p>
            </div>
            <div id="nmkr-analytics-filters" class="nmkr-analytics-section nmkr-filters" aria-label="<?php esc_attr_e( 'Analytics filters', 'rocsi-connector-for-nmkr' ); ?>"></div>
        </section>

        <section class="panel nmkr-analytics-panel" aria-labelledby="nmkr-analytics-kpis-title">
            <div class="nmkr-analytics-panel-heading">
                <div>
                    <p class="nmkr-analytics-kicker"><?php esc_html_e( 'Overview', 'rocsi-connector-for-nmkr' ); ?></p>
                    <h2 id="nmkr-analytics-kpis-title"><?php esc_html_e( 'Engagement summary', 'rocsi-connector-for-nmkr' ); ?></h2>
                </div>
            </div>
            <div id="nmkr-analytics-kpis" class="nmkr-analytics-section" aria-live="polite"></div>
        </section>

        <section class="panel nmkr-analytics-panel" aria-labelledby="nmkr-analytics-trends-title">
            <div class="nmkr-analytics-panel-heading">
                <div>
                    <p class="nmkr-analytics-kicker"><?php esc_html_e( 'Trend', 'rocsi-connector-for-nmkr' ); ?></p>
                    <h2 id="nmkr-analytics-trends-title"><?php esc_html_e( 'Views and clicks over time', 'rocsi-connector-for-nmkr' ); ?></h2>
                </div>
            </div>
            <div id="nmkr-analytics-charts" class="nmkr-analytics-section"></div>
        </section>

        <section class="panel nmkr-analytics-panel" aria-labelledby="nmkr-analytics-ranking-title">
            <div class="nmkr-analytics-panel-heading">
                <div>
                    <p class="nmkr-analytics-kicker"><?php esc_html_e( 'Ranking', 'rocsi-connector-for-nmkr' ); ?></p>
                    <h2 id="nmkr-analytics-ranking-title"><?php esc_html_e( 'Top projects and tokens', 'rocsi-connector-for-nmkr' ); ?></h2>
                </div>
                <p><?php esc_html_e( 'Search by UID prefix and sort the recorded local engagement totals.', 'rocsi-connector-for-nmkr' ); ?></p>
            </div>
            <div id="nmkr-analytics-tables" class="nmkr-analytics-section"></div>
        </section>

        <section class="panel nmkr-analytics-panel nmkr-analytics-export-panel" aria-labelledby="nmkr-analytics-export-title">
            <div class="nmkr-analytics-panel-heading">
                <div>
                    <p class="nmkr-analytics-kicker"><?php esc_html_e( 'Portability', 'rocsi-connector-for-nmkr' ); ?></p>
                    <h2 id="nmkr-analytics-export-title"><?php esc_html_e( 'Export report data', 'rocsi-connector-for-nmkr' ); ?></h2>
                </div>
            </div>
            <div id="nmkr-analytics-exports" class="nmkr-analytics-section"></div>
        </section>

        <p class="nmkr-analytics-privacy-note">
            <span class="dashicons dashicons-shield" aria-hidden="true"></span>
            <?php esc_html_e( 'Analytics is optional and off by default. Consent, logged-in-user tracking, retention, GA4 delivery, and uninstall cleanup are controlled from plugin Settings.', 'rocsi-connector-for-nmkr' ); ?>
        </p>
    </div>
    <?php
}
