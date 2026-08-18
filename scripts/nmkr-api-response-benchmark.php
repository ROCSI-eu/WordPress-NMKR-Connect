<?php
/** Guarded, bounded, read-only M3-06 benchmark. Invoke only through its controller. */
function nmkr_benchmark_now() { return function_exists('hrtime') ? hrtime(true) / 1000000000 : microtime(true); }
function nmkr_benchmark_stats($values) { sort($values, SORT_NUMERIC); $n=count($values); return array('count'=>$n,'minimum'=>$values[0],'mean'=>array_sum($values)/$n,'median'=>$n%2?$values[(int)floor($n/2)]:($values[$n/2-1]+$values[$n/2])/2,'p95'=>$values[(int)ceil(.95*$n)-1],'maximum'=>$values[$n-1]); }
function nmkr_benchmark_list_shape($data, $body) { return is_array($data) && substr(ltrim($body),0,1)==='[' && (empty($data)||array_keys($data)===range(0,count($data)-1)); }
function nmkr_benchmark_detail_shape($data, $body) { return is_array($data) && !empty($data) && substr(ltrim($body),0,1)==='{'; }
function nmkr_benchmark_request($class,$url,$args,$shape,&$attempts,$checkpoint,$request=null,$clock=null,$sleep=null) {
    $request=$request?:'wp_remote_get'; $clock=$clock?:'nmkr_benchmark_now'; $sleep=$sleep?:function($seconds){usleep((int)round($seconds*1000000));};
    for($attempt=1;$attempt<=3;$attempt++) {
        $halt=$checkpoint('before_dispatch'); if(is_wp_error($halt)) return $halt;
        $started=call_user_func($clock); $response=call_user_func($request,$url,$args); $duration=max(0.0,call_user_func($clock)-$started);
        $valid=false; $retry=false; $data=null;
        if(is_wp_error($response)) $retry=true;
        else { $status=(int)wp_remote_retrieve_response_code($response); $retry=$status===408||$status===429||$status>=500; if($status>=200&&$status<300){$body=wp_remote_retrieve_body($response);$data=json_decode($body,true);$valid=$body!==''&&json_last_error()===JSON_ERROR_NONE&&call_user_func($shape,$data,$body);} }
        $attempts[]=array('class'=>$class,'duration'=>$duration,'valid'=>$valid);
        if($valid) return $data;
        if(!$retry||$attempt===3) return new WP_Error('nmkr_benchmark_request_failed','Benchmark request failed.');
        $halt=$checkpoint('before_backoff'); if(is_wp_error($halt)) return $halt;
        call_user_func($sleep,min(30.0,pow(2,$attempt-1)));
    }
}
if (defined('NMKR_API_BENCHMARK_REGRESSION') && NMKR_API_BENCHMARK_REGRESSION === true) return;
if (PHP_SAPI !== 'cli' || !defined('WP_CLI') || WP_CLI !== true) { fwrite(STDERR, "benchmark: REFUSED\n"); exit(2); }
$truthy=function($value){return is_string($value)&&$value!==''&&!in_array(strtolower($value),array('0','false','no'),true);};
if($truthy(getenv('CI'))||getenv('NMKR_API_BENCHMARK_CONFIRM')!=='I_AUTHORIZE_PRIVATE_READ_ONLY_NMKR_API_BENCHMARK'||getenv('NMKR_API_BENCHMARK_CONTROLLER')!=='1'){fwrite(STDERR,"benchmark: REFUSED\n");exit(2);}
if(!preg_match('/^[0-9a-f]{40}$/',(string)getenv('NMKR_API_BENCHMARK_SOURCE_SHA'))||!preg_match('/^[0-9a-f]{40}$/',(string)getenv('NMKR_API_BENCHMARK_DEPLOYED_SHA'))){fwrite(STDERR,"benchmark: REFUSED\n");exit(2);}
require_once __DIR__.'/nmkr-api-response-benchmark-state.php';
$source_sha=(string)getenv('NMKR_API_BENCHMARK_SOURCE_SHA');$deployed_sha=(string)getenv('NMKR_API_BENCHMARK_DEPLOYED_SHA');
$before=nmkr_benchmark_state_snapshot($source_sha,$deployed_sha,true,true);
if(is_wp_error($before)||$before['active']||$source_sha!==$deployed_sha){fwrite(STDERR,"benchmark: REFUSED\n");exit(2);}
$attempts=array();$samples=array('projects'=>array(),'token_list'=>array(),'token_detail'=>array());$started=nmkr_benchmark_now();$last_dispatch=null;$invalid=false;
$checkpoint=function($phase)use(&$attempts,$started,&$last_dispatch,&$invalid){$now=nmkr_benchmark_now();if($now-$started>=300||count($attempts)>=45){$invalid=true;return new WP_Error('nmkr_benchmark_limit','Benchmark safety ceiling reached.');}if($phase==='before_dispatch'){if($last_dispatch!==null&&($delay=.5-($now-$last_dispatch))>0)usleep((int)ceil($delay*1000000));$last_dispatch=nmkr_benchmark_now();}return true;};
$options=get_option('nmkr_connect_options');$key=is_array($options)?(string)($options['api_key']??''):'';
$stop=function()use($before,$source_sha,$deployed_sha,&$attempts){$after=nmkr_benchmark_state_snapshot($source_sha,$deployed_sha,true,true);$empty=array('count'=>0,'minimum'=>null,'mean'=>null,'median'=>null,'p95'=>null,'maximum'=>null);echo wp_json_encode(array('schema_version'=>1,'profile'=>'m3-06','endpoint_summaries'=>array('projects'=>$empty,'token_list'=>$empty,'token_detail'=>$empty),'combined_summary'=>null,'http_attempts'=>count($attempts),'failed_http_attempts'=>count(array_filter($attempts,function($a){return empty($a['valid']);})),'measured_logical_failures'=>0,'state_equal'=>!is_wp_error($after)&&nmkr_benchmark_state_equal($before,$after),'limits_ok'=>false,'pass'=>false),JSON_UNESCAPED_SLASHES)."\n";exit(1);};
if($key===''||!defined('NMKR_API_URL'))$stop();
$args=function($timeout)use($key){return array('headers'=>array('Authorization'=>'Bearer '.$key),'timeout'=>$timeout);};
$invoke=function($class,$url,$timeout,$shape,$measured)use(&$attempts,&$samples,$checkpoint,$args){$before=count($attempts);$result=nmkr_benchmark_request($class,$url,$args($timeout),$shape,$attempts,$checkpoint);if($measured&&!is_wp_error($result)){for($i=$before;$i<count($attempts);$i++)if($attempts[$i]['valid'])$samples[$class][]=$attempts[$i]['duration'];}return $result;};
$project_id=function($p){return(string)($p['uid']??$p['project_uid']??$p['projectuid']??$p['projectUid']??'');};
$projects=$invoke('projects',NMKR_API_URL.'/ListProjects',30,'nmkr_benchmark_list_shape',false);if(is_wp_error($projects)||empty($projects))$stop();usort($projects,function($a,$b)use($project_id){return strcmp($project_id($a),$project_id($b));});$project_uid=$project_id($projects[0]);if($project_uid===''||strlen($project_uid)>200||preg_match('/^[A-Za-z0-9_-]+$/',$project_uid)!==1)$stop();
$list_url=NMKR_API_URL.'/GetNfts/'.rawurlencode($project_uid).'/all/50/1';$tokens=$invoke('token_list',$list_url,45,'nmkr_benchmark_list_shape',false);if(is_wp_error($tokens)||empty($tokens))$stop();$token_id=function($t){return(string)($t['uid']??$t['token_uid']??$t['tokenUid']??'');};usort($tokens,function($a,$b)use($token_id){return strcmp($token_id($a),$token_id($b));});$token_uid=$token_id($tokens[0]);if($token_uid===''||strlen($token_uid)>200||preg_match('/^[A-Za-z0-9_-]+$/',$token_uid)!==1)$stop();$detail_url=NMKR_API_URL.'/GetNftDetailsById/'.rawurlencode($token_uid);if(is_wp_error($invoke('token_detail',$detail_url,30,'nmkr_benchmark_detail_shape',false)))$stop();
for($round=0;$round<10&&!$invalid;$round++){foreach(array('projects','token_list','token_detail')as$class){$url=$class==='projects'?NMKR_API_URL.'/ListProjects':($class==='token_list'?$list_url:$detail_url);$timeout=$class==='token_list'?45:30;$shape=$class==='token_detail'?'nmkr_benchmark_detail_shape':'nmkr_benchmark_list_shape';if(is_wp_error($invoke($class,$url,$timeout,$shape,true))){$invalid=true;break;}}}
$after=nmkr_benchmark_state_snapshot($source_sha,$deployed_sha,true,true);$state_equal=!is_wp_error($after)&&nmkr_benchmark_state_equal($before,$after);$summary=array();$combined=array();foreach($samples as$class=>$values){$summary[$class]=count($values)?nmkr_benchmark_stats($values):array('count'=>0,'minimum'=>null,'mean'=>null,'median'=>null,'p95'=>null,'maximum'=>null);$combined=array_merge($combined,$values);}$failed=count(array_filter($attempts,function($a){return !$a['valid'];}));$pass=!$invalid&&$failed===0&&count($attempts)<=45&&$state_equal;foreach($summary as$item)$pass=$pass&&$item['count']===10&&$item['maximum']<1.0;$output=array('schema_version'=>1,'profile'=>'m3-06','endpoint_summaries'=>$summary,'combined_summary'=>count($combined)?nmkr_benchmark_stats($combined):null,'http_attempts'=>count($attempts),'failed_http_attempts'=>$failed,'measured_logical_failures'=>$invalid?1:0,'state_equal'=>$state_equal,'limits_ok'=>!$invalid,'pass'=>$pass);echo wp_json_encode($output,JSON_UNESCAPED_SLASHES)."\n";exit($pass?0:1);
