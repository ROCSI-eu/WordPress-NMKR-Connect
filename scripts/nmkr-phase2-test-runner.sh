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

# Arguments opt in to the unified operator workflow. Legacy environment-only
# invocations continue through the existing path unchanged.
OPERATOR_MODE=false
OP_STAGE= OP_CLASS= OP_PROFILE= OP_REVIEWED_SHA= OP_DEPLOYED_SHA=
OP_TARGET_SUITE= OP_DEPLOY_MODE= OP_RUNTIME_INTEGRITY=
declare -A OP_SEEN=()
operator_error() { printf 'ERROR: Invalid Phase 2 operator arguments.\n' >&2; exit 1; }
while (($#)); do
  OPERATOR_MODE=true
  option="$1"; shift
  case "$option" in
    --stage|--class|--profile|--reviewed-sha|--deployed-sha|--target-suite|--deploy-mode|--runtime-integrity) ;;
    *) operator_error ;;
  esac
  [[ -z "${OP_SEEN[$option]:-}" ]] || operator_error
  OP_SEEN[$option]=true
  (($#)) || operator_error
  value="$1"; shift
  [[ -n "$value" && "$value" != --* ]] || operator_error
  case "$option" in
    --stage) OP_STAGE="$value" ;; --class) OP_CLASS="$value" ;;
    --profile) OP_PROFILE="$value" ;; --reviewed-sha) OP_REVIEWED_SHA="$value" ;;
    --deployed-sha) OP_DEPLOYED_SHA="$value" ;; --target-suite) OP_TARGET_SUITE="$value" ;;
    --deploy-mode) OP_DEPLOY_MODE="$value" ;; --runtime-integrity) OP_RUNTIME_INTEGRITY="$value" ;;
  esac
done
if [[ "$OPERATOR_MODE" == true ]]; then
  for required in OP_STAGE OP_CLASS OP_PROFILE OP_REVIEWED_SHA OP_DEPLOYED_SHA OP_TARGET_SUITE OP_DEPLOY_MODE OP_RUNTIME_INTEGRITY; do
    [[ -n "${!required}" ]] || operator_error
  done
  case "$OP_STAGE" in pre-merge|post-merge) ;; *) operator_error ;; esac
  case "$OP_CLASS" in docs-metadata|test-tooling|ordinary-runtime|high-risk) ;; *) operator_error ;; esac
  case "$OP_PROFILE" in targeted-readonly|existing-readonly) ;; *) operator_error ;; esac
  [[ "$OP_REVIEWED_SHA" =~ ^[0-9a-fA-F]{40}$ && "$OP_DEPLOYED_SHA" =~ ^[0-9a-fA-F]{40}$ ]] || operator_error
  OP_REVIEWED_SHA="${OP_REVIEWED_SHA,,}"
  OP_DEPLOYED_SHA="${OP_DEPLOYED_SHA,,}"
  case "$OP_DEPLOY_MODE" in run|skip) ;; *) operator_error ;; esac
  case "$OP_RUNTIME_INTEGRITY" in true|false) ;; *) operator_error ;; esac
  if [[ "$OP_CLASS" == docs-metadata ]]; then
    printf 'ERROR: Phase 2 private validation is not applicable for docs/metadata; use public CI.\n' >&2
    exit 1
  fi
  [[ "$OP_CLASS" != high-risk || "$OP_PROFILE" == existing-readonly ]] || operator_error
fi

# Lock the parsed request before crossing the private configuration boundary.
# These internal names are deliberately readonly: the sourced file may set
# legacy OP_* names, but it cannot replace or downgrade the authoritative CLI
# request used by any later safety or validation decision.
readonly CLI_OPERATOR_MODE="$OPERATOR_MODE"
readonly CLI_STAGE="$OP_STAGE" CLI_CLASS="$OP_CLASS" CLI_PROFILE="$OP_PROFILE"
readonly CLI_REVIEWED_SHA="$OP_REVIEWED_SHA" CLI_DEPLOYED_SHA="$OP_DEPLOYED_SHA"
readonly CLI_TARGET_SUITE="$OP_TARGET_SUITE" CLI_DEPLOY_MODE="$OP_DEPLOY_MODE"
readonly CLI_RUNTIME_INTEGRITY="$OP_RUNTIME_INTEGRITY"

