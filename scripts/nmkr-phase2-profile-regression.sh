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

# Operator syntax is fail-closed before private preflight. Docs/metadata is a
# public-CI-only class and must reject before even sourcing a configured file.
operator_sha=0123456789abcdef0123456789abcdef01234567
operator_args=(--stage pre-merge --class test-tooling --profile targeted-readonly --reviewed-sha "$operator_sha" --deployed-sha "$operator_sha" --target-suite settings --deploy-mode skip --runtime-integrity false)
for bad_args in '--unknown value' '--stage' '--stage pre-merge --stage pre-merge' '--stage invalid'; do
  read -r -a bad <<<"$bad_args"
  if env -i PATH="$safe_path" HOME="$synthetic_home" bash "$runner" "${bad[@]}" >/dev/null 2>&1; then exit 1; fi
done
if env -i PATH="$safe_path" HOME="$synthetic_home" bash "$runner" "${operator_args[@]/test-tooling/high-risk}" >/dev/null 2>&1; then exit 1; fi
docs_env="$tmp_dir/docs-private.env"
printf 'touch "%s"\n' "$tmp_dir/docs-env-loaded" >"$docs_env"
if env -i PATH="$safe_path" HOME="$synthetic_home" NMKR_PHASE2_ENV_FILE="$docs_env" bash "$runner" "${operator_args[@]/test-tooling/docs-metadata}" >"$tmp_dir/docs-rejection.output" 2>&1; then exit 1; fi
grep -F 'use public CI' "$tmp_dir/docs-rejection.output" >/dev/null
test ! -e "$tmp_dir/docs-env-loaded"

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
cat >"$target_fixture/scripts/nmkr-ajax-runtime-integrity.sh" <<'EOF_RUNTIME_INTEGRITY'
#!/usr/bin/env bash
printf 'runtime-integrity\n' >>"$RUNTIME_INTEGRITY_CALL_LOG"
EOF_RUNTIME_INTEGRITY
chmod 700 "$target_fixture/scripts/nmkr-ajax-runtime-integrity.sh"
git -C "$target_fixture" add AGENTS.md docs package.json scripts
git -C "$target_fixture" commit --allow-empty -q -m 'synthetic targeted profile fixture'
target_sha="$(git -C "$target_fixture" rev-parse HEAD)"
deployed_fixture="$tmp_dir/deployed-fixture"
cp -a "$target_fixture/." "$deployed_fixture/"
target_bin="$tmp_dir/target-bin"; mkdir -p "$target_bin"
real_git="$(command -v git)"
cat >"$target_bin/git" <<'EOF_GIT'
#!/usr/bin/env bash
worktree=
args=("$@")
if [[ "${1:-}" == -C ]]; then worktree="$2"; shift 2; fi
if [[ "${1:-}" == -c && "${2:-}" == core.fileMode=true ]]; then shift 2; fi
if [[ "${1:-}" == status && -n "${FAIL_GIT_STATUS_PATH:-}" && "$worktree" == "$FAIL_GIT_STATUS_PATH" ]]; then
  counter="${GIT_STATUS_COUNTER_DIR}/$(printf '%s' "$worktree" | sed 's/[^A-Za-z0-9]/_/g')"
  count=0; [[ ! -f "$counter" ]] || read -r count <"$counter"
  count=$((count + 1)); printf '%s\n' "$count" >"$counter"
  [[ "$count" != "${FAIL_GIT_STATUS_CALL:-1}" ]] || exit 73
fi
exec "$REAL_GIT" "${args[@]}"
EOF_GIT
cat >"$target_bin/node" <<'EOF_NODE'
#!/usr/bin/env bash
exit 0
EOF_NODE
cat >"$target_bin/npm" <<'EOF_NPM'
#!/usr/bin/env bash
printf '%s\n' "$*" >>"$TARGET_CALL_LOG"
printf 'safety RUN_REAL_SYNC=%s PW_SAVE_ARTIFACTS=%s\n' "${RUN_REAL_SYNC:-unset}" "${PW_SAVE_ARTIFACTS:-unset}" >>"$TARGET_CALL_LOG"
if [[ "${TARGET_DIRTY_AFTER_PLAYWRIGHT:-false}" == true && "$*" == 'run test:e2e:settings' ]]; then
  printf '\nsynthetic-dirty\n' >>"$TARGET_REPO/README.md"
