#!/usr/bin/env bash
set -Eeuo pipefail

# Phase 2 VM-local test orchestrator. Keep console output public-safe: do not
# echo secrets, env file contents, deploy commands, or captured command logs.

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
if REPO_ROOT_FROM_GIT="$(git -C "$SCRIPT_DIR" rev-parse --show-toplevel 2>/dev/null)"; then
  REPO_ROOT="$REPO_ROOT_FROM_GIT"
else
  REPO_ROOT="$(cd -- "$SCRIPT_DIR/.." && pwd)"
fi

RUN_REAL_SYNC="${RUN_REAL_SYNC:-false}"
PW_SAVE_ARTIFACTS="${PW_SAVE_ARTIFACTS:-false}"
WP_CLI_BIN="${WP_CLI_BIN:-wp}"
NMKR_PLUGIN_SLUG="${NMKR_PLUGIN_SLUG:-nmkr-connect/nmkr-connect.php}"
NMKR_DEBUG_LOG_RELATIVE_PATH="${NMKR_DEBUG_LOG_RELATIVE_PATH:-wp-content/debug.log}"
NMKR_DEBUG_LOG_LOOKBACK_MINUTES="${NMKR_DEBUG_LOG_LOOKBACK_MINUTES:-30}"
NMKR_PHASE2_INSTALL_DEPS="${NMKR_PHASE2_INSTALL_DEPS:-auto}"
NMKR_PHASE2_INSTALL_BROWSER="${NMKR_PHASE2_INSTALL_BROWSER:-false}"
NMKR_PHASE2_SKIP_DEPLOY="${NMKR_PHASE2_SKIP_DEPLOY:-false}"
NMKR_PHASE2_WP_READY_TIMEOUT_SECONDS="${NMKR_PHASE2_WP_READY_TIMEOUT_SECONDS:-120}"
NMKR_PHASE2_WP_READY_INTERVAL_SECONDS="${NMKR_PHASE2_WP_READY_INTERVAL_SECONDS:-5}"
NMKR_PHASE2_WP_READY_HTTP_TIMEOUT_SECONDS="${NMKR_PHASE2_WP_READY_HTTP_TIMEOUT_SECONDS:-10}"

ENV_FILE=""
if [[ -n "${NMKR_PHASE2_ENV_FILE:-}" ]]; then
  ENV_FILE="$NMKR_PHASE2_ENV_FILE"
elif [[ -f "$REPO_ROOT/.env.tests" ]]; then
  ENV_FILE="$REPO_ROOT/.env.tests"
fi

if [[ -n "$ENV_FILE" ]]; then
  if [[ ! -f "$ENV_FILE" ]]; then
    printf 'ERROR: Phase 2 env file was configured but does not exist.\n' >&2
    exit 1
  fi
  set -a
  # shellcheck source=/dev/null
  source "$ENV_FILE"
  set +a
fi

export RUN_REAL_SYNC="${RUN_REAL_SYNC:-false}"
export PW_SAVE_ARTIFACTS="${PW_SAVE_ARTIFACTS:-false}"
export WP_CLI_BIN="${WP_CLI_BIN:-wp}"
export NMKR_PLUGIN_SLUG="${NMKR_PLUGIN_SLUG:-nmkr-connect/nmkr-connect.php}"
export NMKR_DEBUG_LOG_RELATIVE_PATH="${NMKR_DEBUG_LOG_RELATIVE_PATH:-wp-content/debug.log}"
export NMKR_DEBUG_LOG_LOOKBACK_MINUTES="${NMKR_DEBUG_LOG_LOOKBACK_MINUTES:-30}"
NMKR_PHASE2_INSTALL_DEPS="${NMKR_PHASE2_INSTALL_DEPS:-auto}"
NMKR_PHASE2_INSTALL_BROWSER="${NMKR_PHASE2_INSTALL_BROWSER:-false}"
NMKR_PHASE2_SKIP_DEPLOY="${NMKR_PHASE2_SKIP_DEPLOY:-false}"
NMKR_PHASE2_WP_READY_TIMEOUT_SECONDS="${NMKR_PHASE2_WP_READY_TIMEOUT_SECONDS:-120}"
NMKR_PHASE2_WP_READY_INTERVAL_SECONDS="${NMKR_PHASE2_WP_READY_INTERVAL_SECONDS:-5}"
NMKR_PHASE2_WP_READY_HTTP_TIMEOUT_SECONDS="${NMKR_PHASE2_WP_READY_HTTP_TIMEOUT_SECONDS:-10}"

