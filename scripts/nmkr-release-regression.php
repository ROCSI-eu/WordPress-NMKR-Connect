<?php
$root = dirname(__DIR__);
function check($condition, $message) { if (!$condition) { fwrite(STDERR, "FAIL: $message\n"); exit(1); } echo "PASS: $message\n"; }
$plugin = file_get_contents($root . '/nmkr-connect.php');
$composer = json_decode(file_get_contents($root . '/composer.json'), true);
$readme = file_get_contents($root . '/readme.txt');
$runtime = $plugin;
foreach (glob($root . '/includes/{shortcodes,pages/shortcodes}/*.php', GLOB_BRACE) as $file) { $runtime .= file_get_contents($file); }
check(!preg_match('/freemius|wnc_fs|can_use_premium_code|is_plan\s*\(/i', $runtime), 'runtime and shortcode paths contain no entitlement SDK references');
foreach (['nmkr-token','nmkr-token-list','nmkr-project','nmkr-carousel','nmkr-grid'] as $code) check(strpos($plugin, "add_shortcode('$code'") !== false, "$code is registered");
check($composer['license'] === 'MIT' && count($composer['require']) === 1, 'Composer declares MIT and PHP as the only runtime requirement');
check(preg_match('/Version:\s*1\.0\.0/', $plugin) && preg_match('/Stable tag:\s*1\.0\.0/i', $readme), 'plugin version and stable tag agree');
check(preg_match('/Requires at least:\s*5\.8/i', $plugin) && preg_match('/Requires at least:\s*5\.8/i', $readme), 'plugin and directory metadata agree on WordPress 5.8 minimum');
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
$integrity = file_get_contents($root . '/scripts/nmkr-ajax-runtime-integrity.sh');
$preflight = file_get_contents($root . '/scripts/nmkr-real-sync-preflight.sh');
$builder = file_get_contents($root . '/scripts/nmkr-build-package.sh');
$gitignore = file_get_contents($root . '/.gitignore');
check(stripos($integrity, 'freemius') === false && strpos($integrity, 'composer.lock') !== false, 'runtime integrity is lock-aware and has no Freemius dependency contract');
check(strpos($preflight, 'nmkr-ajax-runtime-integrity.sh') !== false, 'real-sync preflight delegates current deployment runtime integrity to the lock-aware helper');
check(strpos($builder, 'sha256sum "$(basename "$zip_path")"') !== false, 'package checksum sidecar records the ZIP basename rather than an absolute build path');
check(strpos($builder, 'out=$(cd "$out" && pwd -P)') !== false, 'package builder resolves relative output directories before entering the temporary tree');
check(preg_match('#^/dist/$#m', $gitignore) === 1, 'default package output directory is ignored so successful builds preserve a clean source tree');
check(preg_match('/^\*\.zip\.sha256$/m', $gitignore) === 1, 'package checksum sidecars are ignored for custom in-tree output directories');
