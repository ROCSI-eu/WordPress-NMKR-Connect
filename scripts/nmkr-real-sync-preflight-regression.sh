#!/usr/bin/env bash
set -Eeuo pipefail

ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

pass() { printf 'PASS: %s\n' "$1"; }
fail() { printf 'FAIL: %s\n' "$1" >&2; exit 1; }
run_expect_fail() {
  local label="$1"; shift
  if "$@" >/dev/null 2>&1; then fail "$label unexpectedly passed"; fi
  pass "$label"
}
make_repo() {
  local dir="$1"
  mkdir -p "$dir/scripts"
  cp "$ROOT/scripts/nmkr-real-sync-preflight.sh" "$dir/scripts/"
  cat > "$dir/scripts/nmkr-wpcli-db-state.sh" <<'EOF'
#!/usr/bin/env bash
exit 0
EOF
  chmod +x "$dir/scripts/"*.sh
  git -C "$dir" init -q
  git -C "$dir" config user.email public@example.invalid
  git -C "$dir" config user.name PublicTest
  cat > "$dir/.gitignore" <<'EOF'
/vendor/
/.env
/node_modules/
*.zip
ignored-root.php
EOF
  git -C "$dir" add .gitignore scripts
  git -C "$dir" commit -q -m init
}
base_env() {
  env -u CI \
  -u GITHUB_ACTIONS \
  -u GITLAB_CI \
  -u CIRCLECI \
  -u BUILDKITE \
  -u TF_BUILD \
  -u NMKR_PHASE2_ENV_FILE \
  RUN_REAL_SYNC=true \
  NMKR_REAL_SYNC_CONFIRM=I_UNDERSTAND_THIS_MUTATES_DEV \
  PW_SAVE_ARTIFACTS=false \
  NMKR_REAL_SYNC_BACKUP_CONFIRM=I_CONFIRMED_A_RECENT_DEV_BACKUP \
  WP_CLI_BIN=true \
  WP_BASE_URL=https://example.invalid \
  NMKR_REAL_SYNC_ALLOWED_ORIGIN=https://example.invalid \
  WP_ADMIN_USER=admin \
  NMKR_REAL_SYNC_BACKUP_CONFIRMED_AT=2099-01-01T00:00:00Z \
  "$@"
}

wp_with_plugin_link() {
  local target="$1"
  local name="$2"
  local wp_dir="$TMP/wp-${name}"
  mkdir -p "$wp_dir/wp-content/plugins"
  ln -s "$target" "$wp_dir/wp-content/plugins/nmkr-connect"
  printf '%s' "$wp_dir"
}

# Private-state path tests: invalid locations fail before creating runs or chmodding.
SRC1="$TMP/src1"; WP1="$TMP/wp1"; mkdir -p "$WP1"; make_repo "$SRC1"

REPO_LOCAL_SENTINEL="$TMP/repo-local-env-sentinel"
cat > "$SRC1/.env.tests" <<EOF
touch "$REPO_LOCAL_SENTINEL"
RUN_REAL_SYNC=true
EOF
REPO_LOCAL_OUT="$TMP/repo-local-env.out"
if env -u CI -u GITHUB_ACTIONS -u GITLAB_CI -u CIRCLECI -u BUILDKITE -u TF_BUILD bash "$SRC1/scripts/nmkr-real-sync-preflight.sh" >"$REPO_LOCAL_OUT" 2>&1; then
  fail "repo-local .env.tests unexpectedly passed"
fi
[[ ! -e "$REPO_LOCAL_SENTINEL" ]] || fail "repo-local .env.tests was sourced implicitly"
[[ ! -e "$SRC1/.phase2-private" ]] || fail "repo-local .env.tests created private state"
! grep -Fq "$SRC1/.env.tests" "$REPO_LOCAL_OUT" || fail "repo-local env path leaked"
rm -f "$SRC1/.env.tests"
pass "repo-local .env.tests is not sourced implicitly"

EXPLICIT_REPO_SENTINEL="$TMP/explicit-repo-env-sentinel"
cat > "$SRC1/.env.tests" <<EOF
touch "$EXPLICIT_REPO_SENTINEL"
RUN_REAL_SYNC=true
EOF
EXPLICIT_REPO_OUT="$TMP/explicit-repo-env.out"
if env -u CI -u GITHUB_ACTIONS -u GITLAB_CI -u CIRCLECI -u BUILDKITE -u TF_BUILD NMKR_PHASE2_ENV_FILE="$SRC1/.env.tests" NMKR_PHASE2_LOG_DIR="$TMP/explicit-repo-state" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh" >"$EXPLICIT_REPO_OUT" 2>&1; then
  fail "explicit repo-local env file unexpectedly passed"
fi
grep -q 'failed gate: env-file' "$EXPLICIT_REPO_OUT" || fail "explicit repo-local env file did not fail env-file gate"
[[ ! -e "$EXPLICIT_REPO_SENTINEL" ]] || fail "explicit repo-local env file was sourced"
[[ ! -e "$TMP/explicit-repo-state" ]] || fail "explicit repo-local env file created private state"
! grep -Fq "$SRC1/.env.tests" "$EXPLICIT_REPO_OUT" || fail "explicit repo-local env path leaked"
rm -f "$SRC1/.env.tests"
pass "explicit repo-local env file rejected before sourcing"

