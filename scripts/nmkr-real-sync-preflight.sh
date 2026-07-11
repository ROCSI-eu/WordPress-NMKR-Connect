#!/usr/bin/env bash
set -Eeuo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
if REPO_ROOT_FROM_GIT="$(git -C "$SCRIPT_DIR" rev-parse --show-toplevel 2>/dev/null)"; then
  REPO_ROOT="$REPO_ROOT_FROM_GIT"
else
  REPO_ROOT="$(cd -- "$SCRIPT_DIR/.." && pwd)"
fi

ci_marker_is_active() {
  local value="${1:-}"
  [[ -n "$value" && "$value" != "0" && "$value" != "false" ]]
}

pre_source_ci_refusal() {
  printf '\nPhase 15 real-sync preflight summary\n'
  printf '  result: FAIL\n'
  printf '  failed gate: ci-refusal\n'
  exit 1
}

for ci_name in CI GITHUB_ACTIONS GITLAB_CI CIRCLECI BUILDKITE TF_BUILD; do
  if ci_marker_is_active "${!ci_name:-}"; then
    pre_source_ci_refusal
  fi
done

ENV_FILE=""
if [[ -n "${NMKR_PHASE2_ENV_FILE:-}" ]]; then
  ENV_FILE="$NMKR_PHASE2_ENV_FILE"
elif [[ -f "$REPO_ROOT/.env.tests" ]]; then
  ENV_FILE="$REPO_ROOT/.env.tests"
fi
if [[ -n "$ENV_FILE" ]]; then
  [[ -f "$ENV_FILE" ]] || { printf 'ERROR: configured private environment file is unavailable.\n' >&2; exit 1; }
  set -a
  # shellcheck source=/dev/null
  source "$ENV_FILE"
  set +a
fi

RUN_REAL_SYNC="${RUN_REAL_SYNC:-false}"
PW_SAVE_ARTIFACTS="${PW_SAVE_ARTIFACTS:-false}"
WP_CLI_BIN="${WP_CLI_BIN:-wp}"
NMKR_PLUGIN_SLUG="${NMKR_PLUGIN_SLUG:-nmkr-connect/nmkr-connect.php}"
NMKR_PHASE2_WP_READY_TIMEOUT_SECONDS="${NMKR_PHASE2_WP_READY_TIMEOUT_SECONDS:-120}"
NMKR_PHASE2_WP_READY_HTTP_TIMEOUT_SECONDS="${NMKR_PHASE2_WP_READY_HTTP_TIMEOUT_SECONDS:-10}"
NMKR_REAL_SYNC_MAX_DURATION_SECONDS="${NMKR_REAL_SYNC_MAX_DURATION_SECONDS:-1800}"
NMKR_REAL_SYNC_POLL_TIMEOUT_SECONDS="${NMKR_REAL_SYNC_POLL_TIMEOUT_SECONDS:-45}"
NMKR_REAL_SYNC_RECEIPT_TTL_SECONDS="${NMKR_REAL_SYNC_RECEIPT_TTL_SECONDS:-300}"
NMKR_PHASE2_CURL_CA_BUNDLE="${NMKR_PHASE2_CURL_CA_BUNDLE:-}"

CONFIRMATIONS_STATUS="PENDING"; PRIVATE_STATE_STATUS="PENDING"; SOURCE_INTEGRITY_STATUS="PENDING"; DEPLOYMENT_INTEGRITY_STATUS="PENDING"
ORIGIN_GUARD_STATUS="PENDING"; WORDPRESS_READY_STATUS="PENDING"; PLUGIN_ACTIVE_STATUS="PENDING"; CAPABILITY_STATUS="PENDING"
DB_STATE_STATUS="PENDING"; RUNTIME_STATE_STATUS="PENDING"; CRON_STATE_STATUS="PENDING"; PROFILE_STATUS="PENDING"; BACKUP_STATUS="PENDING"
TIMEOUT_BOUNDS_STATUS="PENDING"; RECEIPT_STATUS="PENDING"; RESULT_STATUS="FAIL"
FAILED_GATE=""; DIAGNOSTIC_FILE=""; RUN_DIR=""; SOURCE_COMMIT=""; DEPLOYED_COMMIT=""

