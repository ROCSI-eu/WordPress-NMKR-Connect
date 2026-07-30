#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"; WP_CLI_BIN="${WP_CLI_BIN:-wp}"; export WP_CLI_BIN
export RUN_REAL_SYNC=false PW_SAVE_ARTIFACTS=false NMKR_RETAIN_AUTH_STATE=false
fail(){ printf '%s: FAIL\n' "$1"; exit 1; }; pass(){ printf '%s: PASS\n' "$1"; }
for v in WP_BASE_URL WP_ADMIN_USER WP_ADMIN_PASSWORD WP_PATH NMKR_MARKETING_USER NMKR_MARKETING_PASSWORD NMKR_AJAX_SECURITY_EXPECTED_SOURCE_SHA; do [[ -n "${!v:-}" ]] || fail preflight; done
[[ "$NMKR_AJAX_SECURITY_EXPECTED_SOURCE_SHA" =~ ^[0-9a-fA-F]{40}$ ]] || fail preflight
RUN_DIR="$(mktemp -d "${TMPDIR:-/tmp}/nmkr-ajax-security.XXXXXXXX")" || fail preflight
chmod 700 "$RUN_DIR"; LOG="$RUN_DIR/private.log"
cleanup(){ rm -rf "$RUN_DIR"; }
trap cleanup EXIT INT TERM
run(){ "$@" >>"$LOG" 2>&1 || fail "$1"; }
[[ "$(git -C "$ROOT" rev-parse HEAD)" == "$NMKR_AJAX_SECURITY_EXPECTED_SOURCE_SHA" ]] || fail source
[[ -z "$(git -C "$ROOT" status --porcelain)" ]] || fail source
command -v "$WP_CLI_BIN" >/dev/null 2>&1 || fail wordpress
run "$WP_CLI_BIN" --path="$WP_PATH" core is-installed --quiet
run "$WP_CLI_BIN" --path="$WP_PATH" plugin is-active "${NMKR_PLUGIN_SLUG:-nmkr-connect/nmkr-connect.php}"
WP_BASE_URL="$WP_BASE_URL" run "$WP_CLI_BIN" --path="$WP_PATH" eval 'exit(untrailingslashit(home_url())===untrailingslashit(getenv("WP_BASE_URL"))?0:1);'
WP_ADMIN_USER="$WP_ADMIN_USER" NMKR_MARKETING_USER="$NMKR_MARKETING_USER" run "$WP_CLI_BIN" --path="$WP_PATH" eval '
$find=function($id){$u=get_user_by("login",$id);if(!$u&&is_email($id))$u=get_user_by("email",$id);return $u;};$a=$find(getenv("WP_ADMIN_USER"));$m=$find(getenv("NMKR_MARKETING_USER"));
if(!$a||!$m||$a->ID===$m->ID)exit(1);if(!user_can($a,"nmkr_view_dashboard")||!user_can($a,"nmkr_view_analytics")||!user_can($a,"nmkr_manage_sync"))exit(2);
if($m->roles!==array("nmkr-marketing")||!user_can($m,"nmkr_view_analytics")||user_can($m,"nmkr_view_dashboard")||user_can($m,"nmkr_manage_sync"))exit(3);'
run "$ROOT/scripts/nmkr-wpcli-ajax-security-state.sh" idle; pass preflight
PRE="$("$ROOT/scripts/nmkr-wpcli-ajax-security-state.sh" digest 2>>"$LOG")" || fail negative-state
run npm --prefix "$ROOT" run test:e2e:ajax-security -- --grep @negative --reporter=list
POST="$("$ROOT/scripts/nmkr-wpcli-ajax-security-state.sh" digest 2>>"$LOG")" || fail negative-state
[[ "$PRE" == "$POST" ]] || fail negative-state; pass negative-state
run npm --prefix "$ROOT" run test:e2e:ajax-security -- --grep @authorized --reporter=list; pass authorized
run "$ROOT/scripts/nmkr-wpcli-smoke.sh"; run "$ROOT/scripts/nmkr-wpcli-db-state.sh"; run "$ROOT/scripts/nmkr-wpcli-ajax-security-state.sh" idle; pass final-state
