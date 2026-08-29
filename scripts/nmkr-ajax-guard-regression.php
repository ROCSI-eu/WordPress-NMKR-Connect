<?php
/** Public-safe executable regression for privileged AJAX guard ordering. */
define('ABSPATH', __DIR__ . '/synthetic/');
define('DAY_IN_SECONDS', 86400);
define('NMKR_SYNC_TRANSIENT_TTL', 3600);
define('ARRAY_A', 'ARRAY_A');
$GLOBALS['nmkr_calls'] = array();
$GLOBALS['nmkr_nonce_ok'] = false;
$GLOBALS['nmkr_caps'] = array();
$GLOBALS['nmkr_hooks'] = array();
$GLOBALS['nmkr_transients'] = array();
$GLOBALS['nmkr_owner'] = false;
$GLOBALS['nmkr_sync_data'] = false;
$GLOBALS['nmkr_history'] = array();

class NmkrSyntheticWpdb {
    public $prefix = 'wp_';
    public function prepare($query, $value) { return str_replace('%d', (string) (int) $value, $query); }
    public function get_row($query, $format=null) {
        preg_match('/WHERE id = ([0-9]+)/', $query, $matches);
        $id = (int) ($matches[1] ?? 0);
        return $GLOBALS['nmkr_history'][$id] ?? null;
    }
}
$GLOBALS['wpdb'] = new NmkrSyntheticWpdb();

class NmkrAjaxTermination extends Error { public $kind; public $status; public $data; public function __construct($kind, $status = 0, $data = null) { parent::__construct($kind); $this->kind=$kind; $this->status=$status; $this->data=$data; } }
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
function get_transient($k) { $GLOBALS['nmkr_calls'][]='transient'; return array_key_exists($k, $GLOBALS['nmkr_transients']) ? $GLOBALS['nmkr_transients'][$k] : false; }
function set_transient($k,$v,$t=0) { $GLOBALS['nmkr_calls'][]='transient-write'; return true; }
function delete_transient($k) { $GLOBALS['nmkr_calls'][]='transient-write'; return true; }
function wp_clear_scheduled_hook($h,$a=array()) { $GLOBALS['nmkr_calls'][]='cron'; }
function wp_schedule_single_event($t,$h,$a=array()) { $GLOBALS['nmkr_calls'][]='cron'; return true; }
function nmkr_admit_sync_owner($id) { $GLOBALS['nmkr_calls'][]='admission'; return array(); }
function nmkr_get_sync_owner() { $GLOBALS['nmkr_calls'][]='owner'; return $GLOBALS['nmkr_owner']; }
function nmkr_is_api_connected() { $GLOBALS['nmkr_calls'][]='api'; return false; }
function nmkr_log_ui_status($message, $type) { $GLOBALS['nmkr_calls'][]='ui-log'; }
function wp_json_encode($v) { return json_encode($v); }
function get_current_blog_id() { return 1; }
function wp_generate_uuid4() { return '00000000-0000-4000-8000-000000000001'; }
function wp_create_nonce($action) { return 'synthetic-nonce'; }
function esc_attr($value) { return (string)$value; }
function esc_html($value) { return (string)$value; }
function nmkr_get_log_retention_limit() { return 20; }
function nmkr_safe_getpid() { return 1; }
function nmkr_log_data_sync($message, $type='info', $context=array()) { $GLOBALS['nmkr_calls'][]='data-log'; }
function wp_next_scheduled($hook) { $GLOBALS['nmkr_calls'][]='cron-read'; return false; }
function nmkr_get_sync_data() { $GLOBALS['nmkr_calls'][]='sync-data'; return $GLOBALS['nmkr_sync_data']; }
function nmkr_should_throttle_logs() { return true; }
function nmkr_sync_finalization_resume_pending($id) { return false; }
function nmkr_is_sync_canonically_finished() { return false; }
function nmkr_is_valid_sync_run_id($id) { return preg_match('/^[a-z0-9-]{8,}$/', $id) === 1; }
function nmkr_is_sync_terminal_status($status) { return in_array(strtolower((string)$status), array('completed','success','failed','error','stopped','cancelled','aborted'), true); }
function nmkr_verify_sync_terminal_result() { return false; }
function nmkr_resume_stopped_sync_recovery($run_id, $sync_stats_id) { $GLOBALS['nmkr_calls'][]='stopped-recovery:'.$run_id.':'.$sync_stats_id; return true; }
function nmkr_with_ownerless_legacy_recovery($callback) { $GLOBALS['nmkr_calls'][]='ownerless-recovery'; return !empty($GLOBALS['nmkr_execute_recovery']) ? $callback() : true; }
function nmkr_get_timestamp() { return '2026-01-02 03:04:05'; }
function nmkr_update_sync_stats($id, $data) { $GLOBALS['nmkr_calls'][]='history-write'; $GLOBALS['nmkr_history'][$id]=array_merge($GLOBALS['nmkr_history'][$id],$data); return true; }
function nmkr_clear_sync_data() { $GLOBALS['nmkr_calls'][]='cleanup'; }
function nmkr_clear_sync_jobs_ownerless($reason, $clear_scheduled, $clear_actions) { $GLOBALS['nmkr_calls'][]='cleanup'; }

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