fi
if [[ -n "${TARGET_HIDDEN_AFTER_PLAYWRIGHT:-}" && "$*" == 'run test:e2e:settings' ]]; then
  hidden_repo="$TARGET_REPO"
  [[ "${TARGET_HIDDEN_WORKTREE:-source}" != deployed ]] || hidden_repo="$TARGET_DEPLOYED_REPO"
  "$REAL_GIT" -C "$hidden_repo" update-index "--$TARGET_HIDDEN_AFTER_PLAYWRIGHT" README.md
  printf '\nsynthetic-hidden\n' >>"$hidden_repo/README.md"
fi
if [[ "${TARGET_MODE_AFTER_PLAYWRIGHT:-false}" == true && "$*" == 'run test:e2e:settings' ]]; then
  mode_repo="$TARGET_REPO"
  [[ "${TARGET_MODE_WORKTREE:-source}" != deployed ]] || mode_repo="$TARGET_DEPLOYED_REPO"
  chmod +x "$mode_repo/README.md"
fi
exit 0
EOF_NPM
cat >"$target_bin/wp" <<'EOF_WP'
#!/usr/bin/env bash
printf 'wp %s\n' "$*" >>"$TARGET_CALL_LOG"
case "$*" in
  *' eval '*)
    case "${TARGET_ACTIVE_PLUGIN_MODE:-valid}" in
      valid) printf '%s' "$TARGET_ACTIVE_PLUGIN_FILE" ;;
      malformed) printf 'not-an-absolute-plugin-path' ;;
      failure) exit 1 ;;
    esac
    ;;
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
chmod 700 "$target_bin/git" "$target_bin/node" "$target_bin/npm" "$target_bin/wp" "$target_bin/curl"
target_private="$tmp_dir/target-private"; mkdir -m700 "$target_private"
target_env="$tmp_dir/target.env"; : >"$target_env"; chmod 600 "$target_env"
target_log="$tmp_dir/target-calls"
git_counter_dir="$tmp_dir/git-status-counters"; mkdir "$git_counter_dir"
runtime_integrity_log="$tmp_dir/runtime-integrity-calls"
target_base=(env -i PATH="$target_bin:$safe_path" HOME="$synthetic_home" TMPDIR="$synthetic_tmp" REAL_GIT="$real_git" TARGET_CALL_LOG="$target_log" TARGET_REPO="$target_fixture" TARGET_DEPLOYED_REPO="$deployed_fixture" TARGET_ACTIVE_PLUGIN_FILE="$deployed_fixture/nmkr-connect.php" RUNTIME_INTEGRITY_CALL_LOG="$runtime_integrity_log" GIT_STATUS_COUNTER_DIR="$git_counter_dir" WP_BASE_URL=http://invalid.test WP_ADMIN_USER=placeholder WP_ADMIN_PASSWORD=placeholder WP_CLI_BIN="$target_bin/wp" WP_PATH="$tmp_dir/wp" NMKR_PLUGIN_SLUG=nmkr-connect.php NMKR_PHASE2_LOG_DIR="$target_private" NMKR_PHASE2_ENV_FILE="$target_env" NMKR_PHASE2_PROFILE=targeted-readonly NMKR_PHASE2_TARGET_SUITE=settings NMKR_PHASE2_EXPECTED_SOURCE_SHA="$target_sha" NMKR_DEPLOYED_PLUGIN_PATH="$deployed_fixture" NMKR_PHASE2_INSTALL_DEPS=false NMKR_PHASE2_INSTALL_BROWSER=false NMKR_PHASE2_SKIP_DEPLOY=true RUN_REAL_SYNC=false PW_SAVE_ARTIFACTS=false NMKR_RETAIN_AUTH_STATE=false)
"${target_base[@]}" bash "$target_fixture/scripts/nmkr-phase2-test-runner.sh" >"$tmp_dir/target-success.output"
grep -Fx 'run test:e2e:settings' "$target_log" >/dev/null
! grep -Fx 'run test:e2e' "$target_log" >/dev/null
grep -F 'targeted-suite: settings' "$tmp_dir/target-success.output" >/dev/null
grep -F 'db-state: SKIPPED' "$tmp_dir/target-success.output" >/dev/null
grep -F 'source-integrity: PASS' "$tmp_dir/target-success.output" >/dev/null
grep -F 'deployed-integrity: PASS' "$tmp_dir/target-success.output" >/dev/null
grep -F 'final-integrity: PASS' "$tmp_dir/target-success.output" >/dev/null

# Unified operator mode preserves every explicit selection across env sourcing,
# validates exact pre-merge identity, and keeps deployment invocation opaque.
operator_target_args=(--stage pre-merge --class test-tooling --profile targeted-readonly --reviewed-sha "$target_sha" --deployed-sha "$target_sha" --target-suite settings --deploy-mode skip --runtime-integrity false)
: >"$target_log"
"${target_base[@]}" env -u NMKR_PHASE2_PROFILE -u NMKR_PHASE2_TARGET_SUITE bash "$target_fixture/scripts/nmkr-phase2-test-runner.sh" "${operator_target_args[@]}" >"$tmp_dir/operator-target.output"
grep -Fx 'run test:e2e:settings' "$target_log" >/dev/null
grep -F 'stage: pre-merge' "$tmp_dir/operator-target.output" >/dev/null
grep -F 'rollback: NOT_ATTEMPTED' "$tmp_dir/operator-target.output" >/dev/null

# Accepted uppercase SHAs normalize to Git's lowercase identity before every
# comparison and are reported in normalized form.
uppercase_sha="${target_sha^^}"
uppercase_args=(--stage pre-merge --class test-tooling --profile targeted-readonly --reviewed-sha "$uppercase_sha" --deployed-sha "$uppercase_sha" --target-suite settings --deploy-mode skip --runtime-integrity false)
"${target_base[@]}" env -u NMKR_PHASE2_PROFILE -u NMKR_PHASE2_TARGET_SUITE bash "$target_fixture/scripts/nmkr-phase2-test-runner.sh" "${uppercase_args[@]}" >"$tmp_dir/operator-uppercase.output"
grep -F "reviewed-commit: ${target_sha:0:12}" "$tmp_dir/operator-uppercase.output" >/dev/null

mismatch_args=(--stage pre-merge --class test-tooling --profile targeted-readonly --reviewed-sha "$target_sha" --deployed-sha 0123456789abcdef0123456789abcdef01234567 --target-suite settings --deploy-mode skip --runtime-integrity false)
if "${target_base[@]}" env -u NMKR_PHASE2_PROFILE -u NMKR_PHASE2_TARGET_SUITE bash "$target_fixture/scripts/nmkr-phase2-test-runner.sh" "${mismatch_args[@]}" >/dev/null 2>&1; then exit 1; fi

# Every explicit operator selection conflicts rather than being replaced or
# downgraded by the sourced environment file.
operator_env_names=(NMKR_PHASE2_STAGE NMKR_PHASE2_VALIDATION_CLASS NMKR_PHASE2_PROFILE NMKR_PHASE2_REVIEWED_SHA NMKR_PHASE2_DEPLOYED_SHA NMKR_PHASE2_TARGET_SUITE NMKR_PHASE2_DEPLOY_MODE NMKR_PHASE2_RUNTIME_INTEGRITY)
for env_name in "${operator_env_names[@]}"; do
  printf '%s=conflict\n' "$env_name" >"$target_env"
  if "${target_base[@]}" env -u NMKR_PHASE2_PROFILE -u NMKR_PHASE2_TARGET_SUITE bash "$target_fixture/scripts/nmkr-phase2-test-runner.sh" "${operator_target_args[@]}" >"$tmp_dir/operator-conflict.output" 2>&1; then exit 1; fi
  grep -F 'operator selection conflicts' "$tmp_dir/operator-conflict.output" >/dev/null
done
: >"$target_env"

# Legacy OP_* assignments cannot change the immutable CLI request, while
# unsafe behavior toggles are forcibly disabled after private configuration.
cat >"$target_env" <<'EOF_OPERATOR_OVERRIDE'
OPERATOR_MODE=false
OP_STAGE=post-merge
OP_CLASS=high-risk
OP_PROFILE=existing-readonly
OP_REVIEWED_SHA=aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa
OP_DEPLOYED_SHA=bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb
OP_TARGET_SUITE=dashboard
OP_DEPLOY_MODE=run
OP_RUNTIME_INTEGRITY=true
RUN_REAL_SYNC=true
PW_SAVE_ARTIFACTS=true
EOF_OPERATOR_OVERRIDE
: >"$target_log"
"${target_base[@]}" env -u NMKR_PHASE2_PROFILE -u NMKR_PHASE2_TARGET_SUITE bash "$target_fixture/scripts/nmkr-phase2-test-runner.sh" "${operator_target_args[@]}" >"$tmp_dir/operator-immutable.output"
grep -F 'stage: pre-merge' "$tmp_dir/operator-immutable.output" >/dev/null
grep -Fx 'run test:e2e:settings' "$target_log" >/dev/null
grep -F 'safety RUN_REAL_SYNC=false PW_SAVE_ARTIFACTS=false' "$target_log" >/dev/null
: >"$target_env"

# Attempts to assign the protected internal namespace fail closed while the
# private file is sourced, before deployment or functional validation, without
# exposing any part of the private source diagnostic.
printf 'CLI_STAGE=post-merge\n' >"$target_env"
protected_state_count="$tmp_dir/protected-state-deploy-count"
protected_output="$tmp_dir/protected-state.output"
: >"$target_log"
if "${target_base[@]}" env -u NMKR_PHASE2_PROFILE -u NMKR_PHASE2_TARGET_SUITE NMKR_DEPLOY_COMMAND="printf 'call\\n' >>'$protected_state_count'" bash "$target_fixture/scripts/nmkr-phase2-test-runner.sh" "${operator_target_args[@]}" >"$protected_output" 2>&1; then exit 1; fi
test ! -e "$protected_state_count"
grep -Fx 'ERROR: Phase 2 private configuration could not be loaded.' "$protected_output" >/dev/null
! grep -F "$target_env" "$protected_output" >/dev/null
! grep -F "$(basename "$target_env")" "$protected_output" >/dev/null
! grep -E 'line [0-9]+|CLI_STAGE=|readonly variable' "$protected_output" >/dev/null
! grep -E '^run test:e2e|wp .*nmkr-wpcli-(smoke|db-state)' "$target_log" >/dev/null
: >"$target_env"

deploy_count="$tmp_dir/operator-deploy-count"
run_deployed_fixture="$tmp_dir/run-deployed-fixture"
deploy_command="printf 'call\\n' >>'$deploy_count'; '$real_git' clone -q '$target_fixture' '$run_deployed_fixture'"
run_args=(--stage pre-merge --class test-tooling --profile targeted-readonly --reviewed-sha "$target_sha" --deployed-sha "$target_sha" --target-suite settings --deploy-mode run --runtime-integrity false)
: >"$target_log"
"${target_base[@]}" env -u NMKR_PHASE2_PROFILE -u NMKR_PHASE2_TARGET_SUITE NMKR_DEPLOYED_PLUGIN_PATH="$run_deployed_fixture" TARGET_ACTIVE_PLUGIN_FILE="$run_deployed_fixture/nmkr-connect.php" NMKR_DEPLOY_COMMAND="$deploy_command" bash "$target_fixture/scripts/nmkr-phase2-test-runner.sh" "${run_args[@]}" >"$tmp_dir/operator-deploy.output"
test "$(wc -l <"$deploy_count")" = 1
grep -F 'deploy: PASS' "$tmp_dir/operator-deploy.output" >/dev/null
! grep -F "$deploy_command" "$tmp_dir/operator-deploy.output" >/dev/null
grep -Fx 'run test:e2e:settings' "$target_log" >/dev/null

# A missing configured verification target fails before the opaque mutation.
rm -f "$deploy_count"
if "${target_base[@]}" env -u NMKR_PHASE2_PROFILE -u NMKR_PHASE2_TARGET_SUITE NMKR_DEPLOYED_PLUGIN_PATH= NMKR_DEPLOY_COMMAND="printf 'call\\n' >>'$deploy_count'" bash "$target_fixture/scripts/nmkr-phase2-test-runner.sh" "${run_args[@]}" >"$tmp_dir/operator-missing-path.output" 2>&1; then exit 1; fi
test ! -e "$deploy_count"
grep -F 'failed step: preflight' "$tmp_dir/operator-missing-path.output" >/dev/null

# Deployment is invoked once, then an incorrect active binding fails at the
# deploy-integrity boundary before any functional command can start.
rm -rf "$run_deployed_fixture"; : >"$target_log"; rm -f "$deploy_count"
if "${target_base[@]}" env -u NMKR_PHASE2_PROFILE -u NMKR_PHASE2_TARGET_SUITE NMKR_DEPLOYED_PLUGIN_PATH="$run_deployed_fixture" TARGET_ACTIVE_PLUGIN_FILE="$target_fixture/nmkr-connect.php" NMKR_DEPLOY_COMMAND="$deploy_command" bash "$target_fixture/scripts/nmkr-phase2-test-runner.sh" "${run_args[@]}" >"$tmp_dir/operator-binding-failure.output" 2>&1; then exit 1; fi
test "$(wc -l <"$deploy_count")" = 1
grep -F 'failed step: deploy-integrity' "$tmp_dir/operator-binding-failure.output" >/dev/null
grep -F 'rollback: NOT_ATTEMPTED' "$tmp_dir/operator-binding-failure.output" >/dev/null
! grep -E '^run test:e2e|wp .*nmkr-wpcli-(smoke|db-state)' "$target_log" >/dev/null
test "$(grep -c '^wp ' "$target_log")" = 1

rm -f "$deploy_count"
"${target_base[@]}" env -u NMKR_PHASE2_PROFILE -u NMKR_PHASE2_TARGET_SUITE NMKR_DEPLOY_COMMAND="$deploy_command" bash "$target_fixture/scripts/nmkr-phase2-test-runner.sh" "${operator_target_args[@]}" >/dev/null
test ! -e "$deploy_count"

# Post-merge accepts distinct commits only when their source-tree objects are
# identical; missing reviewed commits and different trees fail closed.
git -C "$target_fixture" commit --allow-empty -q -m 'synthetic merged equivalent tree'
merged_sha="$(git -C "$target_fixture" rev-parse HEAD)"
git -C "$deployed_fixture" fetch -q "$target_fixture" "$merged_sha"
git -C "$deployed_fixture" reset --hard -q "$merged_sha"
post_args=(--stage post-merge --class test-tooling --profile targeted-readonly --reviewed-sha "$target_sha" --deployed-sha "$merged_sha" --target-suite settings --deploy-mode skip --runtime-integrity false)
"${target_base[@]}" env -u NMKR_PHASE2_PROFILE -u NMKR_PHASE2_TARGET_SUITE bash "$target_fixture/scripts/nmkr-phase2-test-runner.sh" "${post_args[@]}" >"$tmp_dir/operator-post.output"
grep -F 'reviewed-tree-equivalence: PASS' "$tmp_dir/operator-post.output" >/dev/null
# Tree equivalence is established only between the two exact commit identities.
# A matching raw tree or another non-commit object fails before functional work
# and must never be summarized as reviewed-tree equivalence PASS.
matching_tree="$(git -C "$target_fixture" rev-parse "$target_sha^{tree}")"
noncommit_blob="$(git -C "$target_fixture" rev-parse "$target_sha:README.md")"
for identity_case in reviewed-tree deployed-blob; do
  invalid_args=("${post_args[@]}")
  if [[ "$identity_case" == reviewed-tree ]]; then
    invalid_args=("${invalid_args[@]/$target_sha/$matching_tree}")
  else
    invalid_args=("${invalid_args[@]/$merged_sha/$noncommit_blob}")
  fi
  : >"$target_log"
  invalid_output="$tmp_dir/operator-post-$identity_case.output"
  if "${target_base[@]}" env -u NMKR_PHASE2_PROFILE -u NMKR_PHASE2_TARGET_SUITE bash "$target_fixture/scripts/nmkr-phase2-test-runner.sh" "${invalid_args[@]}" >"$invalid_output" 2>&1; then exit 1; fi
  grep -F 'reviewed-tree-equivalence: NOT_ESTABLISHED' "$invalid_output" >/dev/null
  ! grep -F 'reviewed-tree-equivalence: PASS' "$invalid_output" >/dev/null
  ! grep -E '^run test:e2e|wp .*nmkr-wpcli-(smoke|db-state)' "$target_log" >/dev/null
done
missing_sha=aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa
if "${target_base[@]}" env -u NMKR_PHASE2_PROFILE -u NMKR_PHASE2_TARGET_SUITE bash "$target_fixture/scripts/nmkr-phase2-test-runner.sh" "${post_args[@]/$target_sha/$missing_sha}" >/dev/null 2>&1; then exit 1; fi
printf '\nchanged-tree\n' >>"$target_fixture/README.md"
git -C "$target_fixture" add README.md
git -C "$target_fixture" commit -q -m 'synthetic merged different tree'
different_sha="$(git -C "$target_fixture" rev-parse HEAD)"
git -C "$deployed_fixture" fetch -q "$target_fixture" "$different_sha"
git -C "$deployed_fixture" reset --hard -q "$different_sha"
if "${target_base[@]}" env -u NMKR_PHASE2_PROFILE -u NMKR_PHASE2_TARGET_SUITE bash "$target_fixture/scripts/nmkr-phase2-test-runner.sh" "${post_args[@]/$merged_sha/$different_sha}" >/dev/null 2>&1; then exit 1; fi
git -C "$target_fixture" reset --hard -q "$target_sha"
git -C "$deployed_fixture" reset --hard -q "$target_sha"

# Targeted readonly requires deployment identity, while existing readonly keeps
# accepting an omitted deployed path. General runtime integrity runs only after
# deployment and fails closed rather than reaching functional validation.
if "${target_base[@]/NMKR_DEPLOYED_PLUGIN_PATH=$deployed_fixture/NMKR_DEPLOYED_PLUGIN_PATH=}" bash "$target_fixture/scripts/nmkr-phase2-test-runner.sh" >"$tmp_dir/target-missing-deployed.output" 2>&1; then exit 1; fi
grep -F 'failed step: preflight' "$tmp_dir/target-missing-deployed.output" >/dev/null

: >"$target_log"
if "${target_base[@]/NMKR_PHASE2_PROFILE=targeted-readonly/NMKR_PHASE2_PROFILE=existing-readonly}" NMKR_DEPLOYED_PLUGIN_PATH= NMKR_PHASE2_TARGET_SUITE= bash "$target_fixture/scripts/nmkr-phase2-test-runner.sh" >"$tmp_dir/existing-compatible.output" 2>&1; then exit 1; fi
grep -Fx 'run test:e2e' "$target_log" >/dev/null
grep -F 'failed step: wpcli-db-state' "$tmp_dir/existing-compatible.output" >/dev/null
grep -F 'deployed-integrity: SKIPPED' "$tmp_dir/existing-compatible.output" >/dev/null

# A clean clone is insufficient unless the selected WordPress installation's
# active plugin resolves canonically to that deployed worktree.
if "${target_base[@]}" TARGET_ACTIVE_PLUGIN_FILE="$target_fixture/nmkr-connect.php" bash "$target_fixture/scripts/nmkr-phase2-test-runner.sh" >"$tmp_dir/target-binding-mismatch.output" 2>&1; then exit 1; fi
grep -F 'failed step: preflight' "$tmp_dir/target-binding-mismatch.output" >/dev/null
! grep -F "$target_fixture" "$tmp_dir/target-binding-mismatch.output" >/dev/null
for mode in failure malformed; do
  if "${target_base[@]}" TARGET_ACTIVE_PLUGIN_MODE="$mode" bash "$target_fixture/scripts/nmkr-phase2-test-runner.sh" >"$tmp_dir/target-binding-$mode.output" 2>&1; then exit 1; fi
  grep -F 'failed step: preflight' "$tmp_dir/target-binding-$mode.output" >/dev/null
  ! grep -F "$deployed_fixture" "$tmp_dir/target-binding-$mode.output" >/dev/null
done

# Every initial and final source/deployed status boundary fails closed when
# Git itself cannot establish cleanliness.
assert_git_status_failure() {
  local worktree="$1" call="$2" label="$3"
  rm -f "$git_counter_dir"/*
  if "${target_base[@]}" FAIL_GIT_STATUS_PATH="$worktree" FAIL_GIT_STATUS_CALL="$call" bash "$target_fixture/scripts/nmkr-phase2-test-runner.sh" >"$tmp_dir/git-status-$label.output" 2>&1; then exit 1; fi
  if [[ "$call" == 1 ]]; then
    grep -F 'failed step: preflight' "$tmp_dir/git-status-$label.output" >/dev/null
  else
    grep -F 'failed step: final-integrity' "$tmp_dir/git-status-$label.output" >/dev/null
  fi
}
assert_git_status_failure "$target_fixture" 1 initial-source
assert_git_status_failure "$deployed_fixture" 1 initial-deployed
assert_git_status_failure "$target_fixture" 2 final-source
assert_git_status_failure "$deployed_fixture" 2 final-deployed

# Index hints must not hide changed tracked bytes at either integrity boundary.
assert_hidden_initial_failure() {
  local worktree="$1" flag="$2" label="$3"
  git -C "$worktree" update-index "--$flag" README.md
  printf '\nsynthetic-hidden\n' >>"$worktree/README.md"
  if "${target_base[@]}" bash "$target_fixture/scripts/nmkr-phase2-test-runner.sh" >"$tmp_dir/hidden-initial-$label.output" 2>&1; then exit 1; fi
  grep -F 'failed step: preflight' "$tmp_dir/hidden-initial-$label.output" >/dev/null
  git -C "$worktree" update-index "--no-$flag" README.md
  git -C "$worktree" checkout -q -- README.md
}
for flag in assume-unchanged skip-worktree; do
  assert_hidden_initial_failure "$target_fixture" "$flag" "source-$flag"
  assert_hidden_initial_failure "$deployed_fixture" "$flag" "deployed-$flag"
  for worktree in source deployed; do
    if "${target_base[@]}" TARGET_HIDDEN_AFTER_PLAYWRIGHT="$flag" TARGET_HIDDEN_WORKTREE="$worktree" bash "$target_fixture/scripts/nmkr-phase2-test-runner.sh" >"$tmp_dir/hidden-final-$worktree-$flag.output" 2>&1; then exit 1; fi
    grep -F 'failed step: final-integrity' "$tmp_dir/hidden-final-$worktree-$flag.output" >/dev/null
    hidden_repo="$target_fixture"; [[ "$worktree" != deployed ]] || hidden_repo="$deployed_fixture"
    git -C "$hidden_repo" update-index "--no-$flag" README.md
    git -C "$hidden_repo" checkout -q -- README.md
  done
done

# File-mode differences remain visible even when a worktree disables its
# ordinary file-mode detection, at both initial and final integrity boundaries.
for worktree in source deployed; do
  mode_repo="$target_fixture"; [[ "$worktree" != deployed ]] || mode_repo="$deployed_fixture"
  git -C "$mode_repo" config core.fileMode false
  chmod +x "$mode_repo/README.md"
  if "${target_base[@]}" bash "$target_fixture/scripts/nmkr-phase2-test-runner.sh" >"$tmp_dir/mode-initial-$worktree.output" 2>&1; then exit 1; fi
  grep -F 'failed step: preflight' "$tmp_dir/mode-initial-$worktree.output" >/dev/null
  chmod -x "$mode_repo/README.md"

  if "${target_base[@]}" TARGET_MODE_AFTER_PLAYWRIGHT=true TARGET_MODE_WORKTREE="$worktree" bash "$target_fixture/scripts/nmkr-phase2-test-runner.sh" >"$tmp_dir/mode-final-$worktree.output" 2>&1; then exit 1; fi
  grep -F 'failed step: final-integrity' "$tmp_dir/mode-final-$worktree.output" >/dev/null
  chmod -x "$mode_repo/README.md"
done

general_deploy_marker="$tmp_dir/general-deployed"
if "${target_base[@]/NMKR_PHASE2_PROFILE=targeted-readonly/NMKR_PHASE2_PROFILE=}" NMKR_DEPLOYED_PLUGIN_PATH= NMKR_PHASE2_TARGET_SUITE= NMKR_PHASE2_RUNTIME_INTEGRITY=true NMKR_PHASE2_SKIP_DEPLOY=false NMKR_DEPLOY_COMMAND="touch '$general_deploy_marker'" bash "$target_fixture/scripts/nmkr-phase2-test-runner.sh" >"$tmp_dir/general-runtime.output" 2>&1; then exit 1; fi
test -e "$general_deploy_marker"
grep -F 'failed step: runtime-integrity' "$tmp_dir/general-runtime.output" >/dev/null
grep -F 'runtime-integrity: SKIPPED' "$tmp_dir/general-runtime.output" >/dev/null && exit 1

# A nominally successful general deployment cannot validate a clean stale
# deployed commit, and the runtime-integrity helper must not be invoked.
git -C "$deployed_fixture" config user.email public@example.invalid
git -C "$deployed_fixture" config user.name PublicTest
git -C "$deployed_fixture" commit --allow-empty -q -m 'synthetic stale deployment'
: >"$runtime_integrity_log"
stale_deploy_marker="$tmp_dir/general-stale-deployed"
if "${target_base[@]/NMKR_PHASE2_PROFILE=targeted-readonly/NMKR_PHASE2_PROFILE=}" NMKR_PHASE2_TARGET_SUITE= NMKR_PHASE2_RUNTIME_INTEGRITY=true NMKR_PHASE2_SKIP_DEPLOY=false NMKR_DEPLOY_COMMAND="touch '$stale_deploy_marker'" bash "$target_fixture/scripts/nmkr-phase2-test-runner.sh" >"$tmp_dir/general-stale-runtime.output" 2>&1; then exit 1; fi
test -e "$stale_deploy_marker"
grep -F 'failed step: runtime-integrity' "$tmp_dir/general-stale-runtime.output" >/dev/null
test ! -s "$runtime_integrity_log"
git -C "$deployed_fixture" reset --hard -q "$target_sha"

# General and existing-readonly runtime integrity bind to the WordPress-active
# deployment before invoking the helper, and invoke that helper exactly once.
for profile in general existing-readonly; do
  : >"$runtime_integrity_log"; : >"$target_log"
  profile_value=""; suite_value=""
  [[ "$profile" != existing-readonly ]] || profile_value=existing-readonly
  if "${target_base[@]/NMKR_PHASE2_PROFILE=targeted-readonly/NMKR_PHASE2_PROFILE=$profile_value}" NMKR_PHASE2_TARGET_SUITE="$suite_value" NMKR_PHASE2_RUNTIME_INTEGRITY=true TARGET_ACTIVE_PLUGIN_FILE="$target_fixture/nmkr-connect.php" bash "$target_fixture/scripts/nmkr-phase2-test-runner.sh" >"$tmp_dir/runtime-binding-mismatch-$profile.output" 2>&1; then exit 1; fi
  grep -F 'failed step: runtime-integrity' "$tmp_dir/runtime-binding-mismatch-$profile.output" >/dev/null
  test ! -s "$runtime_integrity_log"

  : >"$runtime_integrity_log"
  "${target_base[@]/NMKR_PHASE2_PROFILE=targeted-readonly/NMKR_PHASE2_PROFILE=$profile_value}" NMKR_PHASE2_TARGET_SUITE="$suite_value" NMKR_PHASE2_RUNTIME_INTEGRITY=true bash "$target_fixture/scripts/nmkr-phase2-test-runner.sh" >"$tmp_dir/runtime-binding-match-$profile.output" 2>&1 || true
  test "$(wc -l <"$runtime_integrity_log")" = 1
  grep -F 'runtime-integrity: PASS' "$tmp_dir/runtime-binding-match-$profile.output" >/dev/null
done

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
