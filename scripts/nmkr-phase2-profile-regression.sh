#!/usr/bin/env bash
set -Eeuo pipefail
# Synthetic public-safe preflight cases: no usable host and no credentials.
ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
runner="$ROOT/scripts/nmkr-phase2-test-runner.sh"
tmp_dir="$(mktemp -d)"; trap 'rm -rf "$tmp_dir"' EXIT
mkdir -p "$tmp_dir/wp" "$tmp_dir/private/runs"
base=(env WP_BASE_URL=http://invalid.test WP_ADMIN_USER=placeholder WP_ADMIN_PASSWORD=placeholder WP_CLI_BIN=true WP_PATH="$tmp_dir/wp" NMKR_PHASE2_LOG_DIR="$tmp_dir/private")
expect_fail() {
  local expected="$1"; shift
  local case_id before_runs after_runs new_run output
  case_id="$(mktemp "$tmp_dir/case.XXXXXX")"
  before_runs="$case_id.before"
  after_runs="$case_id.after"
  output="$case_id.output"
  find "$tmp_dir/private/runs" -mindepth 1 -maxdepth 1 -type d -printf '%f\n' | sort >"$before_runs"
  if "${base[@]}" "$@" bash "$runner" >"$output" 2>&1; then echo 'Expected safe failure.' >&2; exit 1; fi
  find "$tmp_dir/private/runs" -mindepth 1 -maxdepth 1 -type d -printf '%f\n' | sort >"$after_runs"
  new_run="$(comm -13 "$before_runs" "$after_runs")"
  if [[ -n "$new_run" ]]; then
    [[ "$(printf '%s\n' "$new_run" | wc -l)" == 1 ]] || { echo 'Expected one Phase 2 run directory.' >&2; exit 1; }
    grep -F -- "$expected" "$tmp_dir/private/runs/$new_run/preflight.log" >/dev/null
  else
    # Unsafe roots are rejected before a private run directory can be created.
    [[ "$expected" == 'ERROR: Phase 2 private root is unsafe.' ]] || { echo 'Expected a preflight log for this case.' >&2; exit 1; }
    grep -F -- "$expected" "$output" >/dev/null
  fi
}
expect_fail 'Unknown Phase 2 profile.' NMKR_PHASE2_PROFILE=unknown
expect_fail 'RUN_REAL_SYNC must be true or false.' RUN_REAL_SYNC=maybe
expect_fail 'NMKR_RETAIN_AUTH_STATE must be true or false.' NMKR_RETAIN_AUTH_STATE=maybe
expect_fail 'ERROR: Phase 2 private root is unsafe.' NMKR_PHASE2_LOG_DIR="$ROOT"
ln -s "$ROOT" "$tmp_dir/escape"; expect_fail 'ERROR: Phase 2 private root is unsafe.' NMKR_PHASE2_LOG_DIR="$tmp_dir/escape"
# A configured root must not be a link even when its external target would
# otherwise meet the owner-private directory policy.
mkdir -p "$tmp_dir/external-private-root"
chmod 700 "$tmp_dir/external-private-root"
ln -s "$tmp_dir/external-private-root" "$tmp_dir/private-root-link"
if "${base[@]}" NMKR_PHASE2_PROFILE=unknown NMKR_PHASE2_LOG_DIR="$tmp_dir/private-root-link" bash "$runner" >"$tmp_dir/private-root-link.output" 2>&1; then
  echo 'Expected configured symlink root rejection.' >&2; exit 1
fi
grep -F -- 'ERROR: Phase 2 private root is unsafe.' "$tmp_dir/private-root-link.output" >/dev/null
test ! -e "$tmp_dir/external-private-root/runs"
# Existing mutable private parents must fail before a new run directory or log is opened.
assert_mutable_private_parent() {
  local target="$1" mode="$2" output="$tmp_dir/mutable-private.output"
  rm -rf "$tmp_dir/private"
  mkdir -p "$tmp_dir/private/runs"
  chmod 700 "$tmp_dir/private" "$tmp_dir/private/runs"
  chmod "$mode" "$target"
  if "${base[@]}" NMKR_PHASE2_PROFILE=unknown bash "$runner" >"$output" 2>&1; then
    echo 'Expected mutable private parent rejection.' >&2; exit 1
  fi
  grep -F -- 'ERROR: Phase 2 private run directory is unsafe.' "$output" >/dev/null
  if find "$tmp_dir/private" -type f -print -quit | grep -q .; then
    echo 'Unsafe private parent received a private file.' >&2; exit 1
  fi
  chmod 700 "$tmp_dir/private" "$tmp_dir/private/runs"
}
assert_mutable_private_parent "$tmp_dir/private/runs" 770
assert_mutable_private_parent "$tmp_dir/private/runs" 707
assert_mutable_private_parent "$tmp_dir/private" 770
assert_mutable_private_parent "$tmp_dir/private" 707
# A caller-selected profile cannot be changed or blanked by a sourced file.
printf 'NMKR_PHASE2_PROFILE=\nNMKR_DEPLOY_COMMAND="touch %s/marker"\nNMKR_PHASE2_SKIP_DEPLOY=false\n' "$tmp_dir" >"$tmp_dir/env"
expect_fail 'Phase 2 profile conflict.' NMKR_PHASE2_PROFILE=existing-readonly NMKR_PHASE2_ENV_FILE="$tmp_dir/env"
test ! -e "$tmp_dir/marker"
printf 'NMKR_PHASE2_PROFILE=general\n' >"$tmp_dir/env"; expect_fail 'Phase 2 profile conflict.' NMKR_PHASE2_PROFILE=existing-readonly NMKR_PHASE2_ENV_FILE="$tmp_dir/env"
printf 'NMKR_PHASE2_SKIP_DEPLOY=true\n' >"$tmp_dir/env"; expect_fail 'Expected source SHA is invalid.' NMKR_PHASE2_PROFILE=existing-readonly NMKR_PHASE2_ENV_FILE="$tmp_dir/env" NMKR_PHASE2_EXPECTED_SOURCE_SHA=invalid
printf 'NMKR_PHASE2_PROFILE=existing-readonly\n' >"$tmp_dir/env"; expect_fail 'Expected source SHA is invalid.' NMKR_PHASE2_ENV_FILE="$tmp_dir/env" NMKR_PHASE2_EXPECTED_SOURCE_SHA=invalid
# Direct Playwright path validation must fail closed without touching an exact-name sentinel.
mkdir -p "$tmp_dir/unrelated"; printf sentinel >"$tmp_dir/unrelated/auth-state.json"
if NMKR_AUTH_STATE_ROOT="$tmp_dir/unrelated" NMKR_AUTH_STATE_PATH="$tmp_dir/unrelated/auth-state.json" node "$ROOT/scripts/nmkr-playwright.js" --list >/dev/null 2>&1; then exit 1; fi
test "$(cat "$tmp_dir/unrelated/auth-state.json")" = sentinel
# Wrapper publishes one approved state path to config and all workers (discovery does no login).
NMKR_AUTH_STATE_ROOT= NMKR_AUTH_STATE_DIR= NMKR_AUTH_STATE_PATH= NMKR_AUTH_STATE_OWNER_TOKEN= node "$ROOT/scripts/nmkr-playwright.js" --list --reporter=list >/dev/null
# A pre-existing runs entry must never redirect private run files.
assert_unsafe_runs() {
  local target="$1" output="$tmp_dir/runs-unsafe.output"
  rm -rf "$tmp_dir/private/runs"
  ln -s "$target" "$tmp_dir/private/runs"
  if "${base[@]}" NMKR_PHASE2_PROFILE=unknown bash "$runner" >"$output" 2>&1; then
    echo 'Expected unsafe runs rejection.' >&2; exit 1
  fi
  grep -F -- 'ERROR: Phase 2 private run directory is unsafe.' "$output" >/dev/null
  test ! -e "$target/marker-private-write"
  rm -f "$tmp_dir/private/runs"; mkdir -p "$tmp_dir/private/runs"
}
mkdir -p "$tmp_dir/repository-target" "$tmp_dir/wordpress-target"
assert_unsafe_runs "$tmp_dir/repository-target"
assert_unsafe_runs "$tmp_dir/wordpress-target"
rm -rf "$tmp_dir/private/runs"; : >"$tmp_dir/private/runs"
if "${base[@]}" NMKR_PHASE2_PROFILE=unknown bash "$runner" >"$tmp_dir/runs-file.output" 2>&1; then
  echo 'Expected non-directory runs rejection.' >&2; exit 1
fi
grep -F -- 'ERROR: Phase 2 private run directory is unsafe.' "$tmp_dir/runs-file.output" >/dev/null
rm -f "$tmp_dir/private/runs"; mkdir -p "$tmp_dir/private/runs"

# Signals must stop a dedicated process group before deployment can complete or readiness begins.
mkdir -p "$tmp_dir/bin"
cat >"$tmp_dir/long-child.sh" <<'EOF_CHILD'
#!/usr/bin/env bash
sleep 30 &
child=$!
printf '%s' "$child" >"$TMP_DIR/active-child.pid"
wait "$child"
touch "$TMP_DIR/deploy-completed"
EOF_CHILD
cat >"$tmp_dir/bin/curl" <<EOF_CURL
#!/usr/bin/env bash
touch "$tmp_dir/later-stage-marker"
exit 1
EOF_CURL
chmod +x "$tmp_dir/long-child.sh" "$tmp_dir/bin/curl"
assert_signal() {
  local signal="$1" expected="$2" pid status=0 attempts=0
  rm -f "$tmp_dir/active-child.pid" "$tmp_dir/deploy-completed" "$tmp_dir/later-stage-marker"
  ( trap - INT TERM; exec setsid env TMP_DIR="$tmp_dir" PATH="$tmp_dir/bin:$PATH" WP_BASE_URL=http://invalid.test WP_ADMIN_USER=placeholder WP_ADMIN_PASSWORD=placeholder WP_CLI_BIN=true WP_PATH="$tmp_dir/wp" NMKR_PHASE2_LOG_DIR="$tmp_dir/private" NMKR_PHASE2_SKIP_DEPLOY=false NMKR_PHASE2_INSTALL_DEPS=false NMKR_PHASE2_INSTALL_BROWSER=false NMKR_DEPLOY_COMMAND="bash '$tmp_dir/long-child.sh'" bash "$runner" ) >"$tmp_dir/signal-$signal.output" 2>&1 &
  pid=$!
  while [[ ! -s "$tmp_dir/active-child.pid" && $attempts -lt 150 ]]; do sleep 0.1; attempts=$((attempts + 1)); done
  [[ -s "$tmp_dir/active-child.pid" ]] || { kill -KILL "$pid" 2>/dev/null || true; echo 'Long-lived child did not start.' >&2; exit 1; }
  kill "-$signal" "$pid"
  while kill -0 "$pid" 2>/dev/null && [[ "$(ps -o stat= -p "$pid" 2>/dev/null)" != Z* ]] && (( attempts < 150 )); do sleep 0.1; attempts=$((attempts + 1)); done
  if kill -0 "$pid" 2>/dev/null && [[ "$(ps -o stat= -p "$pid" 2>/dev/null)" != Z* ]]; then kill -KILL "$pid" 2>/dev/null || true; echo 'Runner did not exit promptly after signal.' >&2; exit 1; fi
  wait "$pid" || status=$?
  [[ "$status" == "$expected" ]] || { echo "Unexpected signal status: $status" >&2; exit 1; }
  child="$(cat "$tmp_dir/active-child.pid")"
  if kill -0 "$child" 2>/dev/null && [[ "$(ps -o stat= -p "$child" 2>/dev/null)" != Z* ]]; then
    echo 'Active child survived signal.' >&2; exit 1
  fi
  test ! -e "$tmp_dir/deploy-completed" && test ! -e "$tmp_dir/later-stage-marker"
}
assert_signal TERM 143
assert_signal INT 130
echo 'PASS: Phase 2 profile and auth-state validation regressions.'
