#!/usr/bin/env bash
set -euo pipefail
root=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
cd "$root"
test -z "$(git status --porcelain=v1)" || { echo 'Package source must be an exact clean tree.' >&2; exit 1; }
slug=${NMKR_PACKAGE_DIR:-nmkr-connect}
[[ "$slug" =~ ^[a-z0-9][a-z0-9._-]*$ ]] || { echo 'Invalid package directory name.' >&2; exit 1; }
out=${1:-"$root/dist"}
mkdir -p "$out"
out=$(cd "$out" && pwd -P)
work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT
mkdir -p "$work/$slug"
git archive --format=tar HEAD | tar -xf - -C "$work/$slug"
rm -rf "$work/$slug"/{.git,.github,docs,tests,scripts,node_modules,playwright-report,test-results,screenshots,videos,traces,vendor,dist}
rm -f "$work/$slug"/{AGENTS.md,.gitattributes,.gitignore,.env.tests.example,composer.phar,composer-setup.php,playwright.config.ts,playwright.security.config.ts,package.json,package-lock.json,composer.json,composer.lock,README.md}
find "$work/$slug" -type f \( -name '.env*' -o -name '*.zip' -o -name '*.log' -o -name '*.trace' -o -name '*.webm' -o -name 'nmkr-connect-auth-*.json' \) -delete
( cd "$work/$slug" && find . -type f ! -name 'PACKAGE-MANIFEST.sha256' -print | LC_ALL=C sort | sed 's#^./##' | while IFS= read -r f; do sha256sum "$f"; done > PACKAGE-MANIFEST.sha256 )
manifest="$work/$slug/PACKAGE-MANIFEST.sha256"
( cd "$work/$slug" && sha256sum -c PACKAGE-MANIFEST.sha256 >/dev/null )
zip_path="$out/$slug-1.0.0.zip"
rm -f "$zip_path" "$zip_path.sha256"
( cd "$work" && find "$slug" -type f -print | LC_ALL=C sort | zip -X -q "$zip_path" -@ )
(
  cd "$(dirname "$zip_path")"
  sha256sum "$(basename "$zip_path")" > "$(basename "$zip_path").sha256"
)
verify=$(mktemp -d); unzip -q "$zip_path" -d "$verify"
test "$(find "$verify" -mindepth 1 -maxdepth 1 -type d | wc -l)" -eq 1
test -f "$verify/$slug/nmkr-connect.php" && test -f "$verify/$slug/readme.txt"
! find "$verify" -type f | grep -Eq '/(\.env|.*\.log$|.*\.zip$|nmkr-connect-auth-)'
( cd "$verify/$slug" && sha256sum -c PACKAGE-MANIFEST.sha256 >/dev/null )
rm -rf "$verify"
printf 'Package verified: %s\nChecksum: %s\n' "$(basename "$zip_path")" "$(cut -d' ' -f1 "$zip_path.sha256")"
