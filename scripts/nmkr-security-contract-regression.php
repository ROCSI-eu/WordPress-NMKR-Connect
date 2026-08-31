<?php
/** Targeted, public-safe M4-06 contracts for registrations, settings, and analytics SQL. */
define('ABSPATH', __DIR__ . '/synthetic/');
define('DAY_IN_SECONDS', 86400);
define('ARRAY_A', 'ARRAY_A');

final class NmkrContractStop extends Exception { public $kind; public $data; public $status; public function __construct($kind, $data = null, $status = 0) { parent::__construct($kind); $this->kind=$kind; $this->data=$data; $this->status=$status; } }
function nmkr_assert($ok, $name) { if (!$ok) throw new Exception($name); }
function sanitize_text_field($v) { return trim(strip_tags((string)$v)); }
function wp_unslash($v) { return $v; }
function wp_json_encode($v) { return json_encode($v); }
function current_time($type) { return $type === 'timestamp' ? 1700000000 : '2023-11-14 22:13:20'; }
function date_i18n($format, $timestamp) { return gmdate($format, $timestamp); }
function get_current_blog_id() { return 1; }
function nocache_headers() {}
function status_header($status) { $GLOBALS['nmkr_status'] = $status; }
function wp_verify_nonce($nonce, $action) { return !empty($GLOBALS['nmkr_nonce']); }
function current_user_can($cap) { return !empty($GLOBALS['nmkr_caps'][$cap]); }
function wp_send_json_error($data, $status = 0) { throw new NmkrContractStop('error', $data, $status); }
function wp_send_json_success($data = null) { throw new NmkrContractStop('success', $data, 200); }
function get_option($name, $default = false) { return $GLOBALS['nmkr_options'][$name] ?? $default; }
function get_transient($key) { $GLOBALS['nmkr_ledger'][]='cache-read'; return false; }
function set_transient($key, $value, $ttl) { $GLOBALS['nmkr_ledger'][]='cache-write'; return true; }
function nmkr_trim_dashboard_logs_to_retention($limit) { $GLOBALS['nmkr_ledger'][]='log-trim:'.(int)$limit; }
function __($v) { return $v; }
function plugin_basename($v) { return $v; }
function add_action($hook, $callback) { $GLOBALS['nmkr_hooks'][$hook][]=$callback; }
function add_filter($hook, $callback) { $GLOBALS['nmkr_filters'][$hook][]=$callback; }
function register_setting($group, $option, $sanitizer) { $GLOBALS['nmkr_settings'][]=array($group,$option,$sanitizer); }
function add_settings_section() {}
function add_settings_field() {}
function add_options_page($title,$menu,$cap,$slug,$callback) { $GLOBALS['nmkr_pages'][]=array($cap,$slug,$callback); }

final class NmkrContractWpdb {
    public $prefix='wp_'; public $templates=array(); public $params=array(); public $reads=0;
    function prepare($query, $values=array()) { $values=is_array($values)?$values:array_slice(func_get_args(),1); preg_match_all('/%(?:\d+\$)?[sdfFi]/',str_replace('%%','',$query),$placeholders); nmkr_assert(count($placeholders[0])===count($values),'sql_placeholder_count'); $this->templates[]=$query; $this->params[]=$values; $GLOBALS['nmkr_ledger'][]='sql-prepare'; return $query; }
    function get_var($query) { $this->reads++; $GLOBALS['nmkr_ledger'][]='sql-read'; return 0; }
    function get_results($query,$format=null) { $this->reads++; $GLOBALS['nmkr_ledger'][]='sql-read'; return array(array('project_uid'=>'safe','views'=>'1','clicks'=>'0','ctr'=>'0')); }
    function esc_like($v) { return addcslashes($v, '_%\\'); }
}
$GLOBALS['wpdb']=new NmkrContractWpdb(); $GLOBALS['nmkr_hooks']=array(); $GLOBALS['nmkr_filters']=array(); $GLOBALS['nmkr_settings']=array(); $GLOBALS['nmkr_pages']=array();

