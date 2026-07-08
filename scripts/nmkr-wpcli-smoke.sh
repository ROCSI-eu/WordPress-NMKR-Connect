#!/usr/bin/env bash
set -Eeuo pipefail

WP_CLI_BIN="${WP_CLI_BIN:-wp}"
WP_PATH="${WP_PATH:-}"
NMKR_PLUGIN_SLUG="${NMKR_PLUGIN_SLUG:-nmkr-connect/nmkr-connect.php}"
NMKR_DEBUG_LOG_RELATIVE_PATH="${NMKR_DEBUG_LOG_RELATIVE_PATH:-wp-content/debug.log}"
NMKR_DEBUG_LOG_LOOKBACK_MINUTES="${NMKR_DEBUG_LOG_LOOKBACK_MINUTES:-30}"

pass() {
  printf 'PASS: %s\n' "$1"
}

notice() {
  printf 'NOTICE: %s\n' "$1"
}

fail() {
  printf 'FAIL: %s\n' "$1" >&2
  exit 1
}

command -v "$WP_CLI_BIN" >/dev/null 2>&1 || fail "WP-CLI binary not found. Set WP_CLI_BIN."
[[ -n "$WP_PATH" ]] || fail "WP_PATH is required and must point to the WordPress install."
[[ -d "$WP_PATH" ]] || fail "WP_PATH does not exist or is not a directory."

wp_cmd() {
  "$WP_CLI_BIN" --path="$WP_PATH" "$@"
}

wp_sql() {
  wp_cmd db query --skip-column-names --silent "$1"
}

sql_escape() {
  printf '%s' "$1" | sed "s/'/''/g"
}

wp_cmd core is-installed >/dev/null 2>&1 || fail "WordPress core is not installed at WP_PATH."
pass "WordPress core is installed."

if wp_cmd plugin is-active "$NMKR_PLUGIN_SLUG" >/dev/null 2>&1; then
  pass "NMKR Connect plugin is active."
else
  fail "NMKR Connect plugin is not active for NMKR_PLUGIN_SLUG."
fi

prefix="$(wp_cmd db prefix)"
[[ -n "$prefix" ]] || fail "Could not determine WordPress database prefix."

required_tables=(
  "${prefix}nmkr_projects"
  "${prefix}nmkr_tokens"
  "${prefix}nmkr_token_details"
  "${prefix}nmkr_sync_stats"
  "${prefix}nmkr_sync_metrics"
)

for table in "${required_tables[@]}"; do
  escaped_table="$(sql_escape "$table")"
  exists="$(wp_sql "SHOW TABLES LIKE '${escaped_table}';" | wc -l | tr -d ' ')"
  [[ "$exists" == "1" ]] || fail "Required NMKR table is missing: $table"
  pass "Required NMKR table exists: $table"
done

api_key_present="$(wp_cmd eval '$options = get_option("nmkr_connect_options", array()); echo ! empty($options["api_key"]) ? "yes" : "no";')"
[[ "$api_key_present" == "yes" ]] || fail "NMKR API key option is missing or empty."
pass "NMKR API key option is present and non-empty (value redacted)."

metrics_count="$(wp_sql "SELECT COUNT(*) FROM ${prefix}nmkr_sync_metrics;" | tr -d ' ')"
if [[ "$metrics_count" == "0" ]]; then
  notice "No sync metrics rows exist yet; skipping last sync time comparison."
else
  latest_metrics_time="$(wp_sql "SELECT last_sync_time FROM ${prefix}nmkr_sync_metrics ORDER BY last_sync_time DESC, id DESC LIMIT 1;" | head -n 1)"
  option_last_sync="$(wp_cmd option get nmkr_last_sync_time 2>/dev/null || true)"

  [[ -n "$option_last_sync" ]] || fail "nmkr_last_sync_time option is empty while sync metrics exist."
  [[ "$option_last_sync" == "$latest_metrics_time" ]] || fail "nmkr_last_sync_time does not match latest nmkr_sync_metrics.last_sync_time."
  pass "nmkr_last_sync_time matches latest sync metrics last_sync_time."
fi

completed_count="$(wp_sql "SELECT COUNT(*) FROM ${prefix}nmkr_sync_stats WHERE status = 'completed';" | tr -d ' ')"
pass "Completed sync history rows visible: $completed_count."

suspicious_cancelled="$(wp_sql "SELECT COUNT(*) FROM ${prefix}nmkr_sync_stats WHERE status = 'cancelled' AND end_time IS NOT NULL AND items_successful > 0 AND (items_failed = 0 OR items_failed IS NULL) AND (error_message IS NULL OR error_message = '');" | tr -d ' ')"
[[ "$suspicious_cancelled" == "0" ]] || fail "Found suspicious cancelled sync rows that look like completed rows: $suspicious_cancelled."
pass "No suspicious completed-to-cancelled sync history mutation pattern detected."

in_progress="$(wp_cmd option get nmkr_sync_in_progress 2>/dev/null || true)"
status="$(wp_cmd option get nmkr_sync_status 2>/dev/null || true)"
if [[ "$in_progress" == "1" || "$status" == "running" || "$status" == "in_progress" ]]; then
  notice "A sync appears to be in progress; Phase 1 smoke script did not start it."
else
  pass "Latest sync state is not marked in progress."
fi

debug_log="$WP_PATH/$NMKR_DEBUG_LOG_RELATIVE_PATH"
if [[ ! -f "$debug_log" ]]; then
  notice "Debug log not found at configured relative path; skipping debug.log check."
  exit 0
fi

lookback_minutes_re='^[0-9]+$'
[[ "$NMKR_DEBUG_LOG_LOOKBACK_MINUTES" =~ $lookback_minutes_re ]] || fail "NMKR_DEBUG_LOG_LOOKBACK_MINUTES must be numeric."

if find "$debug_log" -mmin "-$NMKR_DEBUG_LOG_LOOKBACK_MINUTES" -print -quit | grep -q .; then
  if tail -n 400 "$debug_log" | grep -Eq 'PHP Fatal error|PHP Parse error|PHP Warning|NMKR.*(Fatal|Error|Exception)'; then
    fail "Recent debug.log contains PHP/plugin errors. Inspect the private VM log directly; this script does not print log contents."
  fi

  pass "No fresh PHP/plugin errors found in recent debug.log tail."
else
  notice "debug.log has not changed within lookback window; no fresh errors to inspect."
fi
