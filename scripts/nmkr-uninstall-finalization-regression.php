<?php
// Synthetic regression of the real uninstall callback and production metrics receipt helpers.
// No WordPress bootstrap, external connection, private credentials or filesystem mutation.
define('ARRAY_A', 'ARRAY_A');
define('NMKR_CONNECT_SCHEMA_VERSION_OPTION', 'nmkr_schema_version');
define('NMKR_CONNECT_SCHEMA_UPGRADE_LOCK_OPTION', 'nmkr_schema_upgrade_lock');

function assert_nmkr($ok, $label) {
    if (!$ok) { fwrite(STDERR, "FAIL: $label\n"); exit(1); }
    echo "PASS: $label\n";
}
function extract_function($path, $name) {
    $source = file_get_contents($path);
    $start = strpos($source, 'function ' . $name . '(');
    if ($start === false) { throw new RuntimeException("Missing source function: $name"); }
    $tokens = token_get_all('<?php ' . substr($source, $start));
    $depth = 0;
    $opened = false;
    $function = '';
    foreach ($tokens as $token) {
        $part = is_array($token) ? $token[1] : $token;
        if ($part === '<?php ') { continue; }
        $function .= $part;
        if ($part === '{' || (is_array($token) && in_array($token[0], array(T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES), true))) { $depth++; $opened = true; }
        elseif ($part === '}') { $depth--; if ($opened && $depth === 0) { break; } }
    }
    // T_CURLY_OPEN accounts for interpolated SQL table names.
    // Function extraction is checked against its next registration / declaration below.
    return $function;
}
class NMKR_Uninstall_WPDB {
    public $prefix = 'custom_';
    public $options = 'custom_options';
    public $tables = array();
    public $metrics = array();
    public $next_metric_id = 1;
    public $query_log = array();
    function esc_like($s) { return addcslashes($s, '_%\\'); }
    function prepare($sql, ...$params) { return array($sql, $params); }
    function get_col($prepared) {
        list($sql, $params) = $prepared;
        if (strpos($sql, 'information_schema.TABLES') !== false) { return array('InnoDB', 'InnoDB'); }
        $prefix = str_replace('\\', '', substr($params[0], 0, -1));
        $cursor = $params[1];
        $keys = array_filter(array_keys($GLOBALS['nmkr_test_options']), function($key) use ($prefix, $cursor) {
            return strpos($key, $prefix) === 0 && strcmp($key, $cursor) > 0;
        });
        sort($keys, SORT_STRING);
        return array_slice($keys, 0, 1000);
    }
    function get_var($prepared) {
        list($sql, $params) = $prepared;
        $key = $params[0];
        if (!array_key_exists($key, $GLOBALS['nmkr_test_options'])) { return null; }
        if (strpos($sql, 'option_id') !== false) { return 1; }
        return $GLOBALS['nmkr_test_options'][$key];
    }
    function get_row($prepared, $format = null) {
        $id = $prepared[1][0];
        return $this->metrics[$id] ?? null;
    }
    function query($sql) {
        $this->query_log[] = $sql;
        if (strpos($sql, 'DROP TABLE IF EXISTS ') === 0) {
            unset($this->tables[substr($sql, strlen('DROP TABLE IF EXISTS '))]);
        }
        return 1;
    }
    function insert($table, $values, $formats) {
        $key = $values['option_name'];
        if (array_key_exists($key, $GLOBALS['nmkr_test_options'])) { return false; }
        $GLOBALS['nmkr_test_options'][$key] = $values['option_value'];
        return 1;
    }
}
$GLOBALS['wpdb'] = new NMKR_Uninstall_WPDB();
$GLOBALS['nmkr_test_options'] = array();
$GLOBALS['nmkr_test_site_options'] = array();
$GLOBALS['nmkr_test_transients'] = array();
$GLOBALS['nmkr_test_hooks'] = array();
function get_option($k, $default = false) { return $GLOBALS['nmkr_test_options'][$k] ?? $default; }
function delete_option($k) { unset($GLOBALS['nmkr_test_options'][$k]); return true; }
function delete_site_option($k) { unset($GLOBALS['nmkr_test_site_options'][$k]); return true; }
function delete_transient($k) { unset($GLOBALS['nmkr_test_transients'][$k]); return true; }
function delete_site_transient($k) { return true; }
function wp_clear_scheduled_hook($k) { unset($GLOBALS['nmkr_test_hooks'][$k]); return 1; }
function nmkr_roles_uninstall_caps() { $GLOBALS['nmkr_roles_uninstalled'] = true; }
function maybe_serialize($value) { return serialize($value); }
function maybe_unserialize($value) { return unserialize($value); }
function nmkr_is_valid_sync_end_time($value) { return is_string($value) && $value !== ''; }
function nmkr_log_data_sync() { }
function wp_cache_delete() { }
function nmkr_save_sync_metrics($metrics, $return_id = false) {
    $db = $GLOBALS['wpdb'];
    $id = $db->next_metric_id++;
    $db->metrics[$id] = array('id' => $id, 'last_sync_time' => $metrics['last_sync_time']);
    return $id;
}
$root = dirname(__DIR__);
$plugin = file_get_contents($root . '/rocsi-connector-for-nmkr.php');
$begin = strpos($plugin, 'function nmkr_connect_uninstall() {');
$end = strpos($plugin, "register_uninstall_hook(__FILE__, 'nmkr_connect_uninstall');", $begin);
assert_nmkr($begin !== false && $end !== false, 'genuine registered uninstall callback located');
eval(substr($plugin, $begin, $end - $begin));
$progress = $root . '/includes/synchronization/nmkr-sync-progress-tracking.php';
// Extract actual helpers without loading the entire synchronization runtime.
foreach (array('nmkr_get_sync_metrics_receipt', 'nmkr_persist_sync_metrics_once',
               'nmkr_sync_metrics_receipt_record_exists') as $name) {
    $code = extract_function($progress, $name);
    eval($code);
}
$db = $GLOBALS['wpdb'];
foreach (array('nmkr_projects', 'nmkr_tokens', 'nmkr_token_details', 'nmkr_sync_stats',
               'nmkr_sync_metrics', 'nmkr_analytics') as $table) { $db->tables[$db->prefix . $table] = true; }