RUN_REAL_SYNC="${RUN_REAL_SYNC:-false}"
PW_SAVE_ARTIFACTS="${PW_SAVE_ARTIFACTS:-false}"
CALLER_PROFILE="${NMKR_PHASE2_PROFILE:-}"
CALLER_TARGET_SUITE="${NMKR_PHASE2_TARGET_SUITE:-}"
CALLER_RUNTIME_INTEGRITY="${NMKR_PHASE2_RUNTIME_INTEGRITY:-}"
WP_CLI_BIN="${WP_CLI_BIN:-wp}"
NMKR_PLUGIN_SLUG="${NMKR_PLUGIN_SLUG:-nmkr-connect/nmkr-connect.php}"
NMKR_DEBUG_LOG_RELATIVE_PATH="${NMKR_DEBUG_LOG_RELATIVE_PATH:-wp-content/debug.log}"
NMKR_DEBUG_LOG_LOOKBACK_MINUTES="${NMKR_DEBUG_LOG_LOOKBACK_MINUTES:-30}"
NMKR_PHASE2_INSTALL_DEPS="${NMKR_PHASE2_INSTALL_DEPS:-auto}"
NMKR_PHASE2_INSTALL_BROWSER="${NMKR_PHASE2_INSTALL_BROWSER:-false}"
NMKR_PHASE2_SKIP_DEPLOY="${NMKR_PHASE2_SKIP_DEPLOY:-false}"
NMKR_PHASE2_RUNTIME_INTEGRITY="${NMKR_PHASE2_RUNTIME_INTEGRITY:-false}"
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
  # The chosen file is an input boundary.  Its contents must not be able to
  # replace or clear the selection observed by downstream Playwright workers.
  if [[ ! -f "$ENV_FILE" ]]; then
    printf 'ERROR: Phase 2 env file was configured but does not exist.\n' >&2
    exit 1
  fi
  if ! SELECTED_ENV_FILE="$(realpath -e -- "$ENV_FILE" 2>/dev/null)" || [[ ! -f "$SELECTED_ENV_FILE" ]]; then
    printf 'ERROR: Phase 2 env file could not be resolved.\n' >&2
    exit 1
  fi
  SOURCE_DRAIN_PID=""
  SOURCE_DRAIN_FD=""
  SOURCE_PUBLIC_ERR_FD=""
  cleanup_source_diagnostics() {
    if [[ -n "$SOURCE_DRAIN_FD" ]]; then
      eval "exec ${SOURCE_DRAIN_FD}>&-"
      SOURCE_DRAIN_FD=""
    fi
    if [[ -n "$SOURCE_DRAIN_PID" ]]; then
      kill "$SOURCE_DRAIN_PID" 2>/dev/null || true
      wait "$SOURCE_DRAIN_PID" 2>/dev/null || true
      SOURCE_DRAIN_PID=""
    fi
    if [[ -n "$SOURCE_PUBLIC_ERR_FD" ]]; then
      eval "exec ${SOURCE_PUBLIC_ERR_FD}>&-"
      SOURCE_PUBLIC_ERR_FD=""
    fi
  }
  source_load_error() {
    local status=$?
    [[ "$status" -ne 0 ]] || status=1
    trap - ERR EXIT INT TERM
    printf 'ERROR: Phase 2 private configuration could not be loaded.\n' >&"$SOURCE_ERROR_FD"
    cleanup_source_diagnostics
    exit "$status"
  }
  source_boundary_signal() {
    cleanup_source_diagnostics
    trap - EXIT INT TERM
    exit "$1"
  }
  trap cleanup_source_diagnostics EXIT
  trap 'source_boundary_signal 130' INT
  trap 'source_boundary_signal 143' TERM
  # Drain private source output through an anonymous pipe. Nothing is written
  # beneath caller-selected TMPDIR (or any other persistent location), while
  # the drain's status still records whether the source emitted even one byte.
  coproc SOURCE_OUTPUT_DRAIN {
    if IFS= read -r -n 1; then
      cat >/dev/null
      exit 1
    fi
  }
  SOURCE_DRAIN_PID="$SOURCE_OUTPUT_DRAIN_PID"
  SOURCE_DRAIN_FD="${SOURCE_OUTPUT_DRAIN[1]}"
  exec {SOURCE_PUBLIC_ERR_FD}>&2
  readonly SOURCE_ERROR_FD="$SOURCE_PUBLIC_ERR_FD"
  set -a
  # shellcheck source=/dev/null
  # Keep source as a direct command: placing it in a conditional or command
  # list would suppress errexit for unhandled failures inside the private file.
  # EXIT also covers fatal shell errors (notably readonly-name assignments)
  # that terminate Bash without dispatching an ERR trap.
  trap source_load_error ERR EXIT
  source "$SELECTED_ENV_FILE" >&"$SOURCE_DRAIN_FD"
  trap - ERR EXIT
  set +a
  eval "exec ${SOURCE_DRAIN_FD}>&-"
  SOURCE_DRAIN_FD=""
  source_output_status=0
  wait "$SOURCE_DRAIN_PID" || source_output_status=$?
  SOURCE_DRAIN_PID=""
  if [[ "$source_output_status" -ne 0 ]]; then
    cleanup_source_diagnostics
    trap - EXIT INT TERM
    printf 'ERROR: Phase 2 private configuration could not be loaded.\n' >&2
    exit 1
  fi
  cleanup_source_diagnostics
  trap - EXIT INT TERM
  export NMKR_PHASE2_ENV_FILE="$SELECTED_ENV_FILE"
