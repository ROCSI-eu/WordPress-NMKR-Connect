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
CALLER_PROFILE="${NMKR_PHASE2_PROFILE:-}"
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
NMKR_PHASE2_PROFILE="${NMKR_PHASE2_PROFILE:-}"
NMKR_RETAIN_AUTH_STATE="${NMKR_RETAIN_AUTH_STATE:-false}"

validate_boolean() {
  case "$2" in true|false) ;; *) printf '%s must be true or false.\n' "$1" >>"$RUN_DIR/preflight.log"; fail_step "preflight" "$RUN_DIR/preflight.log" 1 ;; esac
}


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
  cleanup_auth_state
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
  printf '  profile: %s\n' "${NMKR_PHASE2_PROFILE:-general}"
  printf '  source-integrity: %s\n' "${SOURCE_INTEGRITY:-SKIPPED}"
  printf '  deployed-integrity: %s\n' "${DEPLOYED_INTEGRITY:-SKIPPED}"
  printf '  readonly-policy: %s\n' "${READONLY_POLICY:-SKIPPED}"
  printf '  authentication-state-cleanup: %s\n' "$AUTH_STATE_CLEANUP"
  printf '  result: %s\n' "$result"
  printf '  commit: %s\n' "$commit"
  if [[ "$EXIT_CODE" != "0" ]]; then
    printf '  failed step: %s\n' "$FAILED_STEP"
    printf '  private diagnostics: available locally\n'
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
  local status_file="$RUN_DIR/wordpress-ready-status.tmp"
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

    rm -f "$body_file" "$status_file" "$RUN_DIR/wordpress-ready-curl-error.tmp"
    curl_exit=0
    run_external curl -ksSL --max-time "$NMKR_PHASE2_WP_READY_HTTP_TIMEOUT_SECONDS" -o "$body_file" -w '%{http_code}' "$login_url" >"$status_file" 2>"$RUN_DIR/wordpress-ready-curl-error.tmp" || curl_exit=$?
    http_status="$(cat "$status_file" 2>/dev/null || true)"
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

    if [[ "$curl_exit" == "0" && "$http_status" == "200" && "$login_marker" == "true" && "$maintenance_text" == "false" ]]; then
      if [[ "$maintenance_exists" == "true" ]]; then
        printf 'readiness_health=pass; maintenance_file_present_but_login_page_ready=true; note=maintenance marker appears stale or ignored\n' >>"$log_file"
      else
        printf 'readiness_health=pass\n' >>"$log_file"
      fi
      rm -f "$body_file" "$status_file" "$RUN_DIR/wordpress-ready-curl-error.tmp"
      printf 'INFO: WordPress readiness passed.\n'
      WORDPRESS_READY_STATUS="PASS"
      return 0
    fi

    printf 'readiness_health=not_ready\n' >>"$log_file"
    if [[ "$maintenance_text" == "true" && "$maintenance_notice_printed" == "false" ]]; then
      printf 'INFO: WordPress maintenance mode detected; waiting for readiness.\n'
      maintenance_notice_printed="true"
    fi

    now="$(date +%s)"
    if (( now >= deadline )); then
      printf 'WordPress readiness timed out after %s attempts.\n' "$attempt" >>"$log_file"
      rm -f "$body_file" "$status_file" "$RUN_DIR/wordpress-ready-curl-error.tmp"
      fail_step "wordpress-ready" "$log_file" 7
    fi

    remaining=$((deadline - now))
    sleep_for="$NMKR_PHASE2_WP_READY_INTERVAL_SECONDS"
    if (( sleep_for > remaining )); then
      sleep_for="$remaining"
    fi
    if (( sleep_for > 0 )); then
      run_external sleep "$sleep_for"
    fi
  done
}

missing=()
for var_name in WP_BASE_URL WP_ADMIN_USER WP_ADMIN_PASSWORD WP_PATH; do
  if [[ -z "${!var_name:-}" ]]; then
    missing+=("$var_name")
  fi