print_summary() {
  printf '\nPhase 15 real-sync preflight summary\n'
  printf '  confirmations: %s\n' "$CONFIRMATIONS_STATUS"
  printf '  private-state: %s\n' "$PRIVATE_STATE_STATUS"
  printf '  source-integrity: %s\n' "$SOURCE_INTEGRITY_STATUS"
  printf '  deployment-integrity: %s\n' "$DEPLOYMENT_INTEGRITY_STATUS"
  printf '  origin-guard: %s\n' "$ORIGIN_GUARD_STATUS"
  printf '  wordpress-ready: %s\n' "$WORDPRESS_READY_STATUS"
  printf '  plugin-active: %s\n' "$PLUGIN_ACTIVE_STATUS"
  printf '  capability: %s\n' "$CAPABILITY_STATUS"
  printf '  db-state: %s\n' "$DB_STATE_STATUS"
  printf '  runtime-state: %s\n' "$RUNTIME_STATE_STATUS"
  printf '  cron-state: %s\n' "$CRON_STATE_STATUS"
  printf '  profile: %s\n' "$PROFILE_STATUS"
  printf '  backup: %s\n' "$BACKUP_STATUS"
  printf '  timeout-bounds: %s\n' "$TIMEOUT_BOUNDS_STATUS"
  printf '  receipt: %s\n' "$RECEIPT_STATUS"
  printf '  result: %s\n' "$RESULT_STATUS"
  [[ -n "$SOURCE_COMMIT" ]] && printf '  source commit: %.12s\n' "$SOURCE_COMMIT"
  [[ -n "$DEPLOYED_COMMIT" ]] && printf '  deployed commit: %.12s\n' "$DEPLOYED_COMMIT"
  [[ -n "$FAILED_GATE" ]] && printf '  failed gate: %s\n' "$FAILED_GATE"
  [[ -n "$DIAGNOSTIC_FILE" ]] && printf '  private diagnostic file: %s\n' "$DIAGNOSTIC_FILE"
}
fail_gate() { FAILED_GATE="$1"; DIAGNOSTIC_FILE="${2:-}"; RESULT_STATUS="FAIL"; print_summary; exit 1; }
mark_fail() { local var="$1"; printf -v "$var" 'FAIL'; }
require_tool() { command -v "$1" >/dev/null 2>&1 || fail_gate "required-tools" "$RUN_DIR/preflight.log"; }
wp_cli() { local args=("$WP_CLI_BIN"); [[ -n "${WP_PATH:-}" ]] && args+=("--path=$WP_PATH"); args+=("$@"); "${args[@]}"; }
realpath_existing() { python3 -c 'import os,sys; print(os.path.realpath(sys.argv[1]))' "$1"; }

for ci_name in CI GITHUB_ACTIONS GITLAB_CI CIRCLECI BUILDKITE TF_BUILD; do
  if ci_marker_is_active "${!ci_name:-}"; then mark_fail CONFIRMATIONS_STATUS; fail_gate "ci-refusal"; fi
done
[[ "$RUN_REAL_SYNC" == "true" ]] || { mark_fail CONFIRMATIONS_STATUS; fail_gate "confirmations"; }
[[ "${NMKR_REAL_SYNC_CONFIRM:-}" == "I_UNDERSTAND_THIS_MUTATES_DEV" ]] || { mark_fail CONFIRMATIONS_STATUS; fail_gate "confirmations"; }
[[ "$PW_SAVE_ARTIFACTS" == "false" ]] || { mark_fail CONFIRMATIONS_STATUS; fail_gate "confirmations"; }
[[ "${NMKR_REAL_SYNC_BACKUP_CONFIRM:-}" == "I_CONFIRMED_A_RECENT_DEV_BACKUP" ]] || { mark_fail CONFIRMATIONS_STATUS; fail_gate "confirmations"; }
CONFIRMATIONS_STATUS="PASS"

umask 077
[[ -n "${WP_PATH:-}" ]] || { mark_fail PRIVATE_STATE_STATUS; fail_gate "private-state"; }
PRIVATE_STATE_JSON="$(python3 - "${NMKR_PHASE2_LOG_DIR:-}" "$REPO_ROOT" "$WP_PATH" <<'PY'
import json, os, stat, sys
state, repo, wp = sys.argv[1:4]
uid = os.getuid()
def fail():
    raise SystemExit(1)
def is_inside_or_equal(path, root):
    return path == root or path.startswith(root.rstrip(os.sep) + os.sep)
