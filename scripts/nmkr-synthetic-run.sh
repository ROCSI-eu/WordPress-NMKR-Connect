#!/usr/bin/env bash
set -Eeuo pipefail
fail(){ printf 'Synthetic synchronization harness: FAIL (%s)\n' "$1" >&2; exit 1; }
pass(){ printf 'Synthetic synchronization harness: PASS\n'; }
active(){ [[ -n "${1:-}" && "$1" != 0 && "$1" != false ]]; }
active "${CI:-}" && fail ci-refusal
[[ "${RUN_NMKR_SYNTHETIC:-false}" == true ]] || fail default-refusal
[[ "${NMKR_SYNTHETIC_CONFIRM:-}" == I_AUTHORIZE_DISPOSABLE_SYNTHETIC_SYNC ]] || fail authorization
for v in NMKR_SYNTHETIC_WP_ROOT NMKR_SYNTHETIC_RUN_DIR NMKR_SYNTHETIC_ADMIN_USER NMKR_SYNTHETIC_ADMIN_PASSWORD NMKR_SYNTHETIC_PORT NMKR_SYNTHETIC_EXPECTED_SHA NMKR_SYNTHETIC_DB_SOCKET NMKR_SYNTHETIC_DEPLOYED_PLUGIN_PATH NMKR_SYNTHETIC_PLUGIN_SLUG NMKR_SYNTHETIC_RUN_MODE; do [[ -n "${!v:-}" ]] || fail missing-input; done
[[ "${NMKR_SYNTHETIC_PROFILE:-}" == private-2400-v1 ]] || fail profile
[[ "$NMKR_SYNTHETIC_RUN_MODE" == cold || "$NMKR_SYNTHETIC_RUN_MODE" == warm ]] || fail run-mode
[[ "$NMKR_SYNTHETIC_PORT" =~ ^[0-9]+$ ]] && ((NMKR_SYNTHETIC_PORT>=1024&&NMKR_SYNTHETIC_PORT<=65535)) || fail port
repo="$(git rev-parse --show-toplevel)"; integrity="$repo/scripts/nmkr-ajax-runtime-integrity.sh"
check_worktree(){ local root="$1"; [[ "$(git -C "$root" rev-parse HEAD 2>/dev/null)" == "$NMKR_SYNTHETIC_EXPECTED_SHA" && -z "$(git -C "$root" -c core.fileMode=true status --porcelain=v1 --untracked-files=all 2>/dev/null)" ]] && bash "$integrity" "$root" >/dev/null 2>&1; }
check_worktree "$repo" || fail source-integrity
deployed="$(realpath -e -- "$NMKR_SYNTHETIC_DEPLOYED_PLUGIN_PATH" 2>/dev/null)" || fail deployed-integrity
[[ -d "$deployed" && ! -L "$NMKR_SYNTHETIC_DEPLOYED_PLUGIN_PATH" && "$(realpath -e -- "$(git -C "$deployed" rev-parse --show-toplevel 2>/dev/null)" 2>/dev/null)" == "$deployed" ]] || fail deployed-integrity
check_worktree "$deployed" || fail deployed-integrity
[[ -d "$NMKR_SYNTHETIC_WP_ROOT" && ! -L "$NMKR_SYNTHETIC_WP_ROOT" && -d "$NMKR_SYNTHETIC_RUN_DIR" && ! -L "$NMKR_SYNTHETIC_RUN_DIR" ]] || fail private-path
[[ "$(stat -c %a "$NMKR_SYNTHETIC_RUN_DIR")" =~ ^(700|750)$ ]] || fail private-permissions
wp="${WP_CLI_BIN:-wp}"; command -v "$wp" >/dev/null && command -v unshare >/dev/null && command -v ip >/dev/null && command -v php >/dev/null && command -v python3 >/dev/null && command -v composer >/dev/null || fail required-tool
"$wp" --path="$NMKR_SYNTHETIC_WP_ROOT" core is-installed >/dev/null 2>&1 || fail wordpress-ready
NMKR_SYNTHETIC_DEPLOYED_PLUGIN_PATH="$deployed" NMKR_SYNTHETIC_PLUGIN_SLUG="$NMKR_SYNTHETIC_PLUGIN_SLUG" "$wp" --path="$NMKR_SYNTHETIC_WP_ROOT" eval '
require_once ABSPATH."wp-admin/includes/plugin.php";$slug=getenv("NMKR_SYNTHETIC_PLUGIN_SLUG");
if(!is_string($slug)||$slug===""||validate_file($slug)!==0)exit(1);
$active=realpath(WP_PLUGIN_DIR."/".$slug);$expected=realpath(getenv("NMKR_SYNTHETIC_DEPLOYED_PLUGIN_PATH")."/".basename($slug));
if(!is_plugin_active($slug)||$active===false||$expected===false||!hash_equals($expected,$active))exit(1);
' >/dev/null 2>&1 || fail active-plugin-integrity
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
export NMKR_SYNTHETIC_STATE_ASSERT="$repo/scripts/nmkr-synthetic-state-assert.mjs"
export NMKR_SYNTHETIC_RUN_RECEIPT="$NMKR_SYNTHETIC_RUN_DIR/run-receipt.json"
export NMKR_SYNTHETIC_WORKER_LOG="$NMKR_SYNTHETIC_RUN_DIR/worker.log" NMKR_SYNTHETIC_SERVER_LOG="$NMKR_SYNTHETIC_RUN_DIR/server.log" NMKR_SYNTHETIC_DIAGNOSTIC_CLASSIFIER="$repo/scripts/nmkr-debug-log-classifier.py"
unshare --user --map-root-user --net bash -c '
set -Eeuo pipefail; umask 077; ip link set lo up
capture_state(){
  local output="$1" diagnostic="$2" helper_status diagnostic_status
  set +e
  "$NMKR_SYNTHETIC_WP_CLI" --path="$NMKR_SYNTHETIC_WP_ROOT" eval-file "$NMKR_SYNTHETIC_STATE_HELPER" >"$output" 2>"$diagnostic"
  helper_status=$?
  python3 "$NMKR_SYNTHETIC_DIAGNOSTIC_CLASSIFIER" "$diagnostic" 60
  diagnostic_status=$?
  set -e
  [[ "$helper_status" == 0 && ( "$diagnostic_status" == 0 || "$diagnostic_status" == 3 ) ]]
}
[[ "$(find /sys/class/net -mindepth 1 -maxdepth 1 -printf "%f\n")" == lo ]] || exit 40
! ip -4 route show default | grep -q . && ! ip -6 route show default | grep -q . || exit 41
! php -r '\''exit(@file_get_contents("http://network-must-not-resolve.invalid/")===false?0:1);'\'' || exit 42
mu="$NMKR_SYNTHETIC_WP_ROOT/wp-content/mu-plugins"; mkdir -p "$mu"; target="$mu/nmkr-synthetic-provider.php"; [[ ! -e "$target" ]] || exit 43
cleanup(){ rm -f "$target"; [[ -z "${server:-}" ]] || kill "$server" 2>/dev/null || true; }; trap cleanup EXIT
install -m 600 "$NMKR_SYNTHETIC_PROVIDER_SOURCE" "$target"
capture_state "$NMKR_SYNTHETIC_RUN_DIR/before-state.json" "$NMKR_SYNTHETIC_RUN_DIR/before-state.stderr" || exit 46
node "$NMKR_SYNTHETIC_STATE_ASSERT" --preflight "$NMKR_SYNTHETIC_RUN_MODE" "$NMKR_SYNTHETIC_RUN_DIR/before-state.json"
debug_meta="$NMKR_SYNTHETIC_RUN_DIR/debug-meta"; debug_delta="$NMKR_SYNTHETIC_RUN_DIR/debug-delta.log"; debug_baseline_diagnostic="$NMKR_SYNTHETIC_RUN_DIR/debug-baseline.stderr"
set +e
"$NMKR_SYNTHETIC_WP_CLI" --path="$NMKR_SYNTHETIC_WP_ROOT" eval '\''
if(!defined("WP_DEBUG_LOG")||WP_DEBUG_LOG===false)exit(1);$p=WP_DEBUG_LOG===true?WP_CONTENT_DIR."/debug.log":WP_DEBUG_LOG;
if(!is_string($p)||$p===""||!is_dir(dirname($p)))exit(1);$s=@lstat($p);if($s!==false&&(is_link($p)||!is_file($p)))exit(1);if($s===false&&file_put_contents($p,"")===false)exit(1);$s=lstat($p);if(!$s||is_link($p)||!is_file($p)||!is_readable($p))exit(1);@chmod($p,0600);clearstatcache(true,$p);$s=lstat($p);if(!$s||is_link($p)||!is_file($p)||($s["mode"]&077)!==0)exit(1);file_put_contents(getenv("NMKR_SYNTHETIC_RUN_DIR")."/debug-meta",$p."\n".$s["ino"]."\n".$s["size"]."\n",LOCK_EX);'\'' >/dev/null 2>"$debug_baseline_diagnostic"
debug_baseline_status=$?
python3 "$NMKR_SYNTHETIC_DIAGNOSTIC_CLASSIFIER" "$debug_baseline_diagnostic" 60 >/dev/null 2>&1
debug_baseline_diagnostic_status=$?
set -e
[[ "$debug_baseline_status" == 0 && ( "$debug_baseline_diagnostic_status" == 0 || "$debug_baseline_diagnostic_status" == 3 ) ]] || exit 45
php -S "127.0.0.1:$NMKR_SYNTHETIC_PORT" -t "$NMKR_SYNTHETIC_WP_ROOT" >"$NMKR_SYNTHETIC_SERVER_LOG" 2>&1 & server=$!
sleep 1; node "$NMKR_SYNTHETIC_DRIVER"
mapfile -t debug_info <"$debug_meta"; [[ "${#debug_info[@]}" == 3 ]] || exit 45
debug_path="${debug_info[0]}"; debug_inode="${debug_info[1]}"; debug_size="${debug_info[2]}"
debug_source="$(realpath -e -- "$debug_path" 2>/dev/null)"; debug_delta_parent="$(realpath -e -- "$(dirname -- "$debug_delta")" 2>/dev/null)"
[[ -n "$debug_source" && "$debug_source" != "$debug_delta_parent/$(basename -- "$debug_delta")" ]] || exit 45
debug_after_inode="$(stat -c %i "$debug_path" 2>/dev/null)"; debug_after_size="$(stat -c %s "$debug_path" 2>/dev/null)"
[[ "$debug_after_inode" == "$debug_inode" && "$debug_after_size" =~ ^[0-9]+$ && "$debug_size" =~ ^[0-9]+$ && "$debug_after_size" -ge "$debug_size" ]] || exit 45
dd if="$debug_path" of="$debug_delta" bs=1 skip="$debug_size" status=none 2>/dev/null || exit 45
set +e; python3 "$NMKR_SYNTHETIC_DIAGNOSTIC_CLASSIFIER" "$NMKR_SYNTHETIC_WORKER_LOG" 60; diagnostic_status=$?; set -e
[[ "$diagnostic_status" == 0 || "$diagnostic_status" == 3 ]] || exit 44
set +e; python3 "$NMKR_SYNTHETIC_DIAGNOSTIC_CLASSIFIER" --complete-file "$NMKR_SYNTHETIC_SERVER_LOG" 60; diagnostic_status=$?; set -e
[[ "$diagnostic_status" == 0 || "$diagnostic_status" == 3 ]] || exit 44
set +e; python3 "$NMKR_SYNTHETIC_DIAGNOSTIC_CLASSIFIER" --complete-file "$debug_delta" 60; diagnostic_status=$?; set -e
[[ "$diagnostic_status" == 0 || "$diagnostic_status" == 3 ]] || exit 45
capture_state "$NMKR_SYNTHETIC_RUN_DIR/after-state.json" "$NMKR_SYNTHETIC_RUN_DIR/after-state.stderr" || exit 46
node "$NMKR_SYNTHETIC_STATE_ASSERT" "$NMKR_SYNTHETIC_RUN_MODE" "$NMKR_SYNTHETIC_RUN_DIR/before-state.json" "$NMKR_SYNTHETIC_RUN_DIR/after-state.json"
' || fail isolated-lifecycle
check_worktree "$repo" || fail final-source-integrity
check_worktree "$deployed" || fail final-deployed-integrity
pass
