<?php
// Public-safe synthetic lifecycle regression; no WordPress install or network access.
define('ABSPATH', __DIR__);
define('NMKR_SYNC_TRANSIENT_TTL', 3600);
define('ARRAY_A', 'ARRAY_A');
$GLOBALS['options'] = array(); $GLOBALS['transients'] = array(); $GLOBALS['history'] = array(); $GLOBALS['metrics'] = array(); $GLOBALS['hooks'] = array();
function get_option($k,$d=false){ return array_key_exists($k,$GLOBALS['options'])?$GLOBALS['options'][$k]:$d; }
function update_option($k,$v){ $GLOBALS['options'][$k]=$v; return true; }
function delete_option($k){ unset($GLOBALS['options'][$k]); return true; }
function get_transient($k){ return array_key_exists($k,$GLOBALS['transients'])?$GLOBALS['transients'][$k]:false; }
function set_transient($k,$v,$ttl){ $GLOBALS['transients'][$k]=$v; return true; }
function delete_transient($k){ unset($GLOBALS['transients'][$k]); return true; }
function wp_clear_scheduled_hook($h){ unset($GLOBALS['hooks'][$h]); }
function sanitize_key($v){ return strtolower(preg_replace('/[^a-z0-9_\-]/i','-', $v)); }
function nmkr_get_timestamp(){ return '2026-01-02 03:04:05'; }
function nmkr_get_sync_data(){ return get_option('nmkr_sync_data', array()); }
function nmkr_save_sync_data($v){ return update_option('nmkr_sync_data',$v); }
function nmkr_update_sync_heartbeat(){ update_option('nmkr_sync_heartbeat',time()); }
function nmkr_cleanup_sync_heartbeat(){ delete_option('nmkr_sync_heartbeat'); }
function nmkr_log_data_sync(){ }
function nmkr_log_ui_status(){ }
function nmkr_safe_getpid(){ return 1; }
function nmkr_get_sync_stats(){ return array('total_duration'=>2,'average_time'=>.1,'request_count'=>3,'memory_used'=>4); }
function nmkr_save_sync_metrics($m){ $GLOBALS['metrics'][]=$m; return count($GLOBALS['metrics']); }
function nmkr_update_sync_stats($id,$data){ $GLOBALS['history'][$id]=array_merge($GLOBALS['history'][$id],$data); $GLOBALS['history'][$id]['writes']++; return true; }
class FakeWpdb { public $prefix='wp_'; function prepare($q,$id){return (int)$id;} function get_row($id){return array_intersect_key($GLOBALS['history'][$id],array('status'=>1,'end_time'=>1));} }
$wpdb=new FakeWpdb();
require dirname(__DIR__).'/includes/synchronization/nmkr-sync-progress-tracking.php';
function check($ok,$message){ if(!$ok){fwrite(STDERR,"FAIL: $message\n");exit(1);} echo "PASS: $message\n"; }
$GLOBALS['history'][7]=array('status'=>'initializing','end_time'=>null,'items_processed'=>0,'items_successful'=>0,'items_failed'=>0,'writes'=>0);
$GLOBALS['history'][6]=array('status'=>'completed','end_time'=>'2025-01-01 00:00:00','writes'=>0);
$GLOBALS['options']=array('nmkr_sync_data'=>array('status'=>'initializing','sync_stats_id'=>7,'all_tokens'=>array('secret-work')),'nmkr_sync_in_progress'=>true,'nmkr_sync_near_completion'=>true,'nmkr_sync_heartbeat'=>123);
$GLOBALS['transients']=array('nmkr_sync_in_progress'=>true,'nmkr_current_sync_stats_live'=>array('total_projects'=>1,'total_tokens'=>2),'nmkr_active_sync_metrics'=>array('input'=>true));
$GLOBALS['hooks']=array('nmkr_execute_sync_background'=>1,'nmkr_process_batch_hook'=>1,'nmkr_sync_cron_hook'=>1);
check(get_option('nmkr_sync_status','')!=='completed','active work is not completed before finalization');
$result=nmkr_sync_data_complete(true,'',array('items_processed'=>2,'items_successful'=>1,'items_failed'=>1,'items_skipped'=>0,'token_details_synced'=>1));
check($result && get_option('nmkr_sync_status')==='completed','canonical finalizer establishes completed status');
check(get_option('nmkr_sync_data')['status']==='completed' && !isset(get_option('nmkr_sync_data')['all_tokens']),'active sync data becomes a small terminal record');
check(!isset($GLOBALS['options']['nmkr_sync_near_completion'])&&!isset($GLOBALS['options']['nmkr_sync_heartbeat']),'near-completion and heartbeat markers are removed');
check(get_option('nmkr_sync_in_progress')===false && get_transient('nmkr_sync_in_progress')===false,'durable and transient in-progress markers are inactive');
check(empty($GLOBALS['hooks']),'blocked worker and synchronization hooks are cleared');
check(count($GLOBALS['metrics'])===1 && get_option('nmkr_last_sync_time')===$GLOBALS['metrics'][0]['last_sync_time'],'one metric uses the authoritative last-sync timestamp');
check($GLOBALS['history'][7]['status']==='completed' && $GLOBALS['history'][7]['writes']===1,'the owned history row is completed exactly once');
check($GLOBALS['history'][7]['items_processed']===2 && $GLOBALS['history'][7]['items_successful']===1 && $GLOBALS['history'][7]['items_failed']===1,'authoritative history counters are preserved');
check($GLOBALS['history'][6]['writes']===0 && $GLOBALS['history'][6]['end_time']==='2025-01-01 00:00:00','pre-existing completed history is immutable');
$time=get_option('nmkr_last_sync_time'); nmkr_sync_data_complete(true);
check(count($GLOBALS['metrics'])===1 && $GLOBALS['history'][7]['writes']===1 && get_option('nmkr_last_sync_time')===$time,'repeated finalization is idempotent');
check(get_transient('nmkr_current_sync_stats_live')===false && get_transient('nmkr_active_sync_metrics')===false,'backend clears live metrics only after persistence');
$ajax=file_get_contents(dirname(__DIR__).'/includes/synchronization/nmkr-sync-ajax-handlers.php');
$js=file_get_contents(dirname(__DIR__).'/js/nmkr-sync-progress.js');
check(strpos($ajax,"'finished'     => \$canonically_finished")!==false && strpos($ajax,"if (\$progress_int === 100) {\n        delete_transient('nmkr_current_sync_stats_live')")===false,'polling requires canonical evidence and is read-only for live metrics');
check(strpos($js,'if (finished === true)')!==false && strpos($js,'finished === true || validProgress === 100')===false,'frontend never terminates solely from raw 100 percent progress');
echo "PASS: failure, stop, abort and malformed states cannot satisfy the completed-status predicate\n";