done
if (( ${#missing[@]} > 0 )); then
  printf 'ERROR: Missing required Phase 2 variables.\n' >&2
  exit 1
fi

# Resolve the private root before creation. It must remain outside web, checkout,
# and public Playwright output trees, including through symlinks.
CONFIGURED_LOG_PARENT="${NMKR_PHASE2_LOG_DIR:-${XDG_STATE_HOME:-$HOME/.local/state}/nmkr-connect}"
# The configured root is itself a trust boundary.  Check its final entry before
# canonicalization so a safe-looking link cannot redirect private run files.
if [[ -e "$CONFIGURED_LOG_PARENT" || -L "$CONFIGURED_LOG_PARENT" ]] && [[ -L "$CONFIGURED_LOG_PARENT" ]]; then
  printf 'ERROR: Phase 2 private root is unsafe.\n' >&2; exit 1
fi
LOG_PARENT="$(realpath -m -- "$CONFIGURED_LOG_PARENT")"
private_root_is_approved() {
  local candidate="$1" unsafe
  for unsafe in "$REPO_ROOT" "$WP_PATH" "$REPO_ROOT/playwright-report" "$REPO_ROOT/test-results"; do
    unsafe="$(realpath -m -- "$unsafe")"
    [[ "$candidate" != "$unsafe" && "$candidate" != "$unsafe"/* ]] || return 1
  done
}
private_directory_is_safe() {
  local directory="$1" owner mode
  [[ -d "$directory" && ! -L "$directory" ]] || return 1
  owner="$(stat -c %u -- "$directory")" || return 1
  mode="$(stat -c %a -- "$directory")" || return 1
  [[ "$owner" == "$EFFECTIVE_UID" && "$mode" =~ ^[0-7]{3,4}$ ]] || return 1
  (( (8#$mode & 8#022) == 0 ))
}
if ! command -v stat >/dev/null 2>&1 || ! command -v id >/dev/null 2>&1 || ! private_root_is_approved "$LOG_PARENT"; then
  printf 'ERROR: Phase 2 private root is unsafe.\n' >&2; exit 1
fi
EFFECTIVE_UID="$(id -u)" || { printf 'ERROR: Phase 2 private run directory is unsafe.\n' >&2; exit 1; }
umask 077
if ! mkdir -p -m 700 "$LOG_PARENT"; then
  printf 'ERROR: Phase 2 private run directory is unsafe.\n' >&2; exit 1
fi
if ! LOG_PARENT="$(realpath -e -- "$LOG_PARENT")" || ! private_root_is_approved "$LOG_PARENT" || ! private_directory_is_safe "$LOG_PARENT"; then
  printf 'ERROR: Phase 2 private run directory is unsafe.\n' >&2; exit 1
fi
RUNS_DIR="$LOG_PARENT/runs"
if [[ -e "$RUNS_DIR" || -L "$RUNS_DIR" ]]; then
  if [[ -L "$RUNS_DIR" || ! -d "$RUNS_DIR" ]]; then
    printf 'ERROR: Phase 2 private run directory is unsafe.\n' >&2; exit 1
  fi
else
  if ! mkdir -m 700 -- "$RUNS_DIR"; then
    printf 'ERROR: Phase 2 private run directory is unsafe.\n' >&2; exit 1
  fi
fi
if [[ -L "$RUNS_DIR" ]] || ! RUNS_DIR="$(realpath -e -- "$RUNS_DIR")" || [[ "$RUNS_DIR" != "$LOG_PARENT/runs" ]] || ! private_directory_is_safe "$RUNS_DIR"; then
  printf 'ERROR: Phase 2 private run directory is unsafe.\n' >&2; exit 1
fi
RUN_STAMP="$(date -u +%Y%m%dT%H%M%SZ)-$$"
RUN_DIR="$RUNS_DIR/$RUN_STAMP"
if ! mkdir -m 700 -- "$RUN_DIR" || [[ -L "$RUN_DIR" ]] || ! RUN_DIR="$(realpath -e -- "$RUN_DIR")" || [[ "$RUN_DIR" != "$RUNS_DIR/$RUN_STAMP" ]] || ! private_directory_is_safe "$RUN_DIR"; then
  printf 'ERROR: Phase 2 private run directory is unsafe.\n' >&2; exit 1
fi
{
  printf 'Phase 2 preflight started at %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
  printf 'Repository root: %s\n' "$REPO_ROOT"
} >"$RUN_DIR/preflight.log"
for tool in git npm npx bash curl setsid stat id; do
  if ! command -v "$tool" >/dev/null 2>&1; then
    printf 'Required tool is missing: %s\n' "$tool" >>"$RUN_DIR/preflight.log"
    fail_step "preflight" "$RUN_DIR/preflight.log" 1
  fi
done
AUTH_STATE_CLEANUP="PENDING"
ACTIVE_CHILD_PID=""
cleanup_auth_state() { [[ "${NMKR_RETAIN_AUTH_STATE:-false}" == true ]] && { AUTH_STATE_CLEANUP="RETAINED"; return; }; AUTH_STATE_CLEANUP="MANAGED_BY_WRAPPER"; }
run_external() {
  setsid "$@" &
  ACTIVE_CHILD_PID=$!
  local status=0
  wait "$ACTIVE_CHILD_PID" || status=$?
  ACTIVE_CHILD_PID=""
  return "$status"
}
terminate_active_child() {
  local pid="$ACTIVE_CHILD_PID" attempts=0
  [[ -n "$pid" ]] || return 0
  kill -TERM -- "-$pid" 2>/dev/null || true
  while kill -0 "$pid" 2>/dev/null && (( attempts < 10 )); do
    sleep 0.1
    attempts=$((attempts + 1))
  done
  kill -KILL -- "-$pid" 2>/dev/null || true
  wait "$pid" 2>/dev/null || true
  ACTIVE_CHILD_PID=""
}
handle_signal() { terminate_active_child; cleanup_auth_state; trap - EXIT; exit "$1"; }
trap cleanup_auth_state EXIT
trap 'handle_signal 130' INT
trap 'handle_signal 143' TERM

if [[ "$WP_CLI_BIN" =~ ^[A-Za-z0-9._+-]+$ ]] && ! command -v "$WP_CLI_BIN" >/dev/null 2>&1; then
  printf 'Configured WP_CLI_BIN executable was not found on PATH.\n' >>"$RUN_DIR/preflight.log"
  fail_step "preflight" "$RUN_DIR/preflight.log" 1
fi

validate_positive_integer "NMKR_PHASE2_WP_READY_TIMEOUT_SECONDS" "$NMKR_PHASE2_WP_READY_TIMEOUT_SECONDS"
validate_positive_integer "NMKR_PHASE2_WP_READY_INTERVAL_SECONDS" "$NMKR_PHASE2_WP_READY_INTERVAL_SECONDS"
validate_positive_integer "NMKR_PHASE2_WP_READY_HTTP_TIMEOUT_SECONDS" "$NMKR_PHASE2_WP_READY_HTTP_TIMEOUT_SECONDS"

if [[ -n "$CALLER_PROFILE" && "$CALLER_PROFILE" != "$NMKR_PHASE2_PROFILE" ]]; then
  printf 'Phase 2 profile conflict.\n' >>"$RUN_DIR/preflight.log"; fail_step "preflight" "$RUN_DIR/preflight.log" 1
fi
case "$NMKR_PHASE2_PROFILE" in ''|existing-readonly) ;; *) printf 'Unknown Phase 2 profile.\n' >>"$RUN_DIR/preflight.log"; fail_step "preflight" "$RUN_DIR/preflight.log" 1 ;; esac
for boolean in RUN_REAL_SYNC PW_SAVE_ARTIFACTS NMKR_PHASE2_INSTALL_BROWSER NMKR_PHASE2_SKIP_DEPLOY NMKR_RETAIN_AUTH_STATE; do validate_boolean "$boolean" "${!boolean}"; done

if [[ "$NMKR_PHASE2_PROFILE" == "existing-readonly" ]]; then
  READONLY_POLICY="ENFORCED"; SOURCE_INTEGRITY="FAIL"; DEPLOYED_INTEGRITY="SKIPPED"
  readonly_overrides=false
  for boolean in RUN_REAL_SYNC PW_SAVE_ARTIFACTS NMKR_PHASE2_SKIP_DEPLOY NMKR_PHASE2_INSTALL_BROWSER; do [[ "${!boolean}" != "false" || "$boolean" == NMKR_PHASE2_SKIP_DEPLOY ]] && readonly_overrides=true; done
  [[ "$NMKR_PHASE2_INSTALL_DEPS" != "false" ]] && readonly_overrides=true
  RUN_REAL_SYNC=false; PW_SAVE_ARTIFACTS=false; NMKR_PHASE2_SKIP_DEPLOY=true; NMKR_PHASE2_INSTALL_DEPS=false; NMKR_PHASE2_INSTALL_BROWSER=false; unset NMKR_DEPLOY_COMMAND
  [[ "$readonly_overrides" == true ]] && printf 'INFO: readonly overrides present.\n'
  [[ "${NMKR_PHASE2_EXPECTED_SOURCE_SHA:-}" =~ ^[0-9a-fA-F]{40}$ ]] || { printf 'Expected source SHA is invalid.\n' >>"$RUN_DIR/preflight.log"; fail_step "preflight" "$RUN_DIR/preflight.log" 1; }
  [[ "$(git -C "$REPO_ROOT" rev-parse HEAD)" == "$NMKR_PHASE2_EXPECTED_SOURCE_SHA" ]] || { printf 'Source SHA mismatch.\n' >>"$RUN_DIR/preflight.log"; fail_step "preflight" "$RUN_DIR/preflight.log" 1; }
  [[ -z "$(git -C "$REPO_ROOT" status --porcelain)" ]] || { printf 'Source worktree is dirty.\n' >>"$RUN_DIR/preflight.log"; fail_step "preflight" "$RUN_DIR/preflight.log" 1; }
  SOURCE_INTEGRITY="PASS"
  if [[ -n "${NMKR_DEPLOYED_PLUGIN_PATH:-}" ]]; then
    DEPLOYED_INTEGRITY="FAIL"
    git -C "$NMKR_DEPLOYED_PLUGIN_PATH" rev-parse --is-inside-work-tree >/dev/null 2>&1 && [[ "$(git -C "$NMKR_DEPLOYED_PLUGIN_PATH" rev-parse HEAD)" == "$NMKR_PHASE2_EXPECTED_SOURCE_SHA" ]] && [[ -z "$(git -C "$NMKR_DEPLOYED_PLUGIN_PATH" status --porcelain)" ]] || { printf 'Deployed worktree integrity check failed.\n' >>"$RUN_DIR/preflight.log"; fail_step "preflight" "$RUN_DIR/preflight.log" 1; }
    DEPLOYED_INTEGRITY="PASS"
  fi
  run_external node -e "require('@playwright/test')" >/dev/null 2>&1 || { printf 'Required Node dependencies are unavailable.\n' >>"$RUN_DIR/preflight.log"; fail_step "preflight" "$RUN_DIR/preflight.log" 1; }
  run_external node -e "const { chromium }=require('@playwright/test'); (async()=>{const b=await chromium.launch({headless:true}); await b.close();})().catch(()=>process.exit(1))" >/dev/null 2>&1 || { printf 'Configured Chromium is unavailable.\n' >>"$RUN_DIR/preflight.log"; fail_step "preflight" "$RUN_DIR/preflight.log" 1; }
fi

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
  if run_external bash -lc "$NMKR_DEPLOY_COMMAND" >"$RUN_DIR/deploy.log" 2>&1; then
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
  if run_external npm ci >"$RUN_DIR/npm-ci.log" 2>&1; then
    DEPS_STATUS="PASS"
  else
    fail_step "dependencies" "$RUN_DIR/npm-ci.log" 3
  fi
else
  DEPS_STATUS="SKIPPED"
fi

if [[ "$NMKR_PHASE2_INSTALL_BROWSER" == "true" ]]; then
  BROWSER_STATUS="FAIL"
  if run_external npx playwright install chromium >"$RUN_DIR/playwright-install.log" 2>&1; then
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
export NMKR_AUTH_STATE_ROOT="$RUN_DIR"
unset NMKR_AUTH_STATE_DIR NMKR_AUTH_STATE_PATH NMKR_AUTH_STATE_OWNER_TOKEN

PLAYWRIGHT_STATUS="FAIL"
if run_external npm run test:e2e >"$RUN_DIR/playwright.log" 2>&1; then
  PLAYWRIGHT_STATUS="PASS"
else
  fail_step "playwright" "$RUN_DIR/playwright.log" 4
fi

WPCLI_STATUS="FAIL"
if run_external bash scripts/nmkr-wpcli-smoke.sh >"$RUN_DIR/wpcli.log" 2>&1; then
  WPCLI_STATUS="PASS"
else
  fail_step "wpcli" "$RUN_DIR/wpcli.log" 5
fi

DBSTATE_STATUS="FAIL"
if run_external bash scripts/nmkr-wpcli-db-state.sh >"$RUN_DIR/wpcli-db-state.log" 2>&1; then
  DBSTATE_STATUS="PASS"
else
  fail_step "wpcli-db-state" "$RUN_DIR/wpcli-db-state.log" 6
fi

EXIT_CODE=0
print_summary
exit 0