fi

if [[ "$CLI_OPERATOR_MODE" == true ]]; then
  operator_conflict=false
  check_operator_env() { local name="$1" expected="$2"; [[ -z "${!name+x}" || "${!name}" == "$expected" ]] || operator_conflict=true; }
  check_operator_env NMKR_PHASE2_STAGE "$CLI_STAGE"
  check_operator_env NMKR_PHASE2_VALIDATION_CLASS "$CLI_CLASS"
  check_operator_env NMKR_PHASE2_PROFILE "$CLI_PROFILE"
  check_operator_env NMKR_PHASE2_REVIEWED_SHA "$CLI_REVIEWED_SHA"
  check_operator_env NMKR_PHASE2_DEPLOYED_SHA "$CLI_DEPLOYED_SHA"
  check_operator_env NMKR_PHASE2_TARGET_SUITE "$CLI_TARGET_SUITE"
  check_operator_env NMKR_PHASE2_DEPLOY_MODE "$CLI_DEPLOY_MODE"
  check_operator_env NMKR_PHASE2_RUNTIME_INTEGRITY "$CLI_RUNTIME_INTEGRITY"
  [[ "$operator_conflict" == false ]] || { printf 'ERROR: Phase 2 operator selection conflicts with environment configuration.\n' >&2; exit 1; }
  NMKR_PHASE2_STAGE="$CLI_STAGE"; NMKR_PHASE2_VALIDATION_CLASS="$CLI_CLASS"
  NMKR_PHASE2_PROFILE="$CLI_PROFILE"; NMKR_PHASE2_REVIEWED_SHA="$CLI_REVIEWED_SHA"
  NMKR_PHASE2_DEPLOYED_SHA="$CLI_DEPLOYED_SHA"; NMKR_PHASE2_TARGET_SUITE="$CLI_TARGET_SUITE"
  NMKR_PHASE2_DEPLOY_MODE="$CLI_DEPLOY_MODE"; NMKR_PHASE2_RUNTIME_INTEGRITY="$CLI_RUNTIME_INTEGRITY"
  RUN_REAL_SYNC=false; PW_SAVE_ARTIFACTS=false
  NMKR_PHASE2_INSTALL_DEPS=false; NMKR_PHASE2_INSTALL_BROWSER=false
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
NMKR_PHASE2_TARGET_SUITE="${NMKR_PHASE2_TARGET_SUITE:-}"
NMKR_PHASE2_RUNTIME_INTEGRITY="${NMKR_PHASE2_RUNTIME_INTEGRITY:-false}"
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
RUNTIME_INTEGRITY_STATUS="SKIPPED"
FINAL_INTEGRITY="SKIPPED"
SELECTED_SUITE="full"
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
  if [[ "$CLI_OPERATOR_MODE" == true ]]; then
    printf '  stage: %s\n' "$CLI_STAGE"
    printf '  validation-class: %s\n' "$CLI_CLASS"
    printf '  reviewed-commit: %.12s\n' "$CLI_REVIEWED_SHA"
    printf '  expected-deployed-commit: %.12s\n' "$CLI_DEPLOYED_SHA"
    printf '  deploy-mode: %s\n' "$CLI_DEPLOY_MODE"
    printf '  reviewed-tree-equivalence: %s\n' "${TREE_EQUIVALENCE:-NOT_APPLICABLE}"
  fi
  printf '  deploy: %s\n' "$DEPLOY_STATUS"
  printf '  dependencies: %s\n' "$DEPS_STATUS"
  printf '  browser: %s\n' "$BROWSER_STATUS"
  printf '  wordpress-ready: %s\n' "$WORDPRESS_READY_STATUS"
  printf '  playwright: %s\n' "$PLAYWRIGHT_STATUS"
  printf '  wpcli: %s\n' "$WPCLI_STATUS"
  printf '  db-state: %s\n' "$DBSTATE_STATUS"
  printf '  targeted-suite: %s\n' "$SELECTED_SUITE"
  printf '  runtime-integrity: %s\n' "$RUNTIME_INTEGRITY_STATUS"
  printf '  final-integrity: %s\n' "$FINAL_INTEGRITY"
  printf '  profile: %s\n' "${NMKR_PHASE2_PROFILE:-general}"
  printf '  source-integrity: %s\n' "${SOURCE_INTEGRITY:-SKIPPED}"
  printf '  deployed-integrity: %s\n' "${DEPLOYED_INTEGRITY:-SKIPPED}"
  printf '  readonly-policy: %s\n' "${READONLY_POLICY:-SKIPPED}"
  printf '  authentication-state-cleanup: %s\n' "$AUTH_STATE_CLEANUP"
  printf '  result: %s\n' "$result"
  [[ "$CLI_OPERATOR_MODE" != true ]] || printf '  rollback: NOT_ATTEMPTED\n'
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

