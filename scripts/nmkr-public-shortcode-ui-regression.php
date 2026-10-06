<?php
/**
 * Public-safe contract regression for the five public shortcode renderers.
 */

$root = dirname( __DIR__ );

function nmkr_public_shortcode_ui_assert( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

$shortcodes = array(
    'grid' => array(
        'file'       => 'includes/shortcodes/nmkr-shortcode-grid.php',
        'name'       => 'nmkr-grid',
        'root'       => 'nmkr-shortcode-grid',
        'view_scope' => 'grid',
    ),
    'list' => array(
        'file'       => 'includes/shortcodes/nmkr-shortcode-list.php',
        'name'       => 'nmkr-token-list',
        'root'       => 'nmkr-shortcode-list',
        'view_scope' => 'list',
    ),
    'carousel' => array(
        'file'       => 'includes/shortcodes/nmkr-shortcode-carousel.php',
        'name'       => 'nmkr-carousel',
        'root'       => 'nmkr-shortcode-carousel',
        'view_scope' => 'carousel',
    ),
    'token' => array(
        'file'       => 'includes/shortcodes/nmkr-shortcode-token.php',
        'name'       => 'nmkr-token',
        'root'       => 'nmkr-shortcode-token',
        'view_scope' => 'token',
    ),
    'project' => array(
        'file'       => 'includes/shortcodes/nmkr-shortcode-project.php',
        'name'       => 'nmkr-project',
        'root'       => 'nmkr-shortcode-project',
        'view_scope' => 'project',
    ),
);

foreach ( $shortcodes as $key => $contract ) {
    $source = file_get_contents( $root . '/' . $contract['file'] );

    nmkr_public_shortcode_ui_assert(
        false !== strpos( $source, "add_shortcode('" . $contract['name'] . "'" ),
        $contract['name'] . ' registration remains unchanged'
    );
    nmkr_public_shortcode_ui_assert(
        false !== strpos( $source, 'nmkr_enqueue_shortcode_foundation();' ),
        $contract['name'] . ' enqueues the shared frontend foundation'
    );
    nmkr_public_shortcode_ui_assert(
        false !== strpos( $source, 'class="nmkr-shortcode ' . $contract['root'] . '"' ),
        $contract['name'] . ' output is scoped by the shared root'
    );
    nmkr_public_shortcode_ui_assert(
        false !== strpos( $source, 'data-nmkr-evt="view"' )
            && false !== strpos( $source, 'data-nmkr-shortcode="' . $contract['view_scope'] . '"' )
            && false !== strpos( $source, 'data-nmkr-id="' ),
        $contract['name'] . ' keeps analytics view markers'
    );
    nmkr_public_shortcode_ui_assert(
        false !== strpos( $source, 'nmkr_render_shortcode_state(' ),
        $contract['name'] . ' uses the shared empty/error state for pre-render failures'
    );
}

foreach ( array( 'grid', 'list', 'carousel' ) as $key ) {
    $source = file_get_contents( $root . '/' . $shortcodes[ $key ]['file'] );
    nmkr_public_shortcode_ui_assert(
        false !== strpos( $source, 'No tokens match the current filters.' ),
        $shortcodes[ $key ]['name'] . ' has an intentional filtered no-results state'
    );
    nmkr_public_shortcode_ui_assert(
        false !== strpos( $source, 'name="search_token"' )
            && false !== strpos( $source, 'name="filter_minted"' )
            && false !== strpos( $source, 'name="nmkr_project"' ),
        $shortcodes[ $key ]['name'] . ' preserves public filter/query contracts'
    );
}

$grid_source = file_get_contents( $root . '/includes/shortcodes/nmkr-shortcode-grid.php' );
$list_source = file_get_contents( $root . '/includes/shortcodes/nmkr-shortcode-list.php' );
$carousel_source = file_get_contents( $root . '/includes/shortcodes/nmkr-shortcode-carousel.php' );
$token_source = file_get_contents( $root . '/includes/shortcodes/nmkr-shortcode-token.php' );
$project_source = file_get_contents( $root . '/includes/shortcodes/nmkr-shortcode-project.php' );

nmkr_public_shortcode_ui_assert(
    false !== strpos( $list_source, 'class="nmkr-token-list-region"' )
        && false !== strpos( $list_source, 'tabindex="0"' )
        && false !== strpos( $list_source, 'Scroll horizontally on narrow screens.' ),
    '[nmkr-token-list] keeps table density with an explicit focusable overflow region'
);

nmkr_public_shortcode_ui_assert(
    false === strpos( $carousel_source, 'onclick="scrollCarousel' )
        && false !== strpos( $carousel_source, 'class="nmkr-carousel-control carousel-prev"' )
        && false !== strpos( $carousel_source, 'class="nmkr-carousel-control carousel-next"' )
        && false !== strpos( $carousel_source, 'aria-label="' )
        && false !== strpos( $carousel_source, 'tabindex="0" role="region"' ),
    '[nmkr-carousel] uses native accessible controls and focusable native scrolling'
);

$carousel_js = file_get_contents( $root . '/js/nmkr-carousel.js' );
nmkr_public_shortcode_ui_assert(
    false !== strpos( $carousel_js, "closest('.nmkr-carousel-wrapper')" )
        && false !== strpos( $carousel_js, "prefers-reduced-motion: reduce" )
        && false === strpos( $carousel_js, 'setInterval(' ),
    'carousel JS is instance-scoped, reduced-motion aware, and does not auto-scroll'
);

$carousel_css = file_get_contents( $root . '/css/nmkr-shortcode-carousel.css' );
nmkr_public_shortcode_ui_assert(
    false !== strpos( $carousel_css, 'overflow-x: auto;' )
        && false === strpos( $carousel_css, 'scrollbar-width: none' )
        && false === strpos( $carousel_css, '::-webkit-scrollbar' ),
    'carousel preserves discoverable native scrolling rather than hiding scrollbars'
);

$list_css = file_get_contents( $root . '/css/nmkr-shortcode-list.css' );
nmkr_public_shortcode_ui_assert(
    false !== strpos( $list_css, '.nmkr-shortcode-list .nmkr-token-list-region' )
        && false !== strpos( $list_css, 'overflow-x: auto;' )
        && false !== strpos( $list_css, 'min-width: 760px;' ),
    'list renderer uses controlled local horizontal overflow'
);

nmkr_public_shortcode_ui_assert(
    false !== strpos( $token_source, 'not currently available for purchase' )
        && false !== strpos( $project_source, 'not currently available for purchase' ),
    'single-token and project featured-token renderers expose legitimate unavailable states'
);

$media_helper = file_get_contents( $root . '/includes/helpers/nmkr-media-helpers.php' );
$lightbox_helper = file_get_contents( $root . '/includes/helpers/nmkr-lightbox.php' );
$lightbox_js = file_get_contents( $root . '/js/nmkr-lightbox.js' );
nmkr_public_shortcode_ui_assert(
    false !== strpos( $media_helper, 'data-nmkr-lightbox-trigger="1"' )
        && false !== strpos( $media_helper, 'role="button" tabindex="0"' )
        && false === strpos( $media_helper, 'onclick="openLightbox' ),
    'shared token image trigger is keyboard-operable without inline JavaScript'
);
nmkr_public_shortcode_ui_assert(
    false !== strpos( $lightbox_helper, 'role="dialog"' )
        && false !== strpos( $lightbox_helper, 'aria-modal="true"' )
        && false !== strpos( $lightbox_helper, 'type="button" class="nmkr-close"' )
        && false === strpos( $lightbox_helper, 'onclick=' ),
    'lightbox uses dialog semantics and native controls without inline handlers'
);
nmkr_public_shortcode_ui_assert(
    false !== strpos( $lightbox_js, "event.key === 'Enter'" )
        && false !== strpos( $lightbox_js, "event.key === ' '" )
        && false !== strpos( $lightbox_js, "event.key === 'Tab'" )
        && false !== strpos( $lightbox_js, 'trapLightboxFocus' )
        && false !== strpos( $lightbox_js, "event.key === 'Escape'" ),
    'lightbox supports keyboard open, modal focus trapping, and escape-to-close'
);

foreach ( array( $grid_source, $list_source, $carousel_source, $token_source, $project_source ) as $source ) {
    if ( false !== strpos( $source, 'target="_blank"' ) ) {
        nmkr_public_shortcode_ui_assert(
            false !== strpos( $source, 'rel="noopener noreferrer"' ),
            'shortcode target=_blank links keep safe rel behavior'
        );
    }
}

$foundation_css = file_get_contents( $root . '/css/nmkr-shortcode-foundation.css' );
nmkr_public_shortcode_ui_assert(
    false !== strpos( $foundation_css, '.nmkr-shortcode' )
        && false !== strpos( $foundation_css, '@media (prefers-reduced-motion: reduce)' ),
    'shared shortcode foundation is scoped and reduced-motion aware'
);

$css_files = array(
    'css/nmkr-shortcode-foundation.css',
    'css/nmkr-shortcode-grid.css',
    'css/nmkr-shortcode-list.css',
    'css/nmkr-shortcode-carousel.css',
    'css/nmkr-shortcode-token.css',
    'css/nmkr-shortcode-project.css',
    'css/nmkr-buy-button.css',
);
foreach ( $css_files as $relative ) {
    $css = file_get_contents( $root . '/' . $relative );
    foreach ( array( "\nbody ", "\nbody{", "\na ", "\na{", "\nbutton ", "\nbutton{", "\nimg ", "\nimg{" ) as $global_selector ) {
        nmkr_public_shortcode_ui_assert(
            false === strpos( $css, $global_selector ),
            $relative . ' must not add host-theme global element selectors'
        );
    }
}

nmkr_public_shortcode_ui_assert(
    false !== strpos( $grid_source, 'data-nmkr-cta="buy"' )
        && false !== strpos( $list_source, 'data-nmkr-cta="buy"' )
        && false !== strpos( $carousel_source, 'data-nmkr-cta="buy"' )
        && false !== strpos( $token_source, 'data-nmkr-cta="buy"' )
        && false !== strpos( $project_source, 'data-nmkr-cta="buy"' ),
    'buy CTA analytics taxonomy remains present across all five renderers'
);

echo "Public shortcode UI contract regression: PASS\n";