def mode_private(path):
    st = os.stat(path)
    return stat.S_ISDIR(st.st_mode) and st.st_uid == uid and (stat.S_IMODE(st.st_mode) & 0o077) == 0 and os.access(path, os.W_OK)
def ensure_component_safe(path):
    if os.path.islink(path) or not os.path.isdir(path) or not mode_private(path):
        fail()
def existing_components(path):
    cur = os.sep
    for part in [p for p in path.split(os.sep) if p]:
        cur = os.path.join(cur, part)
        if os.path.exists(cur):
            yield cur
        else:
            break
if not state or not os.path.isabs(state): fail()
state_parts = [part for part in state.split(os.sep) if part]
if any(part in ('.', '..') for part in state_parts): fail()
repo_real = os.path.realpath(repo)
wp_real = os.path.realpath(wp)
if not os.path.isdir(repo_real) or not os.path.isdir(wp_real): fail()
probe = state.rstrip(os.sep) or os.sep
missing = []
while not os.path.exists(probe):
    parent = os.path.dirname(probe)
    if parent == probe: fail()
    missing.append(os.path.basename(probe))
    probe = parent
for component in existing_components(state):
    if os.path.islink(component): fail()
ensure_component_safe(probe)
parent_real = os.path.realpath(probe)
intended = parent_real
for part in reversed(missing):
    intended = os.path.join(intended, part)
state_real = os.path.realpath(state) if os.path.exists(state) else intended
for root in (repo_real, wp_real):
    if is_inside_or_equal(state_real, root) or is_inside_or_equal(root, state_real): fail()
create_path = probe
for part in reversed(missing):
    ensure_component_safe(create_path)
    create_path = os.path.join(create_path, part)
    if os.path.exists(create_path):
        ensure_component_safe(create_path)
    else:
        os.mkdir(create_path, 0o700)
        ensure_component_safe(create_path)
if os.path.realpath(state) != state_real: fail()
ensure_component_safe(state)
for root in (repo_real, wp_real):
    if is_inside_or_equal(os.path.realpath(state), root) or is_inside_or_equal(root, os.path.realpath(state)): fail()
runs = os.path.join(state, 'runs')
if os.path.exists(runs):
    ensure_component_safe(runs)
print(json.dumps({'state_real': os.path.realpath(state), 'repo_real': repo_real, 'wp_real': wp_real}, separators=(',', ':')))
PY
)" || { mark_fail PRIVATE_STATE_STATUS; fail_gate "private-state"; }
LOG_REAL="$(python3 -c 'import json,sys; print(json.loads(sys.argv[1])["state_real"])' "$PRIVATE_STATE_JSON")"
REPO_REAL="$(python3 -c 'import json,sys; print(json.loads(sys.argv[1])["repo_real"])' "$PRIVATE_STATE_JSON")"
WP_REAL="$(python3 -c 'import json,sys; print(json.loads(sys.argv[1])["wp_real"])' "$PRIVATE_STATE_JSON")"
if [[ ! -d "$NMKR_PHASE2_LOG_DIR/runs" ]]; then mkdir -m 700 "$NMKR_PHASE2_LOG_DIR/runs" || { mark_fail PRIVATE_STATE_STATUS; fail_gate "private-state"; }; fi

RUN_STAMP="$(date -u +%Y%m%dT%H%M%SZ)-$$"; RUN_DIR="$NMKR_PHASE2_LOG_DIR/runs/$RUN_STAMP"; mkdir -m 700 "$RUN_DIR"; DIAGNOSTIC_FILE="$RUN_DIR/preflight.log"; : >"$DIAGNOSTIC_FILE"; chmod 600 "$DIAGNOSTIC_FILE"
PRIVATE_STATE_STATUS="PASS"

for tool in bash git php python3 curl; do require_tool "$tool"; done
require_tool "$WP_CLI_BIN"

