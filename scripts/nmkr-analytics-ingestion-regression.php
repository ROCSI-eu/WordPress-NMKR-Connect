<?php
/* Public-safe deterministic regression for the analytics-ingestion contracts. */
define('ABSPATH', __DIR__ . '/');
define('AUTH_SALT', 'synthetic-public-safe-salt');
$GLOBALS['options_store'] = array();
$GLOBALS['transients'] = array();
$GLOBALS['mode'] = 'custom';
$GLOBALS['consent_setting'] = 0;
$GLOBALS['insert_ok'] = true;
$GLOBALS['inserts'] = 0;
$GLOBALS['ga4'] = 0;
$GLOBALS['now_mysql'] = '2026-01-01 00:00:00';
$GLOBALS['rate_lock_available'] = true;
$GLOBALS['rate_lock_held'] = false;
$GLOBALS['storage_write_fail'] = false;
$GLOBALS['storage_write_fail_name'] = null;
$GLOBALS['before_add'] = null;
$GLOBALS['before_cas'] = null;
$GLOBALS['add_fail'] = false;
class WP_REST_Response { private $status; public function __construct($body=null,$status=200){$this->status=$status;} public function get_status(){return $this->status;} }
class FakeWpdb {
    public $options='wp_options'; public $prefix='wp_';
    public function prepare($sql,...$args){return array($sql,$args);}
    public function get_var($q){if(strpos($q[0],'GET_LOCK')!==false){if(!$GLOBALS['rate_lock_available']||$GLOBALS['rate_lock_held'])return '0';$GLOBALS['rate_lock_held']=true;return '1';}if(strpos($q[0],'RELEASE_LOCK')!==false){$GLOBALS['rate_lock_held']=false;return '1';}$n=$q[1][0];return array_key_exists($n,$GLOBALS['options_store'])?maybe_serialize($GLOBALS['options_store'][$n]):null;}
    public function query($q){$sql=$q[0];$a=$q[1];$n=strpos($sql,'UPDATE ')===0?$a[1]:$a[0];$old=strpos($sql,'UPDATE ')===0?($a[2]??null):($a[1]??null);if(strpos($sql,'UPDATE ')===0){if($GLOBALS['storage_write_fail']||$GLOBALS['storage_write_fail_name']===$n)return false;if(is_callable($GLOBALS['before_cas'])){$hook=$GLOBALS['before_cas'];$GLOBALS['before_cas']=null;$hook($n);}if(!isset($GLOBALS['options_store'][$n])||(null!==$old&&maybe_serialize($GLOBALS['options_store'][$n])!==$old))return 0;$GLOBALS['options_store'][$n]=maybe_unserialize($a[0]);return 1;}if(strpos($sql,'DELETE ')===0){if(!isset($GLOBALS['options_store'][$n])||maybe_serialize($GLOBALS['options_store'][$n])!==$old)return 0;unset($GLOBALS['options_store'][$n]);return 1;}return false;}
    public function insert($table,$data,$formats){$GLOBALS['inserts']++;return $GLOBALS['insert_ok']?1:false;}
}
$GLOBALS['wpdb']=new FakeWpdb();
function maybe_serialize($v){return serialize($v);} function maybe_unserialize($v){return unserialize($v);} function add_option($n,$v,$d='',$a=false){if(is_callable($GLOBALS['before_add'])){$hook=$GLOBALS['before_add'];$GLOBALS['before_add']=null;$hook($n,$v);}if($GLOBALS['add_fail']||isset($GLOBALS['options_store'][$n]))return false;$GLOBALS['options_store'][$n]=$v;return true;}
function wp_cache_delete($n,$g){return true;} function get_transient($n){return $GLOBALS['transients'][$n]??false;} function set_transient($n,$v,$ttl){$GLOBALS['transients'][$n]=$v;return true;}
function wp_json_encode($v){return json_encode($v);} function sanitize_key($v){return preg_replace('/[^a-z0-9_\-]/','',strtolower($v));} function sanitize_text_field($v){return trim(strip_tags($v));}
function get_option($n,$d=array()){if($n==='nmkr_connect_options')return array('analytics_mode'=>$GLOBALS['mode'],'analytics_sample_rate'=>1,'analytics_track_logged_in'=>0,'analytics_require_consent'=>$GLOBALS['consent_setting'],'nmkr_ga4_measurement_id'=>'G-PUBLIC','nmkr_ga4_api_secret'=>'synthetic');return $d;}
function is_user_logged_in(){return false;} function home_url(){return 'https://example.invalid';} function current_time($t,$gmt=0){return $GLOBALS['now_mysql'];} function get_current_user_id(){return 0;}
function nmkr_ga4_send_event($a,$b,$c,$d,array $e){$GLOBALS['ga4']++;}
require dirname(__DIR__).'/includes/helpers/nmkr-analytics-helpers.php';
function check($ok,$label){if(!$ok){fwrite(STDERR,"FAIL: ".$label."\n");exit(1);}}
function reset_state(){ $GLOBALS['options_store']=array();$GLOBALS['transients']=array();$GLOBALS['inserts']=0;$GLOBALS['ga4']=0;$GLOBALS['insert_ok']=true;$GLOBALS['rate_lock_available']=true;$GLOBALS['rate_lock_held']=false;$GLOBALS['storage_write_fail']=false;$GLOBALS['storage_write_fail_name']=null;$GLOBALS['add_fail']=false;$GLOBALS['before_add']=null;$GLOBALS['before_cas']=null;$_SERVER=array('HTTP_HOST'=>'example.invalid','REMOTE_ADDR'=>'192.0.2.1');$_COOKIE=array(); }
function payload(){return array('event_type'=>'view','shortcode'=>'grid','project_uid'=>str_repeat('p',64),'token_uid'=>str_repeat('t',64),'element_id'=>str_repeat('e',128),'session_id'=>'123e4567-e89b-42d3-a456-426614174000','meta'=>array('lang'=>'en'));}

