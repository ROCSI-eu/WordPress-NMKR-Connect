<?php
/** Public-safe token image source and markup regression. */
define('ABSPATH', __DIR__ . '/../');
define('NMKR_CONNECT_PLUGIN_FILE', __DIR__ . '/../nmkr-connect.php');

$GLOBALS['nmkr_test_gateway'] = '';
function apply_filters($hook, $value) { return 'nmkr_ipfs_gateway_base' === $hook ? $GLOBALS['nmkr_test_gateway'] : $value; }
function esc_url_raw($url) { return is_string($url) && preg_match('#^https://#i', $url) && filter_var($url, FILTER_VALIDATE_URL) ? $url : ''; }
function esc_url($url) { return esc_url_raw($url); }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function plugins_url($path) { return 'https://plugin.example.test/' . ltrim($path, '/'); }
function plugin_dir_path() { return __DIR__ . '/../'; }
function wp_enqueue_script($handle) { $GLOBALS['nmkr_enqueued'][$handle] = true; }
require_once __DIR__ . '/../includes/helpers/nmkr-media-helpers.php';

function nmkr_image_assert($condition, $message) {
    if (!$condition) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
}
function nmkr_image_same($expected, $actual, $message) { nmkr_image_assert($expected === $actual, $message); }

$cid0 = 'QmYwAPJzv5CZsnAzt8auVZRnGi2C9B7F9hTQYxA9Z9n2mR';
$cid32 = 'bafybeigdyrzt5sfp7udm7hu76uh7y26nf3ditjj7guc2v7qxu3qvbnvwya';
$cid58 = 'zdj7WVRuyEiwKpFdZU8UPrxcX9KtmQTzkbR4nUWfu7D9DWycA';
$cid36 = 'k2jmtxrd43ukk1y7sbez98gxlwwbobotav868thdas64o6xu8eqxruvz';
$provider = 'https://provider.example.test/ipfs/original/image.png';
$placeholder = 'https://plugin.example.test/images/placeholder.png';

nmkr_image_same('', nmkr_resolve_ipfs_url('ipfs://' . $cid0), 'empty default must disable IPFS fallback');
$accepted = array(
    'https://gateway.example.test' => 'https://gateway.example.test/ipfs/',
    'https://gateway.example.test/' => 'https://gateway.example.test/ipfs/',
    'https://gateway.example.test/ipfs' => 'https://gateway.example.test/ipfs/',
    'https://gateway.example.test/ipfs/' => 'https://gateway.example.test/ipfs/',
    'https://gateway.example.test/content/' => 'https://gateway.example.test/content/ipfs/',
);
foreach ($accepted as $input => $expected) nmkr_image_same($expected, nmkr_normalize_ipfs_gateway_base($input), 'gateway normalization: ' . $input);
$rejected = array('', ' http://gateway.test', 'http://gateway.test', '//gateway.test', '/relative', 'javascript:alert(1)', 'data:text/plain,x', 'file:///tmp/x', 'https://', "https://gateway.test/\n", 'https://user:pass@gateway.test', 'https://gateway.test/path?q=1', 'https://gateway.test/path#x', array('x'), (object) array('x' => 1));
foreach ($rejected as $input) nmkr_image_same('', nmkr_normalize_ipfs_gateway_base($input), 'unsafe gateway must be rejected');

$GLOBALS['nmkr_test_gateway'] = 'https://gateway.example.test/content';
foreach (array($cid0, $cid32, $cid58, $cid36) as $cid) {
    nmkr_image_assert(nmkr_is_usable_ipfs_image_input('ipfs://' . $cid . '/token.png'), 'supported CID must remain accepted');
    nmkr_image_same('https://gateway.example.test/content/ipfs/' . $cid . '/token.png', nmkr_resolve_ipfs_url('/ipfs/' . $cid . '/token.png'), 'CID path must be preserved');
}
nmkr_image_assert(!nmkr_is_usable_ipfs_image_input('not-a-cid') && !nmkr_is_usable_ipfs_image_input(" \t\n"), 'malformed IPFS inputs must be rejected');

