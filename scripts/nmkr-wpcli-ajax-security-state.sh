#!/usr/bin/env bash
set -Eeuo pipefail
fail(){ printf 'ajax_security_state_failed\n' >&2; exit 1; }
[[ -n "${WP_PATH:-}" ]] || fail
WP_CLI_BIN="${WP_CLI_BIN:-wp}"; command -v "$WP_CLI_BIN" >/dev/null 2>&1 || fail
PLUGIN="${NMKR_PLUGIN_SLUG:-nmkr-connect/nmkr-connect.php}"
"$WP_CLI_BIN" --path="$WP_PATH" core is-installed --quiet >/dev/null 2>&1 || fail
"$WP_CLI_BIN" --path="$WP_PATH" plugin is-active "$PLUGIN" >/dev/null 2>&1 || fail
MODE="${1:-digest}"; [[ "$MODE" == digest || "$MODE" == idle ]] || fail
NMKR_AJAX_STATE_MODE="$MODE" "$WP_CLI_BIN" --path="$WP_PATH" eval '
global $wpdb;
$h=function($v){return hash("sha256",serialize($v));};
$rows=$wpdb->get_results("SELECT option_name,option_value FROM {$wpdb->options} WHERE option_name LIKE "."\"nmkr\\_%\""." OR option_name LIKE "."\"\\_transient\\_%nmkr%\""." OR option_name LIKE "."\"\\_site\\_transient\\_%nmkr%\""." ORDER BY option_name",ARRAY_A);
$options=array(); foreach((array)$rows as $r){$options[]=array($h($r["option_name"]),$h($r["option_value"]));}
$hooks=array("nmkr_execute_sync_background","nmkr_resume_sync_finalization","nmkr_resume_stopped_sync_recovery"); $cron=array();
foreach((array)_get_cron_array() as $ts=>$entries) foreach($hooks as $hook) if(isset($entries[$hook])) foreach($entries[$hook] as $event) $cron[]=array((int)$ts,$h($hook),$h(isset($event["args"])?$event["args"]:array())); sort($cron);
$tables=array($wpdb->prefix."nmkr_sync_stats",$wpdb->prefix."nmkr_sync_metrics"); $signatures=array();
foreach($tables as $table){if($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s",$wpdb->esc_like($table)))===$table){$all=$wpdb->get_results("SELECT * FROM `".esc_sql($table)."` ORDER BY 1",ARRAY_A);$signatures[]=array($h($table),count($all),$h($all));}else{$signatures[]=array($h($table),0,$h(array()));}}
$owner=get_option("nmkr_sync_owner",false); $active=$wpdb->get_var("SELECT COUNT(*) FROM `".esc_sql($wpdb->prefix."nmkr_sync_stats")."` WHERE status IN (\"initializing\",\"queued\",\"running\",\"processing\",\"finalizing\",\"stop_requested\")");
$queued=false; foreach($cron as $event){/* hook identities are hashed in output, but direct worker is checked independently below */}
foreach((array)_get_cron_array() as $entries) if(isset($entries["nmkr_execute_sync_background"])) {$queued=true;break;}
$progress=get_option("nmkr_sync_in_progress",false)||get_transient("nmkr_sync_in_progress")||((int)get_option("nmkr_sync_progress",0)>0&&(int)get_option("nmkr_sync_progress",0)<100)||((int)get_transient("nmkr_sync_progress")>0&&(int)get_transient("nmkr_sync_progress")<100);
$pending=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE "."\"nmkr\\_sync\\_finalization\\_resume\\_%\"");
if(getenv("NMKR_AJAX_STATE_MODE")==="idle" && ($owner!==false || (int)$active!==0 || $queued || $progress || $pending!==0)) exit(23);
$state=array("options"=>$options,"cron"=>$cron,"tables"=>$signatures,"owner"=>$h($owner),"active"=>(int)$active,"queued"=>$queued,"progress"=>$progress,"finalization"=>$pending);
echo hash("sha256",serialize($state));' 2>/dev/null || fail
printf '\n'
