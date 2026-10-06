<?php
if ( ! defined( 'ABSPATH' ) ) exit;

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
            nmkr_render_access_denied_page( __( 'NFT Projects', 'rocsi-connector-for-nmkr' ) );
            return;
        }
        wp_die( esc_html__( 'Access denied.', 'rocsi-connector-for-nmkr' ) );
    }

    global $wpdb;
    $projects_table      = $wpdb->prefix . 'nmkr_projects';
    $tokens_table        = $wpdb->prefix . 'nmkr_tokens';
    $token_details_table = $wpdb->prefix . 'nmkr_token_details';

    // Fetch projects from the plugin-owned projects table.
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- The table identifier is derived only from the validated WordPress table prefix plus a fixed plugin suffix.
    $projects = $wpdb->get_results( "SELECT * FROM {$projects_table}" );

    $selected_project_uid = '';
    $search_query          = '';
    $filter_minted         = '';
    $request_method        = isset( $_SERVER['REQUEST_METHOD'] )
        ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) )
        : '';

    if ( 'POST' === $request_method ) {
        check_admin_referer( 'nmkr_projects_filter', 'nmkr_projects_filter_nonce' );

        $selected_project_uid = isset( $_POST['project_uid'] )
            ? sanitize_text_field( wp_unslash( $_POST['project_uid'] ) )
            : '';
        $search_query = isset( $_POST['search_token'] )
            ? sanitize_text_field( wp_unslash( $_POST['search_token'] ) )
            : '';
        $filter_minted_raw = isset( $_POST['filter_minted'] )
            ? sanitize_text_field( wp_unslash( $_POST['filter_minted'] ) )
            : '';
        $filter_minted = in_array( $filter_minted_raw, array( '0', '1' ), true )
            ? $filter_minted_raw
            : '';
    }

    $project_count = is_array( $projects ) ? count( $projects ) : 0;
    ?>
    <div class="wrap nmkr-admin-shell nmkr-projects-page">
        <header class="nmkr-projects-header">
            <div>
                <p class="nmkr-projects-eyebrow"><?php esc_html_e( 'ROCSI Connector for NMKR', 'rocsi-connector-for-nmkr' ); ?></p>
                <h1><?php esc_html_e( 'NFT Projects', 'rocsi-connector-for-nmkr' ); ?></h1>
                <p class="nmkr-projects-intro"><?php esc_html_e( 'Inspect synchronized NMKR projects, review project health at a glance, and drill into token availability without changing synchronization state.', 'rocsi-connector-for-nmkr' ); ?></p>
            </div>
        </header>

        <section class="panel nmkr-project-selector-panel" aria-labelledby="nmkr-project-selector-title">
            <div class="panel-header nmkr-projects-panel-heading">
                <div>
                    <p class="nmkr-projects-kicker"><?php esc_html_e( 'Step 1', 'rocsi-connector-for-nmkr' ); ?></p>
                    <h2 id="nmkr-project-selector-title"><?php esc_html_e( 'Choose a synchronized project', 'rocsi-connector-for-nmkr' ); ?></h2>
                </div>
                <span class="nmkr-project-count">
                    <?php
                    printf(
                        esc_html( _n( '%d project', '%d projects', $project_count, 'rocsi-connector-for-nmkr' ) ),
                        esc_html( $project_count )
                    );
                    ?>
                </span>
            </div>

            <?php if ( empty( $projects ) ) : ?>
                <div class="nmkr-admin-empty nmkr-projects-empty-state">
                    <span class="dashicons dashicons-portfolio" aria-hidden="true"></span>
                    <div>
                        <h3><?php esc_html_e( 'No synchronized projects yet', 'rocsi-connector-for-nmkr' ); ?></h3>
                        <p><?php esc_html_e( 'Projects appear here after NMKR data has been synchronized. No project or token data is changed from this screen.', 'rocsi-connector-for-nmkr' ); ?></p>
                    </div>
                </div>
            <?php else : ?>
                <form method="post" class="project-selector-form">
                    <?php wp_nonce_field( 'nmkr_projects_filter', 'nmkr_projects_filter_nonce' ); ?>
                    <div class="nmkr-projects-field">
                        <label for="project_uid"><?php esc_html_e( 'Project', 'rocsi-connector-for-nmkr' ); ?></label>
                        <select name="project_uid" id="project_uid" onchange="this.form.submit()">
                            <option value=""><?php esc_html_e( 'Select a project', 'rocsi-connector-for-nmkr' ); ?></option>
                            <?php foreach ( $projects as $project ) : ?>
                                <option value="<?php echo esc_attr( $project->project_uid ); ?>" <?php selected( $selected_project_uid, $project->project_uid ); ?>>
                                    <?php echo esc_html( $project->project_name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e( 'Selecting a project reloads this page and reveals its summary, filters, and synchronized tokens.', 'rocsi-connector-for-nmkr' ); ?></p>
                    </div>
                    <noscript>
                        <button type="submit" class="button button-secondary"><?php esc_html_e( 'View project', 'rocsi-connector-for-nmkr' ); ?></button>
                    </noscript>
                </form>
            <?php endif; ?>
        </section>

        <?php if ( ! $selected_project_uid && ! empty( $projects ) ) : ?>
            <div class="nmkr-admin-state nmkr-projects-guidance" role="status">
                <span class="dashicons dashicons-arrow-up-alt" aria-hidden="true"></span>
                <div>
                    <strong><?php esc_html_e( 'Select a project to continue', 'rocsi-connector-for-nmkr' ); ?></strong>
                    <p><?php esc_html_e( 'The project summary and token explorer will appear here. This page is read-only apart from its local filters.', 'rocsi-connector-for-nmkr' ); ?></p>
                </div>
            </div>
        <?php endif; ?>

        <?php
        if ( $selected_project_uid ) :
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- The table identifier is plugin-owned; the selected UID remains a prepared value.
            $selected_project = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$projects_table} WHERE project_uid = %s", $selected_project_uid ) );

            if ( $selected_project ) :
                $counters = nmkr_get_project_counters( $selected_project->project_uid );
                ?>
                <section class="panel nmkr-project-overview" aria-labelledby="nmkr-selected-project-title">
                    <div class="nmkr-projects-panel-heading nmkr-project-overview-heading">
                        <div>
                            <p class="nmkr-projects-kicker"><?php esc_html_e( 'Step 2 · Project summary', 'rocsi-connector-for-nmkr' ); ?></p>
                            <h2 id="nmkr-selected-project-title"><?php echo esc_html( $selected_project->project_name ); ?></h2>
                            <p class="nmkr-project-uid"><?php esc_html_e( 'Project UID:', 'rocsi-connector-for-nmkr' ); ?> <code><?php echo esc_html( $selected_project->project_uid ); ?></code></p>
                        </div>
                        <?php if ( ! empty( $selected_project->project_url ) ) : ?>
                            <a class="button button-secondary" href="<?php echo esc_url( $selected_project->project_url ); ?>" target="_blank" rel="noopener noreferrer">
                                <?php esc_html_e( 'Open project website', 'rocsi-connector-for-nmkr' ); ?>
                                <span class="screen-reader-text"><?php esc_html_e( ' (opens in a new tab)', 'rocsi-connector-for-nmkr' ); ?></span>
                            </a>
                        <?php endif; ?>
                    </div>

                    <?php if ( ! empty( $selected_project->description ) ) : ?>
                        <p class="nmkr-project-description"><?php echo esc_html( $selected_project->description ); ?></p>
                    <?php endif; ?>

                    <div class="nmkr-project-stats-grid" aria-label="<?php esc_attr_e( 'Project token statistics', 'rocsi-connector-for-nmkr' ); ?>">
                        <div class="nmkr-project-stat">
                            <span><?php esc_html_e( 'Total', 'rocsi-connector-for-nmkr' ); ?></span>
                            <strong><?php echo esc_html( $counters->total_tokens ); ?></strong>
                        </div>
                        <div class="nmkr-project-stat">
                            <span><?php esc_html_e( 'Available', 'rocsi-connector-for-nmkr' ); ?></span>
                            <strong><?php echo esc_html( $counters->available_count ); ?></strong>
                        </div>
                        <div class="nmkr-project-stat">
                            <span><?php esc_html_e( 'Minted', 'rocsi-connector-for-nmkr' ); ?></span>
                            <strong><?php echo esc_html( $counters->minted_count ); ?></strong>
                        </div>
                        <div class="nmkr-project-stat">
                            <span><?php esc_html_e( 'Sold', 'rocsi-connector-for-nmkr' ); ?></span>
                            <strong><?php echo esc_html( $counters->sold_count ); ?></strong>
                        </div>
                        <div class="nmkr-project-stat">
                            <span><?php esc_html_e( 'Reserved', 'rocsi-connector-for-nmkr' ); ?></span>
                            <strong><?php echo esc_html( $counters->reserved_active_count ); ?></strong>
                        </div>
                    </div>
                </section>

                <section class="panel nmkr-token-explorer" aria-labelledby="nmkr-token-explorer-title">
                    <div class="nmkr-projects-panel-heading">
                        <div>
                            <p class="nmkr-projects-kicker"><?php esc_html_e( 'Step 3 · Token explorer', 'rocsi-connector-for-nmkr' ); ?></p>
                            <h2 id="nmkr-token-explorer-title"><?php esc_html_e( 'Filter and inspect tokens', 'rocsi-connector-for-nmkr' ); ?></h2>
                        </div>
                    </div>

                    <form method="post" class="token-filter-form">
                        <?php wp_nonce_field( 'nmkr_projects_filter', 'nmkr_projects_filter_nonce' ); ?>
                        <input type="hidden" name="project_uid" value="<?php echo esc_attr( $selected_project_uid ); ?>">
                        <div class="filters-container">
                            <div class="nmkr-projects-field">
                                <label for="search_token"><?php esc_html_e( 'Token name', 'rocsi-connector-for-nmkr' ); ?></label>
                                <input type="search" name="search_token" id="search_token" value="<?php echo esc_attr( $search_query ); ?>" placeholder="<?php esc_attr_e( 'Search token name', 'rocsi-connector-for-nmkr' ); ?>">
                            </div>
                            <div class="nmkr-projects-field">
                                <label for="filter_minted"><?php esc_html_e( 'Minted status', 'rocsi-connector-for-nmkr' ); ?></label>
                                <select name="filter_minted" id="filter_minted">
                                    <option value=""><?php esc_html_e( 'All tokens', 'rocsi-connector-for-nmkr' ); ?></option>
                                    <option value="1" <?php selected( $filter_minted, '1' ); ?>><?php esc_html_e( 'Minted', 'rocsi-connector-for-nmkr' ); ?></option>
                                    <option value="0" <?php selected( $filter_minted, '0' ); ?>><?php esc_html_e( 'Not minted', 'rocsi-connector-for-nmkr' ); ?></option>
                                </select>
                            </div>
                            <div class="nmkr-filter-actions">
                                <button type="submit" class="button button-primary"><?php esc_html_e( 'Apply filters', 'rocsi-connector-for-nmkr' ); ?></button>
                            </div>
                        </div>
                    </form>

                    <?php
                    $token_query = "SELECT t.*, td.*
                                   FROM {$tokens_table} t
                                   LEFT JOIN {$token_details_table} td ON t.token_uid = td.token_uid
                                   WHERE t.project_uid = %s";
                    $query_params = array( $selected_project_uid );

                    if ( ! empty( $search_query ) ) {
                        $token_query .= " AND (t.token_name LIKE %s OR td.title LIKE %s)";
                        $query_params[] = '%' . $wpdb->esc_like( $search_query ) . '%';
                        $query_params[] = '%' . $wpdb->esc_like( $search_query ) . '%';
                    }

                    if ( '' !== $filter_minted ) {
                        $token_query .= " AND t.minted = %d";
                        $query_params[] = (int) $filter_minted;
                    }

                    // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared -- Dynamic SQL shape is fixed and manually constrained; values are prepared via $query_params.
                    $tokens = $wpdb->get_results( $wpdb->prepare( $token_query, ...$query_params ) );
                    $filters_active = '' !== $search_query || '' !== $filter_minted;

                    if ( ! empty( $tokens ) ) :
                        ?>
                        <div class="nmkr-token-results-heading">
                            <h3><?php esc_html_e( 'Project tokens', 'rocsi-connector-for-nmkr' ); ?></h3>
                            <span>
                                <?php
                                printf(
                                    esc_html( _n( '%d result', '%d results', count( $tokens ), 'rocsi-connector-for-nmkr' ) ),
                                    esc_html( count( $tokens ) )
                                );
                                ?>
                            </span>
                        </div>

                        <div class="token-grid">
                            <?php foreach ( $tokens as $token ) : ?>
                                <article class="token-item">
                                    <div class="token-image">
                                        <?php
                                        $img = nmkr_get_token_image_url( $token );
                                        if ( empty( $img ) ) {
                                            $img = plugins_url( 'images/placeholder.png', dirname(__FILE__) );
                                        }

                                        if ( ! empty( $token->token_name ) ) {
                                            $t_alt = $token->token_name;
                                        } elseif ( ! empty( $token->asset_name ) ) {
                                            $t_alt = $token->asset_name;
                                        } else {
                                            $t_alt = __( 'Token', 'rocsi-connector-for-nmkr' );
                                        }
                                        ?>
                                        <button
                                          type="button"
                                          class="nmkr-token-preview-button"
                                          aria-label="<?php echo esc_attr( sprintf( __( 'Open preview for %s', 'rocsi-connector-for-nmkr' ), $t_alt ) ); ?>"
                                          onclick="if(window.openLightbox){openLightbox(this.querySelector('img').src)}"
                                        >
                                            <img
                                              src="<?php echo esc_url( $img ); ?>"
                                              alt="<?php echo esc_attr( $t_alt ); ?>"
                                              width="150"
                                              loading="lazy"
                                              decoding="async"
                                            />
                                        </button>
                                    </div>
                                    <div class="token-details">
                                        <div class="nmkr-token-heading">
                                            <h4><?php echo esc_html( $t_alt ); ?></h4>
                                            <?php
                                            $status_label = nmkr_token_status_label( $token );
                                            $status_class = 'status-' . strtolower( $status_label );
                                            ?>
                                            <span class="token-status <?php echo esc_attr( $status_class ); ?>">
                                                <span class="screen-reader-text"><?php esc_html_e( 'Status:', 'rocsi-connector-for-nmkr' ); ?> </span>
                                                <?php echo esc_html( $status_label ); ?>
                                            </span>
                                        </div>

                                        <?php
                                        $price_html = nmkr_render_token_price_badges( $token );
                                        if ( $price_html ) {
                                            echo wp_kses(
                                                $price_html,
                                                array(
                                                    'div'  => array( 'class' => true ),
                                                    'span' => array( 'class' => true ),
                                                )
                                            );
                                        }
                                        ?>

                                        <dl class="nmkr-token-meta">
                                            <div>
                                                <dt><?php esc_html_e( 'Token UID', 'rocsi-connector-for-nmkr' ); ?></dt>
                                                <dd><code><?php echo esc_html( $token->token_uid ); ?></code></dd>
                                            </div>
                                            <?php if ( ! empty( $token->series ) ) : ?>
                                                    <div>
                                                        <dt><?php esc_html_e( 'Series', 'rocsi-connector-for-nmkr' ); ?></dt>
                                                        <dd><?php echo esc_html( $token->series ); ?></dd>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ( ! empty( $token->asset_name ) ) : ?>
                                                    <div>
                                                        <dt><?php esc_html_e( 'Asset', 'rocsi-connector-for-nmkr' ); ?></dt>
                                                        <dd><?php echo esc_html( $token->asset_name ); ?></dd>
                                                    </div>
                                                <?php endif; ?>
                                        </dl>

                                        <?php
                                        $buyable = nmkr_token_is_buyable( $token );
                                        if ( $buyable && ! empty( $token->payment_gateway_link ) ) :
                                            ?>
                                            <div class="nmkr-token-actions">
                                                <a class="button button-primary" href="<?php echo esc_url( $token->payment_gateway_link ); ?>" target="_blank" rel="noopener noreferrer">
                                                    <?php esc_html_e( 'Buy with NMKR', 'rocsi-connector-for-nmkr' ); ?>
                                                    <span class="screen-reader-text"><?php esc_html_e( ' (opens in a new tab)', 'rocsi-connector-for-nmkr' ); ?></span>
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php else : ?>
                        <div class="nmkr-admin-empty nmkr-token-empty-state" role="status">
                            <span class="dashicons dashicons-filter" aria-hidden="true"></span>
                            <div>
                                <h3>
                                    <?php
                                    echo esc_html(
                                        $filters_active
                                            ? __( 'No tokens match these filters', 'rocsi-connector-for-nmkr' )
                                            : __( 'No tokens found for this project', 'rocsi-connector-for-nmkr' )
                                    );
                                    ?>
                                </h3>
                                <p>
                                    <?php
                                    echo esc_html(
                                        $filters_active
                                            ? __( 'Adjust the token name or minted-status filter and try again.', 'rocsi-connector-for-nmkr' )
                                            : __( 'Tokens will appear here when synchronized data is available for this project.', 'rocsi-connector-for-nmkr' )
                                    );
                                    ?>
                                </p>
                            </div>
                        </div>
                    <?php endif; ?>
                </section>
            <?php else : ?>
                <div class="nmkr-admin-state nmkr-project-not-found" role="alert">
                    <span class="dashicons dashicons-warning" aria-hidden="true"></span>
                    <div>
                        <strong><?php esc_html_e( 'Project not found', 'rocsi-connector-for-nmkr' ); ?></strong>
                        <p><?php esc_html_e( 'The selected project is no longer available in synchronized plugin data. Choose another project above.', 'rocsi-connector-for-nmkr' ); ?></p>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php

    nmkr_print_lightbox_once();
}
?>
