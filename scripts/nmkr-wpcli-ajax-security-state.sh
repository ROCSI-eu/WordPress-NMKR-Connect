#!/usr/bin/env bash
set -Eeuo pipefail
fail(){ printf 'ajax_security_state_failed\n' >&2; exit 1; }
[[ -n "${WP_PATH:-}" ]] || fail
WP_CLI_BIN="${WP_CLI_BIN:-wp}"; command -v "$WP_CLI_BIN" >/dev/null 2>&1 || fail
PLUGIN="${NMKR_PLUGIN_SLUG:-connector-for-nmkr/nmkr-connect.php}"
"$WP_CLI_BIN" --path="$WP_PATH" core is-installed --quiet >/dev/null 2>&1 || fail
"$WP_CLI_BIN" --path="$WP_PATH" plugin is-active "$PLUGIN" >/dev/null 2>&1 || fail
MODE="${1:-digest}"; [[ "$MODE" == digest || "$MODE" == sync || "$MODE" == idle ]] || fail
NMKR_AJAX_STATE_MODE="$MODE" "$WP_CLI_BIN" --path="$WP_PATH" eval '
global $wpdb;
$h=function($v){return hash("sha256",serialize($v));};
$exists=function($table)use($wpdb){return $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s",$wpdb->esc_like($table)))===$table;};
$stats=$wpdb->prefix."nmkr_sync_stats"; $metrics=$wpdb->prefix."nmkr_sync_metrics";
if(!$exists($stats)||!$exists($metrics)) exit(20);
$table_sig=function($table)use($wpdb,$h){$rows=$wpdb->get_results("SELECT * FROM `".esc_sql($table)."` ORDER BY 1",ARRAY_A);if(!is_array($rows))exit(21);return array(count($rows),$h($rows));};
$hooks=array("nmkr_execute_sync_background","nmkr_resume_sync_finalization","nmkr_resume_stopped_sync_recovery","nmkr_process_batch_hook","nmkr_sync_cron_hook","nmkr_install_sync_cron_hook");
$cron=array(); $raw_cron=_get_cron_array(); if(!is_array($raw_cron))exit(22);
foreach($raw_cron as $ts=>$entries)foreach($hooks as $hook)if(isset($entries[$hook]))foreach($entries[$hook] as $event)$cron[]=array((int)$ts,$h($hook),$h(isset($event["args"])?array_values($event["args"]):array())); sort($cron);
$owner=get_option("nmkr_sync_owner",false);
$lifecycle=array(); foreach(array("nmkr_sync_data","nmkr_sync_status","nmkr_sync_in_progress","nmkr_sync_progress","nmkr_sync_stop_requested","nmkr_sync_user_stopped","nmkr_sync_heartbeat","nmkr_last_progress_update_time","nmkr_last_progress_value") as $key)$lifecycle[]=array($h($key),$h(get_option($key,false)),$h(get_transient($key)));
$active_statuses=array("initializing","processing","processing_projects","processing_tokens","in_progress","running","pending","queued","finalizing","stop_requested");
$truthy=function($value)use($active_statuses){if(is_bool($value))return $value;if(is_int($value)||is_float($value))return (float)$value>0;if(is_string($value))return in_array(strtolower(trim($value)),array_merge(array("1","true","yes","on"),$active_statuses),true);return !empty($value);};
$status_active=function($value)use($active_statuses){return is_scalar($value)&&in_array(strtolower(trim((string)$value)),$active_statuses,true);};
$progress_active=function($value){return is_numeric($value)&&(float)$value>0&&(float)$value<100;};
$data_active=function($value)use($status_active){if(!is_array($value))return false;foreach(array("status","sync_status","state") as $key)if(array_key_exists($key,$value)&&$status_active($value[$key]))return true;if(array_key_exists("completed",$value)&&$value["completed"]===false)return true;foreach(array("in_progress","is_running","running","pending","active") as $key)if(array_key_exists($key,$value)&&$value[$key]===true)return true;return false;};
$quoted=array_map(function($s)use($wpdb){return "\"".esc_sql($s)."\"";},$active_statuses);
$active=$wpdb->get_var("SELECT COUNT(*) FROM `".esc_sql($stats)."` WHERE status IN (".implode(",",$quoted).")"); if(!is_numeric($active))exit(23);
$sync=array("owner"=>$h($owner),"lifecycle"=>$lifecycle,"cron"=>$cron,"stats"=>$table_sig($stats),"metrics"=>$table_sig($metrics),"active"=>(int)$active);
if(getenv("NMKR_AJAX_STATE_MODE")==="sync"){echo $h($sync);return;}
$rows=$wpdb->get_results("SELECT option_name,option_value FROM {$wpdb->options} WHERE option_name LIKE "."\"nmkr\\_%\""." OR option_name LIKE "."\"\\_transient\\_%nmkr%\""." OR option_name LIKE "."\"\\_site\\_transient\\_%nmkr%\""." ORDER BY option_name",ARRAY_A); if(!is_array($rows))exit(24);
$options=array();foreach($rows as $r)$options[]=array($h($r["option_name"]),$h($r["option_value"]));
$status_active_now=$status_active(get_option("nmkr_sync_status",""));
$sync_data_active=$data_active(get_option("nmkr_sync_data",array()));
$durable=$truthy(get_option("nmkr_sync_in_progress",false))||$status_active_now||$sync_data_active;
$progress=$durable||$progress_active(get_option("nmkr_sync_progress",0))||$truthy(get_transient("nmkr_sync_in_progress"))||$status_active(get_transient("nmkr_sync_status"))||$progress_active(get_transient("nmkr_sync_progress"))||$truthy(get_transient("nmkr_stale_recovery_running"));
if($durable&&($truthy(get_option("nmkr_sync_near_completion",false))||(is_numeric(get_option("nmkr_sync_heartbeat",0))&&(float)get_option("nmkr_sync_heartbeat",0)>0)))$progress=true;
$pending=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE "."\"nmkr\\_sync\\_finalization\\_resume\\_%\""." OR option_name LIKE "."\"nmkr\\_stopped\\_sync\\_recovery\\_%\"");
if(getenv("NMKR_AJAX_STATE_MODE")==="idle"&&($owner!==false||(int)$active!==0||count($cron)!==0||$progress||$pending!==0))exit(25);
echo $h(array("options"=>$options,"sync"=>$sync,"progress"=>$progress,"recovery"=>$pending));' 2>/dev/null || fail
printf '\n'
