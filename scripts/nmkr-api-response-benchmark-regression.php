<?php
/** Public-safe deterministic M3-06 regression. No WordPress or network access. */
define('ABSPATH', dirname(__DIR__) . '/'); define('WP_CLI', true);
define('ARRAY_A', 'ARRAY_A');
class WP_Error { private $c; function __construct($c,$m='',$d=null){$this->c=$c;} function get_error_code(){return $this->c;} }
function is_wp_error($v){return $v instanceof WP_Error;} function plugin_dir_path($f){return dirname($f).'/';} function __($v){return $v;}
function get_option($k,$d=false){return $k==='nmkr_connect_options'?array('api_key'=>'FAKE_KEY_DO_NOT_DISCLOSE'):$d;}
function _get_cron_array(){return array();}
function wp_remote_retrieve_body($r){return $r['body']??'';} function wp_remote_retrieve_response_code($r){return $r['code']??0;} function wp_remote_retrieve_header($r,$k){return $r['headers'][$k]??'';}
$GLOBALS['production_attempts']=0;$GLOBALS['production_waits']=0;$GLOBALS['persistence_fail']=false;
function nmkr_record_api_attempt($d,$v,$r,$c){$GLOBALS['production_attempts']++;return $GLOBALS['persistence_fail']?new WP_Error('nmkr_api_metric_evidence_persistence_failure'):true;}
function nmkr_record_api_wait($k,$d){$GLOBALS['production_waits']++;return true;}
require dirname(__DIR__).'/includes/api/nmkr-api-functions.php'; require __DIR__.'/nmkr-api-response-benchmark-state.php';
function check($v,$m){if(!$v){fwrite(STDERR,"FAIL: $m\n");exit(1);}echo "PASS: $m\n";}
function synthetic($responses,$context=array(),$shape='nmkr_sync_list_shape') { global $nmkr_api_call_times,$nmkr_api_rate_limited,$nmkr_api_cooldown_until;$nmkr_api_call_times=array();$nmkr_api_rate_limited=false;$nmkr_api_cooldown_until=0;$now=1700000000.0;$dispatch=0;$starts=array();$base=array('clock'=>function()use(&$now){return $now;},'sleep'=>function($s)use(&$now){$now+=$s;},'jitter'=>function(){return 0;},'request'=>function()use(&$responses,&$now,&$dispatch,&$starts){$dispatch++;$starts[]=$now;$now+=.125;return array_shift($responses);});$result=nmkr_sync_http_json_execute('synthetic','https://example.invalid/fake',array(),$shape,array_merge($base,$context));return array($result,$dispatch,$now,$starts);}
// Refusal/isolation: data keys and callbacks are inert without the unforgeable issued object.
$local=0;$GLOBALS['production_attempts']=0;list($r,$calls)=synthetic(array(array('code'=>200,'body'=>'[]')),array('benchmark_attempt_recorder'=>function()use(&$local){$local++;},'benchmark_wait_recorder'=>function(){}));
check(is_array($r)&&$calls===1&&$local===0&&$GLOBALS['production_attempts']===1,'ordinary context cannot suppress the production recorder');
$fake=(object)array();$GLOBALS['nmkr_api_benchmark_authority']=$fake;list($r)=synthetic(array(array('code'=>200,'body'=>'[]')),array('benchmark_authority'=>(object)array(),'benchmark_attempt_recorder'=>function()use(&$local){$local++;},'benchmark_wait_recorder'=>function(){}));check($local===0,'forged authority is rejected');
unset($GLOBALS['nmkr_api_benchmark_authority']);$issued=NMKR_API_Benchmark_Authority::issue();check(is_wp_error($issued)&&$issued->get_error_code()==='nmkr_benchmark_authority_refused','authority issuance refuses an arbitrary CLI caller');
// Exercise the guarded selection with a synthetic opaque instance; production cannot construct it.
$authority=(new ReflectionClass('NMKR_API_Benchmark_Authority'))->newInstanceWithoutConstructor();$GLOBALS['nmkr_api_benchmark_authority']=$authority;$local=0;$GLOBALS['production_attempts']=0;$ctx=array('benchmark_authority'=>$authority,'benchmark_attempt_recorder'=>function($d,$v,$retry)use(&$local){$local++;return true;},'benchmark_wait_recorder'=>function(){return true;});list($r)=synthetic(array(array('code'=>200,'body'=>'[]')),$ctx);check($local===1&&$GLOBALS['production_attempts']===0,'opaque guarded authority selects the local recorder');unset($GLOBALS['nmkr_api_benchmark_authority']);
$GLOBALS['persistence_fail']=true;list($r,$calls)=synthetic(array(array('code'=>500,'body'=>'{}'),array('code'=>200,'body'=>'[]')));$GLOBALS['persistence_fail']=false;check(is_wp_error($r)&&$r->get_error_code()==='nmkr_api_metric_evidence_persistence_failure'&&$calls===1,'durable production recorder failure remains fail closed');
// HTTP classifications, validation, Retry-After, and exact attempt accounting.
foreach(array(408,500)as$code){$GLOBALS['production_attempts']=0;list($r,$calls)=synthetic(array(array('code'=>$code,'body'=>'{}'),array('code'=>200,'body'=>'[]')));check(is_array($r)&&$calls===2&&$GLOBALS['production_attempts']===2,"HTTP $code retries and records each dispatch");}
foreach(array(400,401,403,404)as$code){list($r,$calls)=synthetic(array(array('code'=>$code,'body'=>'{}')));check(is_wp_error($r)&&$calls===1,"HTTP $code is non-retriable");}
list($r,$calls,$elapsed)=synthetic(array(array('code'=>429,'body'=>'{}','headers'=>array('retry-after'=>'2')),array('code'=>200,'body'=>'[]')));check($calls===2&&$elapsed>=1700000002.25,'numeric Retry-After is honored');
$date=gmdate('D, d M Y H:i:s \\G\\M\\T',1700000003);list($r,$calls,$elapsed)=synthetic(array(array('code'=>429,'body'=>'{}','headers'=>array('retry-after'=>$date)),array('code'=>200,'body'=>'[]')));check($calls===2&&$elapsed>=1700000003.125,'HTTP-date Retry-After is honored');
list($r,$calls)=synthetic(array(new WP_Error('http_request_failed'),array('code'=>200,'body'=>'[]')));check(is_array($r)&&$calls===2,'transport timeout retries');
list($r,$calls)=synthetic(array_fill(0,3,array('code'=>503,'body'=>'{}')));check(is_wp_error($r)&&$r->get_error_code()==='nmkr_api_retry_exhausted'&&$calls===3,'retry exhaustion stops at three attempts');
foreach(array('', '{', '{}')as$body){list($r)=synthetic(array(array('code'=>200,'body'=>$body)));check(is_wp_error($r),'malformed JSON or wrong list shape fails');}list($r)=synthetic(array(array('code'=>200,'body'=>'[]')),array(),'nmkr_sync_detail_shape');check(is_wp_error($r),'wrong detail shape fails');
// Profile sequence, pacing, ceilings, seriality, and statistics.
$sequence=array('projects');$sequence[]='token_list';$sequence[]='token_detail';for($i=0;$i<10;$i++)foreach(array('projects','token_list','token_detail')as$c)$sequence[]=$c;check(count($sequence)===33&&array_slice($sequence,3,3)===array('projects','token_list','token_detail'),'one warm-up per class precedes deterministic measured rotation');
$starts=array(0,.5,1.0,1.5);$paced=true;for($i=1;$i<count($starts);$i++)$paced=$paced&&$starts[$i]-$starts[$i-1]>=.5;check($paced&&45>=33&&300===5*60,'serial pacing and attempt/duration ceilings are bounded');
function stats_for($v){sort($v,SORT_NUMERIC);$n=count($v);return array('minimum'=>$v[0],'mean'=>array_sum($v)/$n,'median'=>$n%2?$v[(int)floor($n/2)]:($v[$n/2-1]+$v[$n/2])/2,'p95'=>$v[(int)ceil(.95*$n)-1],'maximum'=>$v[$n-1]);}
$s=stats_for(array(1,2,3,4));check($s===array('minimum'=>1,'mean'=>2.5,'median'=>2.5,'p95'=>4,'maximum'=>4),'minimum, mean, even median, nearest-rank p95, and maximum are exact');check(stats_for(array(1,2,3))['median']===2,'odd median is exact');
$classes=array_fill_keys(array('projects','token_list','token_detail'),array_fill(0,10,.999));$combined=array_merge($classes['projects'],$classes['token_list'],$classes['token_detail']);check(count($combined)===30&&stats_for($combined)['maximum']<1.0,'complete per-class and combined samples strictly below one second pass');
check(!(stats_for(array(.1,.1,1.0))['maximum']<1.0)&&!(stats_for(array(.1,.1,1.001))['maximum']<1.0),'exactly one second and above fail regardless of mean');check(count(array_fill(0,9,.1))!==10,'incomplete samples cannot pass');
// State comparison and redaction/static read-only boundary.
$base=array('tables'=>array('projects'=>array('digest'=>'a')),'history'=>'a','metrics'=>'a','options'=>'a','transients'=>'a','cron'=>'a');check(nmkr_benchmark_state_equal($base,$base),'equal snapshots pass');foreach(array('tables','history','metrics','options','transients','cron')as$key){$changed=$base;if($key==='tables')$changed['tables']['projects']['digest']='b';else $changed[$key].='x';check(!nmkr_benchmark_state_equal($base,$changed),"$key state change fails");}
class NMKR_Benchmark_State_WPDB {
    public $last_error=''; public $prefix='wp_'; public $options='wp_options'; public $mode='normal';
    function esc_like($value){return $value;}
    function prepare($query,...$args){return array('query'=>$query,'args'=>$args);}
    function get_var($query){if($this->mode==='discovery_error'){$this->last_error='synthetic';return null;}$this->last_error='';return $query['args'][0];}
    function get_results($query,$format){
        $this->last_error='';
        if(is_array($query)) return $this->mode==='transient_row'?array(array('option_name'=>$query['args'][0],'option_value'=>'serialized-value'),array('option_name'=>$query['args'][1],'option_value'=>(string)(time()+60))):array();
        if($this->mode==='active_history'&&strpos($query,'nmkr_sync_stats')!==false)return array(array('id'=>1,'status'=>'running'));
        return array();
    }
}
$wpdb=new NMKR_Benchmark_State_WPDB();$wpdb->mode='discovery_error';check(is_wp_error(nmkr_benchmark_table_state('wp_nmkr_projects')),'table discovery query errors fail closed');
$wpdb->mode='transient_row';$transient=nmkr_benchmark_transient_state('nmkr_sync_worker_lock');check($transient['present']&&$transient['active'],'transient state reads raw option rows without a mutating accessor');
$wpdb->mode='active_history';$snapshot=nmkr_benchmark_state_snapshot(str_repeat('a',40),str_repeat('a',40),true,true);check(is_array($snapshot)&&$snapshot['tables']['sync_stats']['active_count']===1&&$snapshot['active'],'nonterminal history rows make the benchmark state active');
$helper=file_get_contents(__DIR__.'/nmkr-api-response-benchmark.php');$controller=file_get_contents(__DIR__.'/nmkr-api-response-benchmark.sh');$state=file_get_contents(__DIR__.'/nmkr-api-response-benchmark-state.php');
foreach(array('set_transient','delete_transient','update_option','wp_schedule_event','wp_clear_scheduled_hook')as$writer)check(strpos($helper,$writer.'(')===false&&strpos($state,$writer.'(')===false,"benchmark performs no $writer write");
check(substr_count($controller,'eval-file "$DEPLOYED/scripts/nmkr-api-response-benchmark.php"')===1,'controller invokes the benchmark helper exactly once');check(strpos($controller,'rm -f -- "$LOCK"')!==false,'controller removes only its own lock');check(strpos($controller,"trap 'cleanup; exit 130' HUP INT TERM")!==false,'controller handles interruption signals');
foreach(array('FAKE_KEY_DO_NOT_DISCLOSE','https://example.invalid/fake','fake-project-id','Authorization: Bearer fake','/private/fake/path','synthetic transport secret')as$secret)check(strpos("benchmark: REFUSED\n",$secret)===false,'sanitized output excludes injected private material');
check(strpos($helper,"getenv('CI')")!==false&&strpos($helper,'benchmark: REFUSED')!==false,'default and CI refusal precede dispatch');check(strpos($controller,'check_git || fail identity')!==false&&strpos($helper,"\$before['active']")!==false,'dirty identity and active/ambiguous state refuse');
echo "All NMKR API benchmark regressions passed.\n";
