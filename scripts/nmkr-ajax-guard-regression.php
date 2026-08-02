<?php
/** Public-safe executable regression for privileged AJAX guard ordering. */
define('ABSPATH', __DIR__ . '/synthetic/');
define('DAY_IN_SECONDS', 86400);
$GLOBALS['nmkr_calls'] = array();
$GLOBALS['nmkr_nonce_ok'] = false;
$GLOBALS['nmkr_caps'] = array();
$GLOBALS['nmkr_hooks'] = array();

class NmkrAjaxTermination extends Exception { public $kind; public $status; public function __construct($kind, $status = 0) { parent::__construct($kind); $this->kind=$kind; $this->status=$status; } }
function add_action($hook, $callback) { $GLOBALS['nmkr_hooks'][$hook] = $callback; }
function check_ajax_referer($action, $field) { $GLOBALS['nmkr_calls'][]='nonce:'.$action.':'.$field; if (!$GLOBALS['nmkr_nonce_ok']) throw new NmkrAjaxTermination('nonce'); }
function wp_verify_nonce($nonce, $action) { $GLOBALS['nmkr_calls'][]='nonce:'.$action.':nonce'; return $GLOBALS['nmkr_nonce_ok']; }
function current_user_can($cap) { $GLOBALS['nmkr_calls'][]='cap:'.$cap; return !empty($GLOBALS['nmkr_caps'][$cap]); }
function wp_send_json_error($data=null, $status=0) { throw new NmkrAjaxTermination('error', (int)$status); }
function wp_send_json_success($data=null, $status=0) { throw new NmkrAjaxTermination('success', (int)$status); }
function wp_die() { throw new NmkrAjaxTermination('die'); }
function __($text, $domain=null) { return $text; }
function nocache_headers() { $GLOBALS['nmkr_calls'][]='headers'; }
function status_header($status) { $GLOBALS['nmkr_calls'][]='status'; }
function sanitize_text_field($v) { return (string)$v; }
function wp_unslash($v) { return $v; }
function get_option($k, $d=false) { $GLOBALS['nmkr_calls'][]='option'; return $d; }
function update_option($k,$v,$a=null) { $GLOBALS['nmkr_calls'][]='option-write'; return true; }
function delete_option($k) { $GLOBALS['nmkr_calls'][]='option-write'; return true; }
function get_transient($k) { $GLOBALS['nmkr_calls'][]='transient'; return false; }
function set_transient($k,$v,$t=0) { $GLOBALS['nmkr_calls'][]='transient-write'; return true; }
function delete_transient($k) { $GLOBALS['nmkr_calls'][]='transient-write'; return true; }
function wp_clear_scheduled_hook($h,$a=array()) { $GLOBALS['nmkr_calls'][]='cron'; }
function wp_schedule_single_event($t,$h,$a=array()) { $GLOBALS['nmkr_calls'][]='cron'; return true; }
function nmkr_admit_sync_owner($id) { $GLOBALS['nmkr_calls'][]='admission'; return array(); }
function nmkr_get_sync_owner() { $GLOBALS['nmkr_calls'][]='owner'; return false; }
function nmkr_is_api_connected() { $GLOBALS['nmkr_calls'][]='api'; return false; }
function wp_json_encode($v) { return json_encode($v); }
function get_current_blog_id() { return 1; }
function wp_generate_uuid4() { return '00000000-0000-4000-8000-000000000001'; }

require __DIR__.'/../includes/synchronization/nmkr-sync-ajax-handlers.php';
require __DIR__.'/../includes/pages/dashboard/nmkr-dashboard-ajax.php';
require __DIR__.'/../includes/pages/analytics/nmkr-analytics-ajax.php';

function nmkr_test($name, $handler, $nonce, $caps, $expected, $forbidden) {
    $GLOBALS['nmkr_calls']=array(); $GLOBALS['nmkr_nonce_ok']=$nonce; $GLOBALS['nmkr_caps']=$caps;
    $_POST=array('nonce'=>'synthetic');
    if ($name === 'stop_cap') $_POST['run_id']='restricted-malformed-run-id';
    $_REQUEST=$_POST; $_SERVER['REQUEST_METHOD']='POST';
    try { call_user_func($handler); throw new Exception('no_termination'); } catch (NmkrAjaxTermination $e) { $kind=$e->kind; $status=$e->status; }
    foreach ($expected as $call) if (!in_array($call,$GLOBALS['nmkr_calls'],true)) throw new Exception($name.'_expected_guard_missing');
    foreach ($forbidden as $call) if (in_array($call,$GLOBALS['nmkr_calls'],true)) throw new Exception($name.'_downstream_reached');
    if ($nonce && empty($caps) && ($kind!=='error' || $status!==403)) throw new Exception($name.'_forbidden_shape');
}

try {
    nmkr_test('start_nonce','nmkr_start_sync_handler',false,array(),array('nonce:nmkr_sync_nonce:nonce'),array('cap:nmkr_manage_sync','admission','option','transient','cron'));
    nmkr_test('start_cap','nmkr_start_sync_handler',true,array(),array('cap:nmkr_manage_sync'),array('admission','option','option-write','transient','transient-write','cron'));
    nmkr_test('progress_cap','nmkr_sync_progress_handler',true,array(),array('cap:nmkr_view_dashboard'),array('owner','option','transient'));
    nmkr_test('stop_cap','nmkr_stop_sync_handler',true,array(),array('cap:nmkr_manage_sync'),array('owner','option','option-write','transient','transient-write','cron'));
    nmkr_test('api_nonce','nmkr_check_api_status',false,array(),array('nonce:nmkr_dashboard_nonce:nonce'),array('cap:nmkr_view_dashboard','option','api'));
    nmkr_test('api_cap','nmkr_check_api_status',true,array(),array('cap:nmkr_view_dashboard'),array('option','api'));
    nmkr_test('logs_nonce','nmkr_clear_all_logs_ajax',false,array(),array('nonce:nmkr_clear_logs_nonce:nonce'),array('cap:nmkr_manage_sync','option-write'));
    nmkr_test('logs_cap','nmkr_clear_all_logs_ajax',true,array(),array('nonce:nmkr_clear_logs_nonce:nonce','cap:nmkr_manage_sync'),array('option','option-write','transient','transient-write','cron'));
    nmkr_test('analytics_nonce','nmkr_analytics_kpis_ajax',false,array(),array('nonce:nmkr_dashboard_nonce:nonce'),array('cap:nmkr_view_analytics','transient','option'));
    nmkr_test('analytics_cap','nmkr_analytics_kpis_ajax',true,array(),array('cap:nmkr_view_analytics'),array('transient','option'));
    $protected=array('nmkr_start_sync','nmkr_sync_progress','nmkr_stop_sync','nmkr_check_sync_health','nmkr_check_api_status','nmkr_clear_all_logs','nmkr_analytics_kpis');
    foreach ($protected as $action) { if (!isset($GLOBALS['nmkr_hooks']['wp_ajax_'.$action]) || isset($GLOBALS['nmkr_hooks']['wp_ajax_nopriv_'.$action])) throw new Exception('registration_boundary_failed'); }
    // nmkr_analytics_event is deliberately public and registered by a different,
    // unloaded endpoint module.  Its absence here is not registration evidence.
    echo "Privileged AJAX guard regression: PASS\n";
} catch (Throwable $e) { fwrite(STDERR, "Privileged AJAX guard regression: FAIL ".$e->getMessage()."\n"); exit(1); }
