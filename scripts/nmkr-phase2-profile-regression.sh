#!/usr/bin/env bash
set -Eeuo pipefail
# Synthetic public-safe preflight cases: no usable host and no credentials.
ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
runner="$ROOT/scripts/nmkr-phase2-test-runner.sh"

# Resolve the caller's Node tools before clearing the environment below.  In
# particular, asdf's standard shims depend on HOME/ASDF_DATA_DIR and cannot be
# used after env -i replaces HOME.  Ask asdf for the selected executable while
# its caller environment is still intact, then retain only the resulting tool
# directories in the synthetic PATH.
resolve_node_tool() {
  local tool="$1" command_path resolved asdf_path
  command_path="$(command -v "$tool" 2>/dev/null || true)"
  if [[ -z "$command_path" || "$command_path" != /* ]]; then
    printf 'ERROR: Required Node tool is unavailable for Phase 2 regression.\n' >&2
    return 1
  fi

  if [[ "$command_path" == */.asdf/shims/* ]]; then
    asdf_path="$(command -v asdf 2>/dev/null || true)"
    if [[ -n "$asdf_path" && "$asdf_path" == /* ]]; then
      resolved="$("$asdf_path" which "$tool" 2>/dev/null || true)"
    else
      resolved=""
    fi
  else
    resolved="$command_path"
  fi

  if [[ -z "$resolved" ]] || ! resolved="$(realpath -e -- "$resolved" 2>/dev/null)" || [[ ! -x "$resolved" || "$resolved" == */.asdf/shims/* ]]; then
    printf 'ERROR: Active Node toolchain could not be resolved for Phase 2 regression.\n' >&2
    return 1
  fi
  printf '%s\n' "$resolved"
}

# Synthetic cases must not inherit a maintainer's Phase 2 settings. Resolve
# only the active Node toolchain and combine it with the minimum system PATH.
tool_dirs=()
for tool in node npm npx; do
  tool_path="$(resolve_node_tool "$tool")" || exit 1
  tool_dir="$(dirname -- "$tool_path")"
  seen=false
  for existing_dir in "${tool_dirs[@]}"; do
    [[ "$existing_dir" == "$tool_dir" ]] && seen=true && break
  done
  [[ "$seen" == true ]] || tool_dirs+=("$tool_dir")
done
safe_path="$(IFS=:; printf '%s' "${tool_dirs[*]}"):/usr/local/bin:/usr/bin:/bin"

if [[ "${1:-}" == --toolchain-smoke ]]; then
  smoke_dir="$(mktemp -d)"; trap 'rm -rf "$smoke_dir"' EXIT
  [[ -z "${ASDF_DATA_DIR+x}" && -z "${NMKR_PHASE2_PROFILE+x}" ]]
  [[ "$(env -i PATH="$safe_path" bash -c 'command -v node')" != */.asdf/shims/* ]]
  [[ "$(env -i PATH="$safe_path" bash -c 'command -v npm')" != */.asdf/shims/* ]]
  [[ "$(env -i PATH="$safe_path" bash -c 'command -v npx')" != */.asdf/shims/* ]]
  env -i PATH="$safe_path" HOME="$smoke_dir" TMPDIR="$smoke_dir" node --version >/dev/null
  env -i PATH="$safe_path" HOME="$smoke_dir" TMPDIR="$smoke_dir" npm --version >/dev/null
  env -i PATH="$safe_path" HOME="$smoke_dir" TMPDIR="$smoke_dir" npx --version >/dev/null
  exit 0
fi

tmp_dir="$(mktemp -d)"; trap 'rm -rf "$tmp_dir"' EXIT
mkdir -p "$tmp_dir/wp" "$tmp_dir/private/runs"
# Keep the synthetic private fixture owner-private regardless of the invoking
# shell's umask. Later cases intentionally relax these modes to exercise the
# runner's rejection paths and reset them to 0700.
chmod 700 "$tmp_dir/private" "$tmp_dir/private/runs"
[[ "$(stat -c '%a' "$tmp_dir/private")" == 700 ]]
[[ "$(stat -c '%a' "$tmp_dir/private/runs")" == 700 ]]
# Every synthetic runner invocation must explicitly select this empty,
# owner-private file. This prevents an ignored checkout .env.tests (including a
# private symlink) from influencing the public-safe fixtures.
synthetic_env="$tmp_dir/synthetic.env"
: >"$synthetic_env"
chmod 600 "$synthetic_env"
synthetic_home="$tmp_dir/home"
synthetic_tmp="$tmp_dir/tmp"
mkdir -p "$synthetic_home" "$synthetic_tmp"
chmod 700 "$synthetic_home" "$synthetic_tmp"
base=(env -i PATH="$safe_path" HOME="$synthetic_home" TMPDIR="$synthetic_tmp" WP_BASE_URL=http://invalid.test WP_ADMIN_USER=placeholder WP_ADMIN_PASSWORD=placeholder WP_CLI_BIN=true WP_PATH="$tmp_dir/wp" NMKR_PHASE2_LOG_DIR="$tmp_dir/private" NMKR_PHASE2_ENV_FILE="$synthetic_env" NMKR_PHASE2_PROFILE= RUN_REAL_SYNC=false PW_SAVE_ARTIFACTS=false NMKR_RETAIN_AUTH_STATE=false NMKR_PHASE2_INSTALL_DEPS=false NMKR_PHASE2_INSTALL_BROWSER=false NMKR_PHASE2_SKIP_DEPLOY=true)

# Exercise asdf-style shims without requiring asdf in CI. The child starts
# with only shim entries for Node tools and a minimal fake `asdf which`; the
# resolver must replace them with the caller's actual executable directories
# before its env -i toolchain smoke test runs.
asdf_fixture="$tmp_dir/.asdf"
mkdir -p "$asdf_fixture/shims" "$asdf_fixture/bin"
for tool in node npm npx; do
  actual_tool="$(resolve_node_tool "$tool")"
  cat >"$asdf_fixture/shims/$tool" <<EOF_SHIM
#!/usr/bin/env bash
exec "$actual_tool" "\$@"
EOF_SHIM
  chmod 700 "$asdf_fixture/shims/$tool"
done
cat >"$asdf_fixture/bin/asdf" <<EOF_ASDF
#!/usr/bin/env bash
case "\$1:\${2:-}" in
  which:node) printf '%s\\n' "$(resolve_node_tool node)" ;;
  which:npm) printf '%s\\n' "$(resolve_node_tool npm)" ;;
  which:npx) printf '%s\\n' "$(resolve_node_tool npx)" ;;
  *) exit 1 ;;
esac
EOF_ASDF
chmod 700 "$asdf_fixture/bin/asdf"
env -i PATH="$asdf_fixture/shims:$asdf_fixture/bin:/usr/local/bin:/usr/bin:/bin" HOME="$tmp_dir/asdf-home" TMPDIR="$synthetic_tmp" bash "$0" --toolchain-smoke
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
# Caller-selected runtime integrity cannot be downgraded by a sourced file.
printf 'NMKR_PHASE2_RUNTIME_INTEGRITY=false\n' >"$tmp_dir/env"; expect_fail 'Phase 2 runtime integrity conflict.' NMKR_PHASE2_RUNTIME_INTEGRITY=true NMKR_PHASE2_ENV_FILE="$tmp_dir/env"
printf 'NMKR_PHASE2_SKIP_DEPLOY=true\n' >"$tmp_dir/env"; expect_fail 'Expected source SHA is invalid.' NMKR_PHASE2_PROFILE=existing-readonly NMKR_PHASE2_ENV_FILE="$tmp_dir/env" NMKR_PHASE2_EXPECTED_SOURCE_SHA=invalid
printf 'NMKR_PHASE2_PROFILE=existing-readonly\n' >"$tmp_dir/env"; expect_fail 'Expected source SHA is invalid.' NMKR_PHASE2_ENV_FILE="$tmp_dir/env" NMKR_PHASE2_EXPECTED_SOURCE_SHA=invalid
# A sourced env file cannot clear the runner's selected-file identity before
# the later Playwright command is launched.
mkdir -p "$tmp_dir/env-selection-bin"
cat >"$tmp_dir/env-selection-bin/curl" <<'EOF_CURL'
#!/usr/bin/env bash
while (($#)); do
  if [[ "$1" == -o ]]; then
    shift
    printf '<input id="user_login">' >"$1"
  fi
  shift
done
printf '200'
EOF_CURL
cat >"$tmp_dir/env-selection-bin/npm" <<EOF_NPM
#!/usr/bin/env bash
printf '%s' "\$NMKR_PHASE2_ENV_FILE" >"$tmp_dir/selected-env-observed"
exit 1
EOF_NPM
chmod 700 "$tmp_dir/env-selection-bin/curl" "$tmp_dir/env-selection-bin/npm"
outside_dir="$tmp_dir/outside"
mkdir -p "$outside_dir"
chmod 700 "$outside_dir"
selected_env="$outside_dir/selected.env"
printf 'NMKR_PHASE2_ENV_FILE=\nNMKR_PHASE2_SKIP_DEPLOY=true\nNMKR_PHASE2_INSTALL_DEPS=false\nNMKR_PHASE2_INSTALL_BROWSER=false\n' >"$selected_env"
chmod 600 "$selected_env"
checkout_collision="$ROOT/$(basename "$selected_env")"
if [[ -e "$checkout_collision" || -L "$checkout_collision" ]]; then
  echo 'Expected an unused checkout collision fixture name.' >&2; exit 1
fi
printf 'NMKR_PHASE2_ENV_FILE=checkout-replacement\n' >"$checkout_collision"
trap 'rm -f "$checkout_collision"; rm -rf "$tmp_dir"' EXIT
if (cd "$outside_dir" && env -i PATH="$tmp_dir/env-selection-bin:$safe_path" HOME="$synthetic_home" TMPDIR="$synthetic_tmp" WP_BASE_URL=http://invalid.test WP_ADMIN_USER=placeholder WP_ADMIN_PASSWORD=placeholder WP_CLI_BIN=true WP_PATH="$tmp_dir/wp" NMKR_PHASE2_LOG_DIR="$tmp_dir/private" NMKR_PHASE2_ENV_FILE=selected.env NMKR_PHASE2_PROFILE= RUN_REAL_SYNC=false PW_SAVE_ARTIFACTS=false NMKR_RETAIN_AUTH_STATE=false bash "$runner") >/dev/null 2>&1; then
  echo 'Expected synthetic Playwright failure.' >&2; exit 1
fi
test "$(cat "$tmp_dir/selected-env-observed")" = "$selected_env"
# Direct Playwright path validation must fail closed without touching an exact-name sentinel.
mkdir -p "$tmp_dir/unrelated"; printf sentinel >"$tmp_dir/unrelated/auth-state.json"
if "${base[@]}" NMKR_AUTH_STATE_ROOT="$tmp_dir/unrelated" NMKR_AUTH_STATE_PATH="$tmp_dir/unrelated/auth-state.json" node "$ROOT/scripts/nmkr-playwright.js" --list >/dev/null 2>&1; then exit 1; fi
test "$(cat "$tmp_dir/unrelated/auth-state.json")" = sentinel
# Wrapper publishes one approved state path to config and all workers (discovery does no login).
"${base[@]}" node "$ROOT/scripts/nmkr-playwright.js" --list --reporter=list >/dev/null
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
  rm -f "$tmp_dir/private/runs"; mkdir -p "$tmp_dir/private/runs"; chmod 700 "$tmp_dir/private/runs"
}
mkdir -p "$tmp_dir/repository-target" "$tmp_dir/wordpress-target"
assert_unsafe_runs "$tmp_dir/repository-target"
assert_unsafe_runs "$tmp_dir/wordpress-target"
rm -rf "$tmp_dir/private/runs"; : >"$tmp_dir/private/runs"
if "${base[@]}" NMKR_PHASE2_PROFILE=unknown bash "$runner" >"$tmp_dir/runs-file.output" 2>&1; then
  echo 'Expected non-directory runs rejection.' >&2; exit 1
fi
grep -F -- 'ERROR: Phase 2 private run directory is unsafe.' "$tmp_dir/runs-file.output" >/dev/null
rm -f "$tmp_dir/private/runs"; mkdir -p "$tmp_dir/private/runs"; chmod 700 "$tmp_dir/private/runs"

rm -f "$checkout_collision"
# Targeted readonly selection is allowlisted, cannot be replaced by the env file,
# invokes only the selected package script, skips DB state, and rechecks integrity.
target_fixture="$tmp_dir/target-fixture"
cp -a "$ROOT/." "$target_fixture/"
git -C "$target_fixture" config user.email public@example.invalid
git -C "$target_fixture" config user.name PublicTest
git -C "$target_fixture" add AGENTS.md docs package.json scripts
git -C "$target_fixture" commit --allow-empty -q -m 'synthetic targeted profile fixture'
target_sha="$(git -C "$target_fixture" rev-parse HEAD)"
target_bin="$tmp_dir/target-bin"; mkdir -p "$target_bin"
cat >"$target_bin/node" <<'EOF_NODE'
#!/usr/bin/env bash
exit 0
EOF_NODE
cat >"$target_bin/npm" <<'EOF_NPM'
#!/usr/bin/env bash
printf '%s\n' "$*" >>"$TARGET_CALL_LOG"
if [[ "${TARGET_DIRTY_AFTER_PLAYWRIGHT:-false}" == true && "$*" == 'run test:e2e:settings' ]]; then
  printf '\nsynthetic-dirty\n' >>"$TARGET_REPO/README.md"
fi
exit 0
EOF_NPM
cat >"$target_bin/wp" <<'EOF_WP'
#!/usr/bin/env bash
case "$*" in
  *'db prefix'*) printf 'wp_\n' ;;
  *'SHOW TABLES LIKE'*) printf '%s\n' "$*" | sed -n "s/.*SHOW TABLES LIKE '\([^']*\)'.*/\1/p" ;;
  *'option get nmkr_api_key'*) printf '"synthetic"\n' ;;
  *'SELECT COUNT(*)'*) printf '0\n' ;;
  *) : ;;