LOG_PARENT="${NMKR_PHASE2_LOG_DIR:-$REPO_ROOT/.phase2-private}"
RUN_STAMP="$(date -u +%Y%m%dT%H%M%SZ)-$$"
RUN_DIR="$LOG_PARENT/runs/$RUN_STAMP"
umask 077
mkdir -p "$RUN_DIR"

DEPLOY_STATUS="SKIPPED"
DEPS_STATUS="SKIPPED"
BROWSER_STATUS="SKIPPED"
WORDPRESS_READY_STATUS="SKIPPED"
PLAYWRIGHT_STATUS="SKIPPED"
WPCLI_STATUS="SKIPPED"
DBSTATE_STATUS="SKIPPED"
FAILED_STEP=""
FAILED_LOG=""
EXIT_CODE=0

print_summary() {
  local result="PASS"
  if [[ "$EXIT_CODE" != "0" ]]; then
    result="FAIL"
  fi
  local commit="unknown"
  commit="$(git -C "$REPO_ROOT" rev-parse --short HEAD 2>/dev/null || printf 'unknown')"
  printf '\nPhase 2 summary\n'
  printf '  deploy: %s\n' "$DEPLOY_STATUS"
  printf '  dependencies: %s\n' "$DEPS_STATUS"
  printf '  browser: %s\n' "$BROWSER_STATUS"
  printf '  wordpress-ready: %s\n' "$WORDPRESS_READY_STATUS"
  printf '  playwright: %s\n' "$PLAYWRIGHT_STATUS"
  printf '  wpcli: %s\n' "$WPCLI_STATUS"
  printf '  db-state: %s\n' "$DBSTATE_STATUS"
  printf '  result: %s\n' "$result"
  printf '  commit: %s\n' "$commit"
  printf '  private run dir: %s\n' "$RUN_DIR"
  if [[ "$EXIT_CODE" != "0" ]]; then
    printf '  failed step: %s\n' "$FAILED_STEP"
    printf '  private log: %s\n' "$FAILED_LOG"
    printf '  next diagnostic command: less %q\n' "$FAILED_LOG"
  fi
}

fail_step() {
  FAILED_STEP="$1"
  FAILED_LOG="$2"
  EXIT_CODE="$3"
  print_summary
  exit "$EXIT_CODE"
}

require_tool() {
  command -v "$1" >/dev/null 2>&1 || fail_step "preflight" "$RUN_DIR/preflight.log" 1
}

validate_positive_integer() {
  local name="$1"
  local value="$2"
  if [[ ! "$value" =~ ^[1-9][0-9]*$ ]]; then
    printf '%s must be a positive integer.\n' "$name" >>"$RUN_DIR/preflight.log"
    fail_step "preflight" "$RUN_DIR/preflight.log" 1
  fi
}