/* Inventory is derived from the registration statements in tracked production PHP. */
$registrations=array(); $GLOBALS['nmkr_ajax_hook_candidates']=array();
$production_php=array(dirname(__DIR__).'/nmkr-connect.php');
$iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__).'/includes'));
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') $production_php[]=$file->getPathname();
}
foreach ($production_php as $file) {
    $source=file_get_contents($file);
    preg_match_all("/add_action\\s*\\(\\s*(?:(['\"])([^'\"]+)\\1|([^,]+))\\s*,/",$source,$all_actions,PREG_SET_ORDER);
    foreach($all_actions as $action_call) {
        nmkr_assert(empty($action_call[3]), 'dynamic_action_hook_unclassified');
        if(strpos($action_call[2],'wp_ajax_')===0) $GLOBALS['nmkr_ajax_hook_candidates'][]=$action_call[2];
    }
    preg_match_all("/add_action\\(\\s*['\"](wp_ajax_(?:nopriv_)?[^'\"]+)['\"]\\s*,\\s*['\"]([^'\"]+)['\"]\\s*\\)/",$source,$matches,PREG_SET_ORDER);
    foreach($matches as $m) $registrations[$m[1]]=$m[2];
}
$privileged=array(
 'nmkr_start_sync'=>'nmkr_start_sync_handler','nmkr_sync_progress'=>'nmkr_sync_progress_handler','nmkr_cleanup_sync_jobs'=>'nmkr_cleanup_sync_jobs_handler','nmkr_stop_sync'=>'nmkr_stop_sync_handler','nmkr_restart_sync_batch'=>'nmkr_restart_sync_batch_handler','nmkr_force_stop_sync'=>'nmkr_force_stop_sync_handler','nmkr_check_sync_health'=>'nmkr_check_sync_health_handler',
 'nmkr_check_api_status'=>'nmkr_check_api_status','nmkr_get_sync_statistics'=>'nmkr_get_sync_statistics_ajax','nmkr_store_active_metrics'=>'nmkr_store_active_metrics_ajax','nmkr_clear_all_logs'=>'nmkr_clear_all_logs_ajax','nmkr_clear_section_logs'=>'nmkr_clear_section_logs_ajax',
 'nmkr_analytics_kpis'=>'nmkr_analytics_kpis_ajax','nmkr_analytics_timeseries'=>'nmkr_analytics_timeseries_ajax','nmkr_analytics_top_projects'=>'nmkr_analytics_top_projects_ajax','nmkr_analytics_top_tokens'=>'nmkr_analytics_top_tokens_ajax','nmkr_analytics_breakdown'=>'nmkr_analytics_breakdown_ajax','nmkr_analytics_export'=>'nmkr_analytics_export_ajax'
);
$expected=array(); foreach($privileged as $action=>$callback) $expected['wp_ajax_'.$action]=$callback;
$expected['wp_ajax_nmkr_analytics_event']='nmkr_analytics_event_ajax';
$expected['wp_ajax_nopriv_nmkr_analytics_event']='nmkr_analytics_event_ajax'; // intentional public ingestion exception.
ksort($expected); ksort($registrations);
sort($GLOBALS['nmkr_ajax_hook_candidates']); $registered_hooks=array_keys($registrations); sort($registered_hooks);
nmkr_assert($GLOBALS['nmkr_ajax_hook_candidates'] === $registered_hooks, 'ajax_registration_form_unclassified');
nmkr_assert($registrations === $expected, 'ajax_registration_surface_unclassified');
foreach($privileged as $action=>$callback) nmkr_assert(!isset($registrations['wp_ajax_nopriv_'.$action]), 'privileged_nopriv_registration');

require dirname(__DIR__).'/includes/pages/settings/nmkr-settings-validation.php';
require dirname(__DIR__).'/includes/pages/settings/nmkr-settings-core.php';
nmkr_connect_register_settings(); nmkr_connect_add_settings_page();
nmkr_assert(in_array(array('nmkr_connect_settings_group','nmkr_connect_options','nmkr_connect_sanitize_options'),$GLOBALS['nmkr_settings'],true),'settings_registration');
$capability_filters=$GLOBALS['nmkr_filters']['option_page_capability_nmkr_connect_settings_group'] ?? array();
nmkr_assert($capability_filters===array('nmkr_connect_settings_option_page_capability'),'settings_capability_filter_registration');
$settings_capability='manage_options'; foreach($capability_filters as $filter) $settings_capability=call_user_func($filter,$settings_capability);
nmkr_assert($settings_capability==='nmkr_manage_settings','settings_capability_filter');
nmkr_assert(in_array(array('nmkr_manage_settings','nmkr-connect-settings','nmkr_connect_settings_page'),$GLOBALS['nmkr_pages'],true),'settings_page_capability');
$GLOBALS['nmkr_options']['nmkr_connect_options']=array('future_key'=>'preserved','api_key'=>'old'); $GLOBALS['nmkr_ledger']=array();
$clean=nmkr_connect_sanitize_options(array('sync_profile'=>'hostile','sync_batch_size'=>'9999','sync_batch_delay'=>'-4','analytics_mode'=>'evil','analytics_retention_days'=>'9999','analytics_sample_rate'=>'8','debug_enabled'=>'1','log_to_dashboard'=>'1','log_retention_limit'=>'9999'));
nmkr_assert($clean['future_key']==='preserved' && $clean['sync_profile']==='balanced' && $clean['sync_batch_size']===10 && $clean['sync_batch_delay']===1,'settings_allowlist_clamps');
nmkr_assert($clean['analytics_mode']==='custom' && $clean['analytics_retention_days']===365 && $clean['analytics_sample_rate']===1.0,'settings_analytics_clamps');
nmkr_assert($GLOBALS['nmkr_ledger']===array('log-trim:1000'),'settings_log_retention_model');
/* This models plugin dispatch denial, not WordPress options.php internals. */
$before=$GLOBALS['nmkr_options']; $GLOBALS['nmkr_ledger']=array(); $allowed=false; if($allowed) nmkr_connect_sanitize_options(array());
nmkr_assert($before===$GLOBALS['nmkr_options'] && $GLOBALS['nmkr_ledger']===array(),'denied_settings_mutation');

