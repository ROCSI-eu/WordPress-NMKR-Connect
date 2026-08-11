#!/usr/bin/env bash
set -Eeuo pipefail

ROOT="${1:-}"
[[ -n "$ROOT" && -d "$ROOT" && ! -L "$ROOT" ]] || exit 1

private="$(mktemp -d)" || exit 1
chmod 700 "$private" || exit 1
ignored="$private/ignored"
trap 'rm -rf -- "$private"' EXIT
git -C "$ROOT" ls-files --others --ignored --exclude-standard -z -- vendor >"$ignored" 2>/dev/null || exit 1

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
            if relative == "composer/installed.php":
                continue
            digest = hashlib.sha256()
            with open(path, "rb") as handle:
                for chunk in iter(lambda: handle.read(1024 * 1024), b""):
                    digest.update(chunk)
            result[relative] = digest.digest()
    return result

if inventory(sys.argv[1]) != inventory(sys.argv[2]):
    raise SystemExit(1)
PY

# Composer embeds the root checkout and absolute package install locations in
# installed.php. Parse that generated data without executing either copy, then
# compare it while keeping every non-location dependency field (including
# versions and references) significant. All other files are byte-compared above.
cat >"$private/compare-installed.php" <<'PHP'
<?php
final class InstalledParser {
    private $tokens;
    private $position = 0;

    public function __construct($source) {
        $raw = token_get_all($source);
        $this->tokens = array_values(array_filter($raw, static function ($token) {
            return !is_array($token) || !in_array($token[0], array(T_OPEN_TAG, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true);
        }));
    }

    public function parse() {
        $this->take(T_RETURN);
        $value = $this->value();
        $this->take(';');
        if ($this->position !== count($this->tokens)) {
            throw new RuntimeException('unexpected content');
        }
        return $value;
    }

    private function take($expected) {
        if (!isset($this->tokens[$this->position])) {
            throw new RuntimeException('unexpected end');
        }
        $token = $this->tokens[$this->position++];
        $actual = is_array($token) ? $token[0] : $token;
        if ($actual !== $expected) {
            throw new RuntimeException('unexpected token');
        }
        return is_array($token) ? $token[1] : $token;
    }

    private function peek($expected) {
        if (!isset($this->tokens[$this->position])) {
            return false;
        }
        $token = $this->tokens[$this->position];
        return (is_array($token) ? $token[0] : $token) === $expected;
    }

    private function stringValue() {
        $literal = $this->take(T_CONSTANT_ENCAPSED_STRING);
        if (strlen($literal) < 2 || $literal[0] !== "'" || substr($literal, -1) !== "'") {
            throw new RuntimeException('unsupported string');
        }
        return preg_replace_callback('/\\\\([\\\\\'])/', static function ($match) {
            return $match[1];
        }, substr($literal, 1, -1));
    }

    private function value() {
        if ($this->peek(T_ARRAY)) {
            $this->take(T_ARRAY);
            $this->take('(');
            return $this->arrayValue(')');
        }
        if ($this->peek('[')) {
            $this->take('[');
            return $this->arrayValue(']');
        }
        if ($this->peek(T_CONSTANT_ENCAPSED_STRING)) {
            return $this->stringValue();
        }
        if ($this->peek(T_LNUMBER)) {
            $number = $this->take(T_LNUMBER);
            if (!preg_match('/^(0|[1-9][0-9]*)$/', $number)) {
                throw new RuntimeException('unsupported number');
            }
            return (int) $number;
        }
        if ($this->peek(T_DIR)) {
            $this->take(T_DIR);
            $this->take('.');
            return '__composer_dir__' . $this->stringValue();
        }
        if ($this->peek(T_STRING)) {
            $constant = strtolower($this->take(T_STRING));
            if ($constant === 'true') return true;
            if ($constant === 'false') return false;
            if ($constant === 'null') return null;
        }
        throw new RuntimeException('unsupported value');
    }

    private function arrayValue($close) {
        $result = array();
        $nextIndex = 0;
        while (!$this->peek($close)) {
            $first = $this->value();
            if ($this->peek(T_DOUBLE_ARROW)) {
                $this->take(T_DOUBLE_ARROW);
                if (!is_string($first) && !is_int($first)) {
                    throw new RuntimeException('unsupported key');
                }
                $key = $first;
                $value = $this->value();
            } else {
                $key = $nextIndex;
                $value = $first;
            }
            if (array_key_exists($key, $result)) {
                throw new RuntimeException('duplicate key');
            }
            $result[$key] = $value;
            if (is_int($key) && $key >= $nextIndex) $nextIndex = $key + 1;
            if ($this->peek(',')) {
                $this->take(',');
                continue;
            }
            if (!$this->peek($close)) throw new RuntimeException('missing separator');
        }
        $this->take($close);
        return $result;
    }
}

function normalized_installed($path) {
    $source = file_get_contents($path);
    if ($source === false) throw new RuntimeException('unreadable input');
    $installed = (new InstalledParser($source))->parse();
    if (!is_array($installed) || array_keys($installed) !== array('root', 'versions') ||
        !is_array($installed['root']) || !isset($installed['root']['name']) ||
        $installed['root']['name'] !== 'nmkr/nmkr-connect' ||
        !is_array($installed['versions']) || !isset($installed['versions']['nmkr/nmkr-connect'])) {
        throw new RuntimeException('unexpected structure');
    }
    $versions = $installed['versions'];
    unset($versions['nmkr/nmkr-connect']);
    foreach ($versions as $package => &$metadata) {
        if (!is_string($package) || !is_array($metadata)) throw new RuntimeException('unexpected package');
        if (array_key_exists('install_path', $metadata)) $metadata['install_path'] = '__normalized_install_path__';
        ksort($metadata);
    }
    unset($metadata);
    ksort($versions);
    return $versions;
}

try {
    if (normalized_installed($argv[1]) !== normalized_installed($argv[2])) exit(1);
} catch (Throwable $error) {
    exit(1);
}
PHP
php "$private/compare-installed.php" "$ROOT/vendor/composer/installed.php" "$private/vendor/composer/installed.php" >/dev/null 2>&1
