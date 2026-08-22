<?php
class WP_Error { public $code; function __construct($c,$m){$this->code=$c;} }
function check($ok,$m){if(!$ok){fwrite(STDERR,"FAIL: $m\n");exit(1);}}
require __DIR__.'/nmkr-synthetic-provider.php';
$p=nmkr_synthetic_profiles(); check(count($p)===2,'only fixed profiles');
check($p['public-v1']['projects']===3&&$p['public-v1']['details']===12&&$p['public-v1']['total_requests']===22,'public arithmetic');
check($p['private-2400-v1']['projects']*$p['private-2400-v1']['tokens_per_project']===2400&&$p['private-2400-v1']['appearances']-$p['private-2400-v1']['details']===1200&&$p['private-2400-v1']['total_requests']===2497,'private arithmetic');
$a=array('execution_id'=>str_repeat('a',32),'profile_id'=>'public-v1','expiry'=>time()+60);
$r=nmkr_synthetic_dispatch('GET','https://studio-api.nmkr.io/v2/ListProjects',$a,false); check(count(json_decode($r['body'],true))===3,'project dispatch');
$r=nmkr_synthetic_dispatch('GET','https://studio-api.nmkr.io/v2/GetNfts/synthetic-project-000/all/50/2',$a,false); check(count(json_decode($r['body'],true))===2,'same-project duplicate page');
check(nmkr_synthetic_dispatch('POST','https://studio-api.nmkr.io/v2/ListProjects',$a,false) instanceof WP_Error,'method refusal');
check(nmkr_synthetic_dispatch('GET','https://studio-api.nmkr.io/v2/unknown',$a,false) instanceof WP_Error,'path refusal');
check(nmkr_synthetic_loopback_url('http://127.0.0.1:8123/wp-admin/',8123)&&!nmkr_synthetic_loopback_url('http://localhost:8123/',8123)&&!nmkr_synthetic_loopback_url('https://127.0.0.1:8123/',8123),'narrow loopback');
putenv('NMKR_SYNTHETIC_SENTINEL=bad'); check(nmkr_synthetic_activation()===false,'inactive without valid activation');
$x=nmkr_synthetic_token(nmkr_synthetic_project(0,$p['public-v1']),0)['uid']; $y=nmkr_synthetic_token(nmkr_synthetic_project(1,$p['public-v1']),0)['uid']; check($x!==$y,'cross-project UID conflict prevented');
echo "Synthetic provider regression: PASS\n";
