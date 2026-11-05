<?php
/**
 * M5 B1: no cross-chain explorer links in public NFT presentation.
 * PHP-only unit and scoped source contract checks (no WordPress state required).
 */

define( 'ABSPATH', __DIR__ );
require_once dirname( __DIR__ ) . '/includes/helpers/nmkr-ui-helpers.php';

function nmkr_b1_expect( $condition, $label ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$label}\n" );
        exit( 1 );
    }
}

$policy = str_repeat( 'a', 56 );
$cases = array(
    'cardano' => (object) array( 'blockchain' => 'Cardano', 'policy_id' => $policy ),
    'case-insensitive' => (object) array( 'blockchain' => ' cArDaNo ', 'policy_id' => ' ' . $policy . ' ' ),
    'solana-with-legacy-policy' => (object) array( 'blockchain' => 'Solana', 'policy_id' => $policy ),
    'unknown-chain' => (object) array( 'blockchain' => 'Polygon', 'policy_id' => $policy ),
    'missing-chain' => (object) array( 'policy_id' => $policy ),
    'missing-policy' => (object) array( 'blockchain' => 'Cardano' ),
    'empty-policy' => (object) array( 'blockchain' => 'Cardano', 'policy_id' => '  ' ),
    'invalid-record' => null,
    'array-policy' => (object) array( 'blockchain' => 'Cardano', 'policy_id' => array( $policy ) ),
);

foreach ( $cases as $label => $project ) {
    $actual = nmkr_get_project_cardanoscan_url( $project );
    $expected = in_array( $label, array( 'cardano', 'case-insensitive' ), true )
        ? 'https://cardanoscan.io/tokenPolicy/' . $policy
        : '';
    nmkr_b1_expect( $actual === $expected, $label . ' expected a chain-safe CardanoScan link' );
}

foreach ( array( 'project', 'carousel' ) as $renderer ) {
    $source = file_get_contents( dirname( __DIR__ ) . '/includes/shortcodes/nmkr-shortcode-' . $renderer . '.php' );
    nmkr_b1_expect( false !== strpos( $source, 'nmkr_get_project_cardanoscan_url(' ), $renderer . ' uses the shared chain gate' );
    nmkr_b1_expect( false === strpos( $source, 'if ( ! empty( $project->policy_id ) )' )
        && false === strpos( $source, 'if ( ! empty( $selected_project->policy_id ) )' ),
        $renderer . ' does not gate CardanoScan by presence of policy_id alone'
    );
    nmkr_b1_expect( false !== strpos( $source, 'esc_url( $cardanoscan_url )' ), $renderer . ' escapes the generated URL' );
}

echo "PASS: M5 B1 Cardano-only explorer link contract\n";
