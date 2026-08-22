#!/usr/bin/env bash
set -Eeuo pipefail
fail(){ printf 'Synthetic synchronization harness: FAIL (%s)\n' "$1" >&2; exit 1; }
pass(){ printf 'Synthetic synchronization harness: PASS\n'; }
active(){ [[ -n "${1:-}" && "$1" != 0 && "$1" != false ]]; }
active "${CI:-}" && fail ci-refusal
[[ "${RUN_NMKR_SYNTHETIC:-false}" == true ]] || fail default-refusal
[[ "${NMKR_SYNTHETIC_CONFIRM:-}" == I_AUTHORIZE_DISPOSABLE_SYNTHETIC_SYNC ]] || fail authorization
for v in NMKR_SYNTHETIC_WP_ROOT NMKR_SYNTHETIC_RUN_DIR NMKR_SYNTHETIC_ADMIN_USER NMKR_SYNTHETIC_ADMIN_PASSWORD NMKR_SYNTHETIC_PORT NMKR_SYNTHETIC_EXPECTED_SHA NMKR_SYNTHETIC_DB_SOCKET; do [[ -n "${!v:-}" ]] || fail missing-input; done
[[ "${NMKR_SYNTHETIC_PROFILE:-}" == private-2400-v1 ]] || fail profile
[[ "$NMKR_SYNTHETIC_PORT" =~ ^[0-9]+$ ]] && ((NMKR_SYNTHETIC_PORT>=1024&&NMKR_SYNTHETIC_PORT<=65535)) || fail port
repo="$(git rev-parse --show-toplevel)"; [[ "$(git -C "$repo" rev-parse HEAD)" == "$NMKR_SYNTHETIC_EXPECTED_SHA" && -z "$(git -C "$repo" status --porcelain --untracked-files=no)" ]] || fail source-integrity
[[ -d "$NMKR_SYNTHETIC_WP_ROOT" && ! -L "$NMKR_SYNTHETIC_WP_ROOT" && -d "$NMKR_SYNTHETIC_RUN_DIR" && ! -L "$NMKR_SYNTHETIC_RUN_DIR" ]] || fail private-path
[[ "$(stat -c %a "$NMKR_SYNTHETIC_RUN_DIR")" =~ ^(700|750)$ ]] || fail private-permissions
wp="${WP_CLI_BIN:-wp}"; command -v "$wp" >/dev/null && command -v unshare >/dev/null && command -v ip >/dev/null && command -v php >/dev/null || fail required-tool
"$wp" --path="$NMKR_SYNTHETIC_WP_ROOT" core is-installed >/dev/null 2>&1 || fail wordpress-ready
NMKR_SYNTHETIC_DB_SOCKET="$NMKR_SYNTHETIC_DB_SOCKET" "$wp" --path="$NMKR_SYNTHETIC_WP_ROOT" eval '
$socket=getenv("NMKR_SYNTHETIC_DB_SOCKET"); $home=(string)get_option("home","");
if(!defined("WP_ENVIRONMENT_TYPE")||WP_ENVIRONMENT_TYPE==="production"||!defined("DISABLE_WP_CRON")||DISABLE_WP_CRON!==true)exit(1);
if(strpos((string)DB_HOST,$socket)===false||strpos($home,"http://127.0.0.1:")!==0)exit(1);
if(get_option("nmkr_sync_owner",false)||get_option("nmkr_sync_in_progress",false)||get_transient("nmkr_sync_in_progress"))exit(1);
' >/dev/null 2>&1 || fail target-assumptions
"$wp" --path="$NMKR_SYNTHETIC_WP_ROOT" db check >/dev/null 2>&1 || fail database-socket
unshare --user --map-root-user --net true 2>/dev/null || fail namespace-preflight
export NMKR_SYNTHETIC_EXECUTION_ID="$(php -r 'echo bin2hex(random_bytes(16));')" NMKR_SYNTHETIC_EXPIRY="$(( $(date +%s)+2700 ))" NMKR_SYNTHETIC_SENTINEL=NMKR_SYNTHETIC_TEST_ONLY_V1
export NMKR_SYNTHETIC_STATE_FILE="$NMKR_SYNTHETIC_RUN_DIR/provider-state.json" NMKR_SYNTHETIC_BASE_URL="http://127.0.0.1:$NMKR_SYNTHETIC_PORT" NMKR_SYNTHETIC_WP_CLI="${WP_CLI_BIN:-wp}"
export NMKR_SYNTHETIC_PROVIDER_SOURCE="$repo/scripts/nmkr-synthetic-provider.php" NMKR_SYNTHETIC_DRIVER="$repo/scripts/nmkr-synthetic-driver.mjs" NMKR_SYNTHETIC_STATE_HELPER="$repo/scripts/nmkr-synthetic-state.php"
unshare --user --map-root-user --net bash -c '
set -Eeuo pipefail; ip link set lo up
[[ "$(find /sys/class/net -mindepth 1 -maxdepth 1 -printf "%f\n")" == lo ]] || exit 40
! ip -4 route show default | grep -q . && ! ip -6 route show default | grep -q . || exit 41
! php -r '\''exit(@file_get_contents("http://network-must-not-resolve.invalid/")===false?0:1);'\'' || exit 42
mu="$NMKR_SYNTHETIC_WP_ROOT/wp-content/mu-plugins"; mkdir -p "$mu"; target="$mu/nmkr-synthetic-provider.php"; [[ ! -e "$target" ]] || exit 43
cleanup(){ rm -f "$target"; [[ -z "${server:-}" ]] || kill "$server" 2>/dev/null || true; }; trap cleanup EXIT
install -m 600 "$NMKR_SYNTHETIC_PROVIDER_SOURCE" "$target"
php -S "127.0.0.1:$NMKR_SYNTHETIC_PORT" -t "$NMKR_SYNTHETIC_WP_ROOT" >/dev/null 2>&1 & server=$!
sleep 1; node "$NMKR_SYNTHETIC_DRIVER"
"$NMKR_SYNTHETIC_WP_CLI" --path="$NMKR_SYNTHETIC_WP_ROOT" eval-file "$NMKR_SYNTHETIC_STATE_HELPER" >/dev/null
' || fail isolated-lifecycle
pass