git -C "$REPO_ROOT" rev-parse --is-inside-work-tree >/dev/null 2>>"$DIAGNOSTIC_FILE" || { mark_fail SOURCE_INTEGRITY_STATUS; fail_gate "source-integrity" "$DIAGNOSTIC_FILE"; }
SOURCE_COMMIT="$(git -C "$REPO_ROOT" rev-parse HEAD 2>>"$DIAGNOSTIC_FILE")"; [[ "$SOURCE_COMMIT" =~ ^[0-9a-f]{40}$ ]] || { mark_fail SOURCE_INTEGRITY_STATUS; fail_gate "source-integrity" "$DIAGNOSTIC_FILE"; }
if ! SOURCE_STATUS="$(git -C "$REPO_ROOT" -c core.fileMode=true status --porcelain=v1 --untracked-files=all 2>>"$DIAGNOSTIC_FILE")"; then
  mark_fail SOURCE_INTEGRITY_STATUS; fail_gate "source-integrity" "$DIAGNOSTIC_FILE"
fi
[[ -z "$SOURCE_STATUS" ]] || { mark_fail SOURCE_INTEGRITY_STATUS; fail_gate "source-integrity" "$DIAGNOSTIC_FILE"; }
SRC_OWNER="$(python3 -c 'import os,sys; print(os.stat(sys.argv[1]).st_uid)' "$REPO_ROOT")"; [[ "$SRC_OWNER" == "$(id -u)" ]] || { mark_fail SOURCE_INTEGRITY_STATUS; fail_gate "source-integrity" "$DIAGNOSTIC_FILE"; }
SOURCE_INTEGRITY_STATUS="PASS"

[[ -n "${WP_PATH:-}" ]] || { mark_fail DEPLOYMENT_INTEGRITY_STATUS; fail_gate "deployment-integrity" "$DIAGNOSTIC_FILE"; }
ACTIVE_PLUGIN_PATH="${WP_PATH%/}/wp-content/plugins/${NMKR_PLUGIN_SLUG%/*}"
ACTIVE_PLUGIN_REAL="$(realpath_existing "$ACTIVE_PLUGIN_PATH" 2>>"$DIAGNOSTIC_FILE")" || { mark_fail DEPLOYMENT_INTEGRITY_STATUS; fail_gate "deployment-integrity" "$DIAGNOSTIC_FILE"; }
if [[ -n "${NMKR_DEPLOYED_PLUGIN_PATH:-}" ]]; then DEPLOYED_PATH="$NMKR_DEPLOYED_PLUGIN_PATH"; else DEPLOYED_PATH="$ACTIVE_PLUGIN_PATH"; fi
DEPLOYED_REAL="$(realpath_existing "$DEPLOYED_PATH" 2>>"$DIAGNOSTIC_FILE")" || { mark_fail DEPLOYMENT_INTEGRITY_STATUS; fail_gate "deployment-integrity" "$DIAGNOSTIC_FILE"; }
[[ "$DEPLOYED_REAL" == "$ACTIVE_PLUGIN_REAL" ]] || { mark_fail DEPLOYMENT_INTEGRITY_STATUS; fail_gate "deployment-integrity" "$DIAGNOSTIC_FILE"; }
python3 - "$DEPLOYED_REAL" "$REPO_REAL" <<'PY' || { mark_fail DEPLOYMENT_INTEGRITY_STATUS; fail_gate "deployment-integrity" "$DIAGNOSTIC_FILE"; }
import os,sys
deployed, repo = map(os.path.realpath, sys.argv[1:3])
def inside_or_equal(a,b): return a == b or a.startswith(b.rstrip(os.sep) + os.sep)
if inside_or_equal(deployed, repo) or inside_or_equal(repo, deployed): raise SystemExit(1)
PY
git -C "$DEPLOYED_REAL" rev-parse --is-inside-work-tree >/dev/null 2>>"$DIAGNOSTIC_FILE" || { mark_fail DEPLOYMENT_INTEGRITY_STATUS; fail_gate "deployment-integrity" "$DIAGNOSTIC_FILE"; }
DEPLOYED_TOP="$(git -C "$DEPLOYED_REAL" rev-parse --show-toplevel 2>>"$DIAGNOSTIC_FILE")" || { mark_fail DEPLOYMENT_INTEGRITY_STATUS; fail_gate "deployment-integrity" "$DIAGNOSTIC_FILE"; }
DEPLOYED_TOP_REAL="$(realpath_existing "$DEPLOYED_TOP")"
[[ "$DEPLOYED_TOP_REAL" == "$DEPLOYED_REAL" ]] || { mark_fail DEPLOYMENT_INTEGRITY_STATUS; fail_gate "deployment-integrity" "$DIAGNOSTIC_FILE"; }
DEPLOYED_COMMIT="$(git -C "$DEPLOYED_REAL" rev-parse HEAD 2>>"$DIAGNOSTIC_FILE")"; [[ "$DEPLOYED_COMMIT" =~ ^[0-9a-f]{40}$ ]] || { mark_fail DEPLOYMENT_INTEGRITY_STATUS; fail_gate "deployment-integrity" "$DIAGNOSTIC_FILE"; }
if ! DEPLOYED_STATUS="$(git -C "$DEPLOYED_REAL" -c core.fileMode=true status --porcelain=v1 --untracked-files=all 2>>"$DIAGNOSTIC_FILE")"; then
  mark_fail DEPLOYMENT_INTEGRITY_STATUS; fail_gate "deployment-integrity" "$DIAGNOSTIC_FILE"