# These values are required by the runner and by its Playwright/WP-CLI child
# processes. Private configuration may use ordinary shell assignments; do not
# require operators to add export statements to make the validated values
# available downstream.
export WP_BASE_URL WP_ADMIN_USER WP_ADMIN_PASSWORD WP_PATH

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
git_head_and_clean() {
  local worktree="$1" expected_sha="$2" head status
  if ! head="$(git -C "$worktree" rev-parse HEAD 2>/dev/null)" || [[ "$head" != "$expected_sha" ]]; then
    return 1
  fi
  if ! status="$(git -C "$worktree" -c core.fileMode=true status --porcelain 2>/dev/null)" || [[ -n "$status" ]]; then
    return 1
  fi
  # status intentionally honors index hints that can hide modified tracked
  # bytes. Exact-head validation must reject either hint rather than trust it.
  if ! git -C "$worktree" ls-files -v -z 2>/dev/null |
    while IFS= read -r -d '' index_entry; do
      [[ "${index_entry:0:1}" != S && "${index_entry:0:1}" != [a-z] ]] || exit 1
    done; then
    return 1
  fi
}
bind_active_plugin_to_deployed_worktree() {
  local deployed_root git_root expected_file active_file active_file_output wp_cli_args
  [[ "$NMKR_PLUGIN_SLUG" != /* && "$NMKR_PLUGIN_SLUG" != *//* && "$NMKR_PLUGIN_SLUG" != ../* && "$NMKR_PLUGIN_SLUG" != */../* && "$NMKR_PLUGIN_SLUG" != */.. && "$NMKR_PLUGIN_SLUG" != ./* && "$NMKR_PLUGIN_SLUG" != */./* ]] || return 1
  [[ ! -L "$NMKR_DEPLOYED_PLUGIN_PATH" ]] || return 1
  deployed_root="$(realpath -e -- "$NMKR_DEPLOYED_PLUGIN_PATH" 2>/dev/null)" || return 1
  [[ -d "$deployed_root" ]] || return 1
  git_root="$(git -C "$deployed_root" rev-parse --show-toplevel 2>/dev/null)" || return 1
  git_root="$(realpath -e -- "$git_root" 2>/dev/null)" || return 1
  [[ "$git_root" == "$deployed_root" ]] || return 1
  expected_file="$deployed_root/${NMKR_PLUGIN_SLUG##*/}"
  [[ -f "$expected_file" && ! -L "$expected_file" ]] || return 1
  [[ "$(realpath -e -- "$expected_file" 2>/dev/null)" == "$expected_file" ]] || return 1
  wp_cli_args=("$WP_CLI_BIN")
  [[ -z "$WP_PATH" ]] || wp_cli_args+=("--path=$WP_PATH")
  wp_cli_args+=(eval 'require_once ABSPATH . "wp-admin/includes/plugin.php"; $slug = getenv("NMKR_PLUGIN_SLUG"); if (!is_string($slug) || $slug === "" || validate_file($slug) !== 0 || !is_plugin_active($slug)) { exit(1); } $file = realpath(WP_PLUGIN_DIR . "/" . $slug); if ($file === false || !is_file($file)) { exit(1); } echo $file;')
  active_file_output="$RUN_DIR/active-plugin-path.tmp"
  if ! run_external "${wp_cli_args[@]}" >"$active_file_output" 2>/dev/null; then
    rm -f "$active_file_output"
    return 1
  fi
  active_file="$(cat "$active_file_output")" || { rm -f "$active_file_output"; return 1; }
  rm -f "$active_file_output"
  [[ "$active_file" == "$expected_file" ]]
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
if [[ -n "$CALLER_TARGET_SUITE" && "$CALLER_TARGET_SUITE" != "$NMKR_PHASE2_TARGET_SUITE" ]]; then
  printf 'Phase 2 target suite conflict.\n' >>"$RUN_DIR/preflight.log"; fail_step "preflight" "$RUN_DIR/preflight.log" 1
fi
if [[ -n "$CALLER_RUNTIME_INTEGRITY" && "$CALLER_RUNTIME_INTEGRITY" != "$NMKR_PHASE2_RUNTIME_INTEGRITY" ]]; then
  printf 'Phase 2 runtime integrity conflict.\n' >>"$RUN_DIR/preflight.log"; fail_step "preflight" "$RUN_DIR/preflight.log" 1
fi
case "$NMKR_PHASE2_PROFILE" in ''|existing-readonly|targeted-readonly) ;; *) printf 'Unknown Phase 2 profile.\n' >>"$RUN_DIR/preflight.log"; fail_step "preflight" "$RUN_DIR/preflight.log" 1 ;; esac
for boolean in RUN_REAL_SYNC PW_SAVE_ARTIFACTS NMKR_PHASE2_INSTALL_BROWSER NMKR_PHASE2_SKIP_DEPLOY NMKR_PHASE2_RUNTIME_INTEGRITY NMKR_RETAIN_AUTH_STATE; do validate_boolean "$boolean" "${!boolean}"; done

