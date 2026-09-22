#!/usr/bin/env bash
set -euo pipefail
root=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
cd "$root"
test -z "$(git status --porcelain=v1)" || { echo 'Package source must be an exact clean tree.' >&2; exit 1; }
slug=${NMKR_PACKAGE_DIR:-rocsi-connector-for-nmkr}
[[ "$slug" =~ ^[a-z0-9][a-z0-9._-]*$ ]] || { echo 'Invalid package directory name.' >&2; exit 1; }
[[ "$slug" == 'rocsi-connector-for-nmkr' ]] || { echo 'Package directory must be rocsi-connector-for-nmkr.' >&2; exit 1; }
main_file='rocsi-connector-for-nmkr.php'
test -f "$main_file" || { echo 'Canonical main plugin file is missing.' >&2; exit 1; }
test ! -e nmkr-connect.php || { echo 'Obsolete main plugin file must not be present.' >&2; exit 1; }\ntest ! -e connector-for-nmkr.php || { echo 'Superseded pre-review main plugin file must not be present.' >&2; exit 1; }
plugin_name=$(sed -n 's/^[[:space:]]*Plugin Name:[[:space:]]*\(.*[^[:space:]]\)[[:space:]]*$/\1/p' "$main_file" | head -n 1)
[[ "$plugin_name" == 'ROCSI Connector for NMKR' ]] || { echo 'Plugin Name header does not match the release identity.' >&2; exit 1; }
text_domain=$(sed -n 's/^[[:space:]]*Text Domain:[[:space:]]*\([^[:space:]]*\)[[:space:]]*$/\1/p' "$main_file" | head -n 1)
[[ "$text_domain" == 'rocsi-connector-for-nmkr' ]] || { echo 'Text Domain header does not match the release identity.' >&2; exit 1; }
version=$(sed -n 's/^[[:space:]]*Version:[[:space:]]*\([0-9][0-9]*\.[0-9][0-9]*\.[0-9][0-9]*\)[[:space:]]*$/\1/p' "$main_file" | head -n 1)
[[ "$version" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || { echo 'Plugin Version header must be numeric x.y.z.' >&2; exit 1; }
stable_tag=$(sed -n 's/^Stable tag:[[:space:]]*\([0-9][0-9]*\.[0-9][0-9]*\.[0-9][0-9]*\)[[:space:]]*$/\1/p' readme.txt | head -n 1)
[[ "$stable_tag" == "$version" ]] || { echo "readme.txt Stable tag must match plugin Version ($version)." >&2; exit 1; }
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
zip_path="$out/$slug-$version.zip"
rm -f "$zip_path" "$zip_path.sha256"
( cd "$work" && find "$slug" -type f -print | LC_ALL=C sort | zip -X -q "$zip_path" -@ )
(
  cd "$(dirname "$zip_path")"
  sha256sum "$(basename "$zip_path")" > "$(basename "$zip_path").sha256"
  sha256sum -c "$(basename "$zip_path").sha256" >/dev/null
)
verify=$(mktemp -d); unzip -q "$zip_path" -d "$verify"
test "$(find "$verify" -mindepth 1 -maxdepth 1 -print | wc -l)" -eq 1
test -d "$verify/$slug"
test -f "$verify/$slug/$main_file" && test -f "$verify/$slug/readme.txt" && test -f "$verify/$slug/PACKAGE-MANIFEST.sha256"
test ! -e "$verify/$slug/nmkr-connect.php"\ntest ! -e "$verify/$slug/connector-for-nmkr.php"
test "$(grep -Il '^Plugin Name:' "$verify/$slug"/*.php 2>/dev/null | wc -l)" -eq 1
grep -Eq '^Plugin Name:[[:space:]]*ROCSI Connector for NMKR[[:space:]]*$' "$verify/$slug/$main_file"
grep -Eq '^Text Domain:[[:space:]]*rocsi-connector-for-nmkr[[:space:]]*$' "$verify/$slug/$main_file"
grep -Eq "^Version:[[:space:]]*$version[[:space:]]*$" "$verify/$slug/$main_file"
! find "$verify" -type f | grep -Eq '/(\.env|.*\.log$|.*\.zip$|nmkr-connect-auth-)'
( cd "$verify/$slug" && sha256sum -c PACKAGE-MANIFEST.sha256 >/dev/null )
rm -rf "$verify"
printf 'Package verified: %s\nChecksum: %s\n' "$(basename "$zip_path")" "$(cut -d' ' -f1 "$zip_path.sha256")"
