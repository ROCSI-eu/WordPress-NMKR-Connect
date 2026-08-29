<?php
/** Public-safe executable regression for privileged AJAX guard ordering. */
define('ABSPATH', __DIR__ . '/synthetic/');
define('DAY_IN_SECONDS', 86400);
define('NMKR_SYNC_TRANSIENT_TTL', 3600);
$GLOBALS['nmkr_calls'] = array();
$GLOBALS['nmkr_nonce_ok'] = false;
$GLOBALS['nmkr_caps'] = array();
$GLOBALS['nmkr_hooks'] = array();

class NmkrAjaxTermination extends Exception { public $kind; public $status; public $data; public function __construct($kind, $status = 0, $data = null) { parent::__construct($kind); $this->kind=$kind; $this->status=$status; $this->data=$data; } }
function add_action($hook, $callback) { $GLOBALS['nmkr_hooks'][$hook] = $callback; }
function check_ajax_referer($action, $field) { $GLOBALS['nmkr_calls'][]='nonce:'.$action.':'.$field; if (!$GLOBALS['nmkr_nonce_ok']) throw new NmkrAjaxTermination('nonce'); }
function wp_verify_nonce($nonce, $action) { $GLOBALS['nmkr_calls'][]='nonce:'.$action.':nonce'; return $GLOBALS['nmkr_nonce_ok']; }
function current_user_can($cap) { $GLOBALS['nmkr_calls'][]='cap:'.$cap; return !empty($GLOBALS['nmkr_caps'][$cap]); }
function wp_send_json_error($data=null, $status=0) { throw new NmkrAjaxTermination('error', (int)$status, $data); }
function wp_send_json_success($data=null, $status=0) { throw new NmkrAjaxTermination('success', (int)$status, $data); }
function wp_die() { throw new NmkrAjaxTermination('die'); }
function __($text, $domain=null) { return $text; }
function nocache_headers() { $GLOBALS['nmkr_calls'][]='headers'; }
function status_header($status) { $GLOBALS['nmkr_calls'][]='status'; }
function sanitize_text_field($v) { return (string)$v; }
function wp_unslash($v) { return $v; }
function get_option($k, $d=false) { $GLOBALS['nmkr_calls'][]='option'; return array_key_exists($k, $GLOBALS['nmkr_options'] ?? array()) ? $GLOBALS['nmkr_options'][$k] : $d; }
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
function nmkr_log_ui_status($message, $type) { $GLOBALS['nmkr_calls'][]='ui-log'; }
function wp_json_encode($v) { return json_encode($v); }
function get_current_blog_id() { return 1; }
function wp_generate_uuid4() { return '00000000-0000-4000-8000-000000000001'; }
function wp_create_nonce($action) { return 'synthetic-nonce'; }
function esc_attr($value) { return (string)$value; }
function esc_html($value) { return (string)$value; }
function nmkr_get_log_retention_limit() { return 20; }

require __DIR__.'/../includes/synchronization/nmkr-sync-ajax-handlers.php';
require __DIR__.'/../includes/pages/dashboard/nmkr-dashboard-ajax.php';
require __DIR__.'/../includes/pages/dashboard/nmkr-dashboard-ui.php';
require __DIR__.'/../includes/pages/analytics/nmkr-analytics-ajax.php';

function nmkr_test($name, $handler, $nonce, $caps, $expected, $forbidden, $expected_response = null) {
    $GLOBALS['nmkr_calls']=array(); $GLOBALS['nmkr_nonce_ok']=$nonce; $GLOBALS['nmkr_caps']=$caps;
    $_POST=array('nonce'=>'synthetic');
    if ($name === 'stop_cap') $_POST['run_id']='restricted-malformed-run-id';
    $_REQUEST=$_POST; $_SERVER['REQUEST_METHOD']='POST';
    try { call_user_func($handler); throw new Exception('no_termination'); } catch (NmkrAjaxTermination $e) { $kind=$e->kind; $status=$e->status; }
    foreach ($expected as $call) if (!in_array($call,$GLOBALS['nmkr_calls'],true)) throw new Exception($name.'_expected_guard_missing');
    foreach ($forbidden as $call) if (in_array($call,$GLOBALS['nmkr_calls'],true)) throw new Exception($name.'_downstream_reached');
    if ($nonce && empty($caps) && ($kind!=='error' || $status!==403)) throw new Exception($name.'_forbidden_shape');
    if ($expected_response !== null && ($kind !== $expected_response['kind'] || $status !== $expected_response['status'] || $e->data !== $expected_response['data'])) throw new Exception($name.'_response_contract');
}