case "$NMKR_PHASE2_TARGET_SUITE" in
  settings|dashboard|projects|shortcodes|analytics|ajax-security|sync-state|sync-run-authority|sync-resilience|sync-final-state) ;;
  '') [[ "$NMKR_PHASE2_PROFILE" != "targeted-readonly" ]] || { printf 'Target suite is required.\n' >>"$RUN_DIR/preflight.log"; fail_step "preflight" "$RUN_DIR/preflight.log" 1; } ;;
  *) printf 'Unknown target suite.\n' >>"$RUN_DIR/preflight.log"; fail_step "preflight" "$RUN_DIR/preflight.log" 1 ;;
esac
if [[ "$CLI_OPERATOR_MODE" == true && "$NMKR_PHASE2_TARGET_SUITE" == ajax-security ]]; then
  printf 'Specialized AJAX security validation must use its separate runner.\n' >>"$RUN_DIR/preflight.log"; fail_step "preflight" "$RUN_DIR/preflight.log" 1
fi
if [[ "$NMKR_PHASE2_PROFILE" != "targeted-readonly" && -n "$NMKR_PHASE2_TARGET_SUITE" && "$CLI_OPERATOR_MODE" != true ]]; then
  printf 'Target suite requires targeted-readonly profile.\n' >>"$RUN_DIR/preflight.log"; fail_step "preflight" "$RUN_DIR/preflight.log" 1
