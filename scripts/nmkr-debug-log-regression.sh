#!/usr/bin/env bash
set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CLASSIFIER="$ROOT_DIR/scripts/nmkr-debug-log-classifier.py"
FIXTURE_ROOT="$(mktemp -d)"
trap 'rm -rf "$FIXTURE_ROOT"' EXIT

fail() {
  printf 'debug-log-regression: FAIL\n' >&2
  exit 1
}

timestamp() {
  python3 - "$1" <<'PY'
import datetime as dt
import sys
print((dt.datetime.now(dt.timezone.utc) + dt.timedelta(minutes=int(sys.argv[1]))).strftime("%d-%b-%Y %H:%M:%S UTC"))
PY
}

run_case() {
  local expected="$1" log_file="$2" output status
  set +e
  output="$(python3 "$CLASSIFIER" "$log_file" 30 2>&1)"
  status=$?
  set -e
  [[ "$status" == "$expected" ]] || fail
  [[ -z "$output" ]] || fail
}

old="$(timestamp -60)"
fresh="$(timestamp -1)"

printf '[%s] PHP Warning: SYNTHETIC_PRIVATE_MARKER_OLD\n[%s] benign entry\n' "$old" "$fresh" >"$FIXTURE_ROOT/old.log"
run_case 0 "$FIXTURE_ROOT/old.log"

printf '[%s] PHP Warning: SYNTHETIC_PRIVATE_MARKER_WARNING\n' "$fresh" >"$FIXTURE_ROOT/warning.log"
run_case 1 "$FIXTURE_ROOT/warning.log"

printf '[%s] PHP Fatal error: SYNTHETIC_PRIVATE_MARKER_FATAL\n' "$fresh" >"$FIXTURE_ROOT/fatal.log"
run_case 1 "$FIXTURE_ROOT/fatal.log"

printf '[%s] NMKR Error: SYNTHETIC_PRIVATE_MARKER_NMKR\n' "$fresh" >"$FIXTURE_ROOT/nmkr.log"
run_case 1 "$FIXTURE_ROOT/nmkr.log"

printf 'PHP Notice: SYNTHETIC_PRIVATE_MARKER_UNCLASSIFIED\n' >"$FIXTURE_ROOT/unclassified.log"
run_case 1 "$FIXTURE_ROOT/unclassified.log"

printf 'debug-log-regression: PASS\n'
