<?php
/**
 * Public-safe shortcode output-escaping regression.
 */

define( 'ABSPATH', __DIR__ . '/../' );
define( 'NMKR_CONNECT_PLUGIN_FILE', __DIR__ . '/../rocsi-connector-for-nmkr.php' );

function nmkr_shortcode_output_assert( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

function number_format_i18n( $number, $decimals = 0 ) {
    return '<em>' . number_format( (float) $number, (int) $decimals, '.', ',' ) . '</em>';
}
function esc_html( $value ) {
    return htmlspecialchars( (string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
}
function esc_attr( $value ) {
    return htmlspecialchars( (string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
}
function esc_url_raw( $url ) {
    return is_string( $url ) && preg_match( '#^https://#i', $url ) && filter_var( $url, FILTER_VALIDATE_URL ) ? $url : '';
}
function esc_url( $url ) {
    return esc_url_raw( $url );
}
function apply_filters( $hook, $value ) {
    return $value;
}
function wp_parse_url( $url, $component = -1 ) {
    return parse_url( $url, $component );
}
function plugins_url( $path ) {
    return 'https://plugin.example.test/' . ltrim( $path, '/' );
}
function plugin_dir_path() {
    return __DIR__ . '/../';
}
function wp_enqueue_script( $handle ) {
    $GLOBALS['nmkr_shortcode_output_enqueued'][ $handle ] = true;
}

require_once __DIR__ . '/../includes/helpers/nmkr-utility-functions.php';
require_once __DIR__ . '/../includes/helpers/nmkr-media-helpers.php';

$price_token = (object) array(
    'price'        => 123450000,
    'price_solana' => 2500000000,
);
$price_markup = nmkr_render_token_price_badges( $price_token );
nmkr_shortcode_output_assert( false === strpos( $price_markup, '<em>' ), 'localized price text must not remain raw HTML' );
nmkr_shortcode_output_assert( false !== strpos( $price_markup, '&lt;em&gt;123.45&lt;/em&gt; ADA' ), 'ADA price text must be HTML escaped' );
nmkr_shortcode_output_assert( false !== strpos( $price_markup, '&lt;em&gt;2.5000&lt;/em&gt; SOL' ), 'SOL price text must be HTML escaped' );

$image_token = (object) array(
    'gateway_link' => 'https://provider.example.test/image.png',
);
$image_markup = nmkr_get_token_image_markup(
    $image_token,
    'Token "alt"',
    'token-image',
    'color:red" onerror="alert(1)'
);
nmkr_shortcode_output_assert( false === strpos( $image_markup, ' onerror="' ), 'style input must not create a new HTML attribute' );
nmkr_shortcode_output_assert(
    false !== strpos( $image_markup, 'style="color:red&quot; onerror=&quot;alert(1)"' ),
    'style input must stay escaped inside the style attribute'
);
nmkr_shortcode_output_assert(
    false !== strpos( $image_markup, 'alt="Token &quot;alt&quot;"' ),
    'image alt text must be escaped at the attribute boundary'
);

$root = dirname( __DIR__ );
$status_files = array(
    'includes/shortcodes/nmkr-shortcode-token.php',
    'includes/shortcodes/nmkr-shortcode-grid.php',
    'includes/shortcodes/nmkr-shortcode-list.php',
);
foreach ( $status_files as $relative ) {
    $source = file_get_contents( $root . '/' . $relative );
    nmkr_shortcode_output_assert(
        false !== strpos( $source, "esc_attr( \$status_class )" ),
        $relative . ' must escape the status class at the attribute boundary'
    );
    nmkr_shortcode_output_assert(
        false === strpos( $source, "' . \$status_class . '" ),
        $relative . ' must not append a raw status class'
    );
}

$project_source = file_get_contents( $root . '/includes/shortcodes/nmkr-shortcode-project.php' );
$carousel_source = file_get_contents( $root . '/includes/shortcodes/nmkr-shortcode-carousel.php' );
foreach ( array( $project_source, $carousel_source ) as $source ) {
    nmkr_shortcode_output_assert(
        false === strpos( $source, "https://cardanoscan.io/tokenPolicy/' . esc_html" ),
        'CardanoScan URL paths must not use HTML escaping as URL escaping'
    );
    nmkr_shortcode_output_assert(
        false === strpos( $source, "https://twitter.com/' . esc_attr" ),
        'Twitter URL paths must not use attribute escaping as URL escaping'
    );
    nmkr_shortcode_output_assert(
        false !== strpos( $source, "esc_url( 'https://cardanoscan.io/tokenPolicy/' . rawurlencode" ),
        'CardanoScan URL must escape the final URL after encoding its path segment'
    );
    nmkr_shortcode_output_assert(
        false !== strpos( $source, "esc_url( 'https://twitter.com/' . rawurlencode" ),
        'Twitter URL must escape the final URL after encoding its path segment'
    );
}

$selector_source = file_get_contents( $root . '/includes/helpers/nmkr-projects-util.php' );
foreach ( array(
    'esc_url( $action )',
    'esc_attr( $key )',
    'esc_attr( $value )',
    'esc_attr( $p->project_uid )',
    'esc_html( $label )',
) as $needle ) {
    nmkr_shortcode_output_assert(
        false !== strpos( $selector_source, $needle ),
        'project selector trusted markup must escape dynamic output: ' . $needle
    );
}

echo "Shortcode output-escaping regression: PASS\n";
