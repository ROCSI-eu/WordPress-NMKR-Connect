#!/usr/bin/env bash
set -Eeuo pipefail
runner="$(cd "$(dirname "$0")" && pwd)/nmkr-synthetic-run.sh"; tmp="$(mktemp -d)"; trap 'rm -rf "$tmp"' EXIT
run_fail(){ local out; if out="$(env -i PATH="$PATH" HOME="$HOME" "$@" bash "$runner" 2>&1)"; then echo "FAIL: accepted unsafe invocation" >&2; exit 1; fi; [[ "$out" != *password* && "$out" != *credential* && "$out" != *"$tmp"* ]] || { echo 'FAIL: unsafe output' >&2; exit 1; }; }
run_fail
run_fail CI=true RUN_NMKR_SYNTHETIC=true
run_fail RUN_NMKR_SYNTHETIC=true NMKR_SYNTHETIC_CONFIRM=I_AUTHORIZE_DISPOSABLE_SYNTHETIC_SYNC
! rg -n 'docker|iptables|nft|wp core download' "$runner" >/dev/null || { echo 'FAIL: prohibited provisioning' >&2; exit 1; }
echo 'Synthetic controller regression: PASS'
