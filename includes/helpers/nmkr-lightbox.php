<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function nmkr_print_lightbox_once() {
    static $printed = false;
    if ( $printed ) return;
    $printed = true;
    ?>
    <style>
      .nmkr-lightbox{position:fixed;inset:0;background:rgba(0,0,0,.9);display:none;align-items:center;justify-content:center;z-index:9999}
      .nmkr-lightbox img{max-width:90vw;max-height:90vh}
      .nmkr-lightbox.is-open{display:flex}
      .nmkr-lightbox .nmkr-close{position:absolute;top:16px;right:20px;font-size:32px;color:#fff;text-decoration:none}
    </style>
    <div id="nmkr-lightbox" class="nmkr-lightbox" onclick="if(event.target.id==='nmkr-lightbox'){closeLightbox()}">
      <a href="#" class="nmkr-close" onclick="closeLightbox();return false;">×</a>
      <img src="" alt="">
    </div>
    <script>
      function openLightbox(src){var el=document.getElementById('nmkr-lightbox'); if(!el) return; el.querySelector('img').src=src; el.classList.add('is-open');}
      function closeLightbox(){var el=document.getElementById('nmkr-lightbox'); if(!el) return; el.classList.remove('is-open'); el.querySelector('img').src='';}
    </script>
    <?php
}
