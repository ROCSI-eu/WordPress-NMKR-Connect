<?php
/** Guarded, bounded, read-only M3-06 benchmark. Invoke only through its controller. */
if (PHP_SAPI !== 'cli' || !defined('WP_CLI') || WP_CLI !== true) { fwrite(STDERR, "benchmark: REFUSED\n"); exit(2); }
$truthy = function ($value) { return is_string($value) && $value !== '' && !in_array(strtolower($value), array('0','false','no'), true); };
if ($truthy(getenv('CI')) || getenv('NMKR_API_BENCHMARK_CONFIRM') !== 'I_AUTHORIZE_PRIVATE_READ_ONLY_NMKR_API_BENCHMARK' || getenv('NMKR_API_BENCHMARK_CONTROLLER') !== '1') { fwrite(STDERR, "benchmark: REFUSED\n"); exit(2); }
if (!preg_match('/^[0-9a-f]{40}$/', (string) getenv('NMKR_API_BENCHMARK_SOURCE_SHA')) || !preg_match('/^[0-9a-f]{40}$/', (string) getenv('NMKR_API_BENCHMARK_DEPLOYED_SHA'))) { fwrite(STDERR, "benchmark: REFUSED\n"); exit(2); }
define('NMKR_API_RESPONSE_BENCHMARK_AUTHORIZED', true);
require_once __DIR__ . '/nmkr-api-response-benchmark-state.php';
$authority = NMKR_API_Benchmark_Authority::issue();
if (is_wp_error($authority)) { fwrite(STDERR, "benchmark: REFUSED\n"); exit(2); }
function nmkr_benchmark_now() { return function_exists('hrtime') ? hrtime(true) / 1000000000 : microtime(true); }
function nmkr_benchmark_stats($values) { sort($values, SORT_NUMERIC); $n=count($values); return array('count'=>$n,'minimum'=>$values[0],'mean'=>array_sum($values)/$n,'median'=>$n%2?$values[(int)floor($n/2)]:($values[$n/2-1]+$values[$n/2])/2,'p95'=>$values[(int)ceil(.95*$n)-1],'maximum'=>$values[$n-1]); }
$source_sha=(string)getenv('NMKR_API_BENCHMARK_SOURCE_SHA'); $deployed_sha=(string)getenv('NMKR_API_BENCHMARK_DEPLOYED_SHA');
$before=nmkr_benchmark_state_snapshot($source_sha,$deployed_sha,true,true);
if (is_wp_error($before) || $before['active'] || $source_sha !== $deployed_sha) { fwrite(STDERR,"benchmark: REFUSED\n"); exit(2); }
$stop=function()use($before,$source_sha,$deployed_sha,&$attempts){$after=nmkr_benchmark_state_snapshot($source_sha,$deployed_sha,true,true);$empty=array('count'=>0,'minimum'=>null,'mean'=>null,'median'=>null,'p95'=>null,'maximum'=>null);echo wp_json_encode(array('schema_version'=>1,'profile'=>'m3-06','endpoint_summaries'=>array('projects'=>$empty,'token_list'=>$empty,'token_detail'=>$empty),'combined_summary'=>null,'http_attempts'=>count((array)$attempts),'failed_http_attempts'=>count(array_filter((array)$attempts,function($a){return empty($a['valid']);})),'measured_logical_failures'=>0,'state_equal'=>!is_wp_error($after)&&nmkr_benchmark_state_equal($before,$after),'limits_ok'=>false,'pass'=>false),JSON_UNESCAPED_SLASHES)."\n";exit(1);};
$attempts=array(); $waits=array(); $last_dispatch=null; $started=nmkr_benchmark_now(); $invalid=false; $active_class=null; $active_measured=false;
$samples=array('projects'=>array(),'token_list'=>array(),'token_detail'=>array());
$attempt_recorder=function($duration,$valid,$retry)use(&$attempts,&$samples,&$active_class,&$active_measured){$attempts[]=array('duration'=>(float)$duration,'valid'=>(bool)$valid,'retry'=>(bool)$retry);if($active_measured&&$valid&&isset($samples[$active_class]))$samples[$active_class][]=(float)$duration;return true;};
$wait_recorder=function($kind,$duration)use(&$waits){$waits[]=array('kind'=>(string)$kind,'duration'=>(float)$duration);return true;};
$checkpoint=function($phase)use(&$attempts,&$last_dispatch,$started,&$invalid){$now=nmkr_benchmark_now();if($now-$started>=300||count($attempts)>=45){$invalid=true;return new WP_Error('nmkr_benchmark_limit','Benchmark safety ceiling reached.');}if($phase==='before_api_dispatch'){if($last_dispatch!==null){$delay=.5-($now-$last_dispatch);if($delay>0)usleep((int)ceil($delay*1000000));}$last_dispatch=nmkr_benchmark_now();}return true;};
$context=array('benchmark_authority'=>$authority,'benchmark_attempt_recorder'=>$attempt_recorder,'benchmark_wait_recorder'=>$wait_recorder,'checkpoint'=>$checkpoint,'clock'=>'nmkr_benchmark_now');
$logical_failures=0;
$invoke=function($class,$measured)use(&$context,&$logical_failures,&$project_uid,&$token_uid,&$active_class,&$active_measured){$active_class=$class;$active_measured=$measured;if($class==='projects')$result=nmkr_connect_fetch_projects($context);elseif($class==='token_list')$result=nmkr_connect_fetch_nfts_page($project_uid,50,1,$context);else $result=nmkr_connect_fetch_nft_details($token_uid,$context);if(is_wp_error($result)){if($measured)$logical_failures++;return $result;}return $result;};
$projects=$invoke('projects',false); if(is_wp_error($projects)||empty($projects)){$stop();} usort($projects,function($a,$b){return strcmp((string)($a['projectuid']??$a['projectUid']??''),(string)($b['projectuid']??$b['projectUid']??''));}); $project_uid=(string)($projects[0]['projectuid']??$projects[0]['projectUid']??''); if($project_uid===''){$stop();}
$tokens=$invoke('token_list',false); if(is_wp_error($tokens)||empty($tokens)){$stop();} usort($tokens,function($a,$b){return strcmp((string)($a['uid']??$a['tokenUid']??''),(string)($b['uid']??$b['tokenUid']??''));}); $token_uid=(string)($tokens[0]['uid']??$tokens[0]['tokenUid']??''); if($token_uid===''||is_wp_error($invoke('token_detail',false))){$stop();}
for($round=0;$round<10&&!$invalid;$round++)foreach(array('projects','token_list','token_detail')as$class)if(is_wp_error($invoke($class,true)))$invalid=true;
$after=nmkr_benchmark_state_snapshot($source_sha,$deployed_sha,true,true); $state_equal=!is_wp_error($after)&&nmkr_benchmark_state_equal($before,$after);
$summary=array();$combined=array();foreach($samples as$class=>$values){$summary[$class]=count($values)?nmkr_benchmark_stats($values):array('count'=>0,'minimum'=>null,'mean'=>null,'median'=>null,'p95'=>null,'maximum'=>null);$combined=array_merge($combined,$values);}
$failed_attempts=count(array_filter($attempts,function($a){return !$a['valid'];}));$pass=!$invalid&&$logical_failures===0&&$failed_attempts===0&&count($attempts)<=45&&$state_equal;
foreach($summary as$item)$pass=$pass&&$item['count']===10&&$item['maximum']<1.0;
$output=array('schema_version'=>1,'profile'=>'m3-06','endpoint_summaries'=>$summary,'combined_summary'=>count($combined)?nmkr_benchmark_stats($combined):null,'http_attempts'=>count($attempts),'failed_http_attempts'=>$failed_attempts,'measured_logical_failures'=>$logical_failures,'state_equal'=>$state_equal,'limits_ok'=>!$invalid,'pass'=>$pass);
echo wp_json_encode($output,JSON_UNESCAPED_SLASHES)."\n"; exit($pass?0:1);
