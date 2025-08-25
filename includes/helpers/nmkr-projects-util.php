<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Returns the most recently created project, or null if none.
 */
function nmkr_get_latest_project() {
    global $wpdb;
    $projects_table = $wpdb->prefix . 'nmkr_projects';
    $sql = "SELECT * FROM {$projects_table} ORDER BY created_at DESC LIMIT 1";
    return $wpdb->get_row( $sql );
}

/**
 * Returns the latest project UID or null.
 */
function nmkr_get_latest_project_uid() {
    $p = nmkr_get_latest_project();
    return $p ? $p->project_uid : null;
}

/**
 * Fetch tokens for a project with token_details joined (for sell_date, reserved_until, etc.).
 * Pass $limit for capping results; pass $only_buyable=true to only return tokens that are
 * not minted, not sold, and not actively reserved.
 */
function nmkr_get_project_tokens_joined( $project_uid, $limit = 0, $only_buyable = false ) {
    global $wpdb;
    $t  = $wpdb->prefix . 'nmkr_tokens';
    $td = $wpdb->prefix . 'nmkr_token_details';

    $where = $wpdb->prepare( "WHERE t.project_uid = %s", $project_uid );
    $limit_sql = $limit > 0 ? $wpdb->prepare( " LIMIT %d", $limit ) : "";

    // We'll filter buyable in PHP using helper to be consistent with time-aware logic
    $sql = "
        SELECT t.*, td.*
        FROM {$t} t
        LEFT JOIN {$td} td ON td.token_uid = t.token_uid
        {$where}
        ORDER BY t.created_at DESC
        {$limit_sql}
    ";

    $rows = $wpdb->get_results( $sql );
    if ( $only_buyable && is_array( $rows ) ) {
        $filtered = [];
        foreach ( $rows as $row ) {
            if ( function_exists('nmkr_token_is_buyable') ) {
                if ( nmkr_token_is_buyable( $row ) ) $filtered[] = $row;
            } else {
                $filtered[] = $row; // fallback
            }
        }
        return $filtered;
    }
    return $rows;
}

/**
 * Returns a single "first buyable" token for a project (or first token if none buyable),
 * using the joined query above.
 */
function nmkr_get_first_token_for_project( $project_uid, $prefer_buyable = true ) {
    $rows = nmkr_get_project_tokens_joined( $project_uid, 50, false ); // get a handful; filter client-side
    if ( ! $rows ) return null;

    if ( $prefer_buyable && function_exists('nmkr_token_is_buyable') ) {
        foreach ( $rows as $row ) {
            if ( nmkr_token_is_buyable( $row ) ) return $row;
        }
    }

    return $rows[0]; // else first token
}

/**
 * Single-instance resolver:
 * Precedence: GET[nmkr_project] -> $atts['project_uid'] -> latest project.
 */
function nmkr_get_active_project_uid_single( $atts ) {
    $uid = null;

    if ( isset($_GET['nmkr_project']) ) {
        $uid = sanitize_text_field( wp_unslash( $_GET['nmkr_project'] ) );
    }

    if ( empty( $uid ) && ! empty( $atts['project_uid'] ) ) {
        $uid = sanitize_text_field( $atts['project_uid'] );
    }

    if ( empty( $uid ) ) {
        $uid = nmkr_get_latest_project_uid();
    }

    return $uid;
}

/**
 * Render a simple selector that submits as ?nmkr_project=<uid> (single-instance).
 * Preserves other GET params (except nmkr_project).
 */
function nmkr_render_project_selector_simple( $projects, $active_uid ) {
    if ( empty( $projects ) ) {
        return '<p>' . esc_html__( 'No projects available.', 'nmkr-connect' ) . '</p>';
    }

    $scheme = is_ssl() ? 'https://' : 'http://';
    $action = esc_url( strtok( $scheme . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], '#' ) );

    ob_start(); ?>
    <form method="get" action="<?php echo $action; ?>" class="nmkr-project-selector" style="margin:12px 0;">
        <?php
        foreach ( $_GET as $k => $v ) {
            if ( $k === 'nmkr_project' ) { continue; }
            if ( is_array($v) ) { continue; }
            echo '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
        }
        ?>
        <label for="nmkr_project" style="margin-right:6px;">
            <?php esc_html_e( 'Select a Project:', 'nmkr-connect' ); ?>
        </label>
        <select id="nmkr_project" name="nmkr_project" onchange="this.form.submit()" style="min-width:260px;">
            <?php foreach ( $projects as $p ): ?>
                <option value="<?php echo esc_attr( $p->project_uid ); ?>" <?php selected( $p->project_uid, $active_uid ); ?>>
                    <?php
                    $label = '';
                    if ( ! empty( $p->project_name ) ) {
                        $label = $p->project_name;
                    } elseif ( ! empty( $p->name ) ) {
                        $label = $p->name;
                    } else {
                        $label = $p->project_uid; // last-resort visibility
                    }
                    echo esc_html( $label );
                    ?>
                </option>
            <?php endforeach; ?>
        </select>
        <noscript><button type="submit"><?php esc_html_e('Go', 'nmkr-connect'); ?></button></noscript>
    </form>
    <?php
    return ob_get_clean();
}
