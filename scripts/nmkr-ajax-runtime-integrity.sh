#!/usr/bin/env bash
set -Eeuo pipefail

ROOT="${1:-}"
[[ -n "$ROOT" && -d "$ROOT" && ! -L "$ROOT" ]] || exit 1

private="$(mktemp -d)" || exit 1
chmod 700 "$private" || exit 1
ignored="$private/ignored"
trap 'rm -rf -- "$private"' EXIT
git -C "$ROOT" ls-files --others --ignored --exclude-standard -z >"$ignored" 2>/dev/null || exit 1

python3 - "$ROOT" "$ignored" <<'PY'
import os
import sys

root, ignored_path = sys.argv[1:3]
root = os.path.realpath(root)
with open(ignored_path, "rb") as handle:
    paths = [item.decode("utf-8", "surrogateescape") for item in handle.read().split(b"\0") if item]

allowed_file = "vendor/autoload.php"
allowed_prefixes = ("vendor/composer/", "vendor/freemius/wordpress-sdk/")
required = (
    "vendor/autoload.php",
    "vendor/composer/installed.php",
    "vendor/freemius/wordpress-sdk/start.php",
    "vendor/freemius/wordpress-sdk/includes/class-freemius.php",
)

def safe_regular_file(relative):
    if not relative or relative.startswith("/") or ".." in relative.split("/"):
        return False
    current = root
    for component in relative.split("/"):
        current = os.path.join(current, component)
        if os.path.islink(current):
            return False
    return os.path.isfile(current) and os.path.commonpath((root, os.path.realpath(current))) == root

if not paths:
    raise SystemExit(1)
for relative in paths:
    if relative != allowed_file and not relative.startswith(allowed_prefixes):
        raise SystemExit(1)
    if not safe_regular_file(relative):
        raise SystemExit(1)
for relative in required:
    if not safe_regular_file(relative):
        raise SystemExit(1)
PY

composer --working-dir="$ROOT" validate --no-check-publish --no-interaction --no-ansi >/dev/null 2>&1
cp -- "$ROOT/composer.json" "$ROOT/composer.lock" "$private/"
composer --working-dir="$private" install --no-dev --prefer-dist --no-interaction --no-progress --no-ansi >/dev/null 2>&1
python3 - "$ROOT/vendor" "$private/vendor" <<'PY'
import hashlib
import os
import sys

def inventory(root):
    result = {}
    for current, directories, files in os.walk(root, followlinks=False):
        if any(os.path.islink(os.path.join(current, name)) for name in directories):
            raise SystemExit(1)
        for name in files:
            path = os.path.join(current, name)
            if os.path.islink(path) or not os.path.isfile(path):
                raise SystemExit(1)
            relative = os.path.relpath(path, root).replace(os.sep, "/")
            digest = hashlib.sha256()
            with open(path, "rb") as handle:
                for chunk in iter(lambda: handle.read(1024 * 1024), b""):
                    digest.update(chunk)
            result[relative] = digest.digest()
    return result

if inventory(sys.argv[1]) != inventory(sys.argv[2]):
    raise SystemExit(1)
PY