fi
[[ "$NMKR_PHASE2_PROFILE" != "targeted-readonly" ]] || SELECTED_SUITE="$NMKR_PHASE2_TARGET_SUITE"

if [[ "$NMKR_PHASE2_PROFILE" == "existing-readonly" || "$NMKR_PHASE2_PROFILE" == "targeted-readonly" ]]; then
  READONLY_POLICY="ENFORCED"; SOURCE_INTEGRITY="FAIL"; DEPLOYED_INTEGRITY="SKIPPED"
  readonly_overrides=false
  for boolean in RUN_REAL_SYNC PW_SAVE_ARTIFACTS NMKR_PHASE2_INSTALL_BROWSER; do [[ "${!boolean}" != "false" ]] && readonly_overrides=true; done
  [[ "$NMKR_PHASE2_INSTALL_DEPS" != "false" ]] && readonly_overrides=true
  RUN_REAL_SYNC=false; PW_SAVE_ARTIFACTS=false; NMKR_PHASE2_INSTALL_DEPS=false; NMKR_PHASE2_INSTALL_BROWSER=false
  if [[ "$CLI_OPERATOR_MODE" != true ]]; then NMKR_PHASE2_SKIP_DEPLOY=true; unset NMKR_DEPLOY_COMMAND; fi
  [[ "$readonly_overrides" == true ]] && printf 'INFO: readonly overrides present.\n'
  EXPECTED_SOURCE_SHA="${NMKR_PHASE2_EXPECTED_SOURCE_SHA:-}"
  EXPECTED_DEPLOYED_SHA="$EXPECTED_SOURCE_SHA"
  if [[ "$CLI_OPERATOR_MODE" == true ]]; then
    EXPECTED_SOURCE_SHA="$CLI_DEPLOYED_SHA"; EXPECTED_DEPLOYED_SHA="$CLI_DEPLOYED_SHA"
    if [[ "$CLI_STAGE" == pre-merge ]]; then
      [[ "$CLI_REVIEWED_SHA" == "$CLI_DEPLOYED_SHA" ]] || { printf 'Pre-merge reviewed/deployed identity mismatch.\n' >>"$RUN_DIR/preflight.log"; fail_step "preflight" "$RUN_DIR/preflight.log" 1; }
      EXPECTED_SOURCE_SHA="$CLI_REVIEWED_SHA"; TREE_EQUIVALENCE="NOT_APPLICABLE"
    else
      reviewed_commit="$(git -C "$REPO_ROOT" rev-parse --verify "$CLI_REVIEWED_SHA^{commit}" 2>/dev/null || true)"
      deployed_commit="$(git -C "$REPO_ROOT" rev-parse --verify "$CLI_DEPLOYED_SHA^{commit}" 2>/dev/null || true)"
      reviewed_tree=""; deployed_tree=""
      if [[ "$reviewed_commit" == "$CLI_REVIEWED_SHA" && "$deployed_commit" == "$CLI_DEPLOYED_SHA" ]]; then
        reviewed_tree="$(git -C "$REPO_ROOT" rev-parse --verify "$reviewed_commit^{tree}" 2>/dev/null || true)"
        deployed_tree="$(git -C "$REPO_ROOT" rev-parse --verify "$deployed_commit^{tree}" 2>/dev/null || true)"
      fi
      if [[ -z "$reviewed_tree" || -z "$deployed_tree" || "$reviewed_tree" != "$deployed_tree" ]]; then
        TREE_EQUIVALENCE="NOT_ESTABLISHED"
        printf 'Reviewed/merged equivalence was not established; deeper review and validation are required.\n' >>"$RUN_DIR/preflight.log"
        fail_step "preflight" "$RUN_DIR/preflight.log" 1
      fi
      TREE_EQUIVALENCE="PASS"
    fi
  fi
  [[ "$EXPECTED_SOURCE_SHA" =~ ^[0-9a-fA-F]{40}$ ]] || { printf 'Expected source SHA is invalid.\n' >>"$RUN_DIR/preflight.log"; fail_step "preflight" "$RUN_DIR/preflight.log" 1; }
  git_head_and_clean "$REPO_ROOT" "$EXPECTED_SOURCE_SHA" || { printf 'Source worktree integrity check failed.\n' >>"$RUN_DIR/preflight.log"; fail_step "preflight" "$RUN_DIR/preflight.log" 1; }
  SOURCE_INTEGRITY="PASS"
  if [[ ( "$NMKR_PHASE2_PROFILE" == "targeted-readonly" || "$CLI_OPERATOR_MODE" == true ) && -z "${NMKR_DEPLOYED_PLUGIN_PATH:-}" ]]; then
    printf 'Readonly workflow requires deployed plugin path.\n' >>"$RUN_DIR/preflight.log"; fail_step "preflight" "$RUN_DIR/preflight.log" 1
  fi
  if [[ -n "${NMKR_DEPLOYED_PLUGIN_PATH:-}" && !( "$CLI_OPERATOR_MODE" == true && "$CLI_DEPLOY_MODE" == run ) ]]; then
    DEPLOYED_INTEGRITY="FAIL"
    git_head_and_clean "$NMKR_DEPLOYED_PLUGIN_PATH" "$EXPECTED_DEPLOYED_SHA" || { printf 'Deployed worktree integrity check failed.\n' >>"$RUN_DIR/preflight.log"; fail_step "preflight" "$RUN_DIR/preflight.log" 1; }
    DEPLOYED_INTEGRITY="PASS"
  fi
  if [[ ( "$NMKR_PHASE2_PROFILE" == "targeted-readonly" && "$CLI_OPERATOR_MODE" != true ) || ( "$CLI_OPERATOR_MODE" == true && "$CLI_DEPLOY_MODE" == skip ) ]]; then
    bind_active_plugin_to_deployed_worktree || { printf 'Active plugin deployment binding failed.\n' >>"$RUN_DIR/preflight.log"; fail_step "preflight" "$RUN_DIR/preflight.log" 1; }
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