REL_ENV_SENTINEL="$TMP/relative-env-sentinel"
cat > "$TMP/relative.env" <<EOF
touch "$REL_ENV_SENTINEL"
RUN_REAL_SYNC=true
EOF
REL_ENV_OUT="$TMP/relative-env.out"
if ( cd "$TMP" && env -u CI -u GITHUB_ACTIONS -u GITLAB_CI -u CIRCLECI -u BUILDKITE -u TF_BUILD NMKR_PHASE2_ENV_FILE="relative.env" NMKR_PHASE2_LOG_DIR="$TMP/relative-state" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh" >"$REL_ENV_OUT" 2>&1 ); then
  fail "relative env file unexpectedly passed"
fi
grep -q 'failed gate: env-file' "$REL_ENV_OUT" || fail "relative env file did not fail env-file gate"
[[ ! -e "$REL_ENV_SENTINEL" ]] || fail "relative env file was sourced"
[[ ! -e "$TMP/relative-state" ]] || fail "relative env file created private state"
pass "relative env file path rejected"

ENV_SUPPLIES_WP_SENTINEL="$TMP/env-supplies-wp-sentinel"
ENV_SUPPLIES_WP_FILE="$TMP/env-supplies-wp.env"
cat > "$ENV_SUPPLIES_WP_FILE" <<EOF
touch "$ENV_SUPPLIES_WP_SENTINEL"
WP_PATH="$WP1"
RUN_REAL_SYNC=true
EOF
ENV_SUPPLIES_WP_OUT="$TMP/env-supplies-wp.out"
if env -u CI -u GITHUB_ACTIONS -u GITLAB_CI -u CIRCLECI -u BUILDKITE -u TF_BUILD -u WP_PATH NMKR_PHASE2_ENV_FILE="$ENV_SUPPLIES_WP_FILE" NMKR_PHASE2_LOG_DIR="$TMP/env-supplies-wp-state" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh" >"$ENV_SUPPLIES_WP_OUT" 2>&1; then
  fail "env file supplying WP_PATH unexpectedly passed"
fi
grep -q 'failed gate: env-file' "$ENV_SUPPLIES_WP_OUT" || fail "env file supplying WP_PATH did not fail env-file gate"
[[ ! -e "$ENV_SUPPLIES_WP_SENTINEL" ]] || fail "env file supplying WP_PATH was sourced"
[[ ! -e "$TMP/env-supplies-wp-state" ]] || fail "env file supplying WP_PATH created private state"
! grep -Fq "$ENV_SUPPLIES_WP_FILE" "$ENV_SUPPLIES_WP_OUT" || fail "env file supplying WP_PATH path leaked"
pass "env file cannot supply WP_PATH"

ALT_WP="$TMP/alternate-wp"; mkdir -p "$ALT_WP"
ALT_WPCLI_SENTINEL="$ALT_WP/wpcli-invoked"
ALT_WPCLI_MOCK="$TMP/alternate-wpcli-mock"
cat > "$ALT_WPCLI_MOCK" <<EOF
#!/usr/bin/env bash
touch "$ALT_WPCLI_SENTINEL"
exit 0
EOF
chmod +x "$ALT_WPCLI_MOCK"
ENV_CHANGES_WP_FILE="$TMP/env-changes-wp.env"
cat > "$ENV_CHANGES_WP_FILE" <<EOF
WP_PATH="$ALT_WP"
WP_CLI_BIN="$ALT_WPCLI_MOCK"
RUN_REAL_SYNC=true
NMKR_REAL_SYNC_CONFIRM=I_UNDERSTAND_THIS_MUTATES_DEV
PW_SAVE_ARTIFACTS=false
NMKR_REAL_SYNC_BACKUP_CONFIRM=I_CONFIRMED_A_RECENT_DEV_BACKUP
NMKR_PHASE2_LOG_DIR=$TMP/env-changes-wp-state
EOF
ENV_CHANGES_WP_OUT="$TMP/env-changes-wp.out"
if env -u CI -u GITHUB_ACTIONS -u GITLAB_CI -u CIRCLECI -u BUILDKITE -u TF_BUILD WP_PATH="$WP1" NMKR_PHASE2_ENV_FILE="$ENV_CHANGES_WP_FILE" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh" >"$ENV_CHANGES_WP_OUT" 2>&1; then
  fail "env file that changes WP_PATH unexpectedly passed"
fi
grep -q 'failed gate: env-file' "$ENV_CHANGES_WP_OUT" || fail "env file that changes WP_PATH did not fail env-file gate"
[[ ! -e "$TMP/env-changes-wp-state" ]] || fail "env file that changes WP_PATH created private state"
[[ ! -e "$ALT_WPCLI_SENTINEL" ]] || fail "env file that changes WP_PATH invoked WP-CLI"
! grep -Fq "$WP1" "$ENV_CHANGES_WP_OUT" || fail "original WP_PATH leaked for changed WP_PATH failure"
! grep -Fq "$ALT_WP" "$ENV_CHANGES_WP_OUT" || fail "alternate WP_PATH leaked for changed WP_PATH failure"
pass "env file cannot redirect WP_PATH after sourcing"

AUTHORITY_ALT_WP="$TMP/authority-alternate-wp"; mkdir -p "$AUTHORITY_ALT_WP"
AUTHORITY_WPCLI_SENTINEL="$AUTHORITY_ALT_WP/wpcli-invoked"
AUTHORITY_TARGET_SENTINEL="$AUTHORITY_ALT_WP/target-touched"
AUTHORITY_WPCLI_MOCK="$TMP/authority-wpcli-mock"
cat > "$AUTHORITY_WPCLI_MOCK" <<EOF
#!/usr/bin/env bash
touch "$AUTHORITY_WPCLI_SENTINEL"
exit 0
EOF
chmod +x "$AUTHORITY_WPCLI_MOCK"
ENV_OVERWRITES_AUTHORITY_FILE="$TMP/env-overwrites-authority.env"
cat > "$ENV_OVERWRITES_AUTHORITY_FILE" <<EOF
WP_PATH="$AUTHORITY_ALT_WP"
WP_PATH_REAL_PRE="$AUTHORITY_ALT_WP"
WP_CLI_BIN="$AUTHORITY_WPCLI_MOCK"
RUN_REAL_SYNC=true
NMKR_REAL_SYNC_CONFIRM=I_UNDERSTAND_THIS_MUTATES_DEV
PW_SAVE_ARTIFACTS=false
NMKR_REAL_SYNC_BACKUP_CONFIRM=I_CONFIRMED_A_RECENT_DEV_BACKUP
NMKR_PHASE2_LOG_DIR=$TMP/env-overwrites-authority-state
EOF
ENV_OVERWRITES_AUTHORITY_OUT="$TMP/env-overwrites-authority.out"
if env -u CI -u GITHUB_ACTIONS -u GITLAB_CI -u CIRCLECI -u BUILDKITE -u TF_BUILD WP_PATH="$WP1" NMKR_PHASE2_ENV_FILE="$ENV_OVERWRITES_AUTHORITY_FILE" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh" >"$ENV_OVERWRITES_AUTHORITY_OUT" 2>&1; then
  fail "env file that overwrites authority variable unexpectedly passed"
fi
grep -q 'failed gate: env-file' "$ENV_OVERWRITES_AUTHORITY_OUT" || fail "env file that overwrites authority variable did not fail env-file gate"
[[ ! -e "$TMP/env-overwrites-authority-state" ]] || fail "env file that overwrites authority variable created private state"
[[ ! -e "$AUTHORITY_WPCLI_SENTINEL" ]] || fail "env file that overwrites authority variable invoked WP-CLI"
[[ ! -e "$AUTHORITY_TARGET_SENTINEL" ]] || fail "env file that overwrites authority variable touched alternate target"
! grep -Fq "$WP1" "$ENV_OVERWRITES_AUTHORITY_OUT" || fail "original WP_PATH leaked for authority overwrite failure"
! grep -Fq "$AUTHORITY_ALT_WP" "$ENV_OVERWRITES_AUTHORITY_OUT" || fail "alternate WP_PATH leaked for authority overwrite failure"
! grep -Fq "$ENV_OVERWRITES_AUTHORITY_FILE" "$ENV_OVERWRITES_AUTHORITY_OUT" || fail "env path leaked for authority overwrite failure"
pass "env file cannot overwrite protected WP_PATH authority"

for wp_mutation_case in empty unset; do
  WP_MUTATION_FILE="$TMP/env-wp-$wp_mutation_case.env"
  if [[ "$wp_mutation_case" == "empty" ]]; then
    printf 'WP_PATH=\nRUN_REAL_SYNC=true\nNMKR_PHASE2_LOG_DIR=%s\n' "$TMP/env-wp-empty-state" > "$WP_MUTATION_FILE"
    WP_MUTATION_STATE="$TMP/env-wp-empty-state"
  else
    printf 'unset WP_PATH\nRUN_REAL_SYNC=true\nNMKR_PHASE2_LOG_DIR=%s\n' "$TMP/env-wp-unset-state" > "$WP_MUTATION_FILE"
    WP_MUTATION_STATE="$TMP/env-wp-unset-state"
  fi
  WP_MUTATION_OUT="$TMP/env-wp-$wp_mutation_case.out"
  if env -u CI -u GITHUB_ACTIONS -u GITLAB_CI -u CIRCLECI -u BUILDKITE -u TF_BUILD WP_PATH="$WP1" NMKR_PHASE2_ENV_FILE="$WP_MUTATION_FILE" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh" >"$WP_MUTATION_OUT" 2>&1; then
    fail "env file with WP_PATH $wp_mutation_case unexpectedly passed"
  fi
  grep -q 'failed gate: env-file' "$WP_MUTATION_OUT" || fail "env file with WP_PATH $wp_mutation_case did not fail env-file gate"
  [[ ! -e "$WP_MUTATION_STATE" ]] || fail "env file with WP_PATH $wp_mutation_case created private state"
done
pass "env file cannot empty or unset WP_PATH"

OUTSIDE_ENV_SENTINEL="$TMP/outside-env-sentinel"
OUTSIDE_ENV_FILE="$TMP/outside-private.env"
cat > "$OUTSIDE_ENV_FILE" <<EOF
touch "$OUTSIDE_ENV_SENTINEL"
RUN_REAL_SYNC=true
NMKR_REAL_SYNC_CONFIRM=I_UNDERSTAND_THIS_MUTATES_DEV
PW_SAVE_ARTIFACTS=false
NMKR_REAL_SYNC_BACKUP_CONFIRM=I_CONFIRMED_A_RECENT_DEV_BACKUP
WP_CLI_BIN=true
WP_BASE_URL=https://example.invalid
NMKR_REAL_SYNC_ALLOWED_ORIGIN=https://example.invalid
WP_ADMIN_USER=admin
NMKR_REAL_SYNC_BACKUP_CONFIRMED_AT=2099-01-01T00:00:00Z
NMKR_PHASE2_LOG_DIR=$TMP/outside-env-state
EOF
OUTSIDE_ENV_OUT="$TMP/outside-env.out"
if env -u CI -u GITHUB_ACTIONS -u GITLAB_CI -u CIRCLECI -u BUILDKITE -u TF_BUILD WP_PATH="$WP1" NMKR_PHASE2_ENV_FILE="$OUTSIDE_ENV_FILE" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh" >"$OUTSIDE_ENV_OUT" 2>&1; then
  fail "outside env file unexpectedly passed full preflight"
fi
[[ -e "$OUTSIDE_ENV_SENTINEL" ]] || fail "outside env file was not sourced"
grep -q 'failed gate: deployment-integrity' "$OUTSIDE_ENV_OUT" || fail "outside env file did not reach later deployment gate"
! grep -Fq "$OUTSIDE_ENV_FILE" "$OUTSIDE_ENV_OUT" || fail "outside env path leaked"
pass "absolute outside env file with exported WP_PATH reaches later gate"

WP_ENV_DIR="$WP1/private-env"; mkdir -p "$WP_ENV_DIR"
WP_ENV_SENTINEL="$TMP/wp-env-sentinel"; WP_ENV_FILE="$WP_ENV_DIR/phase15.env"
cat > "$WP_ENV_FILE" <<EOF
touch "$WP_ENV_SENTINEL"
RUN_REAL_SYNC=true
EOF
WP_ENV_OUT="$TMP/wp-env.out"
if env -u CI -u GITHUB_ACTIONS -u GITLAB_CI -u CIRCLECI -u BUILDKITE -u TF_BUILD WP_PATH="$WP1" NMKR_PHASE2_ENV_FILE="$WP_ENV_FILE" NMKR_PHASE2_LOG_DIR="$TMP/wp-env-state" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh" >"$WP_ENV_OUT" 2>&1; then
  fail "WP-local env file unexpectedly passed"
fi
grep -q 'failed gate: env-file' "$WP_ENV_OUT" || fail "WP-local env file did not fail env-file gate"
[[ ! -e "$WP_ENV_SENTINEL" ]] || fail "WP-local env file was sourced"
[[ ! -e "$TMP/wp-env-state" ]] || fail "WP-local env file created private state"
! grep -Fq "$WP_ENV_FILE" "$WP_ENV_OUT" || fail "WP-local env path leaked"
pass "WP-local env file rejected before sourcing when WP_PATH is known"

INHERITED_ENV_SENTINEL="$TMP/inherited-env-sentinel"; INHERITED_ENV_FILE="$TMP/inherited-private.env"
cat > "$INHERITED_ENV_FILE" <<EOF
touch "$INHERITED_ENV_SENTINEL"
RUN_REAL_SYNC=false
EOF
INHERITED_ENV_OUT="$TMP/inherited-env.out"
if ( export NMKR_PHASE2_ENV_FILE="$INHERITED_ENV_FILE"; base_env WP_PATH="$WP1" NMKR_PHASE2_LOG_DIR="$TMP/inherited-state" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh" >"$INHERITED_ENV_OUT" 2>&1 ); then
  fail "base_env inherited env-file fixture unexpectedly passed"
fi
[[ ! -e "$INHERITED_ENV_SENTINEL" ]] || fail "base_env did not scrub inherited env file"
! grep -q 'failed gate: confirmations' "$INHERITED_ENV_OUT" || fail "base_env fixture was controlled by inherited env file"
pass "base_env scrubs inherited env-file configuration"

CI_PARENT="$TMP/ci-parent"; mkdir -p "$CI_PARENT"; ci_parent_perm_before="$(stat -c %a "$CI_PARENT")"; CI_OUT="$TMP/ci-refusal.out"
if env CI=true RUN_REAL_SYNC=true NMKR_REAL_SYNC_CONFIRM=I_UNDERSTAND_THIS_MUTATES_DEV PW_SAVE_ARTIFACTS=false NMKR_REAL_SYNC_BACKUP_CONFIRM=I_CONFIRMED_A_RECENT_DEV_BACKUP WP_PATH="$WP1" NMKR_PHASE2_LOG_DIR="$CI_PARENT/ci-state" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh" >"$CI_OUT" 2>&1; then
  fail "CI refusal unexpectedly passed"
fi
grep -q 'failed gate: ci-refusal' "$CI_OUT" || fail "CI refusal did not fail at ci-refusal"
[[ ! -e "$CI_PARENT/ci-state" && ! -e "$CI_PARENT/ci-state/runs" ]] || fail "CI refusal created private state"
[[ "$(stat -c %a "$CI_PARENT")" == "$ci_parent_perm_before" ]] || fail "CI refusal changed parent permissions"
pass "CI refusal before private-state mutation"

PRE_ENV_PARENT="$TMP/pre-env-parent"; mkdir -p "$PRE_ENV_PARENT"; pre_env_parent_perm_before="$(stat -c %a "$PRE_ENV_PARENT")"
PRE_SENTINEL="$TMP/pre-env-sentinel"; PRE_ENV_FILE="$TMP/pre-source.env"; PRE_OUT="$TMP/pre-source-ci.out"
cat > "$PRE_ENV_FILE" <<EOF
CI=false
GITHUB_ACTIONS=false
touch "$PRE_SENTINEL"
EOF
if env CI=true NMKR_PHASE2_ENV_FILE="$PRE_ENV_FILE" NMKR_PHASE2_LOG_DIR="$PRE_ENV_PARENT/state" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh" >"$PRE_OUT" 2>&1; then
  fail "pre-source CI refusal unexpectedly passed"
fi
grep -q 'failed gate: ci-refusal' "$PRE_OUT" || fail "pre-source CI refusal did not fail at ci-refusal"
[[ ! -e "$PRE_SENTINEL" ]] || fail "pre-source CI refusal sourced env file"
[[ ! -e "$PRE_ENV_PARENT/state" && ! -e "$PRE_ENV_PARENT/state/runs" ]] || fail "pre-source CI refusal created private state"
[[ "$(stat -c %a "$PRE_ENV_PARENT")" == "$pre_env_parent_perm_before" ]] || fail "pre-source CI refusal changed parent permissions"
pass "pre-source CI refusal does not source env file"

POST_ENV_PARENT="$TMP/post-env-parent"; mkdir -p "$POST_ENV_PARENT"; post_env_parent_perm_before="$(stat -c %a "$POST_ENV_PARENT")"
POST_SENTINEL="$TMP/post-env-sentinel"; POST_ENV_FILE="$TMP/post-source.env"; POST_OUT="$TMP/post-source-ci.out"
cat > "$POST_ENV_FILE" <<EOF
CI=true
touch "$POST_SENTINEL"
EOF
if env -u CI -u GITHUB_ACTIONS -u GITLAB_CI -u CIRCLECI -u BUILDKITE -u TF_BUILD WP_PATH="$WP1" NMKR_PHASE2_ENV_FILE="$POST_ENV_FILE" NMKR_PHASE2_LOG_DIR="$POST_ENV_PARENT/state" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh" >"$POST_OUT" 2>&1; then
  fail "post-source CI refusal unexpectedly passed"
fi
grep -q 'failed gate: ci-refusal' "$POST_OUT" || fail "post-source CI refusal did not fail at ci-refusal"
[[ -e "$POST_SENTINEL" ]] || fail "post-source CI refusal did not source env file"
[[ ! -e "$POST_ENV_PARENT/state" && ! -e "$POST_ENV_PARENT/state/runs" ]] || fail "post-source CI refusal created private state"
[[ "$(stat -c %a "$POST_ENV_PARENT")" == "$post_env_parent_perm_before" ]] || fail "post-source CI refusal changed parent permissions"
pass "post-source CI refusal rejects env-introduced marker"

NESTED_PARENT="$TMP/nested-parent"; mkdir -m 700 "$NESTED_PARENT"; nested_parent_perm_before="$(stat -c %a "$NESTED_PARENT")"
NESTED_STATE="$NESTED_PARENT/one/two/three"; NESTED_OUT="$TMP/nested-valid.out"
if base_env WP_PATH="$WP1" NMKR_PHASE2_LOG_DIR="$NESTED_STATE" NMKR_DEPLOYED_PLUGIN_PATH="$SRC1" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh" >"$NESTED_OUT" 2>&1; then
  fail "nested first-time private state unexpectedly passed full preflight"
fi
grep -q 'failed gate: deployment-integrity' "$NESTED_OUT" || { cat "$NESTED_OUT"; fail "nested first-time private state did not reach later deployment guard"; }
for component in "$NESTED_PARENT/one" "$NESTED_PARENT/one/two" "$NESTED_PARENT/one/two/three" "$NESTED_PARENT/one/two/three/runs"; do
  [[ -d "$component" && ! -L "$component" ]] || fail "nested component missing or symlinked"
  [[ "$(stat -c %a "$component")" == "700" ]] || fail "nested component mode was not 0700"
done
[[ "$(stat -c %a "$NESTED_PARENT")" == "$nested_parent_perm_before" ]] || fail "nested parent permissions changed"
pass "nested first-time private state reaches later guard"

NESTED_REPO_STATE="$SRC1/nested/one/two"
run_expect_fail "nested repository private state" base_env WP_PATH="$WP1" NMKR_PHASE2_LOG_DIR="$NESTED_REPO_STATE" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh"
[[ ! -e "$SRC1/nested" ]] || fail "nested repository private state created components"

DOTDOT_PARENT="$TMP/dotdot-parent"; mkdir -m 700 "$DOTDOT_PARENT"
DOTDOT_STATE="$DOTDOT_PARENT/missing/../../src1/state"
run_expect_fail "dotdot private state into repository" base_env WP_PATH="$WP1" NMKR_PHASE2_LOG_DIR="$DOTDOT_STATE" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh"
[[ ! -e "$DOTDOT_PARENT/missing" ]] || fail "dotdot private state created missing component"
[[ ! -e "$SRC1/state" ]] || fail "dotdot private state created repository component"

NESTED_WP_STATE="$WP1/nested/one/two"
run_expect_fail "nested WordPress private state" base_env WP_PATH="$WP1" NMKR_PHASE2_LOG_DIR="$NESTED_WP_STATE" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh"
[[ ! -e "$WP1/nested" ]] || fail "nested WordPress private state created components"

LINK_PARENT="$TMP/link-parent"; LINK_TARGET="$TMP/link-target"; mkdir -m 700 "$LINK_PARENT" "$LINK_TARGET"; ln -s "$LINK_TARGET" "$LINK_PARENT/link"; link_target_perm_before="$(stat -c %a "$LINK_TARGET")"
run_expect_fail "symlinked intermediate private state" base_env WP_PATH="$WP1" NMKR_PHASE2_LOG_DIR="$LINK_PARENT/link/one/two" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh"
[[ ! -e "$LINK_TARGET/one" ]] || fail "symlinked intermediate target modified"
[[ "$(stat -c %a "$LINK_TARGET")" == "$link_target_perm_before" ]] || fail "symlinked intermediate target permissions changed"

UNSAFE_PARENT="$TMP/unsafe-parent"; mkdir -m 700 "$UNSAFE_PARENT"; mkdir -m 755 "$UNSAFE_PARENT/unsafe"; unsafe_perm_before="$(stat -c %a "$UNSAFE_PARENT/unsafe")"
run_expect_fail "unsafe intermediate private state" base_env WP_PATH="$WP1" NMKR_PHASE2_LOG_DIR="$UNSAFE_PARENT/unsafe/one/two" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh"
[[ ! -e "$UNSAFE_PARENT/unsafe/one" ]] || fail "unsafe intermediate private state created components"
[[ "$(stat -c %a "$UNSAFE_PARENT/unsafe")" == "$unsafe_perm_before" ]] || fail "unsafe intermediate permissions changed"

perm_before="$(stat -c %a "$SRC1")"
run_expect_fail "repository root as private state" base_env WP_PATH="$WP1" NMKR_PHASE2_LOG_DIR="$SRC1" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh"
[[ ! -e "$SRC1/runs" ]] || fail "invalid repository-root state created runs"
[[ "$(stat -c %a "$SRC1")" == "$perm_before" ]] || fail "invalid repository-root state permissions changed"

run_expect_fail "repository subdirectory as private state" base_env WP_PATH="$WP1" NMKR_PHASE2_LOG_DIR="$SRC1/scripts" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh"
[[ ! -e "$SRC1/scripts/runs" ]] || fail "invalid repository-subdirectory state created runs"

run_expect_fail "WP_PATH as private state" base_env WP_PATH="$WP1" NMKR_PHASE2_LOG_DIR="$WP1" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh"
[[ ! -e "$WP1/runs" ]] || fail "invalid WP_PATH state created runs"
mkdir -p "$WP1/state"
run_expect_fail "WordPress subdirectory as private state" base_env WP_PATH="$WP1" NMKR_PHASE2_LOG_DIR="$WP1/state" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh"
[[ ! -e "$WP1/state/runs" ]] || fail "invalid WP subdirectory state created runs"

REAL_STATE="$TMP/real-state"; mkdir -m 700 "$REAL_STATE"; ln -s "$REAL_STATE" "$TMP/state-link"
run_expect_fail "symlinked state directory" base_env WP_PATH="$WP1" NMKR_PHASE2_LOG_DIR="$TMP/state-link" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh"
[[ ! -e "$REAL_STATE/runs" ]] || fail "symlinked state target was modified"
RUNS_TARGET="$TMP/runs-target"; STATE2="$TMP/state2"; mkdir -m 700 "$RUNS_TARGET" "$STATE2"; ln -s "$RUNS_TARGET" "$STATE2/runs"; perm_runs_before="$(stat -c %a "$RUNS_TARGET")"
run_expect_fail "symlinked runs path" base_env WP_PATH="$WP1" NMKR_PHASE2_LOG_DIR="$STATE2" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh"
[[ "$(stat -c %a "$RUNS_TARGET")" == "$perm_runs_before" ]] || fail "symlinked runs target permissions changed"

# Deployment path tests.
VALID_STATE="$TMP/valid-state"; DEPLOY_SRC_SUB="$SRC1/scripts"
run_expect_fail "source subdirectory deployed path" base_env WP_PATH="$WP1" NMKR_PHASE2_LOG_DIR="$VALID_STATE" NMKR_DEPLOYED_PLUGIN_PATH="$DEPLOY_SRC_SUB" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh"
run_expect_fail "source root deployed path" base_env WP_PATH="$WP1" NMKR_PHASE2_LOG_DIR="$TMP/valid-state2" NMKR_DEPLOYED_PLUGIN_PATH="$SRC1" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh"
PARENT_DEPLOY="$TMP/parent-deploy"; mkdir -p "$PARENT_DEPLOY/plugin"; git -C "$PARENT_DEPLOY" init -q; git -C "$PARENT_DEPLOY" config user.email public@example.invalid; git -C "$PARENT_DEPLOY" config user.name PublicTest; touch "$PARENT_DEPLOY/file"; git -C "$PARENT_DEPLOY" add file; git -C "$PARENT_DEPLOY" commit -q -m init
run_expect_fail "deployed Git top-level parent" base_env WP_PATH="$WP1" NMKR_PHASE2_LOG_DIR="$TMP/valid-state3" NMKR_DEPLOYED_PLUGIN_PATH="$PARENT_DEPLOY/plugin" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh"

[[ -z "$(git -C "$SRC1" -c core.fileMode=true status --porcelain=v1 --untracked-files=all)" ]] || fail "source fixture dirty before structural checkout test"
STRUCT_CLONE="$TMP/deployed-structural"; git clone -q "$SRC1" "$STRUCT_CLONE"
STRUCT_WP="$(wp_with_plugin_link "$STRUCT_CLONE" structural)"
STRUCT_OUT="$TMP/structural.out"
if base_env WP_PATH="$STRUCT_WP" NMKR_PHASE2_LOG_DIR="$TMP/valid-state-structural" NMKR_DEPLOYED_PLUGIN_PATH="$STRUCT_CLONE" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh" >"$STRUCT_OUT" 2>&1; then fail "structural clean checkout unexpectedly passed full preflight"; fi
grep -q 'failed gate: origin-guard' "$STRUCT_OUT" || { cat "$STRUCT_OUT"; fail "valid separate deployed checkout did not pass structural checks before later guard"; }
pass "valid separate deployed Git checkout passed structural checks"

OVERRIDE_OTHER="$TMP/deployed-override-other"; git clone -q "$SRC1" "$OVERRIDE_OTHER"
run_expect_fail "override not matching active plugin path" base_env WP_PATH="$STRUCT_WP" NMKR_PHASE2_LOG_DIR="$TMP/override-mismatch-state" NMKR_DEPLOYED_PLUGIN_PATH="$OVERRIDE_OTHER" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh"

VENDOR_ALLOWED_WP="$TMP/wp-vendor-allowed"; mkdir -p "$VENDOR_ALLOWED_WP/wp-content/plugins"; git clone -q "$SRC1" "$VENDOR_ALLOWED_WP/wp-content/plugins/nmkr-connect"
mkdir -p "$VENDOR_ALLOWED_WP/wp-content/plugins/nmkr-connect/vendor/freemius/wordpress-sdk/includes" "$VENDOR_ALLOWED_WP/wp-content/plugins/nmkr-connect/vendor/composer"
touch "$VENDOR_ALLOWED_WP/wp-content/plugins/nmkr-connect/vendor/freemius/wordpress-sdk/start.php"
touch "$VENDOR_ALLOWED_WP/wp-content/plugins/nmkr-connect/vendor/freemius/wordpress-sdk/includes/class-freemius.php"
touch "$VENDOR_ALLOWED_WP/wp-content/plugins/nmkr-connect/vendor/autoload.php" "$VENDOR_ALLOWED_WP/wp-content/plugins/nmkr-connect/vendor/composer/installed.php"
VENDOR_ALLOWED_OUT="$TMP/vendor-allowed.out"
if base_env WP_PATH="$VENDOR_ALLOWED_WP" NMKR_PHASE2_LOG_DIR="$TMP/allowed-vendor-state" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh" >"$VENDOR_ALLOWED_OUT" 2>&1; then
  fail "allowed ignored vendor dependencies unexpectedly passed full preflight"
fi
grep -q 'failed gate: origin-guard' "$VENDOR_ALLOWED_OUT" || { cat "$VENDOR_ALLOWED_OUT"; fail "allowed ignored vendor dependencies did not reach later guard"; }
pass "required ignored vendor dependencies allowed"

VENDOR_WP="$TMP/wp-vendor"; mkdir -p "$VENDOR_WP/wp-content/plugins"; git clone -q "$SRC1" "$VENDOR_WP/wp-content/plugins/nmkr-connect"
mkdir -p "$VENDOR_WP/wp-content/plugins/nmkr-connect/vendor/freemius/wordpress-sdk/includes"
touch "$VENDOR_WP/wp-content/plugins/nmkr-connect/vendor/freemius/wordpress-sdk/start.php"
touch "$VENDOR_WP/wp-content/plugins/nmkr-connect/vendor/freemius/wordpress-sdk/includes/class-freemius.php"
touch "$VENDOR_WP/wp-content/plugins/nmkr-connect/vendor/unexpected-runtime.php"
VENDOR_UNEXPECTED_OUT="$TMP/vendor-unexpected.out"
if base_env WP_PATH="$VENDOR_WP" NMKR_PHASE2_LOG_DIR="$TMP/ignored-runtime-state" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh" >"$VENDOR_UNEXPECTED_OUT" 2>&1; then
  fail "unexpected ignored vendor files unexpectedly passed"
fi
grep -q 'failed gate: deployment-integrity' "$VENDOR_UNEXPECTED_OUT" || { cat "$VENDOR_UNEXPECTED_OUT"; fail "unexpected ignored vendor files did not fail deployment-integrity"; }
pass "unexpected ignored vendor files fail closed"

for ignored_case in env:.env node:node_modules/ignored.js zip:archive.zip php:ignored-root.php; do
  case_label="${ignored_case%%:*}"
  case_path="${ignored_case#*:}"
  CASE_WP="$TMP/wp-ignored-$case_label"; mkdir -p "$CASE_WP/wp-content/plugins"; git clone -q "$SRC1" "$CASE_WP/wp-content/plugins/nmkr-connect"
  mkdir -p "$CASE_WP/wp-content/plugins/nmkr-connect/vendor/freemius/wordpress-sdk/includes" "$CASE_WP/wp-content/plugins/nmkr-connect/$(dirname "$case_path")"
  touch "$CASE_WP/wp-content/plugins/nmkr-connect/vendor/freemius/wordpress-sdk/start.php"
  touch "$CASE_WP/wp-content/plugins/nmkr-connect/vendor/freemius/wordpress-sdk/includes/class-freemius.php"
  touch "$CASE_WP/wp-content/plugins/nmkr-connect/$case_path"
  CASE_OUT="$TMP/ignored-$case_label.out"
  if base_env WP_PATH="$CASE_WP" NMKR_PHASE2_LOG_DIR="$TMP/ignored-$case_label-state" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh" >"$CASE_OUT" 2>&1; then
    fail "ignored $case_label file unexpectedly passed"
  fi
  grep -q 'failed gate: deployment-integrity' "$CASE_OUT" || { cat "$CASE_OUT"; fail "ignored $case_label file did not fail deployment-integrity"; }
  ! grep -Fq "$case_path" "$CASE_OUT" || fail "ignored $case_label filename leaked to public output"
done
pass "non-allowlisted ignored files fail closed without filename leaks"

REAL_GIT_BIN="$(command -v git)"
GIT_WRAPPER_DIR="$TMP/git-wrapper"; mkdir -p "$GIT_WRAPPER_DIR"
cat > "$GIT_WRAPPER_DIR/git" <<'EOF'
#!/usr/bin/env bash
repo=""
args=("$@")
idx=0
while (( idx < ${#args[@]} )); do
  case "${args[$idx]}" in
    -C) repo="${args[$((idx+1))]}"; idx=$((idx+2));;
    -c) idx=$((idx+2));;
    --*) idx=$((idx+1));;
    *) break;;
  esac
done
cmd="${args[$idx]:-}"
if [[ "$cmd" == "status" && -n "${FAIL_GIT_STATUS_REPO:-}" ]]; then
  repo_real="$(python3 -c 'import os,sys; print(os.path.realpath(sys.argv[1]))' "${repo:-.}")"
  fail_real="$(python3 -c 'import os,sys; print(os.path.realpath(sys.argv[1]))' "$FAIL_GIT_STATUS_REPO")"
  if [[ "$repo_real" == "$fail_real" ]]; then
    printf 'fatal: mocked status failure\n' >&2
    exit 128
  fi
fi
if [[ "$cmd" == "ls-files" && -n "${FAIL_GIT_LSFILES_REPO:-}" ]]; then
  repo_real="$(python3 -c 'import os,sys; print(os.path.realpath(sys.argv[1]))' "${repo:-.}")"
  fail_real="$(python3 -c 'import os,sys; print(os.path.realpath(sys.argv[1]))' "$FAIL_GIT_LSFILES_REPO")"
  if [[ "$repo_real" == "$fail_real" ]]; then
    printf 'fatal: mocked ignored-file enumeration failure\n' >&2
    exit 128
  fi
fi
exec "$REAL_GIT_BIN" "$@"
EOF
chmod +x "$GIT_WRAPPER_DIR/git"
FAIL_SOURCE_OUT="$TMP/git-status-source-fail.out"
if PATH="$GIT_WRAPPER_DIR:$PATH" REAL_GIT_BIN="$REAL_GIT_BIN" FAIL_GIT_STATUS_REPO="$SRC1" base_env WP_PATH="$STRUCT_WP" NMKR_PHASE2_LOG_DIR="$TMP/status-source-state" NMKR_DEPLOYED_PLUGIN_PATH="$STRUCT_CLONE" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh" >"$FAIL_SOURCE_OUT" 2>&1; then
  fail "mocked source git status failure unexpectedly passed"
fi
grep -q 'failed gate: source-integrity' "$FAIL_SOURCE_OUT" || fail "mocked source git status failure did not fail at source-integrity"
FAIL_DEPLOYED_OUT="$TMP/git-status-deployed-fail.out"
if PATH="$GIT_WRAPPER_DIR:$PATH" REAL_GIT_BIN="$REAL_GIT_BIN" FAIL_GIT_STATUS_REPO="$STRUCT_CLONE" base_env WP_PATH="$STRUCT_WP" NMKR_PHASE2_LOG_DIR="$TMP/status-deployed-state" NMKR_DEPLOYED_PLUGIN_PATH="$STRUCT_CLONE" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh" >"$FAIL_DEPLOYED_OUT" 2>&1; then
  fail "mocked deployed git status failure unexpectedly passed"
fi
grep -q 'failed gate: deployment-integrity' "$FAIL_DEPLOYED_OUT" || fail "mocked deployed git status failure did not fail at deployment-integrity"
FAIL_LSFILES_OUT="$TMP/git-lsfiles-deployed-fail.out"
if PATH="$GIT_WRAPPER_DIR:$PATH" REAL_GIT_BIN="$REAL_GIT_BIN" FAIL_GIT_LSFILES_REPO="$STRUCT_CLONE" base_env WP_PATH="$STRUCT_WP" NMKR_PHASE2_LOG_DIR="$TMP/lsfiles-deployed-state" NMKR_DEPLOYED_PLUGIN_PATH="$STRUCT_CLONE" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh" >"$FAIL_LSFILES_OUT" 2>&1; then
  fail "mocked ignored-file enumeration failure unexpectedly passed"
fi
grep -q 'failed gate: deployment-integrity' "$FAIL_LSFILES_OUT" || fail "mocked ignored-file enumeration failure did not fail deployment-integrity"
pass "git status and ignored-file enumeration failures fail closed"

RAW_ORIGIN="https://phase15-origin.invalid"
ORIGIN_STATE="$TMP/origin-state"
WPCLI_MOCK="$TMP/wpcli-origin-mock"
cat > "$WPCLI_MOCK" <<'EOF'
#!/usr/bin/env bash
if [[ "$1" == --path=* ]]; then shift; fi
if [[ "$1 $2 $3" == "option get home" || "$1 $2 $3" == "option get siteurl" ]]; then
  printf 'https://different-origin.invalid\n'
  exit 0
fi
exit 0
EOF
chmod +x "$WPCLI_MOCK"
ORIGIN_OUT="$TMP/origin-failure.out"
if base_env WP_CLI_BIN="$WPCLI_MOCK" WP_PATH="$STRUCT_WP" NMKR_PHASE2_LOG_DIR="$ORIGIN_STATE" NMKR_DEPLOYED_PLUGIN_PATH="$STRUCT_CLONE" WP_BASE_URL="$RAW_ORIGIN" NMKR_REAL_SYNC_ALLOWED_ORIGIN="$RAW_ORIGIN" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh" >"$ORIGIN_OUT" 2>&1; then
  fail "origin failure unexpectedly passed"
fi
grep -q 'failed gate: origin-guard' "$ORIGIN_OUT" || fail "origin failure did not reach origin-guard"
! grep -Fq "$RAW_ORIGIN" "$ORIGIN_OUT" || fail "raw origin appeared in captured output"
if [[ -d "$ORIGIN_STATE" ]]; then
  if find "$ORIGIN_STATE" -type f -print0 | xargs -0 grep -Fq "$RAW_ORIGIN" 2>/dev/null; then
    fail "raw origin persisted in private run directory"
  fi
  [[ -z "$(find "$ORIGIN_STATE" -name origin.json -print -quit)" ]] || fail "origin.json persisted after origin failure"
fi
pass "origin failure does not persist raw origin"

MISMATCH="$TMP/deployed-mismatch"; git clone -q "$SRC1" "$MISMATCH"; git -C "$MISMATCH" config user.email public@example.invalid; git -C "$MISMATCH" config user.name PublicTest; echo mismatch > "$MISMATCH/mismatch.txt"; git -C "$MISMATCH" add mismatch.txt; git -C "$MISMATCH" commit -q -m mismatch
MISMATCH_WP="$(wp_with_plugin_link "$MISMATCH" mismatch)"
run_expect_fail "source/deployed commit mismatch" base_env WP_PATH="$MISMATCH_WP" NMKR_PHASE2_LOG_DIR="$TMP/valid-state-mismatch" NMKR_DEPLOYED_PLUGIN_PATH="$MISMATCH" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh"

CLONE="$TMP/deployed-clone"; git clone -q "$SRC1" "$CLONE"; chmod +x "$CLONE/scripts/nmkr-wpcli-db-state.sh"; git -C "$CLONE" config core.fileMode false; chmod -x "$CLONE/scripts/nmkr-real-sync-preflight.sh"
CLONE_WP="$(wp_with_plugin_link "$CLONE" clone)"
run_expect_fail "mode-only deployed change with fileMode false" base_env WP_PATH="$CLONE_WP" NMKR_PHASE2_LOG_DIR="$TMP/valid-state4" NMKR_DEPLOYED_PLUGIN_PATH="$CLONE" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh"

git -C "$SRC1" config core.fileMode false; chmod -x "$SRC1/scripts/nmkr-real-sync-preflight.sh"
run_expect_fail "mode-only source change with fileMode false" base_env WP_PATH="$CLONE_WP" NMKR_PHASE2_LOG_DIR="$TMP/valid-state5" NMKR_DEPLOYED_PLUGIN_PATH="$CLONE" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh"
chmod +x "$SRC1/scripts/nmkr-real-sync-preflight.sh"

git clone -q "$SRC1" "$TMP/deployed-clean"; touch "$TMP/deployed-clean/untracked.txt"
UNCLEAN_WP="$(wp_with_plugin_link "$TMP/deployed-clean" unclean)"
run_expect_fail "untracked deployed file" base_env WP_PATH="$UNCLEAN_WP" NMKR_PHASE2_LOG_DIR="$TMP/valid-state6" NMKR_DEPLOYED_PLUGIN_PATH="$TMP/deployed-clean" bash "$SRC1/scripts/nmkr-real-sync-preflight.sh"


# Backup timestamp parser must require exact canonical UTC shape before parsing.
python3 - <<'PY' || fail "backup timestamp parser regression cases failed"
import datetime, re, sys
def check(value):
    if not re.fullmatch(r'[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z', value):
        raise ValueError('shape')
    dt = datetime.datetime.strptime(value, '%Y-%m-%dT%H:%M:%SZ').replace(tzinfo=datetime.timezone.utc)
    if dt.strftime('%Y-%m-%dT%H:%M:%SZ') != value:
        raise ValueError('roundtrip')
    now = datetime.datetime.now(datetime.timezone.utc)
    if dt < now - datetime.timedelta(hours=24) or dt > now + datetime.timedelta(minutes=5):
        raise ValueError('age')
    return int(dt.timestamp())
now = datetime.datetime.now(datetime.timezone.utc).replace(microsecond=0)
canonical = now.strftime('%Y-%m-%dT%H:%M:%SZ')
check(canonical)
invalids = [
    f'{now.year}-7-{now.day:02d}T{now.hour:02d}:{now.minute:02d}:{now.second:02d}Z',
    f'{now.year}-{now.month:02d}-3T{now.hour:02d}:{now.minute:02d}:{now.second:02d}Z',
    f'{now.year}-{now.month:02d}-{now.day:02d}T7:{now.minute:02d}:{now.second:02d}Z',
    f'{now.year}-{now.month:02d}-{now.day:02d}T{now.hour:02d}:5:{now.second:02d}Z',
    f'{now.year}-{now.month:02d}-{now.day:02d}T{now.hour:02d}:{now.minute:02d}:4Z',
    canonical[:-1] + '.123Z',
    canonical[:-1] + '+00:00',
    canonical[:-1] + 'z',
    ' ' + canonical,
    canonical + ' ',
    f'{now.year}-02-30T{now.hour:02d}:{now.minute:02d}:{now.second:02d}Z',
]
for value in invalids:
    try:
        check(value)
    except Exception:
        continue
    raise SystemExit(f'invalid timestamp accepted: {value!r}')
PY
pass "backup timestamp parser rejects non-canonical forms"

# Cron/runtime static and harness tests.
! grep -q '_get_cron_array' "$ROOT/scripts/nmkr-real-sync-runtime-state.php" || fail "runtime helper calls _get_cron_array"
cat > "$TMP/runtime-harness.php" <<'PHP'
<?php
define('ABSPATH', __DIR__);
class WP_User {}
$GLOBALS['cron_value'] = array('version' => 2);
$GLOBALS['options'] = array();
function get_option($name, $default = false) { if ($name === 'cron') return $GLOBALS['cron_value']; if ($name === 'nmkr_connect_options') return array('sync_profile'=>'light','sync_batch_size'=>1,'sync_batch_delay'=>3); return array_key_exists($name, $GLOBALS['options']) ? $GLOBALS['options'][$name] : $default; }
function get_transient($name) { return false; }
function wp_using_ext_object_cache() { return false; }
function is_email($value) { return strpos($value, '@') !== false; }
function get_user_by($field, $value) { return new WP_User(); }
function user_can($user, $cap) { return true; }
function wp_json_encode($value, $flags = 0) { return json_encode($value, $flags); }
function update_option() { throw new Exception('write attempted'); }
function delete_option() { throw new Exception('write attempted'); }
function set_transient() { throw new Exception('write attempted'); }
function delete_transient() { throw new Exception('write attempted'); }
$case = $argv[1];
if ($case === 'blocked') $GLOBALS['cron_value'] = array('version'=>2, time()=>array('nmkr_sync_cron_hook'=>array('k'=>array('args'=>array()))));
if ($case === 'malformed') $GLOBALS['cron_value'] = array(time()=>array());
if ($case === 'completed_leftovers') $GLOBALS['options'] = array('nmkr_sync_in_progress'=>false,'nmkr_sync_progress'=>100,'nmkr_sync_status'=>'completed','nmkr_sync_data'=>array('completed'=>true,'status'=>'completed'),'nmkr_sync_near_completion'=>true,'nmkr_sync_heartbeat'=>123);
if ($case === 'failed_leftovers') $GLOBALS['options'] = array('nmkr_sync_status'=>'failed','nmkr_sync_data'=>array('completed'=>true,'status'=>'failed'),'nmkr_sync_near_completion'=>true,'nmkr_sync_heartbeat'=>123);
if ($case === 'stopped_leftovers') $GLOBALS['options'] = array('nmkr_sync_status'=>'stopped','nmkr_sync_data'=>array('completed'=>true,'status'=>'stopped'),'nmkr_sync_near_completion'=>true,'nmkr_sync_heartbeat'=>123);
if ($case === 'in_progress_active') $GLOBALS['options'] = array('nmkr_sync_in_progress'=>true);
if ($case === 'status_active') $GLOBALS['options'] = array('nmkr_sync_status'=>'processing_tokens');
if ($case === 'sync_data_active') $GLOBALS['options'] = array('nmkr_sync_data'=>array('completed'=>false,'status'=>'completed'));
if ($case === 'active_leftovers') $GLOBALS['options'] = array('nmkr_sync_status'=>'running','nmkr_sync_near_completion'=>true,'nmkr_sync_heartbeat'=>123);
if ($case === 'progress_active') $GLOBALS['options'] = array('nmkr_sync_progress'=>50);
if ($case === 'progress_zero') $GLOBALS['options'] = array('nmkr_sync_progress'=>0);
if ($case === 'progress_done') $GLOBALS['options'] = array('nmkr_sync_progress'=>100);
include $argv[2];
PHP
valid_json="$(WP_ADMIN_USER=admin php "$TMP/runtime-harness.php" valid "$ROOT/scripts/nmkr-real-sync-runtime-state.php")"
python3 -c 'import json,sys; d=json.loads(sys.argv[1]); assert d["cron_state_inspectable"] is True and d["pending_sync_cron_count"] == 0' "$valid_json" || fail "valid cron not inspectable"
blocked_json="$(WP_ADMIN_USER=admin php "$TMP/runtime-harness.php" blocked "$ROOT/scripts/nmkr-real-sync-runtime-state.php")"
python3 -c 'import json,sys; d=json.loads(sys.argv[1]); assert d["cron_state_inspectable"] is True and d["pending_sync_cron_count"] == 1' "$blocked_json" || fail "blocked cron aggregate count wrong"
malformed_json="$(WP_ADMIN_USER=admin php "$TMP/runtime-harness.php" malformed "$ROOT/scripts/nmkr-real-sync-runtime-state.php")"
python3 -c 'import json,sys; d=json.loads(sys.argv[1]); assert d["cron_state_inspectable"] is False' "$malformed_json" || fail "malformed cron did not fail inspectability"
pass "cron helper regression cases"
for case_name in completed_leftovers failed_leftovers stopped_leftovers progress_zero progress_done; do
  json="$(WP_ADMIN_USER=admin php "$TMP/runtime-harness.php" "$case_name" "$ROOT/scripts/nmkr-real-sync-runtime-state.php")"
  python3 -c 'import json,sys; d=json.loads(sys.argv[1]); assert d["option_active_marker_count"] == 0' "$json" || fail "terminal runtime leftovers blocked for $case_name"
done
for case_name in in_progress_active status_active sync_data_active progress_active; do
  json="$(WP_ADMIN_USER=admin php "$TMP/runtime-harness.php" "$case_name" "$ROOT/scripts/nmkr-real-sync-runtime-state.php")"
  python3 -c 'import json,sys; d=json.loads(sys.argv[1]); assert d["option_active_marker_count"] >= 1' "$json" || fail "active runtime marker did not block for $case_name"
done
active_leftovers_json="$(WP_ADMIN_USER=admin php "$TMP/runtime-harness.php" active_leftovers "$ROOT/scripts/nmkr-real-sync-runtime-state.php")"
python3 -c 'import json,sys; d=json.loads(sys.argv[1]); assert d["option_active_marker_count"] >= 3' "$active_leftovers_json" || fail "active durable state did not count leftovers"
! grep -Eq 'update_option|delete_option|set_transient|delete_transient|wp_schedule_event|wp_clear_scheduled_hook' "$ROOT/scripts/nmkr-real-sync-runtime-state.php" || fail "runtime helper contains write primitive"
pass "runtime option-marker classification cases"

# HTTP/TLS static coverage for the readiness guard.
grep -q -- '--cacert' "$ROOT/scripts/nmkr-real-sync-preflight.sh" || fail "private CA bundle support missing"
! grep -q 'curl -k' "$ROOT/scripts/nmkr-real-sync-preflight.sh" || fail "insecure curl -k present"
grep -q '%{url_effective}' "$ROOT/scripts/nmkr-real-sync-preflight.sh" || fail "effective URL check missing"
grep -q 'ORIGIN_SHA256' "$ROOT/scripts/nmkr-real-sync-preflight.sh" || fail "same-origin digest check missing"
! grep -Eq 'CURL_ARGS=.*(^|[[:space:]])-L([[:space:]]|$)|--location' "$ROOT/scripts/nmkr-real-sync-preflight.sh" || fail "readiness curl follows redirects"
grep -q 'wp-includes/css/dashicons.min.css' "$ROOT/scripts/nmkr-real-sync-preflight.sh" || fail "static readiness asset missing"
! grep -Eq 'wp-login\.php|WP_BASE_URL%/?}/?$|admin-ajax\.php' "$ROOT/scripts/nmkr-real-sync-preflight.sh" || fail "readiness probe loads WordPress web lifecycle"
pass "HTTP readiness guard static checks"

grep -q 'eval-file.*--skip-plugins.*--skip-themes.*--skip-packages' "$ROOT/scripts/nmkr-real-sync-preflight.sh" || fail "runtime eval-file isolation flags missing"
grep -q 'plugin is-active.*--skip-plugins.*--skip-themes.*--skip-packages' "$ROOT/scripts/nmkr-real-sync-preflight.sh" || fail "plugin-active gate isolation flags missing"
grep -q 'NMKR_WPCLI_ISOLATION=true' "$ROOT/scripts/nmkr-real-sync-preflight.sh" || fail "Phase 13B isolation opt-in missing"
grep -q 'NMKR_WPCLI_ISOLATION' "$ROOT/scripts/nmkr-wpcli-db-state.sh" || fail "DB-state isolation opt-in not implemented"
grep -q 'get_option' "$ROOT/scripts/nmkr-real-sync-runtime-state.php" || fail "runtime helper option checks missing"
grep -q 'cron_state_inspectable' "$ROOT/scripts/nmkr-real-sync-runtime-state.php" || fail "runtime helper cron inspectability missing"
grep -q 'wp_using_ext_object_cache' "$ROOT/scripts/nmkr-real-sync-runtime-state.php" || fail "runtime helper object-cache check missing"
grep -q 'user_can.*nmkr_manage_sync' "$ROOT/scripts/nmkr-real-sync-runtime-state.php" || fail "runtime helper capability check missing"
grep -q 'sync_profile' "$ROOT/scripts/nmkr-real-sync-runtime-state.php" || fail "runtime helper profile check missing"
python3 - "$ROOT/scripts/nmkr-real-sync-preflight.sh" <<'PY' || fail "runtime cron guard does not precede login HTTP request"
import sys
lines = open(sys.argv[1], encoding='utf-8').read().splitlines()
def first(fragment):
    for i, line in enumerate(lines, 1):
        if fragment in line:
            return i
    raise SystemExit(1)
eval_line = first('eval-file "$REPO_ROOT/scripts/nmkr-real-sync-runtime-state.php"')
cron_line = first("d['cron_state_inspectable']")
http_line = first('STATIC_READY_URL="${WP_BASE_URL%/}/wp-includes/css/dashicons.min.css"')
if not (eval_line < http_line and cron_line < http_line):
    raise SystemExit(1)
PY
WPCLI_FLAG_MOCK="$TMP/wpcli-flag-mock"
cat > "$WPCLI_FLAG_MOCK" <<'EOF'
#!/usr/bin/env bash
args=" $* "
for flag in --skip-plugins --skip-themes --skip-packages; do
  [[ "$args" == *" $flag "* ]] || exit 42
done
exit 0
EOF
chmod +x "$WPCLI_FLAG_MOCK"
"$WPCLI_FLAG_MOCK" eval-file helper.php --skip-plugins --skip-themes --skip-packages || fail "mock WP-CLI rejected isolated eval-file invocation"
if "$WPCLI_FLAG_MOCK" eval-file helper.php --skip-plugins --skip-themes >/dev/null 2>&1; then
  fail "mock WP-CLI accepted eval-file invocation without all isolation flags"
fi
pass "WP-CLI isolation flag regression checks"

SUMMARY_FUNC_FILE="$TMP/summary-func.sh"
awk '/^print_summary\(\)/{capture=1} capture && /^fail_gate\(\)/{exit} capture{print}' "$ROOT/scripts/nmkr-real-sync-preflight.sh" > "$SUMMARY_FUNC_FILE"
SUMMARY_PRIVATE="$TMP/private-summary-path"
mkdir -p "$SUMMARY_PRIVATE"
SUMMARY_SUCCESS_OUT="$TMP/summary-success.out"
(
  source "$SUMMARY_FUNC_FILE"
  CONFIRMATIONS_STATUS=PASS; PRIVATE_STATE_STATUS=PASS; SOURCE_INTEGRITY_STATUS=PASS; DEPLOYMENT_INTEGRITY_STATUS=PASS
  ORIGIN_GUARD_STATUS=PASS; WORDPRESS_READY_STATUS=PASS; PLUGIN_ACTIVE_STATUS=PASS; CAPABILITY_STATUS=PASS
  DB_STATE_STATUS=PASS; RUNTIME_STATE_STATUS=PASS; CRON_STATE_STATUS=PASS; PROFILE_STATUS=PASS; BACKUP_STATUS=PASS
  TIMEOUT_BOUNDS_STATUS=PASS; RECEIPT_STATUS=PASS; RESULT_STATUS=PASS; FAILED_GATE=""
  SOURCE_COMMIT=0123456789abcdef0123456789abcdef01234567; DEPLOYED_COMMIT=0123456789abcdef0123456789abcdef01234567
  RUN_DIR="$SUMMARY_PRIVATE/run"; DIAGNOSTIC_FILE="$SUMMARY_PRIVATE/run/preflight.log"
  print_summary
) > "$SUMMARY_SUCCESS_OUT"
! grep -Fq "$SUMMARY_PRIVATE" "$SUMMARY_SUCCESS_OUT" || fail "successful summary leaked private path"
! grep -q 'private diagnostic file:' "$SUMMARY_SUCCESS_OUT" || fail "successful summary printed diagnostic path"
grep -q 'result: PASS' "$SUMMARY_SUCCESS_OUT" || fail "successful summary did not report PASS"
SUMMARY_FAIL_OUT="$TMP/summary-fail.out"
(
  source "$SUMMARY_FUNC_FILE"
  CONFIRMATIONS_STATUS=FAIL; PRIVATE_STATE_STATUS=PENDING; SOURCE_INTEGRITY_STATUS=PENDING; DEPLOYMENT_INTEGRITY_STATUS=PENDING
  ORIGIN_GUARD_STATUS=PENDING; WORDPRESS_READY_STATUS=PENDING; PLUGIN_ACTIVE_STATUS=PENDING; CAPABILITY_STATUS=PENDING
  DB_STATE_STATUS=PENDING; RUNTIME_STATE_STATUS=PENDING; CRON_STATE_STATUS=PENDING; PROFILE_STATUS=PENDING; BACKUP_STATUS=PENDING
  TIMEOUT_BOUNDS_STATUS=PENDING; RECEIPT_STATUS=PENDING; RESULT_STATUS=FAIL; FAILED_GATE="confirmations"
  SOURCE_COMMIT=""; DEPLOYED_COMMIT=""; RUN_DIR="$SUMMARY_PRIVATE/run"; DIAGNOSTIC_FILE="$SUMMARY_PRIVATE/run/preflight.log"
  print_summary
) > "$SUMMARY_FAIL_OUT"
grep -Fq "$SUMMARY_PRIVATE/run/preflight.log" "$SUMMARY_FAIL_OUT" || fail "failed summary omitted diagnostic path"
grep -q 'failed gate: confirmations' "$SUMMARY_FAIL_OUT" || fail "failed summary omitted gate"
pass "summary diagnostic path gating"

# Dynamic TLS and same-origin readiness primitives without contacting external hosts.
if command -v openssl >/dev/null 2>&1; then
  CERT_DIR="$TMP/cert"; mkdir -p "$CERT_DIR"
  cat > "$CERT_DIR/openssl.cnf" <<'EOF'
[req]
distinguished_name=req_distinguished_name
x509_extensions=v3_req
prompt=no
[req_distinguished_name]
CN=localhost
[v3_req]
subjectAltName=@alt_names
[alt_names]
DNS.1=localhost
EOF
  openssl req -x509 -newkey rsa:2048 -nodes -days 1 -keyout "$CERT_DIR/key.pem" -out "$CERT_DIR/cert.pem" -config "$CERT_DIR/openssl.cnf" >/dev/null 2>&1
  cat > "$CERT_DIR/https_server.py" <<'PY'
import http.server, ssl, sys
log_path = sys.argv[3]
class Handler(http.server.BaseHTTPRequestHandler):
    def do_GET(self):
        with open(log_path, 'a', encoding='utf-8') as handle:
            handle.write(self.path + '\n')
        if self.path == '/redirect-cross':
            self.send_response(302)
            self.send_header('Location', 'https://other.example/wp-login.php')
            self.end_headers()
            return
        if self.path == '/redirect-same':
            self.send_response(302)
            self.send_header('Location', 'https://%s/wp-login.php' % self.headers.get('Host', 'localhost'))
            self.end_headers()
            return
        if self.path.startswith('/wp-login.php'):
            with open(log_path, 'a', encoding='utf-8') as handle:
                handle.write('BOOTSTRAP_TARGET_CONTACTED\n')
            self.send_response(200)
            self.end_headers()
            self.wfile.write(b'php bootstrap target')
            return
        self.send_response(200)
        self.end_headers()
        self.wfile.write(b'.dashicons{font-family:dashicons}')
    def log_message(self, *args): pass
server = http.server.HTTPServer(('127.0.0.1', 0), Handler)
print(server.server_port, flush=True)
context = ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER)
context.load_cert_chain(certfile=sys.argv[1], keyfile=sys.argv[2])
server.socket = context.wrap_socket(server.socket, server_side=True)
server.serve_forever()
PY
  : > "$CERT_DIR/requests.log"
  python3 "$CERT_DIR/https_server.py" "$CERT_DIR/cert.pem" "$CERT_DIR/key.pem" "$CERT_DIR/requests.log" >"$CERT_DIR/port" 2>/dev/null &
  server_pid=$!
  for _ in {1..50}; do [[ -s "$CERT_DIR/port" ]] && break; sleep 0.1; done
  port="$(cat "$CERT_DIR/port")"
  for _ in {1..50}; do
    curl -sS --cacert "$CERT_DIR/cert.pem" --max-time 1 "https://localhost:$port/wp-includes/css/dashicons.min.css" >/dev/null 2>&1 && break
    sleep 0.1
  done
  if curl -sS --max-time 3 "https://localhost:$port/wp-includes/css/dashicons.min.css" >/dev/null 2>&1; then kill "$server_pid"; fail "invalid TLS certificate unexpectedly passed without CA bundle"; fi
  curl -sS --cacert "$CERT_DIR/cert.pem" --max-time 3 "https://localhost:$port/wp-includes/css/dashicons.min.css" | grep -q 'dashicons' || { kill "$server_pid"; fail "configured CA bundle did not permit valid local TLS response"; }
  cross_status="$(curl -sS --cacert "$CERT_DIR/cert.pem" --max-time 3 -o /dev/null -w '%{http_code}' "https://localhost:$port/redirect-cross")" || { kill "$server_pid"; fail "cross-origin redirect request failed unexpectedly"; }
  [[ "$cross_status" == "302" ]] || { kill "$server_pid"; fail "cross-origin redirect was not rejected as 302"; }
  same_status="$(curl -sS --cacert "$CERT_DIR/cert.pem" --max-time 3 -o /dev/null -w '%{http_code}' "https://localhost:$port/redirect-same")" || { kill "$server_pid"; fail "same-origin bootstrap redirect request failed unexpectedly"; }
  [[ "$same_status" == "302" ]] || { kill "$server_pid"; fail "same-origin bootstrap redirect was not rejected as 302"; }
  ! grep -q 'BOOTSTRAP_TARGET_CONTACTED' "$CERT_DIR/requests.log" || { kill "$server_pid"; fail "redirect target was contacted"; }
  [[ "$(grep -c '^/redirect-cross$' "$CERT_DIR/requests.log")" == "1" ]] || { kill "$server_pid"; fail "cross-origin redirect counter unexpected"; }
  [[ "$(grep -c '^/redirect-same$' "$CERT_DIR/requests.log")" == "1" ]] || { kill "$server_pid"; fail "same-origin redirect counter unexpected"; }
  kill "$server_pid"
  pass "TLS readiness primitives"
  pass "readiness redirects are rejected without following targets"
fi
python3 - <<'PY' || fail "cross-origin redirect helper failed"
import hashlib, urllib.parse, sys
expected = hashlib.sha256(b'https://example.invalid').hexdigest()
url = 'https://other.example/wp-login.php'
p = urllib.parse.urlsplit(url)
origin = 'https://' + p.hostname.lower().rstrip('.')
if hashlib.sha256(origin.encode()).hexdigest() == expected:
    raise SystemExit(1)
PY
pass "cross-origin effective URL rejection primitive"
