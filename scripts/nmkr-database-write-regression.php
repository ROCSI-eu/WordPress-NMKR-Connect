<?php
/** Public-safe deterministic exact database-write regression. */
define('ABSPATH', dirname(__DIR__) . '/'); define('HOUR_IN_SECONDS', 3600); define('NMKR_SYNC_TRANSIENT_TTL', 3600);
class WP_Error { private $code; private $message; function __construct($code,$message=''){ $this->code=$code;$this->message=$message; } function get_error_code(){return $this->code;} function get_error_message(){return $this->message;} }
function is_wp_error($value){return $value instanceof WP_Error;}
$GLOBALS['transients']=array(); $GLOBALS['logs']=array(); $GLOBALS['heartbeats']=0;
function get_transient($key){return $GLOBALS['transients'][$key]??false;} function set_transient($key,$value,$ttl){$GLOBALS['transients'][$key]=$value;return true;} function delete_transient($key){unset($GLOBALS['transients'][$key]);}
function nmkr_get_timestamp(){return '2026-01-02 03:04:05';} function nmkr_log_data_sync($message,$level='info',$context=array()){$GLOBALS['logs'][]=$message;} function nmkr_update_sync_heartbeat(){$GLOBALS['heartbeats']++;}
function sanitize_text_field($v){return (string)$v;} function sanitize_textarea_field($v){return (string)$v;}
function nmkr_generate_project_hash($v){return 'project-hash';} function nmkr_generate_token_hash($v){return 'token-hash';} function nmkr_generate_token_details_hash($v){return 'detail-hash';}
class FakeWpdb {
 public $prefix='wp_',$last_error='',$mode='insert',$existing=null,$insert_calls=0,$update_calls=0,$detail_insert_calls=0;
 function prepare($sql,$value){return $sql;} function get_row($sql){if($this->mode==='query_fail'){$this->last_error='synthetic';return null;}$this->last_error='';return $this->existing;}
 function get_var($sql){if($this->mode==='query_fail'){$this->last_error='synthetic';return null;}$this->last_error='';return $this->existing?1:0;}
 function insert($table,$data){$this->insert_calls++;if(strpos($table,'token_details')!==false)$this->detail_insert_calls++;return $this->mode==='insert_fail'?false:1;}
 function update($table,$data,$where){$this->update_calls++;if($this->mode==='update_fail')return false;if($this->mode==='unchanged')return 0;return 1;}
}
$wpdb=new FakeWpdb(); require dirname(__DIR__).'/includes/helpers/nmkr-performance-functions.php'; require dirname(__DIR__).'/includes/database/nmkr-database-functions.php';
function check($ok,$message){if(!$ok){fwrite(STDERR,"FAIL: $message\n");exit(1);}echo "PASS: $message\n";}
function reset_case($mode,$existing=null){global $wpdb;$wpdb=new FakeWpdb();$wpdb->mode=$mode;$wpdb->existing=$existing;$GLOBALS['transients']=array();$GLOBALS['logs']=array();$GLOBALS['heartbeats']=0;}
$project=array('uid'=>'synthetic-project','projectname'=>'Synthetic');
reset_case('insert');$r=nmkr_store_project_exact($project);check($r['action']==='inserted','project insert success is exact');check(get_transient('nmkr_current_sync_stats_live')['total_projects']===1&&get_transient('nmkr_current_sync_stats_live')['db_queries']===1,'project storage counters increment exactly once');
reset_case('insert_fail');$r=nmkr_store_project_exact($project);check(is_wp_error($r)&&$r->get_error_code()==='nmkr_project_write_failed','project insert failure is typed');check($GLOBALS['heartbeats']===0&&!array_filter($GLOBALS['logs'],function($m){return strpos($m,'Created new project')!==false;}),'failure has no heartbeat or success log');check(is_array(get_transient('nmkr_current_sync_stats_live')),'tracker closes on insert failure');
reset_case('update',(object)array('hash'=>'old'));$r=nmkr_store_project_exact($project);check($r['action']==='updated','changed update is exact');
reset_case('unchanged',(object)array('hash'=>'old'));$r=nmkr_store_project_exact($project);check($r['action']==='unchanged','zero-row update is a successful no-op');
reset_case('update_fail',(object)array('hash'=>'old'));check(is_wp_error(nmkr_store_project_exact($project)),'update failure is typed');
reset_case('query_fail');check(is_wp_error(nmkr_store_project_exact($project)),'existence-query failure is not row absence');
reset_case('insert');check(is_wp_error(nmkr_store_project_exact(array())),'validation failure is typed and tracker closes');
$token=array('uid'=>'synthetic-token','id'=>'1','name'=>'Synthetic');reset_case('insert');$r=nmkr_store_token_exact($token,'synthetic-project');check($r['action']==='inserted'&&get_transient('nmkr_current_sync_stats_live')['total_tokens']===1,'basic-token storage increments token total once');reset_case('insert');$r=nmkr_store_token_details_exact('synthetic-token',array('title'=>'Synthetic'));check($r['action']==='inserted'&&(get_transient('nmkr_current_sync_stats_live')['total_tokens']??0)===0,'token-detail storage does not increment token total');reset_case('insert_fail');$r=nmkr_store_token_exact($token,'synthetic-project');check(is_wp_error($r)&&$GLOBALS['heartbeats']===0,'basic-token failure has no heartbeat');
// Exercise the production direct token worker with an injected successful detail fetch.
function nmkr_update_sync_progress(){} function nmkr_build_sync_api_execution_context(){return array();} function nmkr_sync_worker_checkpoint(){return true;} function nmkr_connect_fetch_nft_details(){return array('uid'=>'synthetic-token','id'=>'1','name'=>'Synthetic');} function nmkr_is_sync_worker_halt_error(){return false;} function nmkr_should_throttle_logs(){return true;}
$core=file_get_contents(dirname(__DIR__).'/includes/synchronization/nmkr-sync-core.php');$start=strpos($core,'function nmkr_sync_token_details(');$end=strpos($core,"\n/**",$start+1);eval(substr($core,$start,$end-$start));
$log=array();$steps=0;$before=$wpdb->detail_insert_calls;$r=nmkr_sync_token_details('synthetic-token','synthetic-project',$log,$steps,1,$token,'',0);check(is_wp_error($r)&&$r->get_error_code()==='nmkr_token_write_failed'&&$wpdb->detail_insert_calls===$before,'basic-token failure prevents token-detail write');
echo "All exact database-write regressions passed.\n";
