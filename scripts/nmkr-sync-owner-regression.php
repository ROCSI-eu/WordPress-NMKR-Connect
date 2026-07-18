<?php
// Public-safe deterministic ownership regression; no WordPress or network access.
define('ABSPATH', __DIR__);
$GLOBALS['options'] = array();
$GLOBALS['transients'] = array();
function __($s) { return $s; }
class WP_Error { private $c; private $m; function __construct($c,$m){$this->c=$c;$this->m=$m;} function get_error_code(){return $this->c;} function get_error_message(){return $this->m;} }
function is_wp_error($v){return $v instanceof WP_Error;}
function get_current_blog_id(){return 1;}
function get_option($k,$d=false){return array_key_exists($k,$GLOBALS['options'])?$GLOBALS['options'][$k]:$d;}
function add_option($k,$v,$deprecated='',$autoload='yes'){if(array_key_exists($k,$GLOBALS['options']))return false;$GLOBALS['options'][$k]=$v;$GLOBALS['autoload'][$k]=$autoload;return true;}
function update_option($k,$v,$autoload=null){$GLOBALS['options'][$k]=$v;if($autoload!==null)$GLOBALS['autoload'][$k]=$autoload;return true;}
function delete_option($k){unset($GLOBALS['options'][$k]);return true;}
function set_transient($k,$v,$ttl=0){$GLOBALS['transients'][$k]=$v;return true;} function get_transient($k){return $GLOBALS['transients'][$k]??false;} function delete_transient($k){unset($GLOBALS['transients'][$k]);return true;}
function add_action(){}
function check_ajax_referer(){}
function current_user_can(){return true;}
function wp_send_json_error($data,$status=null){throw new Exception(json_encode(array('data'=>$data,'status'=>$status)));}
function nmkr_sync_start_blocked_by_finalization($d){return ($d['status']??'')==='finalizing';}
class FakeWpdb {public $locks=0,$fail_lock=false;function prepare($q,...$a){return $q;}function get_var($q){if(strpos($q,'GET_LOCK')!==false){$this->locks++;return $this->fail_lock?0:1;}return 1;}}
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
$GLOBALS['options']['nmkr_sync_data']=array('status'=>'finalizing');check(is_wp_error(nmkr_admit_sync_owner($b)),'ownerless legacy finalization blocks admission atomically');
$ajax=file_get_contents(dirname(__DIR__).'/includes/synchronization/nmkr-sync-ajax-handlers.php');
check(strpos($ajax,"function nmkr_execute_sync_background_job(\$run_id = '')")!==false&&strpos($ajax,"wp_schedule_single_event(time(), 'nmkr_execute_sync_background', \$event_args)")!==false,'direct events and callback carry run ID');
check(strpos($ajax,"nmkr_clear_sync_jobs('sync_start', true)")===false,'direct startup has no destructive global cleanup');
check(strpos($ajax,"'batch_mode_unsupported'")!==false,'direct owner cannot inject legacy batch worker');
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
