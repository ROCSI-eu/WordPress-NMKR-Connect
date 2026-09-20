#!/usr/bin/env bash
set -Eeuo pipefail

WP_PATH="${WP_PATH:-}"
WP_CLI_BIN="${WP_CLI_BIN:-wp}"
NMKR_PLUGIN_SLUG="${NMKR_PLUGIN_SLUG:-connector-for-nmkr/nmkr-connect.php}"
NMKR_DEBUG_LOG_RELATIVE_PATH="${NMKR_DEBUG_LOG_RELATIVE_PATH:-wp-content/debug.log}"
NMKR_DEBUG_LOG_LOOKBACK_MINUTES="${NMKR_DEBUG_LOG_LOOKBACK_MINUTES:-30}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

info() {
  printf 'INFO: %s\n' "$*"
}

fail() {
  printf 'ERROR: %s\n' "$*" >&2
  exit 1
}

wp_cli() {
  local args=("$WP_CLI_BIN")

  if [[ -n "$WP_PATH" ]]; then
    args+=("--path=$WP_PATH")
  fi

  args+=("$@")
  "${args[@]}"
}

sql_escape() {
  printf "%s" "$1" | sed "s/'/''/g"
}

info "Checking WordPress installation."
wp_cli core is-installed >/dev/null

info "Checking NMKR Connect plugin activation state."
wp_cli plugin is-active "$NMKR_PLUGIN_SLUG" >/dev/null

prefix="$(wp_cli db prefix)"
metrics_table="${prefix}nmkr_sync_metrics"
projects_table="${prefix}nmkr_projects"
tokens_table="${prefix}nmkr_tokens"
token_details_table="${prefix}nmkr_token_details"
sync_stats_table="${prefix}nmkr_sync_stats"
api_option="nmkr_api_key"
connect_options="nmkr_connect_options"
last_sync_option="nmkr_last_sync_time"

info "Checking required NMKR database tables."
required_tables=("$projects_table" "$tokens_table" "$token_details_table" "$sync_stats_table" "$metrics_table")
for table in "${required_tables[@]}"; do
  escaped_table="$(sql_escape "$table")"
  exists="$(wp_cli db query "SHOW TABLES LIKE '${escaped_table}';" --skip-column-names 2>/dev/null || true)"
  [[ "$exists" == "$table" ]] || fail "Required table is missing: ${table}"
done

info "Checking API key option exists and is non-empty without printing it."
api_length="$(wp_cli option get "$api_option" --format=json 2>/dev/null | python3 -c 'import json,sys; value=json.load(sys.stdin); print(len(str(value).strip()))' 2>/dev/null || printf '0')"
if [[ ! "$api_length" =~ ^[0-9]+$ || "$api_length" == "0" ]]; then
  api_length="$(wp_cli option get "$connect_options" --format=json 2>/dev/null | python3 -c 'import json,sys; value=json.load(sys.stdin); print(len(str(value.get("api_key", "")).strip()) if isinstance(value, dict) else 0)' 2>/dev/null || printf '0')"
fi
[[ "$api_length" =~ ^[0-9]+$ ]] || fail "Could not inspect API key option length."
(( api_length > 0 )) || fail "API key option is missing or empty."
info "API key option is present and non-empty; value was not printed."

latest_metric_time="$(wp_cli db query "SELECT last_sync_time FROM ${metrics_table} ORDER BY last_sync_time DESC, id DESC LIMIT 1;" --skip-column-names 2>/dev/null || true)"
option_time="$(wp_cli option get "$last_sync_option" 2>/dev/null || true)"

if [[ -n "$latest_metric_time" && -n "$option_time" && "$latest_metric_time" != "$option_time" ]]; then
  fail "${last_sync_option} does not match the latest metrics last_sync_time."
fi

info "Latest sync timestamp option matches metrics when both values exist."

info "Checking completed sync rows for suspicious mutation markers."
suspicious_completed="$(wp_cli db query "SELECT COUNT(*) FROM ${sync_stats_table} WHERE status IN ('completed','success') AND (start_time IS NULL OR start_time = '' OR end_time IS NULL OR end_time = '' OR items_processed < 0 OR items_successful < 0 OR items_failed < 0 OR end_time < start_time);" --skip-column-names 2>/dev/null || printf '0')"
[[ "$suspicious_completed" =~ ^[0-9]+$ ]] || fail "Could not inspect completed sync metrics."
(( suspicious_completed == 0 )) || fail "Completed sync rows contain suspicious values."

info "Checking debug.log for fresh PHP or NMKR plugin errors without printing full logs."
[[ "$NMKR_DEBUG_LOG_LOOKBACK_MINUTES" =~ ^[1-9][0-9]{0,8}$ ]] || fail "Debug-log lookback must be a positive integer."
log_path="${WP_PATH:+${WP_PATH%/}/}${NMKR_DEBUG_LOG_RELATIVE_PATH}"
if [[ -f "$log_path" ]]; then
  if python3 "$SCRIPT_DIR/nmkr-debug-log-classifier.py" "$log_path" "$NMKR_DEBUG_LOG_LOOKBACK_MINUTES"; then
    :
  else
    classifier_status=$?
    case "$classifier_status" in
      1)
        fail "Fresh or timestamp-unclassified debug.log entries contain PHP or NMKR plugin errors. Review the VM-local log manually."
        ;;
      3)
        info "Recent third-party vendor warnings/notices were detected and did not block validation."
        ;;
      *)
        fail "Could not classify debug.log entries safely."
        ;;
    esac
  fi
else
  info "debug.log was not found; skipping log scan."
fi

info "PASS: NMKR Connect WP-CLI smoke checks completed without mutations."