function nmkr_progress_test($name, $nonce, $caps, $post, $owner, $expected, $forbidden, $sync_data=false, $execute_recovery=false, $expect_success=true) {
    $GLOBALS['nmkr_calls']=array(); $GLOBALS['nmkr_nonce_ok']=$nonce; $GLOBALS['nmkr_caps']=$caps;
    $GLOBALS['nmkr_owner']=$owner; $GLOBALS['nmkr_sync_data']=$sync_data; $GLOBALS['nmkr_execute_recovery']=$execute_recovery;
    $GLOBALS['nmkr_transients']=array('nmkr_sync_progress'=>50);
    $_POST=array_merge(array('nonce'=>'synthetic'),$post); $_REQUEST=$_POST; $_SERVER['REQUEST_METHOD']='POST';
    try { nmkr_sync_progress_handler(); throw new Exception($name.'_no_termination'); }
    catch (NmkrAjaxTermination $e) { $kind=$e->kind; $status=$e->status; $data=$e->data; }
    foreach ($expected as $call) if (!in_array($call,$GLOBALS['nmkr_calls'],true)) throw new Exception($name.'_expected_path_missing_'.$call);
    foreach ($forbidden as $call) if (in_array($call,$GLOBALS['nmkr_calls'],true)) throw new Exception($name.'_forbidden_path_'.$call);
    if ($expect_success && $nonce && !empty($caps['nmkr_view_dashboard']) && ($kind!=='success' || !is_array($data) || !array_key_exists('progress',$data))) throw new Exception($name.'_observation_unavailable_'.($data['error_code'] ?? $kind));
    return array($kind,$status,$data);
}

try {
    nmkr_progress_test('progress_nonce',false,array(),array(),false,array('headers','nonce:nmkr_sync_nonce:nonce'),array('cap:nmkr_view_dashboard','cap:nmkr_manage_sync','owner','option','transient','stopped-recovery','ownerless-recovery'));
    $denied=nmkr_progress_test('progress_view_cap',true,array(),array(),false,array('cap:nmkr_view_dashboard'),array('cap:nmkr_manage_sync','owner','option','transient','history-write','cron'));
    if ($denied[0] !== 'error' || $denied[1] !== 403) throw new Exception('progress_view_cap_forbidden_shape');
    nmkr_progress_test('progress_view_only',true,array('nmkr_view_dashboard'=>true),array(),false,array('cap:nmkr_manage_sync','sync-data'),array('ownerless-recovery','history-write','option-write','transient-write','cron','stopped-recovery'));
    $stopped_owner=array('mode'=>'direct','state'=>'stop_requested','run_id'=>'synthetic-run-0001','sync_stats_id'=>41);
    nmkr_progress_test('progress_view_stopped',true,array('nmkr_view_dashboard'=>true),array(),$stopped_owner,array('sync-data'),array('stopped-recovery:synthetic-run-0001:41','ownerless-recovery','history-write','option-write','transient-write','cron'));
    foreach (array(array('recovery'=>'1'),array('check_stalled'=>'1'),array('recovery'=>array('1')),array('check_stalled'=>array('1'))) as $index=>$crafted) {
        nmkr_progress_test('progress_view_crafted_'.$index,true,array('nmkr_view_dashboard'=>true),$crafted,false,array('sync-data'),array('ownerless-recovery','history-write','option-write','transient-write','cron','stopped-recovery'));
    }
    nmkr_progress_test('progress_manager_stopped',true,array('nmkr_view_dashboard'=>true,'nmkr_manage_sync'=>true),array(),$stopped_owner,array('stopped-recovery:synthetic-run-0001:41','sync-data'),array('ownerless-recovery','history-write'));
    if (count(array_filter($GLOBALS['nmkr_calls'],function($call){return $call==='stopped-recovery:synthetic-run-0001:41';})) !== 1) throw new Exception('progress_manager_stopped_duplicate');
    nmkr_progress_test('progress_manager_ownerless',true,array('nmkr_view_dashboard'=>true,'nmkr_manage_sync'=>true),array('recovery'=>'1'),false,array('ownerless-recovery'),array('stopped-recovery','history-write'));

    $GLOBALS['nmkr_options']['nmkr_last_progress_update_time']=1;
    $GLOBALS['nmkr_options']['nmkr_last_progress_value']=50;
    $stale_sync_data=array('sync_stats_id'=>51,'status'=>'processing_tokens','last_update_time'=>1);
    $GLOBALS['nmkr_history'][51]=array('id'=>51,'status'=>'completed','end_time'=>'2026-01-01 00:00:00','error_message'=>'terminal fields');
    nmkr_progress_test('terminal_history_recovery',true,array('nmkr_view_dashboard'=>true,'nmkr_manage_sync'=>true),array('recovery'=>'1'),false,array('ownerless-recovery','cleanup'),array('history-write'),$stale_sync_data,true,false);
    if ($GLOBALS['nmkr_history'][51]['status'] !== 'completed' || $GLOBALS['nmkr_history'][51]['end_time'] !== '2026-01-01 00:00:00' || $GLOBALS['nmkr_history'][51]['error_message'] !== 'terminal fields') throw new Exception('terminal_history_overwritten');
    $stale_sync_data['sync_stats_id']=52;
    $GLOBALS['nmkr_history'][52]=array('id'=>52,'status'=>'processing_tokens','end_time'=>null,'error_message'=>'');
    nmkr_progress_test('nonterminal_history_recovery',true,array('nmkr_view_dashboard'=>true,'nmkr_manage_sync'=>true),array('check_stalled'=>'1'),false,array('ownerless-recovery','history-write','cleanup'),array(),$stale_sync_data,true,false);
    if ($GLOBALS['nmkr_history'][52]['status'] !== 'failed' || empty($GLOBALS['nmkr_history'][52]['end_time'])) throw new Exception('nonterminal_history_not_eligible');

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
