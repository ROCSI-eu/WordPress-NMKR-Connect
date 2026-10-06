<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function nmkr_display_shortcodes_page() {
    if ( ! current_user_can( 'nmkr_view_shortcodes' ) ) {
        if ( function_exists( 'nmkr_render_access_denied_page' ) ) {
            nmkr_render_access_denied_page( __( 'Shortcodes', 'rocsi-connector-for-nmkr' ) );
            return;
        }
        wp_die( esc_html__( 'Access denied.', 'rocsi-connector-for-nmkr' ) );
    }

    $shortcodes = array(
        array(
            'name'       => '[nmkr-grid]',
            'icon'       => 'dashicons-grid-view',
            'purpose'    => __( 'Display one project as a responsive grid of token cards.', 'rocsi-connector-for-nmkr' ),
            'best_for'   => __( 'Gallery-style browsing', 'rocsi-connector-for-nmkr' ),
            'example'    => '[nmkr-grid project_uid="123abc" allow_user_select="0"]',
            'attributes' => array(
                array( 'project_uid', '', __( 'Initial project UID. If omitted, the resolver falls back to the latest synchronized project.', 'rocsi-connector-for-nmkr' ) ),
                array( 'allow_user_select', '1', __( '1 shows the built-in project selector. 0 hides that selector.', 'rocsi-connector-for-nmkr' ) ),
            ),
            'behavior'   => __( 'Project resolution is: ?nmkr_project= URL value, then project_uid, then latest synchronized project. Hiding the selector does not disable the existing URL-selection precedence.', 'rocsi-connector-for-nmkr' ),
        ),
        array(
            'name'       => '[nmkr-token-list]',
            'icon'       => 'dashicons-list-view',
            'purpose'    => __( 'Display one project as an information-dense token list.', 'rocsi-connector-for-nmkr' ),
            'best_for'   => __( 'Catalog and comparison views', 'rocsi-connector-for-nmkr' ),
            'example'    => '[nmkr-token-list project_uid="123abc" allow_user_select="1"]',
            'attributes' => array(
                array( 'project_uid', '', __( 'Initial project UID. If omitted, the resolver falls back to the latest synchronized project.', 'rocsi-connector-for-nmkr' ) ),
                array( 'allow_user_select', '1', __( '1 shows the built-in project selector. 0 hides that selector.', 'rocsi-connector-for-nmkr' ) ),
            ),
            'behavior'   => __( 'Project resolution is: ?nmkr_project= URL value, then project_uid, then latest synchronized project. Use this when visitors benefit from a denser token presentation.', 'rocsi-connector-for-nmkr' ),
        ),
        array(
            'name'       => '[nmkr-carousel]',
            'icon'       => 'dashicons-image-rotate',
            'purpose'    => __( 'Display one project as a horizontally browsable token carousel.', 'rocsi-connector-for-nmkr' ),
            'best_for'   => __( 'Compact featured-token sections', 'rocsi-connector-for-nmkr' ),
            'example'    => '[nmkr-carousel project_uid="123abc" allow_user_select="0"]',
            'attributes' => array(
                array( 'project_uid', '', __( 'Initial project UID. If omitted, the resolver falls back to the latest synchronized project.', 'rocsi-connector-for-nmkr' ) ),
                array( 'allow_user_select', '1', __( '1 shows the built-in project selector. 0 hides that selector.', 'rocsi-connector-for-nmkr' ) ),
            ),
            'behavior'   => __( 'Project resolution is: ?nmkr_project= URL value, then project_uid, then latest synchronized project. The shortcode retains the same project-selection semantics as grid and list.', 'rocsi-connector-for-nmkr' ),
        ),
        array(
            'name'       => '[nmkr-token]',
            'icon'       => 'dashicons-tag',
            'purpose'    => __( 'Display the details and purchase action for a single token.', 'rocsi-connector-for-nmkr' ),
            'best_for'   => __( 'A single featured or linked token', 'rocsi-connector-for-nmkr' ),
            'example'    => '[nmkr-token token_uid="456xyz"]',
            'attributes' => array(
                array( 'token_uid', '', __( 'Exact token UID to render. If omitted, the shortcode resolves the active/latest project and chooses its first buyable token, or its first token when none is buyable.', 'rocsi-connector-for-nmkr' ) ),
            ),
            'behavior'   => __( 'When token_uid is supplied, that token is rendered directly. Parameterless mode can use ?nmkr_project= to choose the source project before falling back to the latest synchronized project.', 'rocsi-connector-for-nmkr' ),
        ),
        array(
            'name'       => '[nmkr-project]',
            'icon'       => 'dashicons-portfolio',
            'purpose'    => __( 'Display a project summary, counters, links, and a featured token when available.', 'rocsi-connector-for-nmkr' ),
            'best_for'   => __( 'Project profile or landing sections', 'rocsi-connector-for-nmkr' ),
            'example'    => '[nmkr-project project_uid="123abc" allow_user_select="0"]',
            'attributes' => array(
                array( 'project_uid', '', __( 'Initial project UID. If omitted, the resolver falls back to the latest synchronized project.', 'rocsi-connector-for-nmkr' ) ),
                array( 'allow_user_select', '1', __( '1 shows the built-in project selector. 0 hides that selector.', 'rocsi-connector-for-nmkr' ) ),
            ),
            'behavior'   => __( 'Project resolution is: ?nmkr_project= URL value, then project_uid, then latest synchronized project. The featured token prefers a buyable token when one exists.', 'rocsi-connector-for-nmkr' ),
        ),
    );
    ?>
    <div class="wrap nmkr-admin-shell nmkr-shortcodes-page">
        <header class="nmkr-shortcodes-header">
            <div>
                <p class="nmkr-shortcodes-eyebrow"><?php esc_html_e( 'ROCSI Connector for NMKR', 'rocsi-connector-for-nmkr' ); ?></p>
                <h1><?php esc_html_e( 'Shortcodes', 'rocsi-connector-for-nmkr' ); ?></h1>
                <p class="nmkr-shortcodes-intro"><?php esc_html_e( 'Choose the presentation that fits your page, copy a canonical example, then replace the sample UID with synchronized NMKR data from the Projects screen.', 'rocsi-connector-for-nmkr' ); ?></p>
            </div>
        </header>

        <section class="panel nmkr-shortcodes-rules" aria-labelledby="nmkr-shortcode-selection-rules">
            <div class="panel-header nmkr-shortcodes-panel-heading">
                <div>
                    <p class="nmkr-shortcodes-kicker"><?php esc_html_e( 'How project selection works', 'rocsi-connector-for-nmkr' ); ?></p>
                    <h2 id="nmkr-shortcode-selection-rules"><?php esc_html_e( 'Selection rules shared by project-based shortcodes', 'rocsi-connector-for-nmkr' ); ?></h2>
                </div>
            </div>

            <ol class="nmkr-selection-rules-list">
                <li>
                    <strong><code>?nmkr_project=&lt;uid&gt;</code></strong>
                    <span><?php esc_html_e( 'takes precedence when present in the page URL.', 'rocsi-connector-for-nmkr' ); ?></span>
                </li>
                <li>
                    <strong><code>project_uid</code></strong>
                    <span><?php esc_html_e( 'provides the shortcode’s initial project when no URL selection is present.', 'rocsi-connector-for-nmkr' ); ?></span>
                </li>
                <li>
                    <strong><?php esc_html_e( 'Latest synchronized project', 'rocsi-connector-for-nmkr' ); ?></strong>
                    <span><?php esc_html_e( 'is used when neither of the above provides a project.', 'rocsi-connector-for-nmkr' ); ?></span>
                </li>
                <li>
                    <strong><code>allow_user_select="0"</code></strong>
                    <span><?php esc_html_e( 'hides the built-in selector; it does not disable the existing URL-selection precedence.', 'rocsi-connector-for-nmkr' ); ?></span>
                </li>
            </ol>
        </section>

        <div class="nmkr-shortcode-guide-grid">
            <?php foreach ( $shortcodes as $shortcode ) : ?>
                <article class="panel nmkr-shortcode-card">
                    <header class="nmkr-shortcode-card-header">
                        <span class="dashicons <?php echo esc_attr( $shortcode['icon'] ); ?>" aria-hidden="true"></span>
                        <div>
                            <h2><?php echo esc_html( $shortcode['name'] ); ?></h2>
                            <p><?php echo esc_html( $shortcode['purpose'] ); ?></p>
                        </div>
                    </header>

                    <div class="nmkr-shortcode-best-for">
                        <span><?php esc_html_e( 'Best for', 'rocsi-connector-for-nmkr' ); ?></span>
                        <strong><?php echo esc_html( $shortcode['best_for'] ); ?></strong>
                    </div>

                    <div class="nmkr-shortcode-example">
                        <div class="nmkr-shortcode-example-heading">
                            <strong><?php esc_html_e( 'Copy-friendly example', 'rocsi-connector-for-nmkr' ); ?></strong>
                            <span><?php esc_html_e( 'Select the code and copy it into a WordPress Shortcode block.', 'rocsi-connector-for-nmkr' ); ?></span>
                        </div>
                        <pre tabindex="0"><code><?php echo esc_html( $shortcode['example'] ); ?></code></pre>
                    </div>

                    <div class="nmkr-shortcode-attributes">
                        <h3><?php esc_html_e( 'Supported attributes', 'rocsi-connector-for-nmkr' ); ?></h3>
                        <dl>
                            <?php foreach ( $shortcode['attributes'] as $attribute ) : ?>
                                <div>
                                    <dt><code><?php echo esc_html( $attribute[0] ); ?></code></dt>
                                    <dd>
                                        <span class="nmkr-attribute-default">
                                            <?php esc_html_e( 'Default:', 'rocsi-connector-for-nmkr' ); ?>
                                            <code><?php echo '' === $attribute[1] ? esc_html__( 'empty', 'rocsi-connector-for-nmkr' ) : esc_html( $attribute[1] ); ?></code>
                                        </span>
                                        <?php echo esc_html( $attribute[2] ); ?>
                                    </dd>
                                </div>
                            <?php endforeach; ?>
                        </dl>
                    </div>

                    <div class="nmkr-shortcode-behavior">
                        <span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
                        <p><?php echo esc_html( $shortcode['behavior'] ); ?></p>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <div class="nmkr-admin-state nmkr-shortcodes-footnote">
            <span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
            <div>
                <strong><?php esc_html_e( 'Need a real UID?', 'rocsi-connector-for-nmkr' ); ?></strong>
                <p><?php esc_html_e( 'Open NFT Projects in this plugin to inspect synchronized project UIDs. Token UIDs are shown in synchronized token data and remain unchanged by this reference screen.', 'rocsi-connector-for-nmkr' ); ?></p>
            </div>
        </div>
    </div>
    <?php
}