$both = (object) array('gateway_link' => $provider, 'ipfs_link' => 'ipfs://' . $cid0 . '/token.png');
$set = nmkr_get_token_image_candidates($both);
nmkr_image_same($provider, $set['primary'], 'provider must be primary and unchanged');
nmkr_image_same('https://gateway.example.test/content/ipfs/' . $cid0 . '/token.png', $set['fallback'], 'configured IPFS must be fallback');
nmkr_image_same($placeholder, $set['placeholder'], 'packaged placeholder must be terminal');
nmkr_image_same($provider, nmkr_get_token_image_url($both), 'compatibility helper must return primary');
nmkr_image_same($provider, nmkr_get_token_image_url((object) array('gateway_link' => $provider)), 'provider-only source');
nmkr_image_same('https://gateway.example.test/content/ipfs/' . $cid0, nmkr_get_token_image_url((object) array('ipfs_link' => $cid0)), 'configured-IPFS-only source');
nmkr_image_same($provider, nmkr_get_token_image_url((object) array('gateway_link' => $provider, 'ipfs_link' => " \t\n")), 'whitespace IPFS must not suppress provider');
nmkr_image_same($provider, nmkr_get_token_image_url((object) array('gateway_link' => $provider, 'ipfs_link' => 'not-a-cid')), 'malformed IPFS must not suppress provider');
nmkr_image_same($placeholder, nmkr_get_token_image_url((object) array('gateway_link' => 'http://unsafe.test/x.png', 'ipfs_link' => 'not-a-cid')), 'malformed sources use placeholder');
nmkr_image_same('https://metadata.example.test/image.webp', nmkr_get_token_image_url((object) array('metadata' => (object) array('image' => 'https://metadata.example.test/image.webp'))), 'metadata fallback');
nmkr_image_same('https://direct.example.test/image.png', nmkr_get_token_image_url((object) array('ipfs_link' => 'https://direct.example.test/image.png')), 'direct HTTPS input');
$dedup = nmkr_get_token_image_candidates((object) array('gateway_link' => $provider, 'metadata' => (object) array('image' => $provider)));
nmkr_image_same('', $dedup['fallback'], 'duplicate URLs must be removed');

$markup = nmkr_get_token_image_markup($both, 'Synthetic "token"', 'token-image', 'data-test="kept"');
foreach (array('data-nmkr-token-image="1"', 'data-nmkr-fallback-src=', 'data-nmkr-placeholder-src=', 'loading="lazy"', 'decoding="async"', 'onclick="openLightbox(this.src)"', 'data-test="kept"') as $needle) nmkr_image_assert(false !== strpos($markup, $needle), 'shared markup state: ' . $needle);
nmkr_image_assert(!empty($GLOBALS['nmkr_enqueued']['nmkr-token-image-fallback']), 'fallback script must be enqueued');

foreach (array('grid', 'list', 'carousel', 'token', 'project') as $shortcode) {
    $source = file_get_contents(__DIR__ . '/../includes/shortcodes/nmkr-shortcode-' . $shortcode . '.php');
    nmkr_image_assert(false !== strpos($source, 'nmkr_get_token_image_markup'), $shortcode . ' must use shared token markup');
    nmkr_image_assert(false === strpos($source, 'images/placeholder.jpg'), $shortcode . ' must not use missing JPG');
}
$project_source = file_get_contents(__DIR__ . '/../includes/shortcodes/nmkr-shortcode-project.php');
nmkr_image_assert(false !== strpos($project_source, 'class="nmkr-project-logo"') && false !== strpos($project_source, 'nmkr_get_project_logo_url'), 'project logo must remain separate');
nmkr_image_assert(is_file(__DIR__ . '/../images/placeholder.png'), 'packaged placeholder must exist');
echo "Shortcode image-source regression: PASS\n";
