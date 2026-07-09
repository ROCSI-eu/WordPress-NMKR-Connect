#!/usr/bin/env bash
set -Eeuo pipefail

WP_PATH="${WP_PATH:-}"
WP_CLI_BIN="${WP_CLI_BIN:-wp}"
NMKR_PLUGIN_SLUG="${NMKR_PLUGIN_SLUG:-nmkr-connect/nmkr-connect.php}"
NMKR_DB_STATE_ALLOW_ACTIVE_SYNC="${NMKR_DB_STATE_ALLOW_ACTIVE_SYNC:-false}"

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

sql_ident() {
  printf '`%s`' "$(printf "%s" "$1" | sed 's/`/``/g')"
}

query_scalar() {
  local sql="$1"
  wp_cli db query "$sql" --skip-column-names 2>/dev/null | tr -d '\r' | tail -n 1
}

query_count() {
  local sql="$1"
  local value
  value="$(query_scalar "$sql" || printf '0')"
  [[ "$value" =~ ^[0-9]+$ ]] || fail "Could not inspect database invariant count."
  printf '%s' "$value"
}

assert_zero_count() {
  local label="$1"
  local sql="$2"
  local count
  count="$(query_count "$sql")"
  (( count == 0 )) || fail "${label} invariant failed with ${count} offending aggregate row(s)."
  info "${label} invariant passed."
}

is_truthy() {
  local value
  value="$(printf '%s' "${1:-}" | tr '[:upper:]' '[:lower:]' | xargs)"
  case "$value" in
    1|true|yes|on|running|in_progress|processing|processing_projects|processing_tokens|initializing|pending)
      return 0
      ;;
    *)
      return 1
      ;;
  esac
}

info "Checking WordPress installation."
wp_cli core is-installed >/dev/null

info "Checking NMKR Connect plugin activation state."
wp_cli plugin is-active "$NMKR_PLUGIN_SLUG" >/dev/null

prefix="$(wp_cli db prefix)"
projects_table="${prefix}nmkr_projects"
tokens_table="${prefix}nmkr_tokens"
token_details_table="${prefix}nmkr_token_details"
sync_stats_table="${prefix}nmkr_sync_stats"
metrics_table="${prefix}nmkr_sync_metrics"
analytics_table="${prefix}nmkr_analytics"
connect_options="nmkr_connect_options"
last_sync_option="nmkr_last_sync_time"

projects_ident="$(sql_ident "$projects_table")"
tokens_ident="$(sql_ident "$tokens_table")"
token_details_ident="$(sql_ident "$token_details_table")"
sync_stats_ident="$(sql_ident "$sync_stats_table")"
metrics_ident="$(sql_ident "$metrics_table")"
analytics_ident="$(sql_ident "$analytics_table")"

info "Checking required NMKR database tables."
required_tables=("$projects_table" "$tokens_table" "$token_details_table" "$sync_stats_table" "$metrics_table" "$analytics_table")
for table in "${required_tables[@]}"; do
  escaped_table="$(sql_escape "$table")"
  exists="$(wp_cli db query "SHOW TABLES LIKE '${escaped_table}';" --skip-column-names 2>/dev/null || true)"
  [[ "$exists" == "$table" ]] || fail "Required NMKR table is missing."
done
info "Required NMKR database tables exist."

info "Checking required schema columns."
check_column() {
  local table="$1"
  local column="$2"
  local escaped_column
  escaped_column="$(sql_escape "$column")"
  local exists
  exists="$(wp_cli db query "SHOW COLUMNS FROM $(sql_ident "$table") LIKE '${escaped_column}';" --skip-column-names 2>/dev/null || true)"
  [[ -n "$exists" ]] || fail "Required schema column is missing."
}

for column in id project_uid project_name state synced_at hash; do check_column "$projects_table" "$column"; done
for column in id token_uid project_uid token_name state synced_at hash; do check_column "$tokens_table" "$column"; done
for column in id token_uid receiver_address sell_date synced_at hash; do check_column "$token_details_table" "$column"; done
for column in id sync_type start_time end_time status items_processed items_successful items_failed failure_breakdown; do check_column "$sync_stats_table" "$column"; done
for column in id last_sync_time total_projects total_tokens total_sync_duration total_api_time average_response_time api_requests memory_usage created_at; do check_column "$metrics_table" "$column"; done
for column in id event_ts event_type shortcode_type site_id meta_json created_at; do check_column "$analytics_table" "$column"; done
info "Required schema columns exist."

info "Checking required indexes and unique keys."
check_index() {
  local table="$1"
  local key="$2"
  local escaped_key
  escaped_key="$(sql_escape "$key")"
  local exists
  exists="$(wp_cli db query "SHOW INDEX FROM $(sql_ident "$table") WHERE Key_name = '${escaped_key}';" --skip-column-names 2>/dev/null || true)"
  [[ -n "$exists" ]] || fail "Required index or key is missing."
}
check_index "$projects_table" project_uid
check_index "$tokens_table" token_uid
check_index "$tokens_table" project_uid
check_index "$token_details_table" token_uid
check_index "$sync_stats_table" status
check_index "$analytics_table" ix_site_ts
check_index "$analytics_table" ix_event_ts
info "Required indexes and unique keys exist."

