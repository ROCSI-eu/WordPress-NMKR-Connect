#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
tmp="$(mktemp -d)"; trap 'rm -rf -- "$tmp"' EXIT
cat >"$tmp/wp" <<'WP'
#!/usr/bin/env bash
set -e
[[ "$*" == *'core is-installed'* || "$*" == *'plugin is-active'* ]] && exit 0
code="${!#}"
HARNESS_CODE="$code" php <<'PHP'
<?php
class DB { public $prefix='wp_'; public $options='wp_options'; function esc_like($v){return $v;} function prepare($q,$v){return $q;} function get_var($q){if(strpos($q,'SHOW TABLES')!==false)return strpos($q,'sync_metrics')!==false?'wp_nmkr_sync_metrics':'wp_nmkr_sync_stats';return 0;} function get_results($q,$m=null){return array();} }
$wpdb=new DB; define('ARRAY_A','ARRAY_A');
function esc_sql($v){return $v;} function _get_cron_array(){return array();}
function get_option($key,$default=false){$fixture=json_decode(getenv('NMKR_IDLE_FIXTURE'),true);return array_key_exists($key,$fixture)?$fixture[$key]:$default;}
function get_transient($key){return false;}
eval(getenv('HARNESS_CODE'));
PHP
WP
chmod +x "$tmp/wp"
check_rejected(){ if NMKR_IDLE_FIXTURE="$1" WP_CLI_BIN="$tmp/wp" WP_PATH="$tmp" "$ROOT/scripts/nmkr-wpcli-ajax-security-state.sh" idle >/dev/null 2>&1; then echo 'AJAX security idle regression: FAIL' >&2; exit 1; fi; }
check_rejected '{"nmkr_sync_status":"running"}'
check_rejected '{"nmkr_sync_data":{"state":"processing_tokens"}}'
check_rejected '{"nmkr_sync_data":{"completed":false}}'
echo 'AJAX security idle regression: PASS'