fi
[[ -z "$DEPLOYED_STATUS" ]] || { mark_fail DEPLOYMENT_INTEGRITY_STATUS; fail_gate "deployment-integrity" "$DIAGNOSTIC_FILE"; }
if ! DEPLOYED_IGNORED_RUNTIME="$(git -C "$DEPLOYED_REAL" ls-files --others --ignored --exclude-standard -- vendor/ 2>>"$DIAGNOSTIC_FILE")"; then
  mark_fail DEPLOYMENT_INTEGRITY_STATUS; fail_gate "deployment-integrity" "$DIAGNOSTIC_FILE"
fi
python3 - "$DEPLOYED_REAL" "$DEPLOYED_IGNORED_RUNTIME" <<'PY' || { mark_fail DEPLOYMENT_INTEGRITY_STATUS; fail_gate "deployment-integrity" "$DIAGNOSTIC_FILE"; }
import os, sys
root = sys.argv[1]
ignored = [line for line in sys.argv[2].splitlines() if line]
allowed_files = {'vendor/autoload.php'}
allowed_prefixes = ('vendor/composer/', 'vendor/freemius/wordpress-sdk/')
for relpath in ignored:
    if not (relpath in allowed_files or relpath.startswith(allowed_prefixes)):
        raise SystemExit(1)
    full = os.path.normpath(os.path.join(root, relpath))
    if not (full == root or full.startswith(root.rstrip(os.sep) + os.sep)):
        raise SystemExit(1)
    current = root
    for part in relpath.split(os.sep):
        current = os.path.join(current, part)
        if os.path.islink(current):
            raise SystemExit(1)
if ignored:
    required = (
        'vendor/freemius/wordpress-sdk/start.php',
        'vendor/freemius/wordpress-sdk/includes/class-freemius.php',
    )
    for relpath in required:
        full = os.path.join(root, relpath)
        if not os.path.isfile(full) or os.path.islink(full):
            raise SystemExit(1)
PY
[[ "$SOURCE_COMMIT" == "$DEPLOYED_COMMIT" ]] || { mark_fail DEPLOYMENT_INTEGRITY_STATUS; fail_gate "deployment-integrity" "$DIAGNOSTIC_FILE"; }
DEPLOYMENT_INTEGRITY_STATUS="PASS"

ORIGIN_SHA256="$(python3 - "${NMKR_REAL_SYNC_ALLOWED_ORIGIN:-}" "${WP_BASE_URL:-}" <<'PY'
import hashlib,sys,urllib.parse
allowed, base = sys.argv[1:3]
def norm(value):
    p=urllib.parse.urlsplit(value.strip())
    try: port = p.port
    except ValueError: raise SystemExit(1)
    if p.scheme != 'https' or not p.hostname or p.username or p.password or p.query or p.fragment or p.path not in ('','/') or port is not None:
        raise SystemExit(1)
    host=p.hostname.lower().rstrip('.')
    if '*' in host:
        raise SystemExit(1)
    return 'https://' + host
