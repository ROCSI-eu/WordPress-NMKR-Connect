#!/usr/bin/env bash
set -Eeuo pipefail

# Public-safe guards: every case must fail in preflight, before network access.
ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
runner="$ROOT/scripts/nmkr-phase2-test-runner.sh"
tmp_dir="$(mktemp -d)"
base=(env WP_BASE_URL=http://invalid.test WP_ADMIN_USER=placeholder WP_ADMIN_PASSWORD=placeholder WP_PATH=/tmp NMKR_PHASE2_LOG_DIR="$tmp_dir")
trap 'rm -rf "$tmp_dir"' EXIT

expect_fail() {
  if "${base[@]}" "$@" bash "$runner" >/dev/null 2>&1; then
    echo "Expected public-safe preflight failure." >&2; exit 1
  fi
}

expect_fail NMKR_PHASE2_PROFILE=unknown
expect_fail RUN_REAL_SYNC=maybe
expect_fail NMKR_PHASE2_PROFILE=existing-readonly NMKR_PHASE2_EXPECTED_SOURCE_SHA=invalid
echo 'PASS: Phase 2 profile validation regressions.'