if [[ "$CLI_OPERATOR_MODE" == true && "$CLI_DEPLOY_MODE" == skip ]]; then
  DEPLOY_STATUS="SKIPPED"
elif [[ "$NMKR_PHASE2_SKIP_DEPLOY" == "true" && "$CLI_OPERATOR_MODE" != true ]]; then
  DEPLOY_STATUS="SKIPPED"
else
  DEPLOY_STATUS="FAIL"
  if [[ -z "${NMKR_DEPLOY_COMMAND:-}" ]]; then
    printf 'NMKR_DEPLOY_COMMAND is required unless deployment is skipped.\n' >"$RUN_DIR/deploy.log"
    fail_step "deploy" "$RUN_DIR/deploy.log" 2
  fi
  if run_external bash -lc "$NMKR_DEPLOY_COMMAND" >"$RUN_DIR/deploy.log" 2>&1; then
    DEPLOY_STATUS="PASS"
  else
    fail_step "deploy" "$RUN_DIR/deploy.log" 2
  fi
  if [[ "$CLI_OPERATOR_MODE" == true ]]; then
    [[ -n "${NMKR_DEPLOYED_PLUGIN_PATH:-}" ]] || fail_step "deploy-integrity" "$RUN_DIR/deploy.log" 1
    DEPLOYED_INTEGRITY="FAIL"
    git_head_and_clean "$NMKR_DEPLOYED_PLUGIN_PATH" "$EXPECTED_DEPLOYED_SHA" || fail_step "deploy-integrity" "$RUN_DIR/deploy.log" 1
    bind_active_plugin_to_deployed_worktree || fail_step "deploy-integrity" "$RUN_DIR/deploy.log" 1
    DEPLOYED_INTEGRITY="PASS"
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