check_wordpress_ready() {
  local log_file="$RUN_DIR/wordpress-ready.log"
  local body_file="$RUN_DIR/wordpress-ready-body.tmp"
  local maintenance_file="${WP_PATH%/}/.maintenance"
  local login_url="${WP_BASE_URL%/}/wp-login.php"
  local start_time deadline attempt now remaining sleep_for
  local maintenance_exists maintenance_details http_status curl_exit login_marker maintenance_text
  local maintenance_notice_printed="false"

  start_time="$(date +%s)"
  deadline=$((start_time + NMKR_PHASE2_WP_READY_TIMEOUT_SECONDS))
  attempt=0

  printf 'WordPress readiness check started at %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" >"$log_file"
  printf 'Timeout seconds: %s\n' "$NMKR_PHASE2_WP_READY_TIMEOUT_SECONDS" >>"$log_file"
  printf 'Interval seconds: %s\n' "$NMKR_PHASE2_WP_READY_INTERVAL_SECONDS" >>"$log_file"
  printf 'HTTP timeout seconds: %s\n' "$NMKR_PHASE2_WP_READY_HTTP_TIMEOUT_SECONDS" >>"$log_file"

  printf 'INFO: Checking WordPress readiness before Playwright.\n'

  while :; do
    attempt=$((attempt + 1))
    now="$(date +%s)"
    maintenance_exists="false"
    maintenance_details="not present"
    if [[ -e "$maintenance_file" ]]; then
      maintenance_exists="true"
      if maintenance_mtime="$(stat -c %Y "$maintenance_file" 2>/dev/null)"; then
        maintenance_details="present; age_seconds=$((now - maintenance_mtime)); mtime_utc=$(date -u -d "@$maintenance_mtime" +%Y-%m-%dT%H:%M:%SZ 2>/dev/null || printf 'unknown')"
      else
        maintenance_details="present; metadata unavailable"
      fi
    fi

    rm -f "$body_file" "$RUN_DIR/wordpress-ready-curl-error.tmp"
    curl_exit=0
    http_status="$(curl -ksSL --max-time "$NMKR_PHASE2_WP_READY_HTTP_TIMEOUT_SECONDS" -o "$body_file" -w '%{http_code}' "$login_url" 2>"$RUN_DIR/wordpress-ready-curl-error.tmp")" || curl_exit=$?
    if [[ "$curl_exit" != "0" ]]; then
      http_status="curl_failed"
    fi

    login_marker="false"
    maintenance_text="false"
    if [[ -f "$body_file" ]]; then
      if grep -qi 'id="user_login"' "$body_file"; then
        login_marker="true"
      fi
      if grep -Eqi 'Briefly unavailable for scheduled maintenance|Check back in a minute' "$body_file"; then
        maintenance_text="true"
      fi
    fi

    {
      printf 'attempt=%s timestamp_utc=%s\n' "$attempt" "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
      printf 'maintenance_file=%s\n' "$maintenance_details"
      printf 'http_status=%s\n' "$http_status"
      printf 'curl_exit=%s\n' "$curl_exit"
      if [[ -s "$RUN_DIR/wordpress-ready-curl-error.tmp" ]]; then
        printf 'curl_error_present=true\n'
      else
        printf 'curl_error_present=false\n'
      fi
      printf 'login_form_marker_found=%s\n' "$login_marker"
      printf 'maintenance_text_found=%s\n' "$maintenance_text"
    } >>"$log_file"

    if [[ "$maintenance_exists" == "false" && "$curl_exit" == "0" && "$http_status" == "200" && "$login_marker" == "true" && "$maintenance_text" == "false" ]]; then
      rm -f "$body_file" "$RUN_DIR/wordpress-ready-curl-error.tmp"
      printf 'INFO: WordPress readiness passed.\n'
      WORDPRESS_READY_STATUS="PASS"
      return 0
    fi

    if [[ ( "$maintenance_exists" == "true" || "$maintenance_text" == "true" ) && "$maintenance_notice_printed" == "false" ]]; then
      printf 'INFO: WordPress maintenance mode detected; waiting for readiness.\n'
      maintenance_notice_printed="true"
    fi

    now="$(date +%s)"
    if (( now >= deadline )); then
      printf 'WordPress readiness timed out after %s attempts.\n' "$attempt" >>"$log_file"
      rm -f "$body_file" "$RUN_DIR/wordpress-ready-curl-error.tmp"
      fail_step "wordpress-ready" "$log_file" 7
    fi

    remaining=$((deadline - now))
    sleep_for="$NMKR_PHASE2_WP_READY_INTERVAL_SECONDS"
    if (( sleep_for > remaining )); then
      sleep_for="$remaining"
    fi
    if (( sleep_for > 0 )); then
      sleep "$sleep_for"
    fi
  done
}

{
  printf 'Phase 2 preflight started at %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
  printf 'Repository root: %s\n' "$REPO_ROOT"
} >"$RUN_DIR/preflight.log"

for tool in git npm npx bash curl; do
  if ! command -v "$tool" >/dev/null 2>&1; then
    printf 'Required tool is missing: %s\n' "$tool" >>"$RUN_DIR/preflight.log"
    fail_step "preflight" "$RUN_DIR/preflight.log" 1
  fi
done

missing=()
for var_name in WP_BASE_URL WP_ADMIN_USER WP_ADMIN_PASSWORD WP_PATH; do
  if [[ -z "${!var_name:-}" ]]; then
    missing+=("$var_name")
  fi
