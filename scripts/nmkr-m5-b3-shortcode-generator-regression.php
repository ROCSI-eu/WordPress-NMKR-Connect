<?php
/**
 * M5 B3: project-aware shortcode generator safety/contract regression.
 */
define( 'ABSPATH', __DIR__ );
require_once dirname( __DIR__ ) . '/includes/pages/shortcodes/nmkr-shortcode-quick-generator.php';

function nmkr_b3_test( $actual, $expected, $name ) {
    if ( $actual !== $expected ) { fwrite( STDERR, "FAIL: {$name}\n" ); exit( 1 ); }
}
$project = 'p-123_abc';
$token = 't-321_xyz';
foreach ( array( 'nmkr-grid', 'nmkr-token-list', 'nmkr-carousel', 'nmkr-project' ) as $type ) {
    nmkr_b3_test( nmkr_quick_shortcode_build( $type, $project, '', '0' ),
        '[' . $type . ' project_uid="' . $project . '" allow_user_select="0"]', $type . ' selector off' );
    nmkr_b3_test( nmkr_quick_shortcode_build( $type, $project, '', '1' ),
        '[' . $type . ' project_uid="' . $project . '" allow_user_select="1"]', $type . ' selector on' );
}
nmkr_b3_test( nmkr_quick_shortcode_build( 'nmkr-token', '', $token, '0' ), '[nmkr-token token_uid="' . $token . '"]', 'single token' );
foreach ( array( '', 'UID" injected', 'UID]bad', 'UID bad' ) as $invalid ) {
    nmkr_b3_test( nmkr_quick_shortcode_build( 'nmkr-grid', $invalid, '', '1' ), '', 'invalid project' );
    nmkr_b3_test( nmkr_quick_shortcode_build( 'nmkr-token', '', $invalid, '0' ), '', 'invalid token' );
}
nmkr_b3_test( nmkr_quick_shortcode_build( 'nmkr-unknown', $project, $token, '1' ), '', 'unknown type rejected' );
$source = file_get_contents( dirname( __DIR__ ) . '/includes/pages/shortcodes/nmkr-shortcodes.php' );
foreach ( array( 'nmkr_view_shortcodes', 'method="get"', 'nmkr_gen_project', 'nmkr_gen_token', 'nmkr_gen_token_custom', 'nmkr-shortcodes-reference', 'esc_textarea( $generated )', 'SELECT token_uid FROM', 'get_var( $wpdb->prepare(', '$both_token_inputs', 'Choose an NFT or paste its UID, not both' ) as $item ) {
    if ( false === strpos( $source, $item ) ) { fwrite( STDERR, "FAIL: missing source contract {$item}\n" ); exit( 1 ); }
}
$bootstrap = file_get_contents( dirname( __DIR__ ) . '/rocsi-connector-for-nmkr.php' );
if ( false === strpos( $bootstrap, "'nmkr-shortcode-generator', 'js/admin/nmkr-shortcode-generator.js'" ) ) {
    fwrite( STDERR, "FAIL: shortcode generator JS must be enqueued on admin_enqueue_scripts before rendering\\n" ); exit( 1 );
}
$js = file_get_contents( dirname( __DIR__ ) . '/js/admin/nmkr-shortcode-generator.js' );
if ( false !== strpos( $js, 'fetch(' ) || false === strpos( $js, 'navigator.clipboard' ) ) {
    fwrite( STDERR, "FAIL: generator JS must remain network-free and copy-capable\n" ); exit( 1 );
}
echo "PASS: M5 B3 quick publish generator contract\n";
