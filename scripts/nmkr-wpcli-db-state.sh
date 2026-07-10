#!/usr/bin/env bash
set -Eeuo pipefail

WP_PATH="${WP_PATH:-}"
WP_CLI_BIN="${WP_CLI_BIN:-wp}"
NMKR_PLUGIN_SLUG="${NMKR_PLUGIN_SLUG:-nmkr-connect/nmkr-connect.php}"
NMKR_DB_STATE_ALLOW_ACTIVE_SYNC="${NMKR_DB_STATE_ALLOW_ACTIVE_SYNC:-false}"
NMKR_DB_STATE_STALE_SYNC_MINUTES="${NMKR_DB_STATE_STALE_SYNC_MINUTES:-180}"

ACTIVE_SYNC_STATUSES_SQL="'initializing','processing_projects','processing_tokens','in_progress','running','pending'"
TERMINAL_SYNC_STATUSES_SQL="'completed','success','failed','error','stopped','cancelled'"
FAILURE_TERMINAL_SYNC_STATUSES_SQL="'failed','error','stopped','cancelled'"

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

normalize_scalar_output() {
  tr -d '\r' | sed '/^[[:space:]]*$/d' | tail -n 1 | sed 's/^[[:space:]]*//; s/[[:space:]]*$//'
}

query_scalar() {
  local label="$1"
  local sql="$2"
  local value

  if ! value="$(wp_cli db query "$sql" --skip-column-names --silent --raw 2>/dev/null | normalize_scalar_output)"; then
    fail "${label} database read failed."
  fi

  printf '%s' "$value"
}

query_optional_scalar() {
  local label="$1"
  local sql="$2"
  local value

  if ! value="$(wp_cli db query "$sql" --skip-column-names --silent --raw 2>/dev/null | normalize_scalar_output)"; then
    fail "${label} optional database read failed."
  fi

  printf '%s' "$value"
}

query_count() {
  local label="$1"
  local sql="$2"
  local value
  value="$(query_scalar "$label" "$sql")"
  [[ "$value" =~ ^[0-9]+$ ]] || fail "${label} invariant returned a non-numeric count."
  printf '%s' "$value"
}

read_option_value() {
  local label="$1"
  local option_name="$2"
  local escaped_option
  escaped_option="$(sql_escape "$option_name")"
  query_optional_scalar "$label" "SELECT option_value FROM ${options_ident} WHERE option_name = '${escaped_option}' LIMIT 1;"
}

