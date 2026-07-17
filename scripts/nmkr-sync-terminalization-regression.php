<?php
// Public-safe synthetic lifecycle regression; no WordPress install or network access.
define('ABSPATH', __DIR__); define('NMKR_SYNC_TRANSIENT_TTL', 3600); define('ARRAY_A', 'ARRAY_A');
$GLOBALS['options']=array(); $GLOBALS['transients']=array(); $GLOBALS['history']=array(); $GLOBALS['metrics']=array(); $GLOBALS['hooks']=array();
function get_option($k,$d=false){return array_key_exists($k,$GLOBALS['options'])?$GLOBALS['options'][$k]:$d;} function update_option($k,$v){$GLOBALS['options'][$k]=$v;return true;} function delete_option($k){unset($GLOBALS['options'][$k]);return true;}
function get_transient($k){return array_key_exists($k,$GLOBALS['transients'])?$GLOBALS['transients'][$k]:false;} function set_transient($k,$v,$ttl){$GLOBALS['transients'][$k]=$v;return true;} function delete_transient($k){unset($GLOBALS['transients'][$k]);return true;}
function wp_clear_scheduled_hook($h){unset($GLOBALS['hooks'][$h]);} function maybe_serialize($v){return serialize($v);} function maybe_unserialize($v){return unserialize($v);} function wp_cache_delete(){}
function nmkr_get_timestamp(){return '2026-01-02 03:04:05';} function nmkr_get_sync_data(){return get_option('nmkr_sync_data',array());} function nmkr_save_sync_data($v){return update_option('nmkr_sync_data',$v);}
function nmkr_update_sync_heartbeat(){update_option('nmkr_sync_heartbeat',time());} function nmkr_cleanup_sync_heartbeat(){delete_option('nmkr_sync_heartbeat');} function nmkr_log_data_sync(){} function nmkr_log_ui_status(){} function nmkr_safe_getpid(){return 1;}
function nmkr_get_sync_stats(){return array('total_duration'=>2,'average_time'=>.1,'request_count'=>3,'memory_used'=>4);} function nmkr_save_sync_metrics($m){global $wpdb;$GLOBALS['metrics'][]=$m;$wpdb->insert_id=count($GLOBALS['metrics']);return $wpdb->insert_id;}
function nmkr_update_sync_stats($id,$data){$GLOBALS['history'][$id]=array_merge($GLOBALS['history'][$id],$data);$GLOBALS['history'][$id]['writes']++;return true;}
class FakeWpdb {
 public $prefix='wp_',$options='wp_options',$insert_id=0,$fail_receipt=false,$snapshot=null,$locks=0;
 function prepare($q,...$args){foreach($args as $arg){$q=preg_replace('/%[sd]/',is_numeric($arg)?(string)$arg:"'".$arg."'",$q,1);}return $q;}
 function get_col($q){return array('InnoDB','InnoDB');}
 function get_var($q){if(strpos($q,'GET_LOCK')!==false){$this->locks++;return 1;}if(strpos($q,'RELEASE_LOCK')!==false)return 1;if(preg_match("/option_name = '([^']+)'/",$q,$m))return isset($GLOBALS['options'][$m[1]])?serialize($GLOBALS['options'][$m[1]]):null;return null;}
 function query($q){if($q==='START TRANSACTION')$this->snapshot=array($GLOBALS['metrics'],$GLOBALS['options']);if($q==='ROLLBACK'&&$this->snapshot){list($GLOBALS['metrics'],$GLOBALS['options'])=$this->snapshot;}if($q==='COMMIT'||$q==='ROLLBACK')$this->snapshot=null;return true;}
 function insert($table,$data,$format=null){if($table===$this->options){if($this->fail_receipt){$this->fail_receipt=false;throw new Exception('synthetic interruption after metrics insert');}$GLOBALS['options'][$data['option_name']]=unserialize($data['option_value']);return 1;}return 1;}
 function get_row($q){$id=(int)preg_replace('/\D/','',$q);return array_intersect_key($GLOBALS['history'][$id],array('status'=>1,'end_time'=>1));}
}
$wpdb=new FakeWpdb(); require dirname(__DIR__).'/includes/synchronization/nmkr-sync-progress-tracking.php';
function check($ok,$message){if(!$ok){fwrite(STDERR,"FAIL: $message\n");exit(1);}echo "PASS: $message\n";}
$final_metrics=array('total_projects'=>1,'total_tokens'=>2,'total_sync_duration'=>2,'total_api_time'=>1,'average_response_time'=>.1,'api_requests'=>3,'memory_usage'=>4);
update_option('nmkr_sync_in_progress',false); $wpdb->fail_receipt=true;
$interrupted=nmkr_persist_sync_metrics_once(7,$final_metrics,'2026-01-02 03:04:05'); check($interrupted===false && count($GLOBALS['metrics'])===0,'interruption after metrics insertion rolls back metrics and receipt');
$receipt=nmkr_persist_sync_metrics_once(7,$final_metrics,'2026-01-02 03:04:05');
check($receipt && count($GLOBALS['metrics'])===1,'retry after interrupted insertion creates exactly one durable metrics row');
$contender=nmkr_persist_sync_metrics_once(7,$final_metrics,'2099-01-01 00:00:00');
check($contender===$receipt && count($GLOBALS['metrics'])===1 && $wpdb->locks===3,'serialized concurrent contender reuses the run-owned receipt');
check(isset($GLOBALS['options']['nmkr_sync_finalizing_7']),'run-owned metrics receipt remains durable against late contenders');
$GLOBALS['history'][7]=array('status'=>'initializing','end_time'=>null,'items_processed'=>0,'items_successful'=>0,'items_failed'=>0,'writes'=>0);
foreach(array(6=>'completed',5=>'success',4=>'error',3=>'cancelled') as $id=>$status)$GLOBALS['history'][$id]=array('status'=>$status,'end_time'=>'2025-01-01 00:00:00','writes'=>0);
$GLOBALS['options']=array_merge($GLOBALS['options'],array('nmkr_sync_data'=>array('status'=>'initializing','sync_stats_id'=>7,'all_tokens'=>array('work')),'nmkr_sync_in_progress'=>true,'nmkr_sync_near_completion'=>true,'nmkr_sync_heartbeat'=>123,'nmkr_sync_user_stopped'=>true));
$GLOBALS['transients']=array('nmkr_sync_in_progress'=>true,'nmkr_sync_user_stopped'=>true,'nmkr_current_sync_stats_live'=>$final_metrics,'nmkr_active_sync_metrics'=>array('input'=>true));
$GLOBALS['hooks']=array_fill_keys(array('nmkr_execute_sync_background','nmkr_process_batch_hook','nmkr_sync_cron_hook','nmkr_install_sync_cron_hook'),1);
$result=nmkr_sync_data_complete(true,'',array('metrics'=>$final_metrics,'items_processed'=>2,'items_successful'=>1,'items_failed'=>1,'items_skipped'=>0,'token_details_synced'=>1));
check($result && get_option('nmkr_sync_status')==='completed' && get_option('nmkr_sync_data')['status']==='completed','active sync data becomes canonically terminal');
check(count($GLOBALS['metrics'])===1 && get_option('nmkr_last_sync_time')===$GLOBALS['metrics'][0]['last_sync_time'],'one metric retains the authoritative timestamp');
check($GLOBALS['history'][7]['writes']===1 && $GLOBALS['history'][7]['items_processed']===2,'owned history completes once with authoritative counters');
check($GLOBALS['history'][6]['writes']===0&&$GLOBALS['history'][5]['writes']===0&&$GLOBALS['history'][4]['writes']===0&&$GLOBALS['history'][3]['writes']===0,'completed, success, error, and cancelled history remain immutable');
check(nmkr_sync_terminal_statuses()===array('completed','success','failed','error','stopped','cancelled','aborted'),'one authoritative terminal-status definition covers every supported outcome');
check(get_option('nmkr_sync_user_stopped')===false&&get_transient('nmkr_sync_user_stopped')===false,'success clears durable and transient user-stop state');
check(empty($GLOBALS['hooks'])&&!isset($GLOBALS['options']['nmkr_sync_near_completion'])&&!isset($GLOBALS['options']['nmkr_sync_heartbeat']),'all recognized hooks and active-only markers are removed');
$time=get_option('nmkr_last_sync_time');nmkr_sync_data_complete(true);check(count($GLOBALS['metrics'])===1&&$GLOBALS['history'][7]['writes']===1&&get_option('nmkr_last_sync_time')===$time,'repeated success preserves metrics, history, and timestamp');
$terminal=array('status'=>'completed','completed'=>true,'sync_stats_id'=>7);check(!nmkr_is_sync_canonically_finished($terminal,true,false,false),'durable active marker prevents finished');check(!nmkr_is_sync_canonically_finished($terminal,false,true,false),'transient active marker prevents finished');check(nmkr_is_sync_canonically_finished($terminal,false,false,false),'both inactive markers permit canonical finished');
$GLOBALS['options']['nmkr_sync_data']=array('status'=>'error','completed'=>true,'sync_stats_id'=>4,'end_time'=>'2025-01-01 00:00:00');nmkr_sync_data_complete(false,'again');check($GLOBALS['history'][4]['writes']===0&&get_option('nmkr_sync_data')['status']==='error','repeated failure preserves terminal history and outcome');
$ajax=file_get_contents(dirname(__DIR__).'/includes/synchronization/nmkr-sync-ajax-handlers.php');$js=file_get_contents(dirname(__DIR__).'/js/nmkr-sync-progress.js');check(strpos($ajax,'nmkr_is_sync_canonically_finished(')!==false&&strpos($ajax,"if (\$progress_int === 100) {\n        delete_transient('nmkr_current_sync_stats_live')")===false,'polling uses shared canonical predicate and is read-only for live metrics');check(strpos($js,'if (finished === true)')!==false&&strpos($js,'finished === true || validProgress === 100')===false,'frontend does not terminate from raw 100 percent progress');
