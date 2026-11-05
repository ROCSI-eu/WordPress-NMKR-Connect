<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/nmkr-shortcode-quick-generator.php';

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
                array( 'token_uid', '', __( 'Exact token UID to render. If omitted, the shortcode resolves the active/latest project, inspects up to its 50 newest synchronized tokens, and prefers the first buyable token in that bounded set; if none is buyable there, it uses the newest token in that set.', 'rocsi-connector-for-nmkr' ) ),
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
            'behavior'   => __( 'Project resolution is: ?nmkr_project= URL value, then project_uid, then latest synchronized project. The featured-token lookup inspects up to the 50 newest synchronized tokens, preferring the first buyable token in that bounded set and otherwise using the newest token in that set.', 'rocsi-connector-for-nmkr' ),
        ),
    );
    global $wpdb;
    $projects_table = $wpdb->prefix . 'nmkr_projects';
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned fixed table identifier.
    $projects = $wpdb->get_results( "SELECT project_uid, project_name FROM {$projects_table} ORDER BY project_name ASC" );
    $projects = is_array( $projects ) ? $projects : array();

    // This is an intentionally read-only GET workflow. No nonce is required to inspect
    // local identifiers, and only plugin-authorized users can reach this screen.
    $quick_type = isset( $_GET['nmkr_gen_type'] ) && is_string( $_GET['nmkr_gen_type'] )
        ? sanitize_text_field( wp_unslash( $_GET['nmkr_gen_type'] ) ) : 'nmkr-grid';
    $allowed_types = array( 'nmkr-grid', 'nmkr-token-list', 'nmkr-carousel', 'nmkr-token', 'nmkr-project' );
    $quick_type = in_array( $quick_type, $allowed_types, true ) ? $quick_type : 'nmkr-grid';

    $requested_project = isset( $_GET['nmkr_gen_project'] ) && is_string( $_GET['nmkr_gen_project'] )
        ? sanitize_text_field( wp_unslash( $_GET['nmkr_gen_project'] ) ) : '';
    $quick_project = '';
    foreach ( $projects as $project ) {
        if ( (string) $project->project_uid === $requested_project ) {
            $quick_project = $requested_project;
            break;
        }
    }

    $quick_selector = isset( $_GET['nmkr_gen_selector'] ) && '0' === $_GET['nmkr_gen_selector'] ? '0' : '1';
    $requested_token = isset( $_GET['nmkr_gen_token'] ) && is_string( $_GET['nmkr_gen_token'] )
        ? sanitize_text_field( wp_unslash( $_GET['nmkr_gen_token'] ) ) : '';
    $manual_token = isset( $_GET['nmkr_gen_token_custom'] ) && is_string( $_GET['nmkr_gen_token_custom'] )
        ? sanitize_text_field( wp_unslash( $_GET['nmkr_gen_token_custom'] ) ) : '';

    $quick_tokens = array();
    $quick_token_count = 0;
    $quick_token = '';
    if ( 'nmkr-token' === $quick_type && '' !== $quick_project ) {
        $tokens_table = $wpdb->prefix . 'nmkr_tokens';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned fixed identifier and bound project value.
        $quick_tokens = $wpdb->get_results( $wpdb->prepare(
            "SELECT token_uid, token_name FROM {$tokens_table} WHERE project_uid = %s ORDER BY token_name ASC LIMIT 2500",
            $quick_project
        ) );
        $quick_tokens = is_array( $quick_tokens ) ? $quick_tokens : array();
        // A manually copied UID can reference a token beyond the bounded selector list.
        // Always verify its ownership by the selected project before making a shortcode.
        $candidate = '' !== $manual_token ? $manual_token : $requested_token;
        if ( nmkr_quick_shortcode_valid_uid( $candidate ) ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned fixed identifier and bound IDs.
            $verified_token = $wpdb->get_var( $wpdb->prepare(
                "SELECT token_uid FROM {$tokens_table} WHERE project_uid = %s AND token_uid = %s LIMIT 1",
                $quick_project,
                $candidate
            ) );
            if ( is_string( $verified_token ) && $verified_token === $candidate ) {
                $quick_token = $candidate;
            }
        }
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned fixed identifier and bound project.
        $quick_token_count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$tokens_table} WHERE project_uid = %s",
            $quick_project
        ) );
    }
    $generated = '';
    if ( '' !== $quick_project ) {
        $generated = nmkr_quick_shortcode_build( $quick_type, $quick_project, $quick_token, $quick_selector );
    }

    ?>
    <div class="wrap nmkr-admin-shell nmkr-shortcodes-page">
        <header class="nmkr-shortcodes-header">
            <div>
                <p class="nmkr-shortcodes-eyebrow"><?php esc_html_e( 'ROCSI Connector for NMKR', 'rocsi-connector-for-nmkr' ); ?></p>
                <h1><?php esc_html_e( 'Shortcodes', 'rocsi-connector-for-nmkr' ); ?></h1>
                <p class="nmkr-shortcodes-intro"><?php esc_html_e( 'Generate ready-to-use shortcode examples from your synchronized projects and NFTs. Detailed technical reference remains available below.', 'rocsi-connector-for-nmkr' ); ?></p>
            </div>
        </header>


        <section class="panel nmkr-quick-publish" aria-labelledby="nmkr-quick-publish-title">
            <div class="panel-header nmkr-shortcodes-panel-heading">
                <div>
                    <p class="nmkr-shortcodes-kicker"><?php esc_html_e( 'Quick publish', 'rocsi-connector-for-nmkr' ); ?></p>
                    <h2 id="nmkr-quick-publish-title"><?php esc_html_e( 'Generate a shortcode for your NFTs', 'rocsi-connector-for-nmkr' ); ?></h2>
                </div>
            </div>
            <p><?php esc_html_e( 'Choose a shortcode type and a synchronized project. Your code will contain real local UIDs and can be pasted into a WordPress Shortcode block.', 'rocsi-connector-for-nmkr' ); ?></p>
            <?php if ( empty( $projects ) ) : ?>
                <div class="nmkr-admin-state" role="status"><p>
                    <?php esc_html_e( 'No synchronized projects yet. Synchronize on the Dashboard before generating a project-specific shortcode.', 'rocsi-connector-for-nmkr' ); ?>
                </p></div>
            <?php else : ?>
                <form class="nmkr-generator-form" id="nmkr-generator-form" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" method="get">
                    <input type="hidden" name="page" value="nmkr-connect-shortcodes">
                    <div class="nmkr-generator-fields">
                        <label for="nmkr-gen-type">
                            <?php esc_html_e( 'Presentation', 'rocsi-connector-for-nmkr' ); ?>
                            <select name="nmkr_gen_type" id="nmkr-gen-type">
                                <?php foreach ( $shortcodes as $shortcode ) :
                                    $shortcode_type = substr( $shortcode['name'], 1, -1 ); ?>
                                    <option value="<?php echo esc_attr( $shortcode_type ); ?>" <?php selected( $quick_type, $shortcode_type ); ?>><?php echo esc_html( $shortcode['name'] ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label for="nmkr-gen-project">
                            <?php esc_html_e( 'Synchronized project', 'rocsi-connector-for-nmkr' ); ?>
                            <select name="nmkr_gen_project" id="nmkr-gen-project" required>
                                <option value=""><?php esc_html_e( 'Choose a project', 'rocsi-connector-for-nmkr' ); ?></option>
                                <?php foreach ( $projects as $project ) : ?>
                                    <option value="<?php echo esc_attr( $project->project_uid ); ?>" <?php selected( $quick_project, $project->project_uid ); ?>><?php echo esc_html( $project->project_name ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label for="nmkr-gen-selector">
                            <?php esc_html_e( 'Visitor project selector', 'rocsi-connector-for-nmkr' ); ?>
                            <select name="nmkr_gen_selector" id="nmkr-gen-selector">
                                <option value="1" <?php selected( $quick_selector, '1' ); ?>><?php esc_html_e( 'Show project selector', 'rocsi-connector-for-nmkr' ); ?></option>
                                <option value="0" <?php selected( $quick_selector, '0' ); ?>><?php esc_html_e( 'Show only chosen project', 'rocsi-connector-for-nmkr' ); ?></option>
                            </select>
                        </label>
                    </div>
                    <?php if ( 'nmkr-token' === $quick_type && '' !== $quick_project ) : ?>
                        <div class="nmkr-generator-token-fields">
                            <?php if ( ! empty( $quick_tokens ) ) : ?>
                                <label for="nmkr-gen-token">
                                    <?php esc_html_e( 'Choose synchronized NFT', 'rocsi-connector-for-nmkr' ); ?>
                                    <select name="nmkr_gen_token" id="nmkr-gen-token">
                                        <option value=""><?php esc_html_e( 'Choose an NFT', 'rocsi-connector-for-nmkr' ); ?></option>
                                        <?php foreach ( $quick_tokens as $token ) : ?>
                                            <option value="<?php echo esc_attr( $token->token_uid ); ?>" <?php selected( $requested_token, $token->token_uid ); ?>><?php echo esc_html( $token->token_name ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                            <?php endif; ?>
                            <label for="nmkr-gen-token-custom">
                                <?php esc_html_e( 'Or paste a token UID', 'rocsi-connector-for-nmkr' ); ?>
                                <input type="text" name="nmkr_gen_token_custom" id="nmkr-gen-token-custom" value="<?php echo esc_attr( $manual_token ); ?>" autocomplete="off" spellcheck="false" maxlength="255">
                            </label>
                            <?php if ( $quick_token_count > count( $quick_tokens ) ) : ?>
                                <p class="description"><?php esc_html_e( 'The selector shows the first 2,500 tokens alphabetically. For other NFTs, copy a token UID from NFT Projects into the field above.', 'rocsi-connector-for-nmkr' ); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <button type="submit" class="button button-primary"><?php esc_html_e( 'Update shortcode', 'rocsi-connector-for-nmkr' ); ?></button>
                </form>
                <?php if ( '' !== $generated ) : ?>
                    <div class="nmkr-generator-result">
                        <label for="nmkr-generated-shortcode"><strong><?php esc_html_e( 'Ready to paste', 'rocsi-connector-for-nmkr' ); ?></strong></label>
                        <textarea id="nmkr-generated-shortcode" rows="2" readonly aria-describedby="nmkr-generator-copy-help"><?php echo esc_textarea( $generated ); ?></textarea>
                        <div class="nmkr-generator-copy-row">
                            <button type="button" class="button button-secondary" id="nmkr-generator-copy" hidden><?php esc_html_e( 'Copy shortcode', 'rocsi-connector-for-nmkr' ); ?></button>
                            <span id="nmkr-generator-copy-feedback" role="status" aria-live="polite"></span>
                        </div>
                        <p id="nmkr-generator-copy-help" class="description"><?php esc_html_e( 'Paste this code into a WordPress Shortcode block. Without JavaScript, select and copy the text manually.', 'rocsi-connector-for-nmkr' ); ?></p>
                    </div>
                <?php elseif ( 'nmkr-token' === $quick_type && '' !== $quick_project ) : ?>
                    <p class="nmkr-admin-state" role="status"><?php esc_html_e( 'Select an NFT from this project (or paste its exact UID) to create a single-token shortcode.', 'rocsi-connector-for-nmkr' ); ?></p>
                <?php elseif ( '' !== $requested_project ) : ?>
                    <p class="nmkr-admin-state" role="status"><?php esc_html_e( 'The selected project is not available. Choose a synchronized project.', 'rocsi-connector-for-nmkr' ); ?></p>
                <?php endif; ?>
            <?php endif; ?>
        </section>

        <details class="nmkr-shortcodes-reference">
            <summary><?php esc_html_e( 'Full shortcode reference and project selection rules', 'rocsi-connector-for-nmkr' ); ?></summary>
            <div class="nmkr-shortcodes-reference-content">
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
        </details>
    </div>
    <?php
}
