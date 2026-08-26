<?php
class WP_Error { public $code; function __construct($c,$m){$this->code=$c;} }
function check($ok,$m){if(!$ok){fwrite(STDERR,"FAIL: $m\n");exit(1);}}
$removed_actions=array();
function remove_action($hook,$callback){global $removed_actions;$removed_actions[]=array($hook,$callback);return true;}
require __DIR__.'/nmkr-synthetic-provider.php';
$p=nmkr_synthetic_profiles(); check(count($p)===2,'only fixed profiles');
check($p['public-v1']['projects']===3&&$p['public-v1']['details']===12&&$p['public-v1']['total_requests']===22,'public arithmetic');
check($p['private-2400-v1']['projects']*$p['private-2400-v1']['tokens_per_project']===2400&&$p['private-2400-v1']['appearances']-$p['private-2400-v1']['details']===1200&&$p['private-2400-v1']['total_requests']===2497,'private arithmetic');
$a=array('execution_id'=>str_repeat('a',32),'profile_id'=>'public-v1','expiry'=>time()+60);
$r=nmkr_synthetic_dispatch('GET','https://studio-api.nmkr.io/v2/ListProjects',$a,false); check(count(json_decode($r['body'],true))===3,'project dispatch');
$r=nmkr_synthetic_dispatch('GET','https://studio-api.nmkr.io/v2/GetNfts/synthetic-project-000/all/50/2',$a,false); check(count(json_decode($r['body'],true))===2,'same-project duplicate page');
$detail=nmkr_synthetic_detail(nmkr_synthetic_token(nmkr_synthetic_project(0,$p['public-v1']),0)); check(is_string($detail['metadata'])&&json_decode($detail['metadata'],true)===array('synthetic'=>true),'detail metadata is persistence-safe JSON');
$run=sys_get_temp_dir().'/nmkr-synthetic-provider-'.bin2hex(random_bytes(6)); check(mkdir($run,0700),'private state directory');
$state=$run.'/state.json'; putenv('NMKR_SYNTHETIC_RUN_DIR='.$run); putenv('NMKR_SYNTHETIC_STATE_FILE='.$state);
$bad=array(
 array('GET','http://studio-api.nmkr.io/v2/ListProjects','scheme'),array('GET','https://studio-api.nmkr.io:444/v2/ListProjects','port'),array('GET','https://studio-api.nmkr.io/v2/ListProjects?x=1','query'),array('GET','https://studio-api.nmkr.io/v2/ListProjects#x','fragment'),array('GET','https://user@studio-api.nmkr.io/v2/ListProjects','userinfo'),
 array('GET','https://studio-api.nmkr.io/v2/GetNfts/synthetic-project-000/all/49/1','page size'),array('GET','https://studio-api.nmkr.io/v2/GetNfts/synthetic-project-000/all/50/0','page number'),array('GET','https://studio-api.nmkr.io/v2/GetNfts/synthetic-project-999/all/50/1','project'),array('GET','https://studio-api.nmkr.io/v2/GetNftDetailsById/synthetic-project-000-token-9999','token'),array('POST','https://studio-api.nmkr.io/v2/ListProjects','method'),array('GET','https://studio-api.nmkr.io/v2/unknown','path'));
foreach($bad as $b) check(nmkr_synthetic_dispatch($b[0],$b[1],$a,true) instanceof WP_Error,'recorded '.$b[2].' refusal');
putenv('NMKR_SYNTHETIC_SENTINEL=NMKR_SYNTHETIC_TEST_ONLY_V1'); putenv('NMKR_SYNTHETIC_EXECUTION_ID='.$a['execution_id']); putenv('NMKR_SYNTHETIC_PROFILE='.$a['profile_id']); putenv('NMKR_SYNTHETIC_EXPIRY='.$a['expiry']); putenv('NMKR_SYNTHETIC_PORT=8123');
check(nmkr_synthetic_pre_http(null,array('method'=>'GET'),'https://example.invalid/') instanceof WP_Error,'recorded external refusal');
$recorded=json_decode(file_get_contents($state),true); check($recorded['counters']['violations']===12&&$recorded['counters']['external']===1&&$recorded['counters']['total']===12,'refusal counters');
unlink($state); rmdir($run);
check(nmkr_synthetic_loopback_url('http://127.0.0.1:8123/wp-admin/',8123)&&!nmkr_synthetic_loopback_url('http://localhost:8123/',8123)&&!nmkr_synthetic_loopback_url('https://127.0.0.1:8123/',8123),'narrow loopback');
putenv('NMKR_SYNTHETIC_SENTINEL=bad'); check(nmkr_synthetic_activation()===false,'inactive without valid activation');
$removed_actions=array(); putenv('NMKR_SYNTHETIC_SUPPRESS_CORE_UPDATES=dashboard-only-v1');
check(nmkr_synthetic_suppress_dashboard_updates()===true,'dashboard suppression enabled only while provider inactive');
check($removed_actions===array(array('admin_init','_maybe_update_core'),array('admin_init','_maybe_update_plugins'),array('admin_init','_maybe_update_themes')),'only core update callbacks suppressed');
$removed_actions=array(); putenv('NMKR_SYNTHETIC_SUPPRESS_CORE_UPDATES');
check(nmkr_synthetic_suppress_dashboard_updates()===false&&$removed_actions===array(),'dashboard suppression remains campaign-scoped');
$x=nmkr_synthetic_token(nmkr_synthetic_project(0,$p['public-v1']),0)['uid']; $y=nmkr_synthetic_token(nmkr_synthetic_project(1,$p['public-v1']),0)['uid']; check($x!==$y,'cross-project UID conflict prevented');
echo "Synthetic provider regression: PASS\n";
