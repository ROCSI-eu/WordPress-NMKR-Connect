<?php
// Public-safe deterministic regression for ownerless force cleanup cron boundaries.
// No WordPress bootstrap, database, filesystem mutation, or network access is used.

define('ABSPATH', __DIR__);
define('ARRAY_A', 'ARRAY_A');

$GLOBALS['options'] = array();
$GLOBALS['transients'] = array();
$GLOBALS['scheduled_events'] = array();
$GLOBALS['cleared_hook_calls'] = array();

function __($value) { return $value; }
function is_wp_error($value) { return $value instanceof WP_Error; }

class WP_Error {
    private $code;
    private $message;

    public function __construct($code, $message) {
        $this->code = $code;
        $this->message = $message;
    }

    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->message; }
}

function get_option($key, $default = false) {
    return array_key_exists($key, $GLOBALS['options']) ? $GLOBALS['options'][$key] : $default;
}

function update_option($key, $value, $autoload = null) {
    $GLOBALS['options'][$key] = $value;
    return true;
}

function delete_option($key) {
    unset($GLOBALS['options'][$key]);
    return true;
}

function get_transient($key) {
    return array_key_exists($key, $GLOBALS['transients']) ? $GLOBALS['transients'][$key] : false;
}

function set_transient($key, $value, $ttl = 0) {
    $GLOBALS['transients'][$key] = $value;
    return true;
}

function delete_transient($key) {
    unset($GLOBALS['transients'][$key]);
    return true;
}

function wp_clear_scheduled_hook($hook, $args = array()) {
    $GLOBALS['cleared_hook_calls'][] = $hook;
    if (array_key_exists($hook, $GLOBALS['scheduled_events'])) {
        unset($GLOBALS['scheduled_events'][$hook]);
        return 1;
    }
    return 0;
}

function nmkr_get_sync_data() { return false; }
function nmkr_update_sync_progress($current, $total, $message = '', $force = false) {
    $GLOBALS['options']['nmkr_sync_progress'] = $current;
}
function nmkr_cleanup_sync_heartbeat() {
    unset($GLOBALS['options']['nmkr_sync_heartbeat']);
}
function nmkr_clear_sync_data() {
    unset($GLOBALS['options']['nmkr_sync_data']);
}
function nmkr_log_data_sync() {}
function nmkr_log_ui_status() {}
function nmkr_get_timestamp() { return '2026-09-20 00:00:00'; }
function nmkr_update_sync_stats($id, $data) { return true; }
function nmkr_is_sync_terminal_status($status) {
    return in_array($status, array('completed', 'success', 'failed', 'error', 'stopped', 'cancelled', 'aborted'), true);
}
function nmkr_coordinate_sync_cleanup($mode, $callback) { return $callback(); }

class NMKR_Force_Cleanup_Fake_WPDB {
    public $prefix = 'wp_';

    public function prepare($query) {
        return $query;
    }

    public function get_row($query, $format = null) {
        return null;
    }

    public function get_results($query, $format = null) {
        return array();
    }
}

$wpdb = new NMKR_Force_Cleanup_Fake_WPDB();

require dirname(__DIR__) . '/includes/synchronization/nmkr-sync-error-handling.php';

function check($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    echo "PASS: {$message}\n";
}

$GLOBALS['transients'] = array(
    'doing_cron' => 'wordpress-global-lock-sentinel',
    'unrelated_plugin_lock' => 'unrelated-sentinel',
    'nmkr_sync_in_progress' => true,
    'nmkr_sync_batch_state' => array('legacy' => true),
);

$GLOBALS['scheduled_events'] = array(
    'nmkr_process_batch_hook' => array('legacy-batch'),
    'nmkr_sync_cron_hook' => array('legacy-sync'),
    'nmkr_install_sync_cron_hook' => array('legacy-install'),
    'nmkr_execute_sync_background' => array('run-id-sentinel'),
    'unrelated_plugin_cron_hook' => array('unrelated'),
);

$GLOBALS['options'] = array(
    'nmkr_sync_in_progress' => true,
    'nmkr_sync_status' => 'running',
    'nmkr_sync_data' => array('sentinel' => 'legacy'),
    'nmkr_sync_heartbeat' => 123,
);

$result = nmkr_force_stop_sync_ownerless('synthetic_regression');

check(is_array($result) && !empty($result['success']), 'ownerless force cleanup completes');

check(
    get_transient('doing_cron') === 'wordpress-global-lock-sentinel',
    'WordPress global doing_cron transient survives NMKR force cleanup unchanged'
);

check(
    get_transient('unrelated_plugin_lock') === 'unrelated-sentinel',
    'unrelated transient survives NMKR force cleanup unchanged'
);

foreach (array('nmkr_process_batch_hook', 'nmkr_sync_cron_hook', 'nmkr_install_sync_cron_hook') as $hook) {
    check(
        !array_key_exists($hook, $GLOBALS['scheduled_events']),
        $hook . ' is cleared as NMKR-owned legacy cron state'
    );
}

check(
    array_key_exists('nmkr_execute_sync_background', $GLOBALS['scheduled_events']),
    'run-scoped direct worker event is not globally cleared by ownerless cleanup'
);

check(
    array_key_exists('unrelated_plugin_cron_hook', $GLOBALS['scheduled_events']),
    'unrelated cron hook survives NMKR force cleanup unchanged'
);

$allowed_hooks = array('nmkr_process_batch_hook', 'nmkr_sync_cron_hook', 'nmkr_install_sync_cron_hook');
$unexpected_hooks = array_values(array_diff(array_unique($GLOBALS['cleared_hook_calls']), $allowed_hooks));
check(
    $unexpected_hooks === array(),
    'force cleanup clears only the expected NMKR-owned legacy cron hooks'
);

$source = file_get_contents(dirname(__DIR__) . '/includes/synchronization/nmkr-sync-error-handling.php');
check(
    strpos($source, "delete_transient('doing_cron')") === false &&
    strpos($source, 'delete_transient("doing_cron")') === false,
    'runtime source contains no doing_cron deletion'
);

echo "All force-cleanup cron-boundary regression checks passed.\n";
