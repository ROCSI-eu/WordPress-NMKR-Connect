<?php
/**
 * M5 B2 mobile NFT-first progressive disclosure source contracts.
 */
$root = dirname( __DIR__ );
function nmkr_b2_assert( $ok, $label ) {
    if ( ! $ok ) { fwrite( STDERR, "FAIL: {$label}\n" ); exit( 1 ); }
}
foreach ( array( 'grid', 'carousel' ) as $view ) {
    $php = file_get_contents( $root . '/includes/shortcodes/nmkr-shortcode-' . $view . '.php' );
    nmkr_b2_assert( 2 === substr_count( $php, 'data-nmkr-collapse-mobile="1"' ), $view . ' offers explicit filter and project disclosure' );
    nmkr_b2_assert( false !== strpos( $php, 'data-nmkr-keep-open="1"' ), $view . ' keeps active filters visible' );
    nmkr_b2_assert( false !== strpos( $php, 'nmkr-mobile-collection.js' ), $view . ' enqueues progressive viewport helper' );
    nmkr_b2_assert( false !== strpos( $php, 'name="nmkr_project"' )
        && false !== strpos( $php, 'name="search_token"' )
        && false !== strpos( $php, 'name="filter_minted"' ), $view . ' preserves URL/filter contract' );
}
$js = file_get_contents( $root . '/js/nmkr-mobile-collection.js' );
$css = file_get_contents( $root . '/css/nmkr-shortcode-foundation.css' );
$carousel_css = file_get_contents( $root . '/css/nmkr-shortcode-carousel.css' );
nmkr_b2_assert( false !== strpos( $js, 'max-width: 640px' )
    && false !== strpos( $js, 'data-nmkr-keep-open' )
    && false === strpos( $js, 'fetch(' ), 'JS is mobile-only, filter-safe and network free' );
nmkr_b2_assert( false !== strpos( $css, 'nmkr-mobile-disclosure' )
    && false !== strpos( $css, 'summary:focus-visible' ), 'disclosures have scoped and focus-visible styling' );
nmkr_b2_assert( false === strpos( $carousel_css, 'display: none;' ), 'mobile navigation controls remain visible' );
echo "PASS: M5 B2 mobile NFT browsing source contract\n";