try {
    nmkr_test('start_nonce','nmkr_start_sync_handler',false,array(),array('nonce:nmkr_sync_nonce:nonce'),array('cap:nmkr_manage_sync','admission','option','transient','cron'));
    nmkr_test('start_cap','nmkr_start_sync_handler',true,array(),array('cap:nmkr_manage_sync'),array('admission','option','option-write','transient','transient-write','cron'));
    nmkr_test('progress_cap','nmkr_sync_progress_handler',true,array(),array('cap:nmkr_view_dashboard'),array('owner','option','transient'));
    nmkr_test('stop_cap','nmkr_stop_sync_handler',true,array(),array('cap:nmkr_manage_sync'),array('owner','option','option-write','transient','transient-write','cron'));
    nmkr_test('api_nonce','nmkr_check_api_status',false,array(),array('nonce:nmkr_dashboard_nonce:nonce'),array('cap:nmkr_view_dashboard','option','api'));
    nmkr_test('api_cap','nmkr_check_api_status',true,array(),array('cap:nmkr_view_dashboard'),array('option','api'));
    nmkr_test('metrics_nonce','nmkr_store_active_metrics_ajax',false,array(),array('nonce:nmkr_dashboard_nonce:nonce'),array('cap:nmkr_manage_sync','transient','transient-write','option','option-write','ui-log'));
    nmkr_test('metrics_view_only','nmkr_store_active_metrics_ajax',true,array('nmkr_view_dashboard'=>true),array('nonce:nmkr_dashboard_nonce:nonce','cap:nmkr_manage_sync'),array('transient','transient-write','option','option-write','ui-log'),array('kind'=>'error','status'=>403,'data'=>array('message'=>'Forbidden')));

    $GLOBALS['nmkr_calls']=array(); $GLOBALS['nmkr_nonce_ok']=true; $GLOBALS['nmkr_caps']=array('nmkr_manage_sync'=>true);
    $_POST=array('nonce'=>'synthetic','metrics'=>array('average_response_time'=>'1.25','api_requests'=>'2','memory_usage'=>'3.5'));
    try { nmkr_store_active_metrics_ajax(); throw new Exception('metrics_manager_no_termination'); }
    catch (NmkrAjaxTermination $e) {
        if ($e->kind !== 'success' || !is_array($e->data) || $e->data['average_response_time'] !== 1.25 || $e->data['api_requests'] !== 2 || $e->data['memory_usage'] !== 3.5) throw new Exception('metrics_manager_response_shape');
    }
    foreach (array('cap:nmkr_manage_sync','transient','transient-write','ui-log') as $call) if (!in_array($call,$GLOBALS['nmkr_calls'],true)) throw new Exception('metrics_manager_authorized_path');
    nmkr_test('logs_nonce','nmkr_clear_all_logs_ajax',false,array(),array('nonce:nmkr_clear_logs_nonce:nonce'),array('cap:nmkr_manage_sync','option-write'));
    nmkr_test('logs_cap','nmkr_clear_all_logs_ajax',true,array(),array('nonce:nmkr_clear_logs_nonce:nonce','cap:nmkr_manage_sync'),array('option','option-write','transient','transient-write','cron'));
    nmkr_test('analytics_nonce','nmkr_analytics_kpis_ajax',false,array(),array('nonce:nmkr_dashboard_nonce:nonce'),array('cap:nmkr_view_analytics','transient','option'));
    nmkr_test('analytics_cap','nmkr_analytics_kpis_ajax',true,array(),array('cap:nmkr_view_analytics'),array('transient','option'));
    $protected=array('nmkr_start_sync','nmkr_sync_progress','nmkr_stop_sync','nmkr_check_sync_health','nmkr_check_api_status','nmkr_store_active_metrics','nmkr_clear_all_logs','nmkr_analytics_kpis');
    foreach ($protected as $action) { if (!isset($GLOBALS['nmkr_hooks']['wp_ajax_'.$action]) || isset($GLOBALS['nmkr_hooks']['wp_ajax_nopriv_'.$action])) throw new Exception('registration_boundary_failed'); }
    $core_source=file_get_contents(__DIR__.'/../includes/pages/dashboard/nmkr-dashboard-core.php');
    $ui_source=file_get_contents(__DIR__.'/../includes/pages/dashboard/nmkr-dashboard-ui.php');
    if (substr_count($core_source, "\$can_manage_sync = current_user_can( 'nmkr_manage_sync' );") !== 1) throw new Exception('dashboard_authority_signal_missing');
    if (strpos($ui_source, '<?php if ( $can_manage_sync ) : ?>') === false || strpos($ui_source, "action === 'nmkr_store_active_metrics'") === false || strpos($ui_source, 'jqXHR.abort();') === false) throw new Exception('dashboard_view_only_gating_missing');

    ob_start(); nmkr_render_sync_data_panel('synthetic-dashboard-nonce', false); $view_sync=ob_get_clean();
    if (strpos($view_sync, 'class="panel sync-data"') === false || strpos($view_sync, 'id="nmkr-sync-progress-container"') === false || strpos($view_sync, 'id="active-sync-metrics"') === false) throw new Exception('dashboard_view_only_observation_missing');
    if (strpos($view_sync, 'id="nmkr-sync-button"') !== false || strpos($view_sync, 'id="nmkr-stop-sync-button"') !== false) throw new Exception('dashboard_view_only_sync_controls_present');
    ob_start(); nmkr_render_sync_data_panel('synthetic-dashboard-nonce', true); $manager_sync=ob_get_clean();
    if (strpos($manager_sync, 'id="nmkr-sync-button"') === false || strpos($manager_sync, 'id="nmkr-stop-sync-button"') === false) throw new Exception('dashboard_manager_sync_controls_missing');

    $GLOBALS['nmkr_calls']=array();
    ob_start(); nmkr_render_dashboard_scripts('synthetic-dashboard-nonce', false); $view_scripts=ob_get_clean();
    if (strpos($view_scripts, 'const canManageSync = false;') === false || strpos($view_scripts, "action === 'nmkr_store_active_metrics'") === false || strpos($view_scripts, 'jqXHR.abort();') === false) throw new Exception('dashboard_view_only_caller_guard_missing');
    ob_start(); nmkr_render_dashboard_scripts('synthetic-dashboard-nonce', true); $manager_scripts=ob_get_clean();
    if (strpos($manager_scripts, 'const canManageSync = true;') === false || strpos($manager_scripts, "action: 'nmkr_store_active_metrics'") === false) throw new Exception('dashboard_manager_caller_missing');

    $GLOBALS['nmkr_options']=array('nmkr_connect_options'=>array('log_to_dashboard'=>true));
    ob_start(); nmkr_render_debug_logs_panel(false); $view_logs=ob_get_clean();
    if (strpos($view_logs, 'class="panel debug-logs-panel"') === false || strpos($view_logs, 'clear-all-logs-btn') !== false || strpos($view_logs, 'clear-section-logs-btn') !== false) throw new Exception('dashboard_view_only_log_controls');
    ob_start(); nmkr_render_debug_logs_panel(true); $manager_logs=ob_get_clean();
    if (strpos($manager_logs, 'clear-all-logs-btn') === false || strpos($manager_logs, 'clear-section-logs-btn') === false) throw new Exception('dashboard_manager_log_controls_missing');
    // nmkr_analytics_event is deliberately public and registered by a different,
    // unloaded endpoint module.  Its absence here is not registration evidence.
    echo "Privileged AJAX guard regression: PASS\n";
} catch (Throwable $e) { fwrite(STDERR, "Privileged AJAX guard regression: FAIL ".$e->getMessage()."\n"); exit(1); }
