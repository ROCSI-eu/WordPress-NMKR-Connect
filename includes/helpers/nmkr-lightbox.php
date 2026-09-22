<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function nmkr_enqueue_lightbox_assets() {
    nmkr_connect_enqueue_style_asset( 'nmkr-lightbox', 'css/nmkr-lightbox.css' );
    nmkr_connect_enqueue_script_asset( 'nmkr-lightbox', 'js/nmkr-lightbox.js', array(), true );
}

function nmkr_print_lightbox_once() {
    static $printed = false;
    if ( $printed ) return;
    $printed = true;
    nmkr_enqueue_lightbox_assets();
    ?>

    <div id="nmkr-lightbox" class="nmkr-lightbox" onclick="if(event.target.id==='nmkr-lightbox'){closeLightbox()}">
      <a href="#" class="nmkr-close" onclick="closeLightbox();return false;">×</a>
      <img src="" alt="">
    </div>

    <?php
}
