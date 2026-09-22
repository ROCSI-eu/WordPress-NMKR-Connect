<?php
$root = dirname(__DIR__);
function check($condition, $message) { if (!$condition) { fwrite(STDERR, "FAIL: $message\n"); exit(1); } echo "PASS: $message\n"; }
$plugin = file_get_contents($root . '/rocsi-connector-for-nmkr.php');
$composer = json_decode(file_get_contents($root . '/composer.json'), true);
$readme = file_get_contents($root . '/readme.txt');
$runtime = $plugin;
foreach (glob($root . '/includes/{shortcodes,pages/shortcodes}/*.php', GLOB_BRACE) as $file) { $runtime .= file_get_contents($file); }
check(!preg_match('/freemius|wnc_fs|can_use_premium_code|is_plan\s*\(/i', $runtime), 'runtime and shortcode paths contain no entitlement SDK references');
foreach (['nmkr-token','nmkr-token-list','nmkr-project','nmkr-carousel','nmkr-grid'] as $code) check(strpos($plugin, "add_shortcode('$code'") !== false, "$code is registered");
check($composer['license'] === 'MIT' && count($composer['require']) === 1, 'Composer declares MIT and PHP as the only runtime requirement');
check(preg_match('/Plugin Name:\s*ROCSI Connector for NMKR\s*$/mi', $plugin) === 1, 'plugin header uses the owner-approved public display name');
check(preg_match('/Text Domain:\s*rocsi-connector-for-nmkr\s*$/mi', $plugin) === 1, 'plugin header text domain matches the candidate directory slug');
check(preg_match('/^=== ROCSI Connector for NMKR ===$/m', $readme) === 1, 'directory readme title matches the owner-approved public display name');
$plugin_version_match = array();
$stable_tag_match = array();
check(preg_match('/^Version:\s*([0-9]+\.[0-9]+\.[0-9]+)\s*$/mi', $plugin, $plugin_version_match) === 1, 'plugin header declares a numeric three-component version');
check(preg_match('/^Stable tag:\s*([0-9]+\.[0-9]+\.[0-9]+)\s*$/mi', $readme, $stable_tag_match) === 1, 'directory readme declares a numeric three-component stable tag');
$plugin_version = $plugin_version_match[1];
$stable_tag = $stable_tag_match[1];
check($plugin_version === '0.25.0', 'first public stable release is 0.25.0');
check($stable_tag === $plugin_version, 'plugin version and stable tag agree');
check(preg_match('/Requires at least:\s*5\.8/i', $plugin) && preg_match('/Requires at least:\s*5\.8/i', $readme), 'plugin and directory metadata agree on WordPress 5.8 minimum');
$runtime_php = $plugin;
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/includes', FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if ($file->isFile() && strtolower($file->getExtension()) === 'php') { $runtime_php .= file_get_contents($file->getPathname()); }
}
check(strpos($runtime_php, "'nmkr-connect'") === false && strpos($runtime_php, '"nmkr-connect"') === false, 'runtime PHP contains no legacy nmkr-connect gettext-domain literal');\ncheck(strpos($runtime_php, "'connector-for-nmkr'") === false && strpos($runtime_php, '"connector-for-nmkr"') === false, 'runtime PHP contains no superseded connector-for-nmkr gettext-domain literal');
$stale_basenames = array(\n    'nmkr-connect/' . 'nmkr-connect.php',\n    'connector-for-nmkr/' . 'nmkr-connect.php',\n    'connector-for-nmkr/' . 'connector-for-nmkr.php',\n    'rocsi-connector-for-nmkr/' . 'nmkr-connect.php',\n    'rocsi-connector-for-nmkr/' . 'connector-for-nmkr.php',\n);
$basename_sources = array($root . '/.env.tests.example');
foreach (array($root . '/scripts', $root . '/tests') as $scan_root) {
    $scan = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($scan_root, FilesystemIterator::SKIP_DOTS));
    foreach ($scan as $file) {
        if ($file->isFile()) { $basename_sources[] = $file->getPathname(); }
    }
}
$stale_basename_files = array();
foreach ($basename_sources as $file) {
    $contents = @file_get_contents($file);
    if (!is_string($contents)) continue;
    foreach ($stale_basenames as $stale_basename) {
        if (strpos($contents, $stale_basename) !== false) { $stale_basename_files[] = $file; break; }
    }
}
check(empty($stale_basename_files), 'current test and tooling defaults contain no stale installed plugin basename');
$validation = file_get_contents($root . '/includes/pages/settings/nmkr-settings-validation.php');
$core = file_get_contents($root . '/includes/pages/settings/nmkr-settings-core.php');
$helpers = file_get_contents($root . '/includes/helpers/nmkr-analytics-helpers.php');
check(substr_count($validation, "'analytics_mode' => 'off'") >= 1 && strpos($core, "val('off')") !== false, 'analytics defaults and reset are Off');
check(strpos($validation, "'analytics_require_consent' => 1") !== false && strpos($helpers, ': true;') !== false, 'consent defaults fail safe');
check(substr_count($plugin, "wp_clear_scheduled_hook('nmkr_analytics_purge_daily')") === 2, 'deactivation and uninstall clear analytics cron');
$roles = file_get_contents($root . '/includes/roles/nmkr-roles.php');
check(strpos($roles, '\'role__in\' => $owned_roles') !== false && strpos($roles, '$user->remove_role( $role_key );') !== false, 'uninstall clears plugin-owned user role assignments before role definitions can be recreated');
check(strpos($roles, 'remove_role( $role_key );') !== false && strpos($roles, "remove_role( 'administrator' )") === false, 'uninstall role cleanup uses the owned-role specification');
check(strpos($plugin, 'wp_add_privacy_policy_content') !== false, 'Privacy Policy Guide content is registered');
check(strpos($plugin, "register_activation_hook(__FILE__, 'nmkr_connect_activate')") !== false, 'activation hook remains bound through the canonical bootstrap __FILE__');
check(strpos($plugin, "register_deactivation_hook(__FILE__, 'nmkr_connect_deactivate')") !== false, 'deactivation hook remains bound through the canonical bootstrap __FILE__');
check(strpos($plugin, "register_uninstall_hook(__FILE__, 'nmkr_connect_uninstall')") !== false, 'uninstall hook remains bound through the canonical bootstrap __FILE__');
check(strpos($core, "'/rocsi-connector-for-nmkr.php'") !== false && strpos($core, "'/nmkr-connect.php'") === false && strpos($core, "'/connector-for-nmkr.php'") === false, 'settings fallback points only to the canonical main plugin file');
check(strpos($core, "plugin_action_links_' . plugin_basename(NMKR_CONNECT_PLUGIN_FILE)") !== false, 'settings action link remains bound through the canonical plugin basename');
$integrity = file_get_contents($root . '/scripts/nmkr-ajax-runtime-integrity.sh');
$preflight = file_get_contents($root . '/scripts/nmkr-real-sync-preflight.sh');
$builder = file_get_contents($root . '/scripts/nmkr-build-package.sh');
check(strpos($builder, 'zip_path="$out/$slug-$version.zip"') !== false, 'package filename derives from the canonical plugin version');
check(strpos($builder, '[[ "$version" =~ ^[0-9]+\\.[0-9]+\\.[0-9]+$ ]]') !== false, 'package builder validates a numeric three-component plugin version');
check(strpos($builder, '[[ "$stable_tag" == "$version" ]]') !== false, 'package builder requires the stable tag to match the plugin version');
$gitignore = file_get_contents($root . '/.gitignore');
check(stripos($integrity, 'freemius') === false && strpos($integrity, 'composer.lock') !== false, 'runtime integrity is lock-aware and has no Freemius dependency contract');
check(strpos($preflight, 'nmkr-ajax-runtime-integrity.sh') !== false, 'real-sync preflight delegates current deployment runtime integrity to the lock-aware helper');
check(strpos($preflight, 'rocsi-connector-for-nmkr/rocsi-connector-for-nmkr.php') !== false, 'real-sync preflight defaults to the canonical installed plugin basename');
check(strpos(file_get_contents($root . '/.env.tests.example'), 'NMKR_PLUGIN_SLUG=rocsi-connector-for-nmkr/rocsi-connector-for-nmkr.php') !== false, 'public test environment example defaults to the canonical installed plugin basename');
check(strpos($builder, 'slug=${NMKR_PACKAGE_DIR:-rocsi-connector-for-nmkr}') !== false && strpos($builder, 'Package directory must be rocsi-connector-for-nmkr.') !== false, 'package builder is locked to the candidate WordPress.org directory slug');
check(strpos($builder, "main_file='rocsi-connector-for-nmkr.php'") !== false && strpos($builder, 'test ! -e nmkr-connect.php') !== false && strpos($builder, 'test ! -e connector-for-nmkr.php') !== false && strpos($builder, 'test ! -e "$verify/$slug/nmkr-connect.php"') !== false && strpos($builder, 'test ! -e "$verify/$slug/connector-for-nmkr.php"') !== false, 'package builder requires the canonical main plugin file and rejects obsolete or superseded filenames');
check(strpos($builder, 'sha256sum "$(basename "$zip_path")"') !== false, 'package checksum sidecar records the ZIP basename rather than an absolute build path');
check(strpos($builder, 'out=$(cd "$out" && pwd -P)') !== false, 'package builder resolves relative output directories before entering the temporary tree');
check(preg_match('#^/dist/$#m', $gitignore) === 1, 'default package output directory is ignored so successful builds preserve a clean source tree');
check(preg_match('/^\*\.zip\.sha256$/m', $gitignore) === 1, 'package checksum sidecars are ignored for custom in-tree output directories');