assert_zero_count() {
  local label="$1"
  local sql="$2"
  local count
  count="$(query_count "$label" "$sql")"
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
options_table="${prefix}options"
connect_options="nmkr_connect_options"
last_sync_option="nmkr_last_sync_time"

projects_ident="$(sql_ident "$projects_table")"
tokens_ident="$(sql_ident "$tokens_table")"
token_details_ident="$(sql_ident "$token_details_table")"
sync_stats_ident="$(sql_ident "$sync_stats_table")"
metrics_ident="$(sql_ident "$metrics_table")"
analytics_ident="$(sql_ident "$analytics_table")"
options_ident="$(sql_ident "$options_table")"

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
for column in id sync_type start_time end_time status items_processed items_successful items_failed failure_breakdown updated_at; do check_column "$sync_stats_table" "$column"; done
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

if [[ ! "$NMKR_DB_STATE_STALE_SYNC_MINUTES" =~ ^[1-9][0-9]*$ ]]; then
  fail "NMKR_DB_STATE_STALE_SYNC_MINUTES must be a positive integer."
fi

stale_cutoff="$(wp_cli eval "echo gmdate('Y-m-d H:i:s', current_time('timestamp') - (${NMKR_DB_STATE_STALE_SYNC_MINUTES} * MINUTE_IN_SECONDS));" --skip-plugins --skip-themes 2>/dev/null | normalize_scalar_output)" || fail "Stale sync cutoff could not be calculated."
if [[ ! "$stale_cutoff" =~ ^[0-9]{4}-[0-9]{2}-[0-9]{2}[[:space:]][0-9]{2}:[0-9]{2}:[0-9]{2}$ ]]; then
  fail "Stale sync cutoff could not be calculated."
fi
stale_cutoff_escaped="$(sql_escape "$stale_cutoff")"

is_numeric_progress_marker() {
  local value
  value="$(printf '%s' "${1:-}" | xargs)"
  [[ "$value" =~ ^[0-9]+$ ]] && (( value >= 1 && value <= 99 ))
}

is_unexpired_transient_marker() {
  local timeout_value="$1"
  local now_epoch="$2"

  [[ -z "$timeout_value" || "$timeout_value" == "0" ]] && return 0
  [[ "$timeout_value" =~ ^[0-9]+$ ]] && (( timeout_value > now_epoch )) && return 0
  return 1
}

info "Checking sync-state invariants using aggregate counts only."
assert_zero_count "Unknown sync stats status" "SELECT COUNT(*) FROM ${sync_stats_ident} WHERE status IS NULL OR TRIM(status) = '' OR status NOT IN (${ACTIVE_SYNC_STATUSES_SQL},${TERMINAL_SYNC_STATUSES_SQL});"
assert_zero_count "Terminal sync stats end_time" "SELECT COUNT(*) FROM ${sync_stats_ident} WHERE status IN (${FAILURE_TERMINAL_SYNC_STATUSES_SQL}) AND (end_time IS NULL OR end_time = '');"
assert_zero_count "Active sync stats end_time" "SELECT COUNT(*) FROM ${sync_stats_ident} WHERE status IN (${ACTIVE_SYNC_STATUSES_SQL}) AND end_time IS NOT NULL AND end_time <> '';"
assert_zero_count "Stale active sync stats" "SELECT COUNT(*) FROM ${sync_stats_ident} WHERE status IN (${ACTIVE_SYNC_STATUSES_SQL}) AND (end_time IS NULL OR end_time = '') AND COALESCE(updated_at, start_time) < '${stale_cutoff_escaped}';"

active_stats="$(query_count "Active sync stats" "SELECT COUNT(*) FROM ${sync_stats_ident} WHERE status IN (${ACTIVE_SYNC_STATUSES_SQL}) AND (end_time IS NULL OR end_time = '');")"
(( active_stats <= 1 )) || fail "Multiple active sync stats invariant failed with ${active_stats} offending aggregate row(s)."
info "Multiple active sync stats invariant passed."

orphan_marker_count=0
if (( active_stats == 0 )); then
  now_epoch="$(date +%s)"
  [[ "$now_epoch" =~ ^[0-9]+$ ]] || fail "Current epoch could not be calculated."

  sync_option="$(read_option_value "Sync in-progress option" nmkr_sync_in_progress)"
  sync_transient="$(read_option_value "Sync in-progress transient" _transient_nmkr_sync_in_progress)"
  sync_transient_timeout="$(read_option_value "Sync in-progress transient timeout" _transient_timeout_nmkr_sync_in_progress)"
  progress_option="$(read_option_value "Sync progress option" nmkr_sync_progress)"
  progress_transient="$(read_option_value "Sync progress transient" _transient_nmkr_sync_progress)"
  progress_transient_timeout="$(read_option_value "Sync progress transient timeout" _transient_timeout_nmkr_sync_progress)"

  if is_truthy "$sync_option"; then
    orphan_marker_count=$(( orphan_marker_count + 1 ))
  fi
  if [[ -n "$sync_transient" ]] && is_unexpired_transient_marker "$sync_transient_timeout" "$now_epoch" && is_truthy "$sync_transient"; then
    orphan_marker_count=$(( orphan_marker_count + 1 ))
  fi
  if is_numeric_progress_marker "$progress_option"; then
    orphan_marker_count=$(( orphan_marker_count + 1 ))
  fi
  if [[ -n "$progress_transient" ]] && is_unexpired_transient_marker "$progress_transient_timeout" "$now_epoch" && is_numeric_progress_marker "$progress_transient"; then
    orphan_marker_count=$(( orphan_marker_count + 1 ))
  fi
fi
(( orphan_marker_count == 0 )) || fail "Orphaned active sync markers invariant failed with ${orphan_marker_count} offending aggregate row(s)."
info "Orphaned active sync markers invariant passed."

if (( active_stats == 0 )); then
  info "No active sync state detected."
elif [[ "$NMKR_DB_STATE_ALLOW_ACTIVE_SYNC" == "true" ]]; then
  info "One fresh active sync was allowed by configuration."
else
  fail "Active sync state detected; run Phase 8 validation only when no sync is active."
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

latest_metric_time="$(query_scalar "Latest sync metrics timestamp" "SELECT last_sync_time FROM ${metrics_ident} ORDER BY last_sync_time DESC, id DESC LIMIT 1;")"
option_time="$(wp_cli option get "$last_sync_option" 2>/dev/null || true)"
if [[ -n "$latest_metric_time" && -n "$option_time" && "$latest_metric_time" != "$option_time" ]]; then
  fail "Latest sync timestamp option does not match latest metrics timestamp."
fi
info "Latest sync timestamp option matches metrics when both values exist."

info "PASS: NMKR Connect WP-CLI database-state checks completed without mutations."