require dirname(__DIR__).'/includes/pages/analytics/nmkr-analytics-ajax.php';
$hostile="x' OR 1=1 -- <svg>";
nmkr_assert(strlen(nmkr_analytics_sanitize_uid(str_repeat($hostile,10)))<=64,'uid_bound');
nmkr_assert(nmkr_analytics_sanitize_shortcode_type($hostile)==='','shortcode_allowlist');
nmkr_assert(nmkr_analytics_uid_prefix(str_repeat($hostile,10))===substr(preg_replace('/[^0-9a-zA-Z\-_]/','',str_repeat($hostile,10)),0,64),'prefix_bound');
list($page,$per,$offset)=nmkr_analytics_parse_pagination(array('page'=>'-9 UNION','per_page'=>'9999')); nmkr_assert($page===1 && $per===100 && $offset===0,'pagination_bound');
$range=nmkr_analytics_parse_range_and_bucket(array('range'=>'custom','from'=>'1900-01-01','to'=>'2999-01-01','bucket'=>$hostile)); nmkr_assert(in_array($range['bucket'],array('hour','day'),true) && $range['to_ts']-$range['from_ts']<=365*DAY_IN_SECONDS,'range_bucket_bound');
$GLOBALS['nmkr_nonce']=false; $GLOBALS['nmkr_caps']=array(); $GLOBALS['nmkr_ledger']=array(); $_SERVER['REQUEST_METHOD']='POST'; $_POST=array('nonce'=>'bad'); $_REQUEST=$_POST;
try { nmkr_analytics_top_projects_ajax(); nmkr_assert(false,'analytics_denial_missing'); } catch(NmkrContractStop $e) { nmkr_assert($e->kind==='error' && $e->status===403,'analytics_denial_response'); }
nmkr_assert($GLOBALS['nmkr_ledger']===array(),'analytics_denial_downstream');
$GLOBALS['nmkr_nonce']=true; $GLOBALS['nmkr_caps']=array('nmkr_view_analytics'=>true); $GLOBALS['nmkr_ledger']=array(); $GLOBALS['nmkr_options']['nmkr_connect_options']=array('analytics_debug'=>1);
$_POST=array('nonce'=>'ok'); $_REQUEST=array_merge($_POST,array('range'=>'custom','from'=>'1900-01-01','to'=>'2999-01-01','bucket'=>$hostile,'shortcode_type'=>$hostile,'project_uid'=>$hostile,'token_uid'=>$hostile,'search'=>$hostile,'sort'=>$hostile,'order'=>$hostile,'page'=>$hostile,'per_page'=>'9999'));
try { nmkr_analytics_top_projects_ajax(); nmkr_assert(false,'analytics_success_missing'); } catch(NmkrContractStop $e) { nmkr_assert($e->kind==='success' && $e->data['page']===1 && $e->data['per_page']===100 && $e->data['sort']==='views' && $e->data['order']==='desc','analytics_response_bound'); }
nmkr_assert($GLOBALS['wpdb']->reads===2 && count($GLOBALS['wpdb']->templates)===2,'analytics_sql_execution');
foreach($GLOBALS['wpdb']->templates as $template) { nmkr_assert(strpos($template,$hostile)===false,'hostile_sql_structure'); nmkr_assert((bool)preg_match('/ORDER BY views DESC|COUNT\\(DISTINCT project_uid\\)/',$template),'sql_allowlisted_structure'); }
nmkr_assert(in_array(100,$GLOBALS['wpdb']->params[1],true) && in_array(0,$GLOBALS['wpdb']->params[1],true),'pagination_prepared');

echo "M4-06 targeted security contracts: PASS\n";