esac
EOF_WP
cat >"$target_bin/curl" <<'EOF_CURL'
#!/usr/bin/env bash
while (($#)); do
  if [[ "$1" == -o ]]; then shift; printf '<input id="user_login">' >"$1"; fi
  shift
done
printf '200'
EOF_CURL
chmod 700 "$target_bin/node" "$target_bin/npm" "$target_bin/wp" "$target_bin/curl"
target_private="$tmp_dir/target-private"; mkdir -m700 "$target_private"
target_env="$tmp_dir/target.env"; : >"$target_env"; chmod 600 "$target_env"
target_log="$tmp_dir/target-calls"
target_base=(env -i PATH="$target_bin:$safe_path" HOME="$synthetic_home" TMPDIR="$synthetic_tmp" TARGET_CALL_LOG="$target_log" TARGET_REPO="$target_fixture" WP_BASE_URL=http://invalid.test WP_ADMIN_USER=placeholder WP_ADMIN_PASSWORD=placeholder WP_CLI_BIN="$target_bin/wp" WP_PATH="$tmp_dir/wp" NMKR_PHASE2_LOG_DIR="$target_private" NMKR_PHASE2_ENV_FILE="$target_env" NMKR_PHASE2_PROFILE=targeted-readonly NMKR_PHASE2_TARGET_SUITE=settings NMKR_PHASE2_EXPECTED_SOURCE_SHA="$target_sha" NMKR_DEPLOYED_PLUGIN_PATH="$target_fixture" NMKR_PHASE2_INSTALL_DEPS=false NMKR_PHASE2_INSTALL_BROWSER=false NMKR_PHASE2_SKIP_DEPLOY=true RUN_REAL_SYNC=false PW_SAVE_ARTIFACTS=false NMKR_RETAIN_AUTH_STATE=false)
"${target_base[@]}" bash "$target_fixture/scripts/nmkr-phase2-test-runner.sh" >"$tmp_dir/target-success.output"
grep -Fx 'run test:e2e:settings' "$target_log" >/dev/null
! grep -Fx 'run test:e2e' "$target_log" >/dev/null
grep -F 'targeted-suite: settings' "$tmp_dir/target-success.output" >/dev/null
grep -F 'db-state: SKIPPED' "$tmp_dir/target-success.output" >/dev/null
grep -F 'source-integrity: PASS' "$tmp_dir/target-success.output" >/dev/null
grep -F 'deployed-integrity: PASS' "$tmp_dir/target-success.output" >/dev/null
grep -F 'final-integrity: PASS' "$tmp_dir/target-success.output" >/dev/null

# Targeted readonly requires deployment identity, while existing readonly keeps
# accepting an omitted deployed path. General runtime integrity runs only after
# deployment and fails closed rather than reaching functional validation.
if "${target_base[@]/NMKR_DEPLOYED_PLUGIN_PATH=$target_fixture/NMKR_DEPLOYED_PLUGIN_PATH=}" bash "$target_fixture/scripts/nmkr-phase2-test-runner.sh" >"$tmp_dir/target-missing-deployed.output" 2>&1; then exit 1; fi
grep -F 'failed step: preflight' "$tmp_dir/target-missing-deployed.output" >/dev/null

: >"$target_log"
if "${target_base[@]/NMKR_PHASE2_PROFILE=targeted-readonly/NMKR_PHASE2_PROFILE=existing-readonly}" NMKR_DEPLOYED_PLUGIN_PATH= NMKR_PHASE2_TARGET_SUITE= bash "$target_fixture/scripts/nmkr-phase2-test-runner.sh" >"$tmp_dir/existing-compatible.output" 2>&1; then exit 1; fi
grep -Fx 'run test:e2e' "$target_log" >/dev/null
grep -F 'failed step: wpcli-db-state' "$tmp_dir/existing-compatible.output" >/dev/null
grep -F 'deployed-integrity: SKIPPED' "$tmp_dir/existing-compatible.output" >/dev/null

general_deploy_marker="$tmp_dir/general-deployed"
if "${target_base[@]/NMKR_PHASE2_PROFILE=targeted-readonly/NMKR_PHASE2_PROFILE=}" NMKR_DEPLOYED_PLUGIN_PATH= NMKR_PHASE2_TARGET_SUITE= NMKR_PHASE2_RUNTIME_INTEGRITY=true NMKR_PHASE2_SKIP_DEPLOY=false NMKR_DEPLOY_COMMAND="touch '$general_deploy_marker'" bash "$target_fixture/scripts/nmkr-phase2-test-runner.sh" >"$tmp_dir/general-runtime.output" 2>&1; then exit 1; fi
test -e "$general_deploy_marker"
grep -F 'failed step: runtime-integrity' "$tmp_dir/general-runtime.output" >/dev/null
grep -F 'runtime-integrity: SKIPPED' "$tmp_dir/general-runtime.output" >/dev/null && exit 1

printf 'NMKR_PHASE2_TARGET_SUITE=dashboard\n' >"$target_env"
if "${target_base[@]}" bash "$target_fixture/scripts/nmkr-phase2-test-runner.sh" >"$tmp_dir/target-conflict.output" 2>&1; then exit 1; fi
grep -F 'failed step: preflight' "$tmp_dir/target-conflict.output" >/dev/null
: >"$target_env"
if "${target_base[@]/NMKR_PHASE2_TARGET_SUITE=settings/NMKR_PHASE2_TARGET_SUITE=unknown}" bash "$target_fixture/scripts/nmkr-phase2-test-runner.sh" >"$tmp_dir/target-unknown.output" 2>&1; then exit 1; fi
grep -F 'failed step: preflight' "$tmp_dir/target-unknown.output" >/dev/null

: >"$target_log"
if "${target_base[@]}" TARGET_DIRTY_AFTER_PLAYWRIGHT=true bash "$target_fixture/scripts/nmkr-phase2-test-runner.sh" >"$tmp_dir/target-dirty.output" 2>&1; then exit 1; fi
grep -F 'failed step: final-integrity' "$tmp_dir/target-dirty.output" >/dev/null
git -C "$target_fixture" checkout -q -- README.md

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
  ( trap - INT TERM; exec setsid env -i PATH="$tmp_dir/bin:$safe_path" HOME="$synthetic_home" TMPDIR="$synthetic_tmp" TMP_DIR="$tmp_dir" WP_BASE_URL=http://invalid.test WP_ADMIN_USER=placeholder WP_ADMIN_PASSWORD=placeholder WP_CLI_BIN=true WP_PATH="$tmp_dir/wp" NMKR_PHASE2_LOG_DIR="$tmp_dir/private" NMKR_PHASE2_ENV_FILE="$synthetic_env" NMKR_PHASE2_PROFILE= RUN_REAL_SYNC=false PW_SAVE_ARTIFACTS=false NMKR_RETAIN_AUTH_STATE=false NMKR_PHASE2_SKIP_DEPLOY=false NMKR_PHASE2_INSTALL_DEPS=false NMKR_PHASE2_INSTALL_BROWSER=false NMKR_DEPLOY_COMMAND="bash '$tmp_dir/long-child.sh'" bash "$runner" ) >"$tmp_dir/signal-$signal.output" 2>&1 &
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
