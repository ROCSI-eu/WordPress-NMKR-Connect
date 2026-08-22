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
  local expected="$1" log_file="$2"; shift 2
  local output status
  set +e
  output="$(python3 "$CLASSIFIER" "$@" "$log_file" 30 2>&1)"
  status=$?
  set -e
  [[ "$status" == "$expected" ]] || fail
  [[ -z "$output" ]] || fail
}

old="$(timestamp -60)"
fresh="$(timestamp -1)"

printf '[%s] PHP Warning: SYNTHETIC_PRIVATE_MARKER_OLD\n[%s] benign entry\n' "$old" "$fresh" >"$FIXTURE_ROOT/old.log"
run_case 0 "$FIXTURE_ROOT/old.log"

printf '[%s] PHP Warning: SYNTHETIC_FIRST_PARTY_WARNING in /synthetic/plugin/includes/check.php on line 12\n' "$fresh" >"$FIXTURE_ROOT/warning.log"
run_case 1 "$FIXTURE_ROOT/warning.log"

printf '[%s] PHP Notice: SYNTHETIC_UNCLASSIFIED_NOTICE\n' "$fresh" >"$FIXTURE_ROOT/unclassified-notice.log"
run_case 1 "$FIXTURE_ROOT/unclassified-notice.log"

printf '[%s] PHP Warning: SYNTHETIC_VENDOR_WARNING in /synthetic/plugin/vendor/package/check.php on line 34\n' "$fresh" >"$FIXTURE_ROOT/vendor-warning.log"
run_case 3 "$FIXTURE_ROOT/vendor-warning.log"

printf '[%s] PHP Notice: SYNTHETIC_VENDOR_NOTICE in C:\\synthetic\\plugin\\vendor\\package\\check.php on line 56\n' "$fresh" >"$FIXTURE_ROOT/vendor-notice.log"
run_case 3 "$FIXTURE_ROOT/vendor-notice.log"

printf '[%s] PHP Warning: SYNTHETIC_AMBIGUOUS_FIRST_PARTY prior location in /synthetic/plugin/vendor/package/check.php on line 57 final location in /synthetic/plugin/includes/check.php on line 58\n' "$fresh" >"$FIXTURE_ROOT/ambiguous-first-party.log"
run_case 1 "$FIXTURE_ROOT/ambiguous-first-party.log"

printf '[%s] PHP Notice: SYNTHETIC_AMBIGUOUS_VENDOR prior location in /synthetic/plugin/includes/check.php on line 59 final location in /synthetic/plugin/vendor/package/check.php on line 60\n' "$fresh" >"$FIXTURE_ROOT/ambiguous-vendor.log"
run_case 1 "$FIXTURE_ROOT/ambiguous-vendor.log"

printf '[%s] PHP Fatal error: SYNTHETIC_VENDOR_FATAL in /synthetic/plugin/vendor/package/check.php on line 78\n' "$fresh" >"$FIXTURE_ROOT/fatal.log"
run_case 1 "$FIXTURE_ROOT/fatal.log"

printf '[%s] NMKR Error: SYNTHETIC_PRIVATE_MARKER_NMKR\n' "$fresh" >"$FIXTURE_ROOT/nmkr.log"
run_case 1 "$FIXTURE_ROOT/nmkr.log"

printf 'PHP Notice: SYNTHETIC_PRIVATE_MARKER_UNCLASSIFIED\n' >"$FIXTURE_ROOT/unclassified.log"
run_case 1 "$FIXTURE_ROOT/unclassified.log"

printf '[%s] PHP Warning: SYNTHETIC_VENDOR_MIXED in /synthetic/plugin/vendor/package/check.php on line 90\n[%s] PHP Warning: SYNTHETIC_FIRST_PARTY_MIXED in /synthetic/plugin/includes/check.php on line 91\n' "$fresh" "$fresh" >"$FIXTURE_ROOT/mixed.log"
run_case 1 "$FIXTURE_ROOT/mixed.log"

printf '[%s] PHP Warning: SYNTHETIC_PRIVATE_MARKER_OUTSIDE_TAIL\n' "$fresh" >"$FIXTURE_ROOT/tail.log"
for i in $(seq 1 300); do printf '[%s] benign tail entry %s\n' "$fresh" "$i"; done >>"$FIXTURE_ROOT/tail.log"
run_case 0 "$FIXTURE_ROOT/tail.log"
run_case 1 "$FIXTURE_ROOT/tail.log" --complete-file

for i in $(seq 1 301); do printf '[%s] benign complete-file entry %s\n' "$fresh" "$i"; done >"$FIXTURE_ROOT/complete-clean.log"
run_case 0 "$FIXTURE_ROOT/complete-clean.log" --complete-file

run_case 3 "$FIXTURE_ROOT/vendor-warning.log" --complete-file

for i in $(seq 1 300); do printf '[%s] benign prefix entry %s\n' "$fresh" "$i"; done >"$FIXTURE_ROOT/tail-match.log"
printf '[%s] PHP Warning: SYNTHETIC_PRIVATE_MARKER_INSIDE_TAIL\n' "$fresh" >>"$FIXTURE_ROOT/tail-match.log"
run_case 1 "$FIXTURE_ROOT/tail-match.log"

printf 'debug-log-regression: PASS\n'