$old_receipt = array('sync_stats_id' => 1, 'metrics_id' => 19, 'end_time' => '2026-01-01 00:00:00');
$GLOBALS['nmkr_test_options'] = array(
    'nmkr_connect_options' => array('analytics_remove_on_uninstall' => 0),
    'nmkr_api_key' => 'synthetic-secret',
    'nmkr_sync_finalizing_1' => serialize($old_receipt),
    'nmkr_sync_finalizing_0' => serialize($old_receipt),
    'nmkr_sync_finalizing_123' => serialize($old_receipt),
    'nmkr_sync_finalizing_1_suffix' => 'keep',
    'nmkr_sync_finalizing_abc' => 'keep',
    'nmkr_sync_finalizing_' => 'keep',
    'other_nmkr_sync_finalizing_2' => 'keep',
    'other_plugin_option' => 'keep',
    'nmkr_ai_abc' => 'preserve',
);
for ($i = 2000; $i < 3105; $i++) {
    $GLOBALS['nmkr_test_options']['nmkr_sync_finalizing_' . $i] = serialize($old_receipt);
}
$GLOBALS['nmkr_test_site_options']['nmkr_api_key'] = 'synthetic-secret';
$GLOBALS['nmkr_test_hooks']['nmkr_analytics_purge_daily'] = true;
assert_nmkr(nmkr_sync_metrics_receipt_record_exists(1), 'stale receipt exists before uninstall');
assert_nmkr(nmkr_get_sync_metrics_receipt(1) === false, 'old receipt references missing metrics');
nmkr_connect_uninstall();
foreach (array_keys($GLOBALS['nmkr_test_options']) as $key) {
    assert_nmkr(!preg_match('/^nmkr_sync_finalizing_[0-9]+$/D', $key), 'no numeric receipt remains: ' . $key);
}
assert_nmkr(!nmkr_sync_metrics_receipt_record_exists(1), 'genuine uninstall removes colliding receipt');
foreach (array('nmkr_sync_finalizing_1_suffix', 'nmkr_sync_finalizing_abc',
               'nmkr_sync_finalizing_', 'other_nmkr_sync_finalizing_2', 'other_plugin_option',
               'nmkr_ai_abc') as $key) {
    assert_nmkr(isset($GLOBALS['nmkr_test_options'][$key]), 'nonmatching or retained option survives: ' . $key);
}
assert_nmkr(!isset($GLOBALS['nmkr_test_options']['nmkr_api_key']) &&
            !isset($GLOBALS['nmkr_test_site_options']['nmkr_api_key']), 'API key removed from site options');
assert_nmkr(!isset($GLOBALS['nmkr_test_hooks']['nmkr_analytics_purge_daily']), 'analytics purge hook cleared');
assert_nmkr(!isset($db->tables['custom_nmkr_sync_stats']) &&
            !isset($db->tables['custom_nmkr_sync_metrics']) &&
            isset($db->tables['custom_nmkr_analytics']), 'plugin tables removed with analytics retention preserved');
assert_nmkr(!empty($GLOBALS['nmkr_roles_uninstalled']), 'plugin roles uninstall preserved');
// Reinstall reuses synchronization history ID 1 with no stale option.
$new_receipt = nmkr_persist_sync_metrics_once(1, array('total_tokens' => 100), '2026-11-07 08:00:00');
assert_nmkr(is_array($new_receipt) && $new_receipt['sync_stats_id'] === 1 &&
            $new_receipt['metrics_id'] === 1, 'reused history ID persists a fresh metrics receipt');
assert_nmkr(nmkr_sync_metrics_receipt_record_exists(1) &&
            nmkr_get_sync_metrics_receipt(1) === $new_receipt, 'valid receipt remains available for terminal finalization');
$second = nmkr_persist_sync_metrics_once(1, array('total_tokens' => 100), '2026-11-07 08:00:00');
assert_nmkr($second === $new_receipt && count($db->metrics) === 1, 'normal retry remains exactly-once');
assert_nmkr(!in_array('DROP TABLE IF EXISTS custom_nmkr_analytics', $db->query_log, true), 'retained analytics table never dropped');
// Opted-in analytics removal still clears the existing analytics state policy.
$GLOBALS['nmkr_test_options']['nmkr_connect_options'] = array('analytics_remove_on_uninstall' => 1);
$db->tables['custom_nmkr_analytics'] = true;
nmkr_connect_uninstall();
assert_nmkr(!isset($db->tables['custom_nmkr_analytics']) &&
            !isset($GLOBALS['nmkr_test_options']['nmkr_ai_abc']), 'analytics removal policy remains effective');
assert_nmkr(!nmkr_sync_metrics_receipt_record_exists(1), 'second genuine uninstall cleans new finalization receipt');
echo "All uninstall finalization-receipt regressions passed.\n";
