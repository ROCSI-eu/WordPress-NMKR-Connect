<?php
/**
 * Source-level regression for the Projects + admin Shortcodes workflow facelift.
 */

$root = dirname( __DIR__ );

function nmkr_projects_shortcodes_ui_assert( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

$projects_page   = file_get_contents( $root . '/includes/pages/projects/nmkr-projects.php' );
$shortcodes_page = file_get_contents( $root . '/includes/pages/shortcodes/nmkr-shortcodes.php' );
$projects_css    = file_get_contents( $root . '/css/admin/nmkr-projects.css' );
$shortcodes_css  = file_get_contents( $root . '/css/admin/nmkr-shortcodes.css' );

$project_contracts = array(
    'current_user_can( \'nmkr_view_projects\' )' => 'Projects capability remains nmkr_view_projects',
    "check_admin_referer( 'nmkr_projects_filter', 'nmkr_projects_filter_nonce' )" => 'Projects POST filter nonce remains unchanged',
    'name="project_uid"' => 'project_uid form contract remains present',
    'name="search_token"' => 'search_token form contract remains present',
    'name="filter_minted"' => 'filter_minted form contract remains present',
    'WHERE t.project_uid = %s' => 'token query remains constrained to the selected project',
    'class="wrap nmkr-admin-shell nmkr-projects-page"' => 'Projects uses its scoped admin shell',
    'nmkr-project-stats-grid' => 'Projects summary counters remain scannable',
    'nmkr-token-explorer' => 'Projects filter-to-content workflow remains explicit',
    'nmkr-token-preview-button' => 'token preview remains keyboard-operable',
    'Token UID' => 'Projects exposes token identifiers needed by the shortcode workflow',
    'No synchronized projects yet' => 'Projects has an intentional empty state',
    'No tokens match these filters' => 'Projects has an intentional no-results state',
    'Project not found' => 'Projects has an intentional stale-selection state',
);
foreach ( $project_contracts as $needle => $message ) {
    nmkr_projects_shortcodes_ui_assert( false !== strpos( $projects_page, $needle ), $message );
}

nmkr_projects_shortcodes_ui_assert(
    false === strpos( $projects_page, 'name="action"' ),
    'Projects facelift does not introduce AJAX/mutating action forms'
);

$shortcode_sources = array(
    'nmkr-grid'       => array( 'includes/shortcodes/nmkr-shortcode-grid.php', 'project_uid', 'allow_user_select' ),
    'nmkr-token-list' => array( 'includes/shortcodes/nmkr-shortcode-list.php', 'project_uid', 'allow_user_select' ),
    'nmkr-carousel'   => array( 'includes/shortcodes/nmkr-shortcode-carousel.php', 'project_uid', 'allow_user_select' ),
    'nmkr-token'      => array( 'includes/shortcodes/nmkr-shortcode-token.php', 'token_uid' ),
    'nmkr-project'    => array( 'includes/shortcodes/nmkr-shortcode-project.php', 'project_uid', 'allow_user_select' ),
);

foreach ( $shortcode_sources as $shortcode => $contract ) {
    $source = file_get_contents( $root . '/' . $contract[0] );
    nmkr_projects_shortcodes_ui_assert(
        false !== strpos( $source, "add_shortcode('{$shortcode}'" ),
        "{$shortcode} remains registered"
    );
    nmkr_projects_shortcodes_ui_assert(
        false !== strpos( $shortcodes_page, '[' . $shortcode . ']' ),
        "admin guide documents {$shortcode}"
    );

    foreach ( array_slice( $contract, 1 ) as $attribute ) {
        nmkr_projects_shortcodes_ui_assert(
            false !== strpos( $source, "'{$attribute}'" ),
            "{$shortcode} runtime keeps {$attribute}"
        );
        nmkr_projects_shortcodes_ui_assert(
            false !== strpos( $shortcodes_page, "'{$attribute}'" ),
            "admin guide documents {$shortcode} {$attribute}"
        );
    }
}

$selection_helper = file_get_contents( $root . '/includes/helpers/nmkr-projects-util.php' );
nmkr_projects_shortcodes_ui_assert(
    false !== strpos( $selection_helper, 'Precedence: GET[nmkr_project] -> $atts[\'project_uid\'] -> latest project.' ),
    'runtime project-selection precedence remains documented in its helper'
);
nmkr_projects_shortcodes_ui_assert(
    false !== strpos( $shortcodes_page, '?nmkr_project=&lt;uid&gt;' )
        && false !== strpos( $shortcodes_page, 'allow_user_select="0"' ),
    'admin guide explains URL selection and selector visibility'
);

nmkr_projects_shortcodes_ui_assert(
    false === strpos( $shortcodes_page, 'tokens from all available projects' ),
    'admin guide does not repeat the old inaccurate all-projects claim'
);
nmkr_projects_shortcodes_ui_assert(
    false === strpos( $shortcodes_page, 'first available token in the system' ),
    'admin guide does not repeat the old inaccurate token fallback claim'
);
nmkr_projects_shortcodes_ui_assert(
    false !== strpos( $shortcodes_page, 'first buyable token' ),
    'admin guide documents the actual parameterless token fallback'
);

$presentation_contracts = array(
    array( $projects_css, '.nmkr-projects-page', 'Projects CSS is page scoped' ),
    array( $projects_css, '@media (max-width: 782px)', 'Projects covers the WordPress narrow-admin breakpoint' ),
    array( $projects_css, '@media (max-width: 520px)', 'Projects covers small mobile widths' ),
    array( $shortcodes_css, '.nmkr-shortcodes-page', 'Shortcodes CSS is page scoped' ),
    array( $shortcodes_css, '.nmkr-shortcode-example pre', 'Shortcode examples are presented as selectable code blocks' ),
    array( $shortcodes_css, '@media (max-width: 782px)', 'Shortcodes covers the WordPress narrow-admin breakpoint' ),
    array( $shortcodes_css, '@media (max-width: 480px)', 'Shortcodes covers small mobile widths' ),
);
foreach ( $presentation_contracts as $contract ) {
    nmkr_projects_shortcodes_ui_assert(
        false !== strpos( $contract[0], $contract[1] ),
        $contract[2]
    );
}

echo "Projects + Shortcodes admin UI regression: PASS\n";
