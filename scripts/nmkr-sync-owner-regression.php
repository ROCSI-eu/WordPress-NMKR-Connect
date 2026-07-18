<?php
// Public-safe deterministic ownership regression; no WordPress or network access.
define('ABSPATH', __DIR__);
$GLOBALS['options'] = array();
function __($s) { return $s; }
class WP_Error { private $c; private $m; function __construct($c,$m){$this->c=$c;$this->m=$m;} function get_error_code(){return $this->c;} function get_error_message(){return $this->m;} }
function is_wp_error($v){return $v instanceof WP_Error;}
function get_current_blog_id(){return 1;}
function get_option($k,$d=false){return array_key_exists($k,$GLOBALS['options'])?$GLOBALS['options'][$k]:$d;}
function add_option($k,$v,$deprecated='',$autoload='yes'){if(array_key_exists($k,$GLOBALS['options']))return false;$GLOBALS['options'][$k]=$v;$GLOBALS['autoload'][$k]=$autoload;return true;}
function update_option($k,$v,$autoload=null){$GLOBALS['options'][$k]=$v;if($autoload!==null)$GLOBALS['autoload'][$k]=$autoload;return true;}
function delete_option($k){unset($GLOBALS['options'][$k]);return true;}
function nmkr_sync_start_blocked_by_finalization($d){return ($d['status']??'')==='finalizing';}
class FakeWpdb {public $locks=0;function prepare($q,...$a){return $q;}function get_var($q){if(strpos($q,'GET_LOCK')!==false){$this->locks++;return 1;}return 1;}}
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
$GLOBALS['options']['nmkr_sync_data']=array('status'=>'finalizing');check(is_wp_error(nmkr_admit_sync_owner($b)),'ownerless legacy finalization blocks admission atomically');
$ajax=file_get_contents(dirname(__DIR__).'/includes/synchronization/nmkr-sync-ajax-handlers.php');
check(strpos($ajax,"function nmkr_execute_sync_background_job(\$run_id = '')")!==false&&strpos($ajax,"wp_schedule_single_event(time(), 'nmkr_execute_sync_background', \$event_args)")!==false,'direct events and callback carry run ID');
check(strpos($ajax,"nmkr_clear_sync_jobs('sync_start', true)")===false,'direct startup has no destructive global cleanup');
check(strpos($ajax,"'batch_mode_unsupported'")!==false,'direct owner cannot inject legacy batch worker');
$progress=file_get_contents(dirname(__DIR__).'/includes/synchronization/nmkr-sync-progress-tracking.php');check(strpos($progress,"wp_clear_scheduled_hook('nmkr_execute_sync_background', array(\$run_id))")!==false,'finalizer clears only exact direct event');
echo "All synchronization ownership regression checks passed.\n";
