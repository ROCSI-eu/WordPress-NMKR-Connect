<?php
/**
 * Synthetic, public-safe regression checks for shortcode token images.
 */

define( 'ABSPATH', __DIR__ . '/../' );

function apply_filters( $hook, $value ) {
    if ( 'nmkr_ipfs_gateway_base' === $hook ) {
        return 'https://gateway.example.test/content';
    }

    return $value;
}

function esc_url_raw( $url ) {
    return filter_var( $url, FILTER_VALIDATE_URL ) ? $url : '';
}

require_once __DIR__ . '/../includes/helpers/nmkr-media-helpers.php';

function nmkr_image_assert_same( $expected, $actual, $message ) {
    if ( $expected !== $actual ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

$cid = 'QmYwAPJzv5CZsnAzt8auVZRnGi2C9B7F9hTQYxA9Z9n2mR';
$normalized = 'https://gateway.example.test/content/ipfs/' . $cid . '/token.png';

nmkr_image_assert_same(
    $normalized,
    nmkr_get_token_image_url( (object) array(
        'gateway_link' => 'https://provider.example.test/image.png',
        'ipfs_link'    => 'ipfs://' . $cid . '/token.png',
    ) ),
    'ipfs_link must take priority over gateway_link'
);

nmkr_image_assert_same(
    'https://provider.example.test/image.png',
    nmkr_get_token_image_url( (object) array(
        'gateway_link' => 'https://provider.example.test/image.png',
    ) ),
    'gateway_link must remain the fallback'
);

nmkr_image_assert_same(
    'https://static.example.test/token.webp',
    nmkr_resolve_ipfs_url( 'https://static.example.test/token.webp' ),
    'existing HTTPS URLs must remain unchanged'
);
nmkr_image_assert_same(
    $normalized,
    nmkr_resolve_ipfs_url( '/ipfs/' . $cid . '/token.png' ),
    'IPFS paths must use the configured gateway'
);
nmkr_image_assert_same(
    '',
    nmkr_get_token_image_url( (object) array(
        'gateway_link' => array( 'not-a-string' ),
        'ipfs_link'    => '',
        'metadata'     => (object) array( 'image' => '' ),
    ) ),
    'empty or unusable fields must return an empty value'
);

$shortcodes = array( 'grid', 'list', 'carousel', 'token', 'project' );
foreach ( $shortcodes as $shortcode ) {
    $source = file_get_contents( __DIR__ . '/../includes/shortcodes/nmkr-shortcode-' . $shortcode . '.php' );
    if ( false === $source || false === strpos( $source, "images/placeholder.png" ) ) {
        fwrite( STDERR, "FAIL: {$shortcode} shortcode must reference images/placeholder.png\n" );
        exit( 1 );
    }
    if ( false !== strpos( $source, "images/placeholder.jpg" ) ) {
        fwrite( STDERR, "FAIL: {$shortcode} shortcode references missing placeholder JPG\n" );
        exit( 1 );
    }
}

if ( ! is_file( __DIR__ . '/../images/placeholder.png' ) ) {
    fwrite( STDERR, "FAIL: packaged placeholder PNG is missing\n" );
    exit( 1 );
}

echo "Shortcode image-source regression: PASS\n";