na, nb = norm(allowed), norm(base)
if na != nb: raise SystemExit(1)
print(hashlib.sha256(na.encode()).hexdigest())
PY
)" || { mark_fail ORIGIN_GUARD_STATUS; fail_gate "origin-guard" "$DIAGNOSTIC_FILE"; }
WP_HOME="$(wp_cli option get home --skip-plugins --skip-themes 2>/dev/null)" || { mark_fail ORIGIN_GUARD_STATUS; fail_gate "origin-guard" "$DIAGNOSTIC_FILE"; }
WP_SITEURL="$(wp_cli option get siteurl --skip-plugins --skip-themes 2>/dev/null)" || { mark_fail ORIGIN_GUARD_STATUS; fail_gate "origin-guard" "$DIAGNOSTIC_FILE"; }
python3 - "$ORIGIN_SHA256" "$WP_HOME" "$WP_SITEURL" <<'PY' || { mark_fail ORIGIN_GUARD_STATUS; fail_gate "origin-guard" "$DIAGNOSTIC_FILE"; }
import hashlib,sys,urllib.parse
expected = sys.argv[1]
def digest(value):
    p=urllib.parse.urlsplit(value.strip())
    try: port = p.port
    except ValueError: raise SystemExit(1)
    if p.scheme!='https' or not p.hostname or p.username or p.password or p.query or p.fragment or p.path not in ('','/') or port is not None: raise SystemExit(1)
    origin = 'https://' + p.hostname.lower().rstrip('.')
    return hashlib.sha256(origin.encode()).hexdigest()
if any(digest(v) != expected for v in sys.argv[2:]): raise SystemExit(1)
PY
ORIGIN_GUARD_STATUS="PASS"

wp_cli core is-installed >/dev/null 2>>"$DIAGNOSTIC_FILE" || { mark_fail WORDPRESS_READY_STATUS; fail_gate "wordpress-ready" "$DIAGNOSTIC_FILE"; }
wp_cli db prefix >/dev/null 2>>"$DIAGNOSTIC_FILE" || { mark_fail WORDPRESS_READY_STATUS; fail_gate "wordpress-ready" "$DIAGNOSTIC_FILE"; }
[[ ! -e "${WP_PATH%/}/.maintenance" ]] || { mark_fail WORDPRESS_READY_STATUS; fail_gate "wordpress-ready" "$DIAGNOSTIC_FILE"; }
BODY_FILE="$RUN_DIR/login-body.tmp"; CURL_ERR="$RUN_DIR/login-curl.err"; CURL_META="$RUN_DIR/login-curl.meta"; LOGIN_URL="${WP_BASE_URL%/}/wp-login.php"
CURL_ARGS=(-sS -L --max-time "$NMKR_PHASE2_WP_READY_HTTP_TIMEOUT_SECONDS" -o "$BODY_FILE" -w '%{http_code} %{url_effective}' "$LOGIN_URL")
if [[ -n "$NMKR_PHASE2_CURL_CA_BUNDLE" ]]; then CURL_ARGS=(--cacert "$NMKR_PHASE2_CURL_CA_BUNDLE" "${CURL_ARGS[@]}"); fi
if ! curl "${CURL_ARGS[@]}" >"$CURL_META" 2>"$CURL_ERR"; then rm -f "$BODY_FILE" "$CURL_ERR" "$CURL_META"; mark_fail WORDPRESS_READY_STATUS; fail_gate "wordpress-ready" "$DIAGNOSTIC_FILE"; fi
HTTP_STATUS="$(awk '{print $1}' "$CURL_META")"
EFFECTIVE_URL="$(cut -d' ' -f2- "$CURL_META")"
if [[ "$HTTP_STATUS" != "200" ]] || ! grep -qi 'id="user_login"' "$BODY_FILE"; then rm -f "$BODY_FILE" "$CURL_ERR" "$CURL_META"; mark_fail WORDPRESS_READY_STATUS; fail_gate "wordpress-ready" "$DIAGNOSTIC_FILE"; fi
python3 - "$ORIGIN_SHA256" "$EFFECTIVE_URL" <<'PY' || { rm -f "$BODY_FILE" "$CURL_ERR" "$CURL_META"; mark_fail WORDPRESS_READY_STATUS; fail_gate "wordpress-ready" "$DIAGNOSTIC_FILE"; }
import hashlib,sys,urllib.parse
expected,url=sys.argv[1:3]
p=urllib.parse.urlsplit(url.strip())
try: port=p.port
except ValueError: raise SystemExit(1)
if p.scheme != 'https' or not p.hostname or p.username or p.password or port is not None: raise SystemExit(1)
origin='https://' + p.hostname.lower().rstrip('.')
if hashlib.sha256(origin.encode()).hexdigest() != expected: raise SystemExit(1)
PY
rm -f "$BODY_FILE" "$CURL_ERR" "$CURL_META"; WORDPRESS_READY_STATUS="PASS"