info "Checking activation/default options without printing values."
options_json="$(wp_cli option get "$connect_options" --format=json 2>/dev/null)" || fail "Required NMKR options object is missing or unreadable."
printf '%s' "$options_json" | python3 -c 'import json,sys; v=json.load(sys.stdin); required={"sync_profile","sync_batch_size","sync_batch_delay","sync_initial_interval","sync_max_interval","sync_interval_increase","sync_interval_decrease","sync_max_errors"}; assert isinstance(v, dict); missing=required-set(v); sys.exit(0 if not missing else 1)' || fail "Required NMKR option keys are missing."
api_present="$(wp_cli option get nmkr_api_key --format=json 2>/dev/null | python3 -c 'import json,sys; print(1 if str(json.load(sys.stdin)).strip() else 0)' 2>/dev/null || printf '0')"
if [[ "$api_present" != "1" ]]; then
  api_present="$(printf '%s' "$options_json" | python3 -c 'import json,sys; v=json.load(sys.stdin); print(1 if isinstance(v, dict) and str(v.get("api_key", "")).strip() else 0)' 2>/dev/null || printf '0')"
fi
[[ "$api_present" == "1" ]] || fail "API key is missing or empty."
info "API key is present and non-empty; value was not printed."

if [[ "$NMKR_DB_STATE_ALLOW_ACTIVE_SYNC" == "true" ]]; then
  info "Active sync-state failure checks were skipped by configuration."
else
  sync_option="$(wp_cli option get nmkr_sync_in_progress 2>/dev/null || true)"
  sync_transient="$(wp_cli transient get nmkr_sync_in_progress 2>/dev/null || true)"
  progress_transient="$(wp_cli transient get nmkr_sync_progress 2>/dev/null || true)"
  if is_truthy "$sync_option" || is_truthy "$sync_transient"; then
    fail "Active sync state detected; run Phase 8 validation only when no sync is active."
  fi
  if [[ "$progress_transient" =~ ^[0-9]+$ ]] && (( progress_transient >= 1 && progress_transient <= 99 )); then
    fail "Active sync state detected; run Phase 8 validation only when no sync is active."
  fi
  active_stats="$(query_count "SELECT COUNT(*) FROM ${sync_stats_ident} WHERE status IN ('initializing','processing_projects','processing_tokens','in_progress','running','pending') AND (end_time IS NULL OR end_time = '');")"
  (( active_stats == 0 )) || fail "Active sync state detected; run Phase 8 validation only when no sync is active."
  info "No active sync state detected."
fi

info "Checking database integrity invariants using aggregate counts only."
assert_zero_count "Duplicate project_uid" "SELECT COUNT(*) FROM (SELECT project_uid FROM ${projects_ident} GROUP BY project_uid HAVING COUNT(*) > 1) duplicates;"
assert_zero_count "Duplicate token_uid" "SELECT COUNT(*) FROM (SELECT token_uid FROM ${tokens_ident} GROUP BY token_uid HAVING COUNT(*) > 1) duplicates;"
assert_zero_count "Duplicate token detail token_uid" "SELECT COUNT(*) FROM (SELECT token_uid FROM ${token_details_ident} GROUP BY token_uid HAVING COUNT(*) > 1) duplicates;"
assert_zero_count "Token project relationship" "SELECT COUNT(*) FROM ${tokens_ident} t LEFT JOIN ${projects_ident} p ON p.project_uid = t.project_uid WHERE t.project_uid IS NOT NULL AND t.project_uid <> '' AND p.id IS NULL;"
assert_zero_count "Token detail relationship" "SELECT COUNT(*) FROM ${token_details_ident} d LEFT JOIN ${tokens_ident} t ON t.token_uid = d.token_uid WHERE d.token_uid IS NOT NULL AND d.token_uid <> '' AND t.id IS NULL;"
assert_zero_count "Project negative counts" "SELECT COUNT(*) FROM ${projects_ident} WHERE free < 0 OR sold < 0 OR reserved < 0 OR total < 0 OR blocked < 0 OR total_blocked < 0 OR total_tokens < 0;"
assert_zero_count "Token negative numeric values" "SELECT COUNT(*) FROM ${tokens_ident} WHERE token_amount < 0 OR price < 0;"
assert_zero_count "Sync metrics impossible values" "SELECT COUNT(*) FROM ${metrics_ident} WHERE total_projects < 0 OR total_tokens < 0 OR total_sync_duration < 0 OR total_api_time < 0 OR average_response_time < 0 OR api_requests < 0 OR memory_usage < 0 OR last_sync_time IS NULL;"
assert_zero_count "Sync stats impossible values" "SELECT COUNT(*) FROM ${sync_stats_ident} WHERE start_time IS NULL OR items_processed < 0 OR items_successful < 0 OR items_failed < 0 OR (end_time IS NOT NULL AND end_time < start_time);"
assert_zero_count "Completed sync stats end_time" "SELECT COUNT(*) FROM ${sync_stats_ident} WHERE status IN ('completed','success') AND (end_time IS NULL OR end_time = '');"
assert_zero_count "Completed sync stats item totals" "SELECT COUNT(*) FROM ${sync_stats_ident} WHERE status IN ('completed','success') AND items_processed > 0 AND (items_successful + items_failed) > items_processed;"

latest_metric_time="$(query_scalar "SELECT last_sync_time FROM ${metrics_ident} ORDER BY last_sync_time DESC, id DESC LIMIT 1;" || true)"
option_time="$(wp_cli option get "$last_sync_option" 2>/dev/null || true)"
if [[ -n "$latest_metric_time" && -n "$option_time" && "$latest_metric_time" != "$option_time" ]]; then
  fail "Latest sync timestamp option does not match latest metrics timestamp."
fi
info "Latest sync timestamp option matches metrics when both values exist."

info "PASS: NMKR Connect WP-CLI database-state checks completed without mutations."
