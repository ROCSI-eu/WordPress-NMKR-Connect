<?php
/** Public-safe deterministic regression for run-scoped API throttle fencing. */

class WP_Error {
    private $code;
    private $message;
    public function __construct($code, $message) { $this->code = $code; $this->message = $message; }
    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->message; }
}

function is_wp_error($value) { return $value instanceof WP_Error; }
function plugin_dir_path($file) { return rtrim(dirname($file), '/\\') . DIRECTORY_SEPARATOR; }
function get_option($name) { return $name === 'nmkr_connect_options' ? array('api_key' => 'synthetic-test-key') : false; }
function nmkr_log_api_status() {}
function nmkr_log_data_sync() {}
function wp_remote_retrieve_body($response) { return isset($response['body']) ? $response['body'] : ''; }
function wp_remote_retrieve_response_code($response) { return isset($response['code']) ? $response['code'] : 0; }

$GLOBALS['nmkr_test_http_calls'] = 0;
$GLOBALS['nmkr_test_tracked_calls'] = 0;
function wp_remote_get($url, $args = array()) {
    $GLOBALS['nmkr_test_http_calls']++;
    return array('code' => 200, 'body' => '[]');
}
function nmkr_tracked_api_call_v2($label, $callback) {
    $GLOBALS['nmkr_test_tracked_calls']++;
    return call_user_func($callback);
}

if (!defined('NMKR_API_URL')) define('NMKR_API_URL', 'https://example.invalid');
require dirname(__DIR__) . '/includes/api/nmkr-api-functions.php';

function check($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    echo "PASS: {$message}\n";
}

function reset_throttle_state() {
    global $nmkr_api_call_times, $nmkr_api_rate_limited, $nmkr_api_cooldown_until;
    $nmkr_api_call_times = array();
    $nmkr_api_rate_limited = false;
    $nmkr_api_cooldown_until = 0;
    $GLOBALS['nmkr_test_http_calls'] = 0;
    $GLOBALS['nmkr_test_tracked_calls'] = 0;
}

reset_throttle_state();
$now = 0.0;
$phases = array();
$nmkr_api_rate_limited = true;
$nmkr_api_cooldown_until = 3.0;
$context = array(
    'clock' => function () use (&$now) { return $now; },
    'sleep' => function ($seconds) use (&$now) { $now += $seconds; },
    'checkpoint' => function ($phase) use (&$phases) {
        $phases[] = $phase;
        if ($phase === 'after_api_cooldown_wait') {
            return new WP_Error('sync_stop_requested', 'Synthetic stop.');
        }
        return true;
    },
);
$result = nmkr_connect_fetch_projects($context);
check(is_wp_error($result) && $result->get_error_code() === 'sync_stop_requested', 'Stop during cooldown returns the exact halt error');
check($GLOBALS['nmkr_test_http_calls'] === 0, 'Stop during cooldown prevents the next HTTP dispatch');
check($GLOBALS['nmkr_test_tracked_calls'] === 0, 'Prevented dispatch is not counted as a tracked API request');
check(in_array('before_api_cooldown_wait', $phases, true) && in_array('after_api_cooldown_wait', $phases, true), 'Cooldown wait is checkpointed around each sleep chunk');

foreach (array('sync_owner_mismatch', 'sync_checkpoint_lock_failed', 'sync_checkpoint_persistence_failed') as $code) {
    reset_throttle_state();
    $halt_context = array('checkpoint' => function () use ($code) { return new WP_Error($code, 'Synthetic halt.'); });
    $token_result = nmkr_connect_fetch_nfts_by_project('synthetic-project', $halt_context);
    check(is_wp_error($token_result) && $token_result->get_error_code() === $code, $code . ' propagates unchanged from token-list throttling');
    check($GLOBALS['nmkr_test_http_calls'] === 0, $code . ' prevents token-list HTTP dispatch');

    reset_throttle_state();
    $detail_result = nmkr_connect_fetch_nft_details('synthetic-token', $halt_context);
    check(is_wp_error($detail_result) && $detail_result->get_error_code() === $code, $code . ' propagates unchanged from token-detail throttling');
    check($GLOBALS['nmkr_test_http_calls'] === 0, $code . ' prevents token-detail HTTP dispatch');
}

reset_throttle_state();
$legacy = nmkr_connect_fetch_projects();
check(is_array($legacy), 'Legacy context-free project fetch remains supported');
check($GLOBALS['nmkr_test_http_calls'] === 1 && $GLOBALS['nmkr_test_tracked_calls'] === 1, 'Legacy context-free fetch dispatches and tracks one synthetic request');

$core = file_get_contents(dirname(__DIR__) . '/includes/synchronization/nmkr-sync-core.php');
$required_fragments = array(
    'function nmkr_is_sync_worker_halt_error($value)',
    'function nmkr_build_sync_api_execution_context($run_id, $sync_stats_id, $request_phase)',
    "nmkr_connect_fetch_projects(nmkr_build_sync_api_execution_context(\$run_id, \$sync_stats_id, 'initial_projects'))",
    "nmkr_connect_fetch_projects(nmkr_build_sync_api_execution_context(\$run_id, \$sync_stats_id, 'projects'))",
    "nmkr_connect_fetch_nfts_by_project(\$project_uid, nmkr_build_sync_api_execution_context(\$run_id, \$sync_stats_id, 'step_count_token_list'))",
    "nmkr_connect_fetch_nfts_by_project(\$project_uid, nmkr_build_sync_api_execution_context(\$run_id, \$sync_stats_id, 'token_list'))",
    "nmkr_connect_fetch_nft_details(\$token_uid, nmkr_build_sync_api_execution_context(\$run_id, \$sync_stats_id, 'token_detail'))",
);
foreach ($required_fragments as $fragment) {
    check(strpos($core, $fragment) !== false, 'Core wires run-scoped context: ' . $fragment);
}
check(substr_count($core, 'if (nmkr_is_sync_worker_halt_error(') >= 6, 'Exact worker halt errors are fenced before intermediate error handling');

echo "All run-scoped API throttle regression checks passed.\n";