wp_cli plugin is-active "$NMKR_PLUGIN_SLUG" >/dev/null 2>>"$DIAGNOSTIC_FILE" || { mark_fail PLUGIN_ACTIVE_STATUS; fail_gate "plugin-active" "$DIAGNOSTIC_FILE"; }; PLUGIN_ACTIVE_STATUS="PASS"
DB_LOG="$RUN_DIR/db-state.log"; if ! NMKR_DB_STATE_ALLOW_ACTIVE_SYNC=false WP_PATH="$WP_PATH" WP_CLI_BIN="$WP_CLI_BIN" NMKR_PLUGIN_SLUG="$NMKR_PLUGIN_SLUG" bash "$REPO_ROOT/scripts/nmkr-wpcli-db-state.sh" >"$DB_LOG" 2>&1; then mark_fail DB_STATE_STATUS; fail_gate "db-state" "$DB_LOG"; fi; chmod 600 "$DB_LOG"; DB_STATE_STATUS="PASS"
RUNTIME_JSON="$RUN_DIR/runtime-state.json"; wp_cli eval-file "$REPO_ROOT/scripts/nmkr-real-sync-runtime-state.php" >"$RUNTIME_JSON" 2>>"$DIAGNOSTIC_FILE" || { mark_fail RUNTIME_STATE_STATUS; fail_gate "runtime-state" "$DIAGNOSTIC_FILE"; }; chmod 600 "$RUNTIME_JSON"
python3 - "$RUNTIME_JSON" <<'PY' || { mark_fail RUNTIME_STATE_STATUS; fail_gate "runtime-state" "$DIAGNOSTIC_FILE"; }
import json,sys
d=json.load(open(sys.argv[1])); schema={'external_object_cache':bool,'runtime_transient_checks_performed':bool,'option_active_marker_count':int,'external_cache_active_marker_count':int,'sync_data_active':bool,'pending_sync_cron_count':int,'cron_state_inspectable':bool,'profile_guard_passed':bool,'admin_capability_ok':bool}
if set(d)!=set(schema): raise SystemExit(1)
for k,t in schema.items():
    if type(d[k]) is not t: raise SystemExit(1)
if d['external_object_cache'] and not d['runtime_transient_checks_performed']: raise SystemExit(1)
PY
if ! python3 - "$RUNTIME_JSON" <<'PY'
import json,sys
d=json.load(open(sys.argv[1])); raise SystemExit(0 if d['admin_capability_ok'] else 1)
PY
then mark_fail CAPABILITY_STATUS; fail_gate "capability" "$DIAGNOSTIC_FILE"; fi
CAPABILITY_STATUS="PASS"
if ! python3 - "$RUNTIME_JSON" <<'PY'
import json,sys
d=json.load(open(sys.argv[1])); raise SystemExit(0 if d['option_active_marker_count'] == 0 and d['external_cache_active_marker_count'] == 0 and not d['sync_data_active'] else 1)
PY
then mark_fail RUNTIME_STATE_STATUS; fail_gate "runtime-state" "$DIAGNOSTIC_FILE"; fi
RUNTIME_STATE_STATUS="PASS"
if ! python3 - "$RUNTIME_JSON" <<'PY'
import json,sys
d=json.load(open(sys.argv[1])); raise SystemExit(0 if d['cron_state_inspectable'] and d['pending_sync_cron_count'] == 0 else 1)
PY
then mark_fail CRON_STATE_STATUS; fail_gate "cron-state" "$DIAGNOSTIC_FILE"; fi
CRON_STATE_STATUS="PASS"
if ! python3 - "$RUNTIME_JSON" <<'PY'
import json,sys
d=json.load(open(sys.argv[1])); raise SystemExit(0 if d['profile_guard_passed'] else 1)
PY
then mark_fail PROFILE_STATUS; fail_gate "profile" "$DIAGNOSTIC_FILE"; fi
PROFILE_STATUS="PASS"

python3 - "${NMKR_REAL_SYNC_BACKUP_CONFIRMED_AT:-}" "$RUN_DIR/backup_epoch" <<'PY' || { mark_fail BACKUP_STATUS; fail_gate "backup" "$DIAGNOSTIC_FILE"; }
import datetime,re,sys
value=sys.argv[1]
if not re.fullmatch(r'[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z', value):
    raise SystemExit(1)
