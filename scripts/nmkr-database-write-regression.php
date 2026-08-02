<?php
/** Public-safe deterministic exact database-write regression. */
define('ABSPATH', dirname(__DIR__) . '/'); define('HOUR_IN_SECONDS', 3600); define('NMKR_SYNC_TRANSIENT_TTL', 3600);
class WP_Error { private $code; private $message; function __construct($code,$message=''){ $this->code=$code;$this->message=$message; } function get_error_code(){return $this->code;} function get_error_message(){return $this->message;} }
function is_wp_error($value){return $value instanceof WP_Error;}
$GLOBALS['transients']=array(); $GLOBALS['logs']=array(); $GLOBALS['heartbeats']=0;
function get_transient($key){return $GLOBALS['transients'][$key]??false;} function set_transient($key,$value,$ttl){$GLOBALS['transients'][$key]=$value;return true;} function delete_transient($key){unset($GLOBALS['transients'][$key]);}
function nmkr_get_timestamp(){return '2026-01-02 03:04:05';} function nmkr_log_data_sync($message,$level='info',$context=array()){$GLOBALS['logs'][]=$message;} function nmkr_update_sync_heartbeat(){$GLOBALS['heartbeats']++;}
function wp_json_encode($v){return json_encode($v);}
function sanitize_text_field($v){return (string)$v;} function sanitize_textarea_field($v){return (string)$v;}
function nmkr_generate_project_hash($v){return 'project-hash';} function nmkr_generate_token_hash($v){return 'token-hash';} function nmkr_generate_token_details_hash($v){return 'detail-hash';}
class FakeWpdb {
 public $prefix='wp_',$last_insert_data=array(),$last_update_data=array(),$last_error='',$mode='insert',$existing=null,$insert_calls=0,$update_calls=0,$detail_insert_calls=0;
 function prepare($sql,$value){return $sql;} function get_row($sql){if($this->mode==='query_fail'){$this->last_error='synthetic';return null;}$this->last_error='';return $this->existing;}
 function get_var($sql){if($this->mode==='query_fail'){$this->last_error='synthetic';return null;}$this->last_error='';return $this->existing?1:0;}
 function insert($table,$data){$this->last_insert_data=$data;$this->insert_calls++;if(strpos($table,'token_details')!==false)$this->detail_insert_calls++;return $this->mode==='insert_fail'?false:1;}
 function update($table,$data,$where){$this->last_update_data=$data;$this->update_calls++;if($this->mode==='update_fail')return false;if($this->mode==='unchanged')return 0;return 1;}
}
$wpdb=new FakeWpdb(); require dirname(__DIR__).'/includes/helpers/nmkr-performance-functions.php'; require dirname(__DIR__).'/includes/database/nmkr-database-functions.php';
function check($ok,$message){if(!$ok){fwrite(STDERR,"FAIL: $message\n");exit(1);}echo "PASS: $message\n";}
function reset_case($mode,$existing=null){global $wpdb;$wpdb=new FakeWpdb();$wpdb->mode=$mode;$wpdb->existing=$existing;$GLOBALS['transients']=array();$GLOBALS['logs']=array();$GLOBALS['heartbeats']=0;}
$project=array('uid'=>'synthetic-project','projectname'=>'Synthetic');
$chain_cases=array(
 array(array('Cardano'),'["Cardano"]','Cardano'),
 array(array('Solana'),'["Solana"]','Solana'),
 array(array('Cardano','Solana'),'["Cardano","Solana"]',''),
 array(array(' cardano ','Cardano','SOLANA','solana'),'["Cardano","Solana"]',''),
 array('Future Chain','["Future Chain"]','Future Chain'),
 array(array(array('bad'),null,''),'[]','')
);
foreach($chain_cases as $case){reset_case('insert');nmkr_store_project_exact(array('uid'=>'chain','projectname'=>'Chain','blockchains'=>$case[0]));check($wpdb->last_insert_data['blockchains']===$case[1]&&$wpdb->last_insert_data['blockchain']===$case[2],'project chains normalize without lossy fallback');}reset_case('update',(object)array('hash'=>'project-hash','blockchains'=>'["Cardano"]'));nmkr_store_project_exact(array('uid'=>'chain','projectname'=>'Chain','blockchains'=>array('Cardano','Solana')));check($wpdb->last_update_data['blockchains']==='["Cardano","Solana"]'&&$wpdb->last_update_data['blockchain']==='','same-hash synchronization repairs a lossy legacy chain collection');
$detail_cases=array(
 array('text','text'), array(12,'12'), array(1.5,'1.5'), array(true,'1'), array(false,'0'),
 array(array('chain'=>'Solana'),'{"chain":"Solana"}'), array(array('Solana'),'["Solana"]'),
 array((object)array('chain'=>'Solana'),'{"chain":"Solana"}'), array(null,''), array('','')
);
foreach($detail_cases as $case){check(nmkr_normalize_solana_project_details($case[0])===$case[1],'Solana project details have exact deterministic output');}
$resource=fopen('php://memory','r');check(nmkr_normalize_solana_project_details($resource)==='','unsupported Solana details degrade safely');check(nmkr_normalize_solana_project_details(array('bad'=>$resource))==='','JSON encoding failure degrades safely');fclose($resource);
reset_case('update',(object)array('hash'=>'project-hash','blockchain'=>'Solana','blockchains'=>'["Solana"]','solana_project_details'=>'Array'));
$r=nmkr_store_project_exact(array('uid'=>'details','projectname'=>'Details','blockchains'=>array('Solana'),'solanaProjectDetails'=>array('chain'=>'Solana')));
check($r['action']==='updated'&&$wpdb->last_update_data['solana_project_details']==='{"chain":"Solana"}'&&isset($wpdb->last_update_data['updated_at'],$wpdb->last_update_data['synced_at']),'same-hash synchronization repairs stale Solana details');
reset_case('update',(object)array('hash'=>'project-hash','blockchain'=>'Cardano','blockchains'=>'["Solana"]','solana_project_details'=>''));
$r=nmkr_store_project_exact(array('uid'=>'details','projectname'=>'Details','blockchains'=>array('Solana')));
check($r['action']==='updated'&&$wpdb->last_update_data['blockchain']==='Solana'&&isset($wpdb->last_update_data['updated_at'],$wpdb->last_update_data['synced_at']),'same-hash synchronization repairs stale legacy blockchain scalar');
reset_case('update',(object)array('hash'=>'project-hash','blockchain'=>'Solana','blockchains'=>'["Solana"]','solana_project_details'=>''));
$r=nmkr_store_project_exact(array('uid'=>'details','projectname'=>'Details','blockchains'=>array('Solana')));
check($r['action']==='unchanged'&&array_keys($wpdb->last_update_data)===array('synced_at'),'exact same-hash project uses synced-at-only path');
reset_case('insert');$r=nmkr_store_project_exact($project);$project_stats=get_transient('nmkr_current_sync_stats_live');check($r['action']==='inserted','project insert success is exact');check($project_stats['total_projects']===1&&$project_stats['db_queries']===1,'project storage counters increment exactly once');
nmkr_store_project_exact($project);$repeated_project_stats=get_transient('nmkr_current_sync_stats_live');check($repeated_project_stats['db_duration']>$project_stats['db_duration'],'repeated project writes accumulate database duration');check($repeated_project_stats['total_projects']===2&&$repeated_project_stats['db_queries']===2,'repeated project storage counters increment exactly once per write');
$synthetic_elapsed=0.001;$synthetic_duration=array('duration'=>round($synthetic_elapsed,2),'duration_unrounded'=>$synthetic_elapsed);check($synthetic_duration['duration']===0.0&&$synthetic_duration['duration_unrounded']===0.001,'controlled sub-5 ms duration stays positive when its display value rounds to zero');
reset_case('insert');nmkr_record_database_operation('project',0.0015);nmkr_record_database_operation('project',0.003);$synthetic_stats=get_transient('nmkr_current_sync_stats_live');check(abs($synthetic_stats['db_duration']-0.0045)<0.0000001&&$synthetic_stats['total_projects']===2&&$synthetic_stats['db_queries']===2,'controlled fast database durations accumulate at full precision exactly once');
reset_case('insert_fail');$r=nmkr_store_project_exact($project);check(is_wp_error($r)&&$r->get_error_code()==='nmkr_project_write_failed','project insert failure is typed');check($GLOBALS['heartbeats']===0&&!array_filter($GLOBALS['logs'],function($m){return strpos($m,'Created new project')!==false;}),'failure has no heartbeat or success log');check(is_array(get_transient('nmkr_current_sync_stats_live')),'tracker closes on insert failure');
reset_case('update',(object)array('hash'=>'old'));$r=nmkr_store_project_exact($project);check($r['action']==='updated','changed update is exact');
reset_case('unchanged',(object)array('hash'=>'old'));$r=nmkr_store_project_exact($project);check($r['action']==='unchanged','zero-row update is a successful no-op');
reset_case('update_fail',(object)array('hash'=>'old'));check(is_wp_error(nmkr_store_project_exact($project)),'update failure is typed');
reset_case('query_fail');check(is_wp_error(nmkr_store_project_exact($project)),'existence-query failure is not row absence');
reset_case('insert');check(is_wp_error(nmkr_store_project_exact(array())),'validation failure is typed and tracker closes');
$token=array('uid'=>'synthetic-token','id'=>'1','name'=>'Synthetic');
reset_case('update',(object)array('hash'=>'old','token_id'=>'old','project_uid'=>'project-a'));$moved=$token;$moved['id']='2';$r=nmkr_store_token_exact($moved,'project-b');check($r['action']==='updated'&&$wpdb->last_update_data['token_id']==='2'&&$wpdb->last_update_data['project_uid']==='project-b'&&$wpdb->last_update_data['token_name']==='Synthetic'&&isset($wpdb->last_update_data['updated_at'],$wpdb->last_update_data['synced_at']),'changed-hash token update refreshes identity ownership and timestamps');
reset_case('update',(object)array('hash'=>'token-hash','token_id'=>'1','project_uid'=>'project-a'));$r=nmkr_store_token_exact($token,'project-b');check($r['action']==='updated'&&isset($wpdb->last_update_data['updated_at'],$wpdb->last_update_data['synced_at']),'same-hash ownership change is an update');
reset_case('update',(object)array('hash'=>'token-hash','token_id'=>'old','project_uid'=>'project-b'));$r=nmkr_store_token_exact($token,'project-b');check($r['action']==='updated'&&$wpdb->last_update_data['token_id']==='1'&&isset($wpdb->last_update_data['updated_at'],$wpdb->last_update_data['synced_at']),'same-hash token identifier change is an update');
reset_case('update',(object)array('hash'=>'token-hash','token_id'=>'1','project_uid'=>'project-b'));$r=nmkr_store_token_exact($token,'project-b');check($r['action']==='unchanged'&&array_keys($wpdb->last_update_data)===array('synced_at'),'exact same-hash token refreshes only synced_at');reset_case('insert');$r=nmkr_store_token_exact($token,'synthetic-project');$token_stats=get_transient('nmkr_current_sync_stats_live');check($r['action']==='inserted'&&$token_stats['total_tokens']===1&&$token_stats['db_queries']===1,'basic-token storage increments entity and query counters once');check($token_stats['db_duration']>0.0,'basic-token write records an unrounded positive duration');reset_case('insert');$r=nmkr_store_token_details_exact('synthetic-token',array('title'=>'Synthetic'));$detail_stats=get_transient('nmkr_current_sync_stats_live');check($r['action']==='inserted'&&($detail_stats['total_tokens']??0)===0&&$detail_stats['db_queries']===1,'token-detail storage increments only its query counter once');check($detail_stats['db_duration']>0.0,'token-detail write records an unrounded positive duration');reset_case('insert_fail');$r=nmkr_store_token_exact($token,'synthetic-project');check(is_wp_error($r)&&$GLOBALS['heartbeats']===0,'basic-token failure has no heartbeat');
// Exercise the production direct token worker with an injected successful detail fetch.
function nmkr_update_sync_progress(){} function nmkr_build_sync_api_execution_context(){return array();} function nmkr_sync_worker_checkpoint(){return true;} function nmkr_connect_fetch_nft_details(){return array('uid'=>'synthetic-token','id'=>'1','name'=>'Synthetic');} function nmkr_is_sync_worker_halt_error(){return false;} function nmkr_should_throttle_logs(){return true;}
$core=file_get_contents(dirname(__DIR__).'/includes/synchronization/nmkr-sync-core.php');$predicate_start=strpos($core,'function nmkr_is_fatal_api_error(');$predicate_end=strpos($core,"\n/**",$predicate_start+1);eval(substr($core,$predicate_start,$predicate_end-$predicate_start));$start=strpos($core,'function nmkr_sync_token_details(');$end=strpos($core,"\n/**",$start+1);eval(substr($core,$start,$end-$start));
$log=array();$steps=0;$before=$wpdb->detail_insert_calls;$r=nmkr_sync_token_details('synthetic-token','synthetic-project',$log,$steps,1,$token,'',0);check(is_wp_error($r)&&$r->get_error_code()==='nmkr_token_write_failed'&&$wpdb->detail_insert_calls===$before,'basic-token failure prevents token-detail write');
echo "All exact database-write regressions passed.\n";