if [[ "$NMKR_PHASE2_RUNTIME_INTEGRITY" == "true" ]]; then
  RUNTIME_INTEGRITY_STATUS="FAIL"
  [[ -n "${NMKR_DEPLOYED_PLUGIN_PATH:-}" ]] || { printf 'Runtime integrity requires deployed plugin path.\n' >"$RUN_DIR/runtime-integrity.log"; fail_step "runtime-integrity" "$RUN_DIR/runtime-integrity.log" 1; }
  if [[ -z "$NMKR_PHASE2_PROFILE" ]]; then
    runtime_source_sha="$(git -C "$REPO_ROOT" rev-parse HEAD 2>/dev/null)" || { printf 'Runtime deployment integrity could not be established.\n' >"$RUN_DIR/runtime-integrity.log"; fail_step "runtime-integrity" "$RUN_DIR/runtime-integrity.log" 1; }
    git_head_and_clean "$REPO_ROOT" "$runtime_source_sha" &&
      git_head_and_clean "$NMKR_DEPLOYED_PLUGIN_PATH" "$runtime_source_sha" || { printf 'Runtime deployment integrity could not be established.\n' >"$RUN_DIR/runtime-integrity.log"; fail_step "runtime-integrity" "$RUN_DIR/runtime-integrity.log" 1; }
  fi
  if [[ "$NMKR_PHASE2_PROFILE" != "targeted-readonly" ]]; then
    bind_active_plugin_to_deployed_worktree || { printf 'Active plugin deployment binding failed.\n' >"$RUN_DIR/runtime-integrity.log"; fail_step "runtime-integrity" "$RUN_DIR/runtime-integrity.log" 1; }
  fi
  run_external "$REPO_ROOT/scripts/nmkr-ajax-runtime-integrity.sh" "$NMKR_DEPLOYED_PLUGIN_PATH" >"$RUN_DIR/runtime-integrity.log" 2>&1 || fail_step "runtime-integrity" "$RUN_DIR/runtime-integrity.log" 1
  RUNTIME_INTEGRITY_STATUS="PASS"
fi

WORDPRESS_READY_STATUS="FAIL"
check_wordpress_ready

export PLAYWRIGHT_HTML_REPORT="$RUN_DIR/playwright-report"
export PLAYWRIGHT_TEST_OUTPUT_DIR="$RUN_DIR/test-results"
export NMKR_AUTH_STATE_ROOT="$RUN_DIR"
unset NMKR_AUTH_STATE_DIR NMKR_AUTH_STATE_PATH NMKR_AUTH_STATE_OWNER_TOKEN

PLAYWRIGHT_STATUS="FAIL"
if [[ "$NMKR_PHASE2_PROFILE" == "targeted-readonly" ]]; then
  playwright_command=(npm run "test:e2e:$NMKR_PHASE2_TARGET_SUITE")
else
  playwright_command=(npm run test:e2e)
fi
if run_external "${playwright_command[@]}" >"$RUN_DIR/playwright.log" 2>&1; then
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

if [[ "$NMKR_PHASE2_PROFILE" == "targeted-readonly" ]]; then
  DBSTATE_STATUS="SKIPPED"
else
  DBSTATE_STATUS="FAIL"
  if run_external bash scripts/nmkr-wpcli-db-state.sh >"$RUN_DIR/wpcli-db-state.log" 2>&1; then
    DBSTATE_STATUS="PASS"
  else
    fail_step "wpcli-db-state" "$RUN_DIR/wpcli-db-state.log" 6
  fi
fi

if [[ "$NMKR_PHASE2_PROFILE" == "existing-readonly" || "$NMKR_PHASE2_PROFILE" == "targeted-readonly" ]]; then
  FINAL_INTEGRITY="FAIL"
  git_head_and_clean "$REPO_ROOT" "$EXPECTED_SOURCE_SHA" || fail_step "final-integrity" "$RUN_DIR/preflight.log" 1
  if [[ -n "${NMKR_DEPLOYED_PLUGIN_PATH:-}" ]]; then
    git_head_and_clean "$NMKR_DEPLOYED_PLUGIN_PATH" "$EXPECTED_DEPLOYED_SHA" || fail_step "final-integrity" "$RUN_DIR/preflight.log" 1
  fi
  FINAL_INTEGRITY="PASS"
fi

EXIT_CODE=0
print_summary
exit 0