try:
    dt=datetime.datetime.strptime(value, '%Y-%m-%dT%H:%M:%SZ').replace(tzinfo=datetime.timezone.utc)
except Exception:
    raise SystemExit(1)
if dt.strftime('%Y-%m-%dT%H:%M:%SZ') != value:
    raise SystemExit(1)
now=datetime.datetime.now(datetime.timezone.utc)
if dt < now - datetime.timedelta(hours=24) or dt > now + datetime.timedelta(minutes=5): raise SystemExit(1)
open(sys.argv[2],'w').write(str(int(dt.timestamp())))
PY
BACKUP_EPOCH="$(cat "$RUN_DIR/backup_epoch")"; rm -f "$RUN_DIR/backup_epoch"; BACKUP_STATUS="PASS"
python3 - "$NMKR_REAL_SYNC_MAX_DURATION_SECONDS" "$NMKR_REAL_SYNC_POLL_TIMEOUT_SECONDS" "$NMKR_REAL_SYNC_RECEIPT_TTL_SECONDS" "$RUN_DIR/timeouts.json" <<'PY' || { mark_fail TIMEOUT_BOUNDS_STATUS; fail_gate "timeout-bounds" "$DIAGNOSTIC_FILE"; }
import json,sys
vals=list(map(str,sys.argv[1:4])); ranges=[(300,3600),(10,60),(60,300)]
out={}
for name,val,(lo,hi) in zip(['max_duration_seconds','poll_timeout_seconds','receipt_ttl_seconds'], vals, ranges):
    if not val.isdecimal() or val.startswith('0'): raise SystemExit(1)
    i=int(val)
    if i<lo or i>hi: raise SystemExit(1)
    out[name]=i
json.dump(out, open(sys.argv[4],'w'), separators=(',',':'))
PY
TIMEOUT_BOUNDS_STATUS="PASS"

RECEIPT="$RUN_DIR/real-sync-preflight.receipt.json"; [[ ! -L "$RECEIPT" && ! -e "$RECEIPT" ]] || { mark_fail RECEIPT_STATUS; fail_gate "receipt" "$DIAGNOSTIC_FILE"; }
python3 - "$RECEIPT.tmp" "$SOURCE_COMMIT" "$DEPLOYED_COMMIT" "$ORIGIN_SHA256" "$BACKUP_EPOCH" "$RUN_DIR/timeouts.json" <<'PY' || { mark_fail RECEIPT_STATUS; fail_gate "receipt" "$DIAGNOSTIC_FILE"; }
import json,os,sys,time
out,src,dep,origin,backup,tfile=sys.argv[1:]
timeouts=json.load(open(tfile)); now=int(time.time())
d={'receipt_version':1,'purpose':'nmkr-real-sync-preflight','created_at_epoch':now,'expires_at_epoch':now+timeouts['receipt_ttl_seconds'],'source_commit':src,'deployed_commit':dep,'origin_sha256':origin,'plugin_active':True,'admin_capability_ok':True,'db_state_clean':True,'api_key_present':True,'runtime_state_clean':True,'cron_state_clean':True,'object_cache_state_clean':True,'profile_guard_passed':True,'backup_confirmed':True,'backup_confirmed_at_epoch':int(backup),**timeouts}
with open(out,'x') as f: json.dump(d,f,separators=(',',':')); f.write('\n')
os.chmod(out,0o600)
PY
mv "$RECEIPT.tmp" "$RECEIPT"
python3 - "$RECEIPT" <<'PY' || { mark_fail RECEIPT_STATUS; fail_gate "receipt" "$DIAGNOSTIC_FILE"; }
import json,re,stat,os,sys
d=json.load(open(sys.argv[1]));
if stat.S_IMODE(os.stat(sys.argv[1]).st_mode)!=0o600: raise SystemExit(1)
if not re.fullmatch(r'[0-9a-f]{64}', d['origin_sha256']): raise SystemExit(1)
if not re.fullmatch(r'[0-9a-f]{40}', d['source_commit']) or not re.fullmatch(r'[0-9a-f]{40}', d['deployed_commit']): raise SystemExit(1)
PY
RECEIPT_STATUS="PASS"; RESULT_STATUS="PASS"; print_summary
