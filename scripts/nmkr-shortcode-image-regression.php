<?php
/**
 * Synthetic, public-safe regression checks for shortcode token images.
 */

define( 'ABSPATH', __DIR__ . '/../' );

function apply_filters( $hook, $value ) {
    if ( 'nmkr_ipfs_gateway_base' === $hook && ! empty( $GLOBALS['nmkr_test_custom_gateway'] ) ) {
        return 'https://gateway.example.test/content';
    }

    return $value;
}

function esc_url_raw( $url ) {
    $url = str_replace( ' ', '%20', trim( $url ) );
    return preg_match( '#^https?://#i', $url ) && filter_var( $url, FILTER_VALIDATE_URL ) ? $url : '';
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
$base58_cid = 'zdj7WVRuyEiwKpFdZU8UPrxcX9KtmQTzkbR4nUWfu7D9DWycA';
$base36_cid = 'k2jmtxrd43ukk1y7sbez98gxlwwbobotav868thdas64o6xu8eqxruvz';

nmkr_image_assert_same(
    'https://gateway.pinata.cloud/ipfs/' . $cid . '/token.png',
    nmkr_resolve_ipfs_url( 'ipfs://' . $cid . '/token.png' ),
    'IPFS paths must use the Pinata gateway by default'
);

$GLOBALS['nmkr_test_custom_gateway'] = true;

nmkr_image_assert_same(
    $normalized,
    nmkr_get_token_image_url( (object) array(
        'gateway_link' => 'https://provider.example.test/image.png',
        'ipfs_link'    => 'ipfs://' . $cid . '/token.png',
    ) ),
    'ipfs_link must take priority over gateway_link'
);

nmkr_image_assert_same(
    'https://gateway.example.test/content/ipfs/' . $base58_cid . '/token.png',
    nmkr_get_token_image_url( (object) array(
        'gateway_link' => 'https://provider.example.test/image.png',
        'ipfs_link'    => 'ipfs://' . $base58_cid . '/token.png',
    ) ),
    'base58btc CIDv1 ipfs_link must take priority over gateway_link'
);

nmkr_image_assert_same(
    'https://gateway.example.test/content/ipfs/' . $base36_cid . '/token.png',
    nmkr_get_token_image_url( (object) array(
        'gateway_link' => 'https://provider.example.test/image.png',
        'ipfs_link'    => '/ipfs/' . $base36_cid . '/token.png',
    ) ),
    'base36 CIDv1 ipfs_link must take priority over gateway_link'
);

nmkr_image_assert_same(
    'https://provider.example.test/image.png',
    nmkr_get_token_image_url( (object) array(
        'gateway_link' => 'https://provider.example.test/image.png',
        'ipfs_link'    => " \t\n ",
    ) ),
    'whitespace-only ipfs_link must not suppress gateway_link'
);

nmkr_image_assert_same(
    'https://provider.example.test/image.png',
    nmkr_get_token_image_url( (object) array(
        'gateway_link' => 'https://provider.example.test/image.png',
        'ipfs_link'    => 'not-a-cid',
    ) ),
    'malformed ipfs_link must not suppress gateway_link'
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