reset_state(); $p=payload();
$dedupe_key=nmkr_analytics_state_key('dedupe',array($p['session_id'],$p['element_id'],'view'));
$GLOBALS['before_add']=function($name){$GLOBALS['options_store'][$name]=array('token'=>'competing-token','status'=>'claim','expires'=>PHP_INT_MAX);};
$c1=nmkr_analytics_dedupe_claim($p['session_id'],$p['element_id'],'view');
check($c1['token']===false&&$GLOBALS['options_store'][$dedupe_key]['token']==='competing-token','controlled competing add admits one owner');
$c2=nmkr_analytics_dedupe_claim($p['session_id'],$p['element_id'],'view');
check($c2['token']===false,'active competing claim remains exclusive');
$GLOBALS['options_store'][$dedupe_key]=array('token'=>'stale-token','status'=>'claim','expires'=>90);
$GLOBALS['before_cas']=function($name){$GLOBALS['options_store'][$name]=array('token'=>'takeover-winner','status'=>'claim','expires'=>130);};
check(nmkr_analytics_claim_acquire($dedupe_key,30,100)===false&&$GLOBALS['options_store'][$dedupe_key]['token']==='takeover-winner','controlled stale takeover CAS preserves winner');
check(nmkr_analytics_claim_release($dedupe_key,'stale-token')===false&&nmkr_analytics_claim_finalize($dedupe_key,'stale-token',60,100)===false,'token ownership rejects stale worker');
$GLOBALS['options_store'][$dedupe_key]=array('token'=>'expired-token','status'=>'claim','expires'=>90);
$GLOBALS['storage_write_fail']=true;
check(nmkr_analytics_claim_acquire($dedupe_key,30,100)===null,'stale takeover database failure is unavailable');
$GLOBALS['storage_write_fail']=false;
$GLOBALS['options_store'][$dedupe_key]=array('token'=>'malformed-token','expires'=>PHP_INT_MAX);
$repaired_future=nmkr_analytics_claim_acquire($dedupe_key,30,100);
check(is_string($repaired_future)&&$GLOBALS['options_store'][$dedupe_key]['status']==='claim'&&$GLOBALS['options_store'][$dedupe_key]['token']===$repaired_future,'malformed future claim is CAS repaired');
$GLOBALS['options_store'][$dedupe_key]=array('token'=>'malformed-token','status'=>'claim');
$repaired_no_expiry=nmkr_analytics_claim_acquire($dedupe_key,30,100);
check(is_string($repaired_no_expiry)&&$GLOBALS['options_store'][$dedupe_key]['expires']===130,'malformed claim without expiry is CAS repaired');
$c3=nmkr_analytics_dedupe_claim($p['session_id'],'independent','view'); check($c3['token']!==false,'independent tuple');
check(strlen($dedupe_key)===strlen(nmkr_analytics_state_key('dedupe',array('a','b','view')))&&strlen($dedupe_key)<96&&strpos($dedupe_key,$p['session_id'])===false,'fixed bounded key');
reset_state(); $ip=str_repeat('a',64); for($i=0;$i<3;$i++)check(nmkr_analytics_rate_limit_outcome($ip,10,3,100)==='allowed','threshold pass'); check(nmkr_analytics_rate_limit_outcome($ip,10,3,100)==='quota','threshold exact quota');
check($GLOBALS['options_store'][nmkr_analytics_state_key('rate',array($ip))]['count']===4,'durable increments retained'); check(nmkr_analytics_rate_limit_outcome($ip,10,3,110)==='allowed','rollover');
check(nmkr_analytics_rate_limit_outcome(str_repeat('b',64),10,1,110)==='allowed','independent ip');
$GLOBALS['rate_lock_held']=true;check(nmkr_analytics_rate_limit_outcome(str_repeat('c',64),10,3,110)==='unavailable','below-limit contention is not quota');$GLOBALS['rate_lock_held']=false;check(nmkr_analytics_rate_limit_outcome(str_repeat('c',64),10,3,110)==='allowed','contender admitted after owner releases lock');
$GLOBALS['rate_lock_available']=false;check(nmkr_analytics_rate_limit_outcome(str_repeat('d',64),10,3,110)==='unavailable','lock timeout fails unavailable');check($GLOBALS['rate_lock_held']===false,'rate lock not retained');
reset_state();$failure_ip=str_repeat('e',64);$failure_key=nmkr_analytics_state_key('rate',array($failure_ip));$GLOBALS['options_store'][$failure_key]=array('start'=>100,'count'=>1,'expires'=>110);$GLOBALS['storage_write_fail']=true;check(nmkr_analytics_rate_limit_outcome($failure_ip,10,3,110)==='unavailable','storage failure is not quota');
reset_state();$repair_ip=str_repeat('f',64);$repair_key=nmkr_analytics_state_key('rate',array($repair_ip));$GLOBALS['options_store'][$repair_key]='malformed';check(nmkr_analytics_rate_limit_outcome($repair_ip,10,3,110)==='allowed'&&$GLOBALS['options_store'][$repair_key]['count']===1,'malformed rate state is repaired under lock');
foreach(array(
    array('start'=>100,'count'=>-1,'expires'=>110),
    array('start'=>100,'count'=>'1','expires'=>110),
    array('start'=>'100','count'=>1,'expires'=>110),
    array('start'=>111,'count'=>1,'expires'=>121),
    array('start'=>100,'count'=>1,'expires'=>'110'),
    array('start'=>100,'count'=>1,'expires'=>111),
) as $invalid_rate_state){$GLOBALS['options_store'][$repair_key]=$invalid_rate_state;check(nmkr_analytics_rate_limit_outcome($repair_ip,10,3,110)==='allowed'&&$GLOBALS['options_store'][$repair_key]===array('start'=>110,'count'=>1,'expires'=>120),'invalid rate schema is repaired to count one');}
$GLOBALS['options_store'][$repair_key]=array('start'=>100,'count'=>3,'expires'=>110);check(nmkr_analytics_rate_limit_outcome($repair_ip,10,3,109)==='quota'&&$GLOBALS['options_store'][$repair_key]['count']===4,'valid repaired schema preserves true quota behavior');
check(nmkr_analytics_decode_body('{')['status']===400,'malformed json'); check(nmkr_analytics_decode_body(str_repeat('x',NMKR_ANALYTICS_RAW_BODY_MAX_BYTES+1))['status']===413,'raw bound');
$n=0;$v=true;nmkr_prepare_analytics_metadata(array('a'=>array('b'=>array('c'=>array('d'=>array('e'=>'x'))))),0,$n,$v);check(!$v,'depth');
$m=array();for($i=0;$i<=NMKR_ANALYTICS_META_MAX_NODES;$i++)$m['k'.$i]=$i;$n=0;$v=true;nmkr_prepare_analytics_metadata($m,0,$n,$v);check(!$v,'nodes');
$n=0;$v=true;nmkr_prepare_analytics_metadata(array('x'=>str_repeat('x',257)),0,$n,$v);check(!$v,'string');
$m=array();for($i=0;$i<10;$i++)$m['k'.$i]=str_repeat('x',250);$n=0;$v=true;nmkr_prepare_analytics_metadata($m,0,$n,$v);check(!$v,'aggregate');
reset_state();$GLOBALS['consent_setting']=null;check(nmkr_analytics_ingest_common(payload())->get_status()===204&&$GLOBALS['inserts']===0&&$GLOBALS['ga4']===0,'consent-safe default blocks sinks');$GLOBALS['consent_setting']=0;
reset_state();check(nmkr_analytics_ingest_common(payload())->get_status()===204&&$GLOBALS['inserts']===1,'valid uuid custom');
foreach(array('bad',str_repeat('a',37),'123e4567-e89b-12d3-a456-426614174000') as $sid){reset_state();$q=payload();$q['session_id']=$sid;check(nmkr_analytics_ingest_common($q)->get_status()===400&&$GLOBALS['inserts']===0&&$GLOBALS['ga4']===0&&count($GLOBALS['options_store'])===0,'invalid uuid before state');}
reset_state();$GLOBALS['insert_ok']=false;check(nmkr_analytics_ingest_common(payload())->get_status()===500&&count($GLOBALS['options_store'])===2,'insert failure retains durable admission');$GLOBALS['insert_ok']=true;check(nmkr_analytics_ingest_common(payload())->get_status()===204&&$GLOBALS['inserts']===1,'retry cannot duplicate sink invocation');
reset_state();for($i=0;$i<120;$i++)nmkr_analytics_rate_limit_outcome(nmkr_hash_ip_address(),300,120,time());check(nmkr_analytics_ingest_common(payload())->get_status()===429&&$GLOBALS['inserts']===0,'empty 429');
reset_state();$GLOBALS['rate_lock_held']=true;check(nmkr_analytics_ingest_common(payload())->get_status()===503&&$GLOBALS['inserts']===0&&$GLOBALS['ga4']===0,'contention returns empty 503 with zero sinks');
reset_state();$dedupe_failure_rate_key=nmkr_analytics_state_key('rate',array(nmkr_hash_ip_address()));$GLOBALS['options_store'][$dedupe_failure_rate_key]=array('start'=>time(),'count'=>0,'expires'=>time()+300);$GLOBALS['before_add']=function(){$GLOBALS['add_fail']=true;};check(nmkr_analytics_ingest_common(payload())->get_status()===503&&$GLOBALS['inserts']===0&&$GLOBALS['ga4']===0,'ambiguous dedupe creation returns empty 503 with zero sinks');
reset_state();$cas_payload=payload();$cas_rate_key=nmkr_analytics_state_key('rate',array(nmkr_hash_ip_address()));$cas_dedupe_key=nmkr_analytics_state_key('dedupe',array($cas_payload['session_id'],$cas_payload['element_id'],$cas_payload['event_type']));$GLOBALS['options_store'][$cas_rate_key]=array('start'=>time(),'count'=>0,'expires'=>time()+300);$GLOBALS['options_store'][$cas_dedupe_key]=array('token'=>'expired-token','status'=>'claim','expires'=>time()-1);$GLOBALS['storage_write_fail_name']=$cas_dedupe_key;check(nmkr_analytics_ingest_common($cas_payload)->get_status()===503&&$GLOBALS['inserts']===0&&$GLOBALS['ga4']===0,'dedupe takeover CAS database failure returns empty 503 with zero sinks');
reset_state();$ingress_key=nmkr_analytics_state_key('rate',array(nmkr_hash_ip_address()));$GLOBALS['options_store'][$ingress_key]=array('start'=>time(),'count'=>1,'expires'=>time()+300);$GLOBALS['storage_write_fail']=true;check(nmkr_analytics_ingest_common(payload())->get_status()===503&&$GLOBALS['inserts']===0&&$GLOBALS['ga4']===0,'storage ambiguity returns empty 503 with zero sinks');
foreach(array('off'=>array(0,0),'custom'=>array(0,1),'ga4'=>array(1,0),'both'=>array(1,1)) as $mode=>$expect){reset_state();$GLOBALS['mode']=$mode;check(nmkr_analytics_ingest_common(payload())->get_status()===204&&array($GLOBALS['ga4'],$GLOBALS['inserts'])===$expect,'sink matrix');}
$js=file_get_contents(dirname(__DIR__).'/js/nmkr-analytics.js');check(strpos($js,'Math.random() > sampleRate')===false&&strpos(file_get_contents(dirname(__DIR__).'/includes/helpers/nmkr-analytics-helpers.php'),'nmkr_get_analytics_sample_rate')!==false,'server authoritative sampling');
check(strpos($js,"-4' + s4().slice(1)")!==false&&strpos($js,".test(sid || '')")!==false,'client regenerates non-v4 sessions');
$rest=file_get_contents(dirname(__DIR__).'/includes/analytics/nmkr-analytics-endpoints.php');$ajax=file_get_contents(dirname(__DIR__).'/includes/ajax/nmkr-ajax-functions.php');check(substr_count($rest,'nmkr_analytics_decode_body')===1&&substr_count($ajax,'nmkr_analytics_decode_body')===1,'transport parity');
$uninstall=file_get_contents(dirname(__DIR__).'/nmkr-connect.php');check(strpos($uninstall,"esc_like('nmkr_ai_')")!==false&&strpos($uninstall,'delete_option($analytics_state_name)')!==false,'configured uninstall removes dynamic admission state');
$cron=file_get_contents(dirname(__DIR__).'/includes/analytics/nmkr-analytics-cron.php');check(strpos($cron,"get_option(\$state_cursor_option, '')")!==false&&strpos($cron,'update_option($state_cursor_option')!==false,'bounded cleanup persists its ordered cursor');
reset_state();check(count($GLOBALS['options_store'])===0&&count($GLOBALS['transients'])===0,'cleanup');
echo "PASS: analytics ingestion contracts\n";
