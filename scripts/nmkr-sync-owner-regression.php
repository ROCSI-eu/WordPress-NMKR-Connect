<?php
// Public-safe deterministic ownership regression; no WordPress or network access.
define('ABSPATH', __DIR__);
define('ARRAY_A', 'ARRAY_A'); define('HOUR_IN_SECONDS',3600);
$GLOBALS['options'] = array();
$GLOBALS['transients'] = array();
$GLOBALS['history'] = array();
function __($s) { return $s; }
class WP_Error { private $c; private $m; function __construct($c,$m){$this->c=$c;$this->m=$m;} function get_error_code(){return $this->c;} function get_error_message(){return $this->m;} }
function is_wp_error($v){return $v instanceof WP_Error;}
function get_current_blog_id(){return 1;}
function get_option($k,$d=false){return array_key_exists($k,$GLOBALS['options'])?$GLOBALS['options'][$k]:$d;}
function add_option($k,$v,$deprecated='',$autoload='yes'){if(array_key_exists($k,$GLOBALS['options']))return false;$GLOBALS['options'][$k]=$v;$GLOBALS['autoload'][$k]=$autoload;return true;}
function update_option($k,$v,$autoload=null){$GLOBALS['options'][$k]=$v;if($autoload!==null)$GLOBALS['autoload'][$k]=$autoload;return true;}
function delete_option($k){unset($GLOBALS['options'][$k]);return true;}
function nmkr_is_sync_terminal_status($s){return in_array($s,array('completed','failed','error'),true);} function nmkr_is_valid_sync_end_time($v){return !empty($v);} function current_time(){return '2026-01-02 03:04:05';} function nmkr_update_sync_stats($id,$data){if(!empty($GLOBALS['fail_history_update']))return false;$GLOBALS['history'][$id]=array_merge($GLOBALS['history'][$id]??array(),$data);$GLOBALS['history'][$id]['writes']=($GLOBALS['history'][$id]['writes']??0)+1;return true;}
function set_transient($k,$v,$ttl=0){$GLOBALS['transients'][$k]=$v;return true;} function get_transient($k){return $GLOBALS['transients'][$k]??false;} function delete_transient($k){unset($GLOBALS['transients'][$k]);return true;}
function add_action(){}
function check_ajax_referer(){}
function current_user_can(){return true;}
function wp_send_json_error($data,$status=null){throw new Exception(json_encode(array('data'=>$data,'status'=>$status)));}
function nmkr_maintain_sync_finalization_resume($d){$GLOBALS['maintained']=true;return true;}
function nmkr_sync_start_blocked_by_finalization($d){return ($d['status']??'')==='finalizing';}
class FakeWpdb {public $locks=0,$fail_lock=false,$prefix='wp_',$options='wp_options';function prepare($q,...$a){foreach($a as $v)$q=preg_replace('/%[sd]/',(string)$v,$q,1);return $q;}function get_var($q){if(strpos($q,'GET_LOCK')!==false){$this->locks++;return $this->fail_lock?0:1;}return 1;}function get_row($q){$id=(int)preg_replace('/^.*id = (\d+).*$/','$1',$q);return isset($GLOBALS['history'][$id])?array_merge(array('id'=>$id,'end_time'=>'2026-01-02 03:04:05'),$GLOBALS['history'][$id]):null;}}
$wpdb=new FakeWpdb();
require dirname(__DIR__).'/includes/helpers/nmkr-utility-functions.php';
function check($ok,$m){if(!$ok){fwrite(STDERR,"FAIL: $m\n");exit(1);}echo "PASS: $m\n";}
$a='11111111-1111-4111-8111-111111111111';$b='22222222-2222-4222-8222-222222222222';
$first=nmkr_admit_sync_owner($a);$snapshot=nmkr_get_sync_owner();$second=nmkr_admit_sync_owner($b);
check(is_array($first)&&is_wp_error($second)&&$second->get_error_code()==='sync_already_owned'&&nmkr_get_sync_owner()===$snapshot,'two admissions retain exactly one owner');
check(($GLOBALS['autoload']['nmkr_sync_owner']??'')==='no','owner option is non-autoloaded');
$claim1=nmkr_transition_sync_owner($a,'queued','running');$claim2=nmkr_transition_sync_owner($a,'queued','running');
check(is_array($claim1)&&$claim2===false,'duplicate callback permits one queued-to-running claim');
check(nmkr_transition_sync_owner($b,'running','running',7)===false&&nmkr_get_sync_owner()['run_id']===$a,'stale callback cannot mutate current owner');
check(nmkr_transition_sync_owner($a,'running','running',7)&&nmkr_get_sync_owner()['sync_stats_id']===7,'history ID binds to exact owner');
check(nmkr_release_sync_owner($a,8)===false&&nmkr_get_sync_owner()['run_id']===$a,'release requires matching history ID');
check(nmkr_transition_sync_owner($a,'running','finalizing',7)&&is_wp_error(nmkr_admit_sync_owner($b)),'finalizing owner blocks admission');
check(nmkr_release_sync_owner($b,7)===false&&nmkr_release_sync_owner($a,7)===true,'only exact finalizing owner releases');
$wpdb->fail_lock=true;$transition_error=nmkr_transition_sync_owner($b,'queued','running');$release_error=nmkr_release_sync_owner($b,0,'queued');$wpdb->fail_lock=false;
check(is_wp_error($transition_error)&&!nmkr_sync_owner_transition_succeeded($transition_error),'transition WP_Error is never successful');
check(is_wp_error($release_error)&&$release_error!==true,'release WP_Error is never successful');
$GLOBALS['options']=array();$GLOBALS['transients']=array('nmkr_sync_in_progress'=>true,'nmkr_sync_progress'=>1,'nmkr_sync_current_item'=>'init');nmkr_admit_sync_owner($a);nmkr_transition_sync_owner($a,'queued','running');update_option('nmkr_sync_in_progress',true);
check(nmkr_cleanup_failed_direct_sync($a,0,'missing API key')===true&&!get_option('nmkr_sync_in_progress')&&!get_transient('nmkr_sync_in_progress')&&!get_transient('nmkr_sync_progress'),'early configuration failure clears option and transient markers after exact release');
$GLOBALS['options']=array();$GLOBALS['transients']=array('nmkr_sync_in_progress'=>true);nmkr_admit_sync_owner($a);nmkr_transition_sync_owner($a,'queued','running');update_option('nmkr_sync_in_progress',true);$wpdb->fail_lock=true;
check(nmkr_cleanup_failed_direct_sync($a,0,'history initialization failed')===false&&is_array(nmkr_get_sync_owner())&&!get_option('nmkr_sync_in_progress')&&!get_transient('nmkr_sync_in_progress'),'failed exact release retains owner but clears false-active dashboard markers');$wpdb->fail_lock=false;
$GLOBALS['options']=array('nmkr_sync_owner'=>array('run_id'=>$b,'mode'=>'direct','state'=>'queued','sync_stats_id'=>0));check(nmkr_cleanup_failed_direct_sync($a,0,'stale')===false&&nmkr_get_sync_owner()['run_id']===$b,'failed stale cleanup never removes successor owner');
$GLOBALS['options']=array();$GLOBALS['transients']=array('nmkr_sync_in_progress'=>true);nmkr_admit_sync_owner($a);nmkr_transition_sync_owner($a,'queued','running');update_option('nmkr_sync_in_progress',true);$GLOBALS['history'][77]=array('id'=>77,'status'=>'initializing','end_time'=>null);$binding_error=new WP_Error('sync_owner_lock_unavailable','lock');nmkr_handle_sync_owner_binding_failure($binding_error,$a,77);check(($GLOBALS['history'][77]['status']??'')==='failed'&&nmkr_get_sync_owner()===false&&!get_option('nmkr_sync_in_progress')&&!get_transient('nmkr_sync_in_progress'),'binding WP_Error fails history and releases exact unbound owner');
$GLOBALS['options']=array();$GLOBALS['transients']=array('nmkr_sync_in_progress'=>true);nmkr_admit_sync_owner($a);nmkr_transition_sync_owner($a,'queued','running');update_option('nmkr_sync_in_progress',true);$GLOBALS['history'][79]=array('id'=>79,'status'=>'initializing','end_time'=>null);$wpdb->fail_lock=true;nmkr_handle_sync_owner_binding_failure($binding_error,$a,79);$wpdb->fail_lock=false;check(($GLOBALS['history'][79]['status']??'')==='failed'&&is_array(nmkr_get_sync_owner())&&!get_option('nmkr_sync_in_progress')&&!get_transient('nmkr_sync_in_progress'),'binding release WP_Error retains ID-zero owner with inactive dashboard markers');
$GLOBALS['options']=array('nmkr_sync_owner'=>array('run_id'=>$b,'mode'=>'direct','state'=>'running','sync_stats_id'=>9),'nmkr_sync_in_progress'=>true);$before=$GLOBALS['options'];$GLOBALS['history'][78]=array('id'=>78,'status'=>'initializing','end_time'=>null);nmkr_handle_sync_owner_binding_failure(false,$a,78);check(($GLOBALS['history'][78]['status']??'')==='failed'&&$GLOBALS['options']===$before,'binding comparison failure leaves successor state untouched');
foreach(array('missing','terminal','update_failed') as $case){$GLOBALS['options']=array('nmkr_sync_owner'=>array('run_id'=>$b,'mode'=>'direct','state'=>'running','sync_stats_id'=>9),'nmkr_sync_in_progress'=>true,'nmkr_sync_status'=>'running');$GLOBALS['transients']=array('nmkr_sync_in_progress'=>true,'nmkr_sync_progress'=>44);unset($GLOBALS['history'][82]);if($case==='terminal')$GLOBALS['history'][82]=array('id'=>82,'status'=>'completed','end_time'=>'2026-01-02 03:04:05');if($case==='update_failed'){$GLOBALS['history'][82]=array('id'=>82,'status'=>'initializing','end_time'=>null);$GLOBALS['fail_history_update']=true;}$before=array($GLOBALS['options'],$GLOBALS['transients']);nmkr_handle_sync_owner_binding_failure(false,$a,82);unset($GLOBALS['fail_history_update']);check($before===array($GLOBALS['options'],$GLOBALS['transients']),'successor lifecycle survives orphan history '.$case);}
check(nmkr_sync_owner_lock_name('wp_options',1,'install_a')!==nmkr_sync_owner_lock_name('wp_options',1,'install_b'),'same blog ID in different installations has distinct lock namespace');check(nmkr_sync_owner_lock_name('wp_options',1,'install_a')!==nmkr_sync_owner_lock_name('wp_2_options',2,'install_a'),'multisite blogs have distinct lock namespace');
$GLOBALS['options']=array();$GLOBALS['transients']=array('nmkr_sync_in_progress'=>true,'nmkr_sync_progress'=>3);nmkr_admit_sync_owner($a);update_option('nmkr_sync_in_progress',true);$wpdb->fail_lock=true;$queued_cleanup=nmkr_cleanup_failed_queued_sync($a);$wpdb->fail_lock=false;check(is_wp_error($queued_cleanup)&&nmkr_sync_owner_matches($a,'queued',0)&&get_option('nmkr_sync_in_progress')===true&&get_transient('nmkr_sync_in_progress')===true&&get_transient('nmkr_sync_progress')===3,'queued cleanup lock failure performs no unlocked lifecycle mutation');
$GLOBALS['options']=array('nmkr_sync_owner'=>array('run_id'=>$b,'mode'=>'direct','state'=>'queued','sync_stats_id'=>0),'nmkr_sync_in_progress'=>true);$before=$GLOBALS['options'];check(nmkr_cleanup_failed_queued_sync($a)===false&&$GLOBALS['options']===$before,'queued rollback leaves successor state untouched');
$GLOBALS['options']=array();$GLOBALS['transients']=array('nmkr_sync_in_progress'=>true);nmkr_admit_sync_owner($a);nmkr_transition_sync_owner($a,'queued','running');$GLOBALS['history'][80]=array('id'=>80,'status'=>'initializing','end_time'=>null);$GLOBALS['fail_history_update']=true;$history_error=nmkr_handle_sync_owner_binding_failure($binding_error,$a,80);unset($GLOBALS['fail_history_update']);check(is_wp_error($history_error)&&nmkr_sync_owner_matches($a,'running',0)&&get_option('nmkr_sync_status')==='history_cleanup_error','failed orphan terminalization retains fail-closed owner');
foreach(array('queued','running') as $owner_state){$GLOBALS['options']=array('nmkr_sync_owner'=>array('run_id'=>$a,'mode'=>'direct','state'=>$owner_state,'sync_stats_id'=>0),'nmkr_sync_in_progress'=>true,'nmkr_sync_heartbeat'=>1,'nmkr_last_progress_update_time'=>1,'nmkr_sync_data'=>array('run_id'=>$a,'status'=>$owner_state));$GLOBALS['transients']=array('nmkr_sync_progress'=>20);$before=array($GLOBALS['options'],$GLOBALS['transients']);$result=nmkr_detect_and_recover_stale_sync();unset($GLOBALS['transients']['nmkr_stale_recovery_running']);check(!empty($result['owner_preserved'])&&$before===array($GLOBALS['options'],$GLOBALS['transients']),'stale recovery preserves '.$owner_state.' owner lifecycle byte-for-byte');}
$GLOBALS['maintained']=false;$GLOBALS['options']=array('nmkr_sync_owner'=>array('run_id'=>$a,'mode'=>'direct','state'=>'finalizing','sync_stats_id'=>81),'nmkr_sync_in_progress'=>true,'nmkr_sync_heartbeat'=>1,'nmkr_last_progress_update_time'=>1,'nmkr_sync_data'=>array('run_id'=>$a,'status'=>'finalizing','sync_stats_id'=>81));$GLOBALS['transients']=array();$result=nmkr_detect_and_recover_stale_sync();check(!empty($result['owner_preserved'])&&!empty($GLOBALS['maintained']),'stale recovery only maintains exact finalizing owner evidence');
$GLOBALS['options']=array('nmkr_sync_in_progress'=>true,'nmkr_sync_heartbeat'=>1,'nmkr_last_progress_update_time'=>1);$GLOBALS['transients']=array();$legacy=nmkr_detect_and_recover_stale_sync();check(!empty($legacy['recovered'])&&!get_option('nmkr_sync_in_progress'),'ownerless stale recovery retains legacy cleanup behavior');
$GLOBALS['options']['nmkr_sync_data']=array('status'=>'finalizing');check(is_wp_error(nmkr_admit_sync_owner($b)),'ownerless legacy finalization blocks admission atomically');
$ajax=file_get_contents(dirname(__DIR__).'/includes/synchronization/nmkr-sync-ajax-handlers.php');
check(strpos($ajax,"function nmkr_execute_sync_background_job(\$run_id = '')")!==false&&strpos($ajax,"wp_schedule_single_event(time(), 'nmkr_execute_sync_background', \$event_args)")!==false,'direct events and callback carry run ID');
check(strpos($ajax,"nmkr_clear_sync_jobs('sync_start', true)")===false,'direct startup has no destructive global cleanup');
check(strpos($ajax,"'batch_mode_unsupported'")!==false,'direct owner cannot inject legacy batch worker');check(strpos($ajax,"&& !\$owner_managed_recovery")!==false,'explicit progress recovery is fenced whenever an owner exists');
$progress=file_get_contents(dirname(__DIR__).'/includes/synchronization/nmkr-sync-progress-tracking.php');check(strpos($progress,"wp_clear_scheduled_hook('nmkr_execute_sync_background', array(\$run_id))")!==false,'finalizer clears only exact direct event');
$batch_source=file_get_contents(dirname(__DIR__).'/includes/synchronization/nmkr-sync-batch-processing.php');
$batch_start=strpos($batch_source,'function nmkr_process_next_batch()');$brace=strpos($batch_source,'{',$batch_start);$depth=0;$batch_end=$brace;
for($i=$brace,$n=strlen($batch_source);$i<$n;$i++){if($batch_source[$i]==='{')$depth++;if($batch_source[$i]==='}'&&--$depth===0){$batch_end=$i+1;break;}}
eval(substr($batch_source,$batch_start,$batch_end-$batch_start));
foreach (array(false,array('mode'=>'direct'),array('mode'=>'broken'),array('mode'=>'batch')) as $owner_fixture) {
    $GLOBALS['options']=array('nmkr_sync_data'=>array('sentinel'=>'unchanged'),'nmkr_sync_owner'=>$owner_fixture);
    $before=$GLOBALS['options'];nmkr_process_next_batch();check($GLOBALS['options']===$before,'legacy batch callback is inert for every owner-shaped fixture');
}
$ajax_start=strpos($ajax,'function nmkr_restart_sync_batch_handler()');$ajax_brace=strpos($ajax,'{',$ajax_start);$depth=0;$ajax_end=$ajax_brace;
for($i=$ajax_brace,$n=strlen($ajax);$i<$n;$i++){if($ajax[$i]==='{')$depth++;if($ajax[$i]==='}'&&--$depth===0){$ajax_end=$i+1;break;}}
eval(substr($ajax,$ajax_start,$ajax_end-$ajax_start));
foreach (array(false,array('mode'=>'direct'),array('mode'=>'broken'),array('mode'=>'batch')) as $owner_fixture) {
    $GLOBALS['options']['nmkr_sync_owner']=$owner_fixture;$conflict=false;try{nmkr_restart_sync_batch_handler();}catch(Exception $e){$payload=json_decode($e->getMessage(),true);$conflict=($payload['status']??0)===409&&($payload['data']['error_code']??'')==='batch_mode_unsupported';}
    check($conflict,'batch restart always conflicts after authorization');
}
$uninstall=file_get_contents(dirname(__DIR__).'/nmkr-connect.php');check(strpos($uninstall,"'nmkr_sync_owner',")!==false,'full uninstall includes owner cleanup');
echo "All synchronization ownership regression checks passed.\n";