done
if (( ${#missing[@]} > 0 )); then
  printf 'Missing required Phase 2 variables: %s\n' "${missing[*]}" >>"$RUN_DIR/preflight.log"
  fail_step "preflight" "$RUN_DIR/preflight.log" 1
fi

if [[ "$WP_CLI_BIN" =~ ^[A-Za-z0-9._+-]+$ ]] && ! command -v "$WP_CLI_BIN" >/dev/null 2>&1; then
  printf 'Configured WP_CLI_BIN executable was not found on PATH.\n' >>"$RUN_DIR/preflight.log"
  fail_step "preflight" "$RUN_DIR/preflight.log" 1
fi

validate_positive_integer "NMKR_PHASE2_WP_READY_TIMEOUT_SECONDS" "$NMKR_PHASE2_WP_READY_TIMEOUT_SECONDS"
validate_positive_integer "NMKR_PHASE2_WP_READY_INTERVAL_SECONDS" "$NMKR_PHASE2_WP_READY_INTERVAL_SECONDS"
validate_positive_integer "NMKR_PHASE2_WP_READY_HTTP_TIMEOUT_SECONDS" "$NMKR_PHASE2_WP_READY_HTTP_TIMEOUT_SECONDS"

case "$NMKR_PHASE2_INSTALL_DEPS" in
  auto|true|false) ;;
  *)
    printf 'NMKR_PHASE2_INSTALL_DEPS must be one of: auto, true, false.\n' >>"$RUN_DIR/preflight.log"
    fail_step "preflight" "$RUN_DIR/preflight.log" 1
    ;;
esac

cd "$REPO_ROOT"

if [[ "$NMKR_PHASE2_SKIP_DEPLOY" == "true" ]]; then
  DEPLOY_STATUS="SKIPPED"
else
  DEPLOY_STATUS="FAIL"
  if [[ -z "${NMKR_DEPLOY_COMMAND:-}" ]]; then
    printf 'NMKR_DEPLOY_COMMAND is required unless NMKR_PHASE2_SKIP_DEPLOY=true.\n' >"$RUN_DIR/deploy.log"
    fail_step "deploy" "$RUN_DIR/deploy.log" 2
  fi
  if bash -lc "$NMKR_DEPLOY_COMMAND" >"$RUN_DIR/deploy.log" 2>&1; then
    DEPLOY_STATUS="PASS"
  else
    fail_step "deploy" "$RUN_DIR/deploy.log" 2
  fi
fi

install_deps=false
case "$NMKR_PHASE2_INSTALL_DEPS" in
  true) install_deps=true ;;
  auto) [[ ! -d "$REPO_ROOT/node_modules" ]] && install_deps=true ;;
  false) install_deps=false ;;
esac

if [[ "$install_deps" == "true" ]]; then
  DEPS_STATUS="FAIL"
  if [[ ! -w "$REPO_ROOT" ]]; then
    printf 'Dependency installation is needed, but the repository root is not writable. Run Phase 2 from a user-owned checkout.\n' >"$RUN_DIR/npm-ci.log"
    fail_step "dependencies" "$RUN_DIR/npm-ci.log" 3
  fi
  if npm ci >"$RUN_DIR/npm-ci.log" 2>&1; then
    DEPS_STATUS="PASS"
  else
    fail_step "dependencies" "$RUN_DIR/npm-ci.log" 3
  fi
else
  DEPS_STATUS="SKIPPED"
fi

if [[ "$NMKR_PHASE2_INSTALL_BROWSER" == "true" ]]; then
  BROWSER_STATUS="FAIL"
  if npx playwright install chromium >"$RUN_DIR/playwright-install.log" 2>&1; then
    BROWSER_STATUS="PASS"
  else
    fail_step "browser" "$RUN_DIR/playwright-install.log" 3
  fi
else
  BROWSER_STATUS="SKIPPED"
fi

WORDPRESS_READY_STATUS="FAIL"
check_wordpress_ready

export PLAYWRIGHT_HTML_REPORT="$RUN_DIR/playwright-report"
export PLAYWRIGHT_TEST_OUTPUT_DIR="$RUN_DIR/test-results"

PLAYWRIGHT_STATUS="FAIL"
if npm run test:e2e >"$RUN_DIR/playwright.log" 2>&1; then
  PLAYWRIGHT_STATUS="PASS"
else
  fail_step "playwright" "$RUN_DIR/playwright.log" 4
fi

WPCLI_STATUS="FAIL"
if bash scripts/nmkr-wpcli-smoke.sh >"$RUN_DIR/wpcli.log" 2>&1; then
  WPCLI_STATUS="PASS"
else
  fail_step "wpcli" "$RUN_DIR/wpcli.log" 5
fi

DBSTATE_STATUS="FAIL"
if bash scripts/nmkr-wpcli-db-state.sh >"$RUN_DIR/wpcli-db-state.log" 2>&1; then
  DBSTATE_STATUS="PASS"
else
  fail_step "wpcli-db-state" "$RUN_DIR/wpcli-db-state.log" 6
fi

EXIT_CODE=0
print_summary
exit 0
