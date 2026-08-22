<?php
/** Test-only MU provider. Inert unless an owner-private activation is valid. */
if (!function_exists('nmkr_synthetic_profiles')) {
function nmkr_synthetic_profiles() {
    return array(
        'public-v1' => array('projects'=>3,'tokens_per_project'=>4,'pages'=>3,'list_requests'=>9,'appearances'=>15,'duplicates'=>3,'details'=>12,'total_requests'=>22,'budget'=>22),
        'private-2400-v1' => array('projects'=>24,'tokens_per_project'=>100,'pages'=>4,'list_requests'=>96,'appearances'=>3600,'duplicates'=>1200,'details'=>2400,'total_requests'=>2497,'budget'=>2497),
    );
}
function nmkr_synthetic_project($index, $profile) {
    $kind = $index % 3; $chains = $kind === 0 ? array('Cardano') : ($kind === 1 ? array('Solana') : array('Cardano','Solana'));
    return array('id'=>100000+$index,'uid'=>'synthetic-project-'.sprintf('%03d',$index),'projectname'=>'Synthetic Project '.sprintf('%03d',$index),'state'=>'active','totalTokens'=>$profile['tokens_per_project'],'blockchains'=>$chains,'solanaProjectDetails'=>in_array('Solana',$chains,true)?array('network'=>'synthetic-local','symbol'=>'SYN'):null);
}
function nmkr_synthetic_token($project, $index) {
    $uid=$project['uid'].'-token-'.sprintf('%04d',$index);
    return array('id'=>200000+$index,'uid'=>$uid,'name'=>'Synthetic Token','displayName'=>'Synthetic Token '.sprintf('%04d',$index),'state'=>'free','minted'=>false,'policyId'=>'synthetic-policy','assetId'=>'synthetic-asset-'.$index,'assetName'=>'SYN'.$index,'fingerprint'=>'synthetic-fingerprint-'.$index,'tokenAmount'=>1,'price'=>0,'priceSolana'=>0);
}
function nmkr_synthetic_detail($token) { return array_merge($token,array('title'=>$token['displayName'],'metadata'=>'{"synthetic":true}','uploadSource'=>'synthetic-generator','receiveraddress'=>'synthetic-not-a-wallet','sendBackCentralPaymentInLovelace'=>0,'sendBackCentralPaymentInLamport'=>0,'priceInLovelaceCentralPayments'=>0,'priceInLamportCentralPayments'=>0)); }
function nmkr_synthetic_page_indexes($profile_id,$page) {
    if ($profile_id==='public-v1') return $page===1?array(0,1,2):($page===2?array(2,3):array());
    if ($page===1) return range(0,49);
    if ($page===2) return array_merge(range(40,49),range(50,89));
    if ($page===3) return array_merge(range(50,89),range(90,99));
    return array();
}
function nmkr_synthetic_loopback_url($url,$port) {
    $p=parse_url($url); if (!is_array($p)||!isset($p['scheme'],$p['host'])) return false;
    return $p['scheme']==='http' && $p['host']==='127.0.0.1' && isset($p['port']) && (int)$p['port']===(int)$port && empty($p['user']) && empty($p['pass']);
}
function nmkr_synthetic_activation() {
    $sentinel=getenv('NMKR_SYNTHETIC_SENTINEL'); $execution=getenv('NMKR_SYNTHETIC_EXECUTION_ID'); $profile=getenv('NMKR_SYNTHETIC_PROFILE'); $expiry=getenv('NMKR_SYNTHETIC_EXPIRY');
    if ($sentinel!=='NMKR_SYNTHETIC_TEST_ONLY_V1'||!preg_match('/^[a-f0-9]{32}$/D',(string)$execution)||!isset(nmkr_synthetic_profiles()[$profile])||!ctype_digit((string)$expiry)||(int)$expiry<=time()) return false;
    return array('execution_id'=>$execution,'profile_id'=>$profile,'expiry'=>(int)$expiry);
}
function nmkr_synthetic_state_update($kind,$activation) {
    $run=getenv('NMKR_SYNTHETIC_RUN_DIR'); $path=getenv('NMKR_SYNTHETIC_STATE_FILE');
    if (!$run||!$path||is_link($run)||is_link($path)||!is_dir($run)||realpath(dirname($path))!==realpath($run)) return false;
    $fh=@fopen($path,'c+'); if (!$fh||!flock($fh,LOCK_EX|LOCK_NB)) { if($fh)fclose($fh); return false; }
    $raw=stream_get_contents($fh); $s=$raw===''?array('schema_version'=>1,'execution_id'=>$activation['execution_id'],'profile_id'=>$activation['profile_id'],'expiry'=>$activation['expiry'],'counters'=>array('projects'=>0,'token_lists'=>0,'details'=>0,'violations'=>0,'external'=>0,'total'=>0)):json_decode($raw,true);
    $valid=is_array($s)&&$s['schema_version']===1&&hash_equals($activation['execution_id'],(string)$s['execution_id'])&&$s['profile_id']===$activation['profile_id']&&$s['expiry']===$activation['expiry']&&isset($s['counters'][$kind],$s['counters']['total']);
    $violation=$kind==='violations'||$kind==='external';
    if (!$valid||(!$violation&&$s['counters']['total']>=nmkr_synthetic_profiles()[$activation['profile_id']]['budget'])) { flock($fh,LOCK_UN); fclose($fh); return false; }
    $s['counters'][$kind]++; if($kind==='external')$s['counters']['violations']++; $s['counters']['total']++; $encoded=json_encode($s); rewind($fh);
    $ok=ftruncate($fh,0)&&fwrite($fh,$encoded)===strlen($encoded)&&fflush($fh); if($ok) @chmod($path,0600); flock($fh,LOCK_UN); fclose($fh); return $ok;
}
function nmkr_synthetic_response($body) { return array('headers'=>array('content-type'=>'application/json'),'body'=>json_encode($body),'response'=>array('code'=>200,'message'=>'OK'),'cookies'=>array(),'filename'=>null); }
function nmkr_synthetic_error($code) { return class_exists('WP_Error')?new WP_Error($code,'Synthetic provider refused request.'):array('synthetic_error'=>$code); }
function nmkr_synthetic_refusal($code,$activation,$record) { if($record)nmkr_synthetic_state_update('violations',$activation); return nmkr_synthetic_error($code); }
function nmkr_synthetic_dispatch($method,$url,$activation,$record=true) {
    if ($method!=='GET') return nmkr_synthetic_refusal('synthetic_method',$activation,$record); $p=parse_url($url); $profile=nmkr_synthetic_profiles()[$activation['profile_id']]; $kind=''; $body=null;
    if (!is_array($p) || ($p['scheme']??'')!=='https' || ($p['host']??'')!=='studio-api.nmkr.io' || isset($p['user']) || isset($p['pass']) || (isset($p['port']) && (int)$p['port']!==443) || isset($p['query']) || isset($p['fragment'])) return nmkr_synthetic_refusal('synthetic_url',$activation,$record);
    $path=isset($p['path'])?$p['path']:'';
    if ($path==='/v2/ListProjects') { $kind='projects'; $body=array(); for($i=0;$i<$profile['projects'];$i++)$body[]=nmkr_synthetic_project($i,$profile); }
    elseif(preg_match('#^/v2/GetNfts/(synthetic-project-[0-9]{3})/all/([0-9]+)/([0-9]+)$#D',$path,$m)) { $kind='token_lists'; $pi=(int)substr($m[1],-3); $page=(int)$m[3]; if($pi>=$profile['projects']||(int)$m[2]!==50||$page<1||$page>$profile['pages'])return nmkr_synthetic_refusal('synthetic_shape',$activation,$record); $project=nmkr_synthetic_project($pi,$profile); $body=array(); foreach(nmkr_synthetic_page_indexes($activation['profile_id'],$page) as $i)$body[]=nmkr_synthetic_token($project,$i); }
    elseif(preg_match('#^/v2/GetNftDetailsById/(synthetic-project-[0-9]{3})-token-([0-9]{4})$#D',$path,$m)) { $kind='details'; $pi=(int)substr($m[1],-3); $ti=(int)$m[2]; if($pi>=$profile['projects']||$ti>=$profile['tokens_per_project'])return nmkr_synthetic_refusal('synthetic_shape',$activation,$record); $body=nmkr_synthetic_detail(nmkr_synthetic_token(nmkr_synthetic_project($pi,$profile),$ti)); }
    else return nmkr_synthetic_refusal('synthetic_unexpected',$activation,$record);
    if($record&&!nmkr_synthetic_state_update($kind,$activation))return nmkr_synthetic_error('synthetic_state'); usleep((int)(getenv('NMKR_SYNTHETIC_LATENCY_MS')?:125)*1000); return nmkr_synthetic_response($body);
}
function nmkr_synthetic_pre_http($pre,$args,$url) {
    $a=nmkr_synthetic_activation(); if(!$a)return $pre;
    if(nmkr_synthetic_loopback_url($url,(int)getenv('NMKR_SYNTHETIC_PORT')))return $pre;
    $host=(string)parse_url($url,PHP_URL_HOST); if($host==='studio-api.nmkr.io')return nmkr_synthetic_dispatch(isset($args['method'])?strtoupper($args['method']):'GET',$url,$a,true);
    nmkr_synthetic_state_update('external',$a); return nmkr_synthetic_error('synthetic_external_blocked');
}
if(function_exists('add_filter')) add_filter('pre_http_request','nmkr_synthetic_pre_http',PHP_INT_MIN,3);
}
