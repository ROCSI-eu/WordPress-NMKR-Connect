#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"; WP_CLI_BIN="${WP_CLI_BIN:-wp}"; export WP_CLI_BIN
export RUN_REAL_SYNC=false PW_SAVE_ARTIFACTS=false NMKR_RETAIN_AUTH_STATE=false NMKR_AJAX_SECURITY_REQUIRE_CONTRACT=true
fail(){ printf '%s: FAIL\n' "${1:-runner}"; exit 1; }; pass(){ printf '%s: PASS\n' "$1"; }
for v in WP_BASE_URL WP_ADMIN_USER WP_ADMIN_PASSWORD WP_PATH NMKR_MARKETING_USER NMKR_MARKETING_PASSWORD NMKR_AJAX_SECURITY_EXPECTED_SOURCE_SHA NMKR_DEPLOYED_PLUGIN_PATH NMKR_PRIVATE_RUN_ROOT; do [[ -n "${!v:-}" ]] || fail preflight; done
[[ "$NMKR_AJAX_SECURITY_EXPECTED_SOURCE_SHA" =~ ^[0-9a-fA-F]{40}$ ]] || fail preflight
canon(){ [[ -e "$1" && ! -L "$1" ]] || return 1; (cd "$1" 2>/dev/null && pwd -P); }
PRIVATE_ROOT="$(canon "$NMKR_PRIVATE_RUN_ROOT")" || fail private-root
WP_ROOT="$(canon "$WP_PATH")" || fail wordpress
DEPLOYED="$(canon "$NMKR_DEPLOYED_PLUGIN_PATH")" || fail deployed-source
owner="$(stat -c '%u' "$PRIVATE_ROOT" 2>/dev/null)"; mode="$(stat -c '%a' "$PRIVATE_ROOT" 2>/dev/null)"
[[ "$owner" == "$(id -u)" && "$mode" == 700 ]] || fail private-root
outside(){ local child="$1" parent="$2"; [[ "$child" != "$parent" && "$child" != "$parent"/* ]]; }
for excluded in "$ROOT" "$WP_ROOT" "$ROOT/playwright-report" "$ROOT/test-results"; do outside "$PRIVATE_ROOT" "$excluded" && outside "$excluded" "$PRIVATE_ROOT" || fail private-root; done
RUN_DIR="$(mktemp -d "$PRIVATE_ROOT/ajax-security.XXXXXXXX")" || fail private-root
LOG="$RUN_DIR/private.log"; : >"$LOG"; chmod 600 "$LOG"
export PLAYWRIGHT_HTML_REPORT="$RUN_DIR/playwright-report"
export PLAYWRIGHT_TEST_OUTPUT_DIR="$RUN_DIR/test-results"
export NMKR_AUTH_STATE_ROOT="$RUN_DIR/auth-state"
ACTIVE_PID=""; ACTIVE_PGID=""
reap_active(){
  [[ -n "$ACTIVE_PID" ]] || return 0
  if kill -0 "$ACTIVE_PID" 2>/dev/null; then
    kill -TERM -- "-$ACTIVE_PGID" 2>/dev/null || kill -TERM "$ACTIVE_PID" 2>/dev/null || true
    for _ in {1..50}; do kill -0 "$ACTIVE_PID" 2>/dev/null || break; sleep 0.1; done
    kill -KILL -- "-$ACTIVE_PGID" 2>/dev/null || true
  fi
  wait "$ACTIVE_PID" 2>/dev/null || true
  ACTIVE_PID=""; ACTIVE_PGID=""
}
cleanup(){ local status=$?; trap - EXIT INT TERM; reap_active; [[ -n "${RUN_DIR:-}" && -d "$RUN_DIR" ]] && rm -rf -- "$RUN_DIR"; exit "$status"; }
signal(){ local code="$1"; trap - INT TERM; reap_active; [[ -n "${RUN_DIR:-}" && -d "$RUN_DIR" ]] && rm -rf -- "$RUN_DIR"; trap - EXIT; exit "$code"; }
trap cleanup EXIT; trap 'signal 130' INT; trap 'signal 143' TERM
run(){
  local stage="$1" status; shift
  setsid "$@" >>"$LOG" 2>&1 & ACTIVE_PID=$!; ACTIVE_PGID=$ACTIVE_PID
  set +e; wait "$ACTIVE_PID"; status=$?; set -e
  ACTIVE_PID=""; ACTIVE_PGID=""
  [[ "$status" -eq 0 ]] || fail "$stage"
}
[[ "$(git -C "$ROOT" rev-parse HEAD 2>/dev/null)" == "$NMKR_AJAX_SECURITY_EXPECTED_SOURCE_SHA" && -z "$(git -C "$ROOT" status --porcelain 2>/dev/null)" ]] || fail source
[[ "$(git -C "$DEPLOYED" rev-parse HEAD 2>/dev/null)" == "$NMKR_AJAX_SECURITY_EXPECTED_SOURCE_SHA" && -z "$(git -C "$DEPLOYED" status --porcelain 2>/dev/null)" ]] || fail deployed-source
command -v "$WP_CLI_BIN" >/dev/null 2>&1 || fail wordpress
run wordpress "$WP_CLI_BIN" --path="$WP_ROOT" core is-installed --quiet
run wordpress "$WP_CLI_BIN" --path="$WP_ROOT" plugin is-active "${NMKR_PLUGIN_SLUG:-nmkr-connect/nmkr-connect.php}"
NMKR_DEPLOYED_PLUGIN_PATH="$DEPLOYED" NMKR_PLUGIN_SLUG="${NMKR_PLUGIN_SLUG:-nmkr-connect/nmkr-connect.php}" run deployed-source "$WP_CLI_BIN" --path="$WP_ROOT" eval '
$active=realpath(WP_PLUGIN_DIR."/".getenv("NMKR_PLUGIN_SLUG"));$expected=realpath(getenv("NMKR_DEPLOYED_PLUGIN_PATH")."/nmkr-connect.php");exit($active!==false&&$expected!==false&&hash_equals($expected,$active)?0:1);'
WP_BASE_URL="$WP_BASE_URL" run wordpress "$WP_CLI_BIN" --path="$WP_ROOT" eval 'exit(untrailingslashit(home_url())===untrailingslashit(getenv("WP_BASE_URL"))?0:1);'
WP_ADMIN_USER="$WP_ADMIN_USER" NMKR_MARKETING_USER="$NMKR_MARKETING_USER" run accounts "$WP_CLI_BIN" --path="$WP_ROOT" eval '
$find=function($id){$u=get_user_by("login",$id);if(!$u&&is_email($id))$u=get_user_by("email",$id);return $u;};$a=$find(getenv("WP_ADMIN_USER"));$m=$find(getenv("NMKR_MARKETING_USER"));
if(!$a||!$m||$a->ID===$m->ID)exit(1);if(!user_can($a,"nmkr_view_dashboard")||!user_can($a,"nmkr_view_analytics")||!user_can($a,"nmkr_manage_sync"))exit(2);
if($m->roles!==array("nmkr-marketing")||!user_can($m,"nmkr_view_analytics")||user_can($m,"nmkr_view_dashboard")||user_can($m,"nmkr_manage_sync"))exit(3);'
run preflight "$ROOT/scripts/nmkr-wpcli-ajax-security-state.sh" idle; pass preflight
PRE="$("$ROOT/scripts/nmkr-wpcli-ajax-security-state.sh" digest 2>>"$LOG")" || fail negative-state
run negative npm --prefix "$ROOT" run test:e2e:ajax-security -- --grep @negative --reporter=list
POST="$("$ROOT/scripts/nmkr-wpcli-ajax-security-state.sh" digest 2>>"$LOG")" || fail negative-state
[[ "$PRE" == "$POST" ]] || fail negative-state; pass negative-state
SYNC_PRE="$("$ROOT/scripts/nmkr-wpcli-ajax-security-state.sh" sync 2>>"$LOG")" || fail authorized-state
run authorized npm --prefix "$ROOT" run test:e2e:ajax-security -- --grep @authorized --reporter=list
SYNC_POST="$("$ROOT/scripts/nmkr-wpcli-ajax-security-state.sh" sync 2>>"$LOG")" || fail authorized-state
[[ "$SYNC_PRE" == "$SYNC_POST" ]] || fail authorized-state; pass authorized
run final-smoke "$ROOT/scripts/nmkr-wpcli-smoke.sh"; run final-db "$ROOT/scripts/nmkr-wpcli-db-state.sh"; run final-idle "$ROOT/scripts/nmkr-wpcli-ajax-security-state.sh" idle; pass final-state
