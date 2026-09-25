#!/usr/bin/env bash
set -Eeuo pipefail

root=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
cd "$root"

test -z "$(git status --porcelain=v1)" || {
  echo "FAIL: package reproducibility regression requires a clean source tree." >&2
  exit 1
}

tmp=$(mktemp -d)
trap 'rm -rf "$tmp"' EXIT
first="$tmp/first"
second="$tmp/second"
mkdir -p "$first" "$second"

bash scripts/nmkr-build-package.sh "$first" >/dev/null
first_zip=$(find "$first" -maxdepth 1 -type f -name 'rocsi-connector-for-nmkr-*.zip' -print -quit)
test -n "$first_zip" || { echo "FAIL: first package ZIP was not created." >&2; exit 1; }

# ZIP stores DOS timestamps with two-second granularity. Cross that boundary so
# wall-clock mtimes on generated files would make the second archive differ.
sleep 3

bash scripts/nmkr-build-package.sh "$second" >/dev/null
second_zip=$(find "$second" -maxdepth 1 -type f -name 'rocsi-connector-for-nmkr-*.zip' -print -quit)
test -n "$second_zip" || { echo "FAIL: second package ZIP was not created." >&2; exit 1; }

first_hash=$(sha256sum "$first_zip" | awk '{print $1}')
second_hash=$(sha256sum "$second_zip" | awk '{print $1}')
[[ "$first_hash" == "$second_hash" ]] || {
  printf 'FAIL: repeated package ZIP hashes differ: %s != %s\n' "$first_hash" "$second_hash" >&2
  exit 1
}
cmp -s "$first_zip" "$second_zip" || {
  echo "FAIL: repeated package ZIPs are not byte-for-byte identical." >&2
  exit 1
}

verify="$tmp/verify"
unzip -q "$second_zip" -d "$verify"
(
  cd "$verify/rocsi-connector-for-nmkr"
  sha256sum -c PACKAGE-MANIFEST.sha256 >/dev/null
)

test -z "$(git status --porcelain=v1)" || {
  echo "FAIL: package generation modified the source tree." >&2
  exit 1
}

printf 'PASS: delayed repeated builds are byte-for-byte reproducible (%s).\n' "$first_hash"
printf 'PASS: packaged manifest verifies and the source tree remains clean.\n'
