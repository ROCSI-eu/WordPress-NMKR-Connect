#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
RUNNER="$ROOT/scripts/nmkr-ajax-security-test-runner.sh"
fail(){ echo 'AJAX security harness regression: FAIL' >&2; exit 1; }

# Keep these source checks narrow: they prevent a future edit from silently restoring
# Playwright's repository-local defaults or cleanup-before-reap signal ordering.
for name in PLAYWRIGHT_HTML_REPORT PLAYWRIGHT_TEST_OUTPUT_DIR NMKR_AUTH_STATE_ROOT; do
  grep -Fq -- "export ${name}=\"\$RUN_DIR/" "$RUNNER" || fail
done
grep -Fq -- 'mkdir -m 700 -- "$NMKR_AUTH_STATE_ROOT" || fail auth-state' "$RUNNER" || fail
grep -Fq -- '[[ -d "$NMKR_AUTH_STATE_ROOT" && ! -L "$NMKR_AUTH_STATE_ROOT" ]] || fail auth-state' "$RUNNER" || fail
grep -Fq -- '[[ "$(stat -c '\''%a'\'' "$NMKR_AUTH_STATE_ROOT" 2>/dev/null)" == 700 ]] || fail auth-state' "$RUNNER" || fail
auth_prepare_line="$(grep -nF -- 'mkdir -m 700 -- "$NMKR_AUTH_STATE_ROOT"' "$RUNNER" | cut -d: -f1)"
playwright_line="$(grep -nF -- 'run negative npm --prefix "$ROOT" run test:e2e:ajax-security' "$RUNNER" | cut -d: -f1)"
[[ "$auth_prepare_line" =~ ^[0-9]+$ && "$playwright_line" =~ ^[0-9]+$ && "$auth_prepare_line" -lt "$playwright_line" ]] || fail
grep -Eq -- 'reap_active; .*rm -rf' "$RUNNER" || fail
grep -Fq -- 'kill -TERM -- "-$ACTIVE_PGID"' "$RUNNER" || fail

# Exercise the ignored-runtime gate with an expected generated layout and a
# deterministic Composer stand-in. The same gate must reject changed package
# content without printing filenames or fixture paths.
integrity_root="$(mktemp -d)"
mkdir -p "$integrity_root/plugin/vendor/composer" "$integrity_root/plugin/vendor/freemius/wordpress-sdk/includes" "$integrity_root/plugin/node_modules/example" "$integrity_root/bin"
printf '/vendor/\n/node_modules/\n' >"$integrity_root/plugin/.gitignore"
printf '{}\n' >"$integrity_root/plugin/composer.json"
printf '{}\n' >"$integrity_root/plugin/composer.lock"
printf 'expected\n' >"$integrity_root/plugin/vendor/autoload.php"
cat >"$integrity_root/plugin/vendor/composer/installed.php" <<'PHP'
<?php return array(
  'root' => array('name' => 'nmkr/nmkr-connect', 'pretty_version' => 'deployed-root'),
  'versions' => array(
    'nmkr/nmkr-connect' => array('pretty_version' => 'deployed-root', 'version' => 'dev-main', 'reference' => 'deployed-vcs', 'type' => 'wordpress-plugin', 'install_path' => __DIR__ . '/../../'),
    'freemius/wordpress-sdk' => array('pretty_version' => '2.12.2', 'version' => '2.12.2.0', 'reference' => 'expected-reference', 'type' => 'library', 'install_path' => __DIR__ . '/../freemius/wordpress-sdk', 'dev_requirement' => false),
  ),
);
PHP
printf 'expected\n' >"$integrity_root/plugin/vendor/freemius/wordpress-sdk/start.php"
printf 'expected\n' >"$integrity_root/plugin/vendor/freemius/wordpress-sdk/includes/class-freemius.php"
printf 'test-only\n' >"$integrity_root/plugin/node_modules/example/index.js"
git -C "$integrity_root/plugin" init -q
git -C "$integrity_root/plugin" add .gitignore composer.json composer.lock
cat >"$integrity_root/bin/composer" <<'SH'
#!/usr/bin/env bash
set -e
root="${1#--working-dir=}"
case " $* " in
  *' install '*)
    mkdir -p "$root/vendor/composer" "$root/vendor/freemius/wordpress-sdk/includes"
    printf 'expected\n' >"$root/vendor/autoload.php"
    cat >"$root/vendor/composer/installed.php" <<'PHP'
<?php return array(
  'root' => array('name' => 'nmkr/nmkr-connect', 'pretty_version' => 'reconstructed-root', 'reference' => 'different-vcs-context'),
  'versions' => array(
    'nmkr/nmkr-connect' => array('pretty_version' => 'reconstructed-root', 'version' => 'dev-main', 'reference' => 'different-vcs-context', 'type' => 'wordpress-plugin', 'install_path' => __DIR__ . '/different-root'),
    'freemius/wordpress-sdk' => array('pretty_version' => '2.12.2', 'version' => '2.12.2.0', 'reference' => 'expected-reference', 'type' => 'library', 'install_path' => __DIR__ . '/different-location', 'dev_requirement' => false),
  ),
);
PHP
    printf 'expected\n' >"$root/vendor/freemius/wordpress-sdk/start.php"
    printf 'expected\n' >"$root/vendor/freemius/wordpress-sdk/includes/class-freemius.php"
    ;;
esac
SH
chmod 700 "$integrity_root/bin/composer"
integrity_output="$(PATH="$integrity_root/bin:$PATH" bash "$ROOT/scripts/nmkr-ajax-runtime-integrity.sh" "$integrity_root/plugin" 2>&1)" || fail
[[ -z "$integrity_output" ]] || fail
printf 'modified\n' >"$integrity_root/plugin/vendor/freemius/wordpress-sdk/start.php"
if integrity_output="$(PATH="$integrity_root/bin:$PATH" bash "$ROOT/scripts/nmkr-ajax-runtime-integrity.sh" "$integrity_root/plugin" 2>&1)"; then fail; fi
[[ -z "$integrity_output" ]] || fail
printf 'expected\n' >"$integrity_root/plugin/vendor/freemius/wordpress-sdk/start.php"
sed -i 's/expected-reference/stale-reference/' "$integrity_root/plugin/vendor/composer/installed.php"
if integrity_output="$(PATH="$integrity_root/bin:$PATH" bash "$ROOT/scripts/nmkr-ajax-runtime-integrity.sh" "$integrity_root/plugin" 2>&1)"; then fail; fi
[[ -z "$integrity_output" ]] || fail
sed -i 's/stale-reference/expected-reference/' "$integrity_root/plugin/vendor/composer/installed.php"
sed -i 's/version'"'"' => '"'"'2.12.2.0/version'"'"' => '"'"'2.11.0.0/' "$integrity_root/plugin/vendor/composer/installed.php"
if integrity_output="$(PATH="$integrity_root/bin:$PATH" bash "$ROOT/scripts/nmkr-ajax-runtime-integrity.sh" "$integrity_root/plugin" 2>&1)"; then fail; fi
[[ -z "$integrity_output" ]] || fail
sed -i 's/version'"'"' => '"'"'2.11.0.0/version'"'"' => '"'"'2.12.2.0/' "$integrity_root/plugin/vendor/composer/installed.php"
marker="$integrity_root/installed-side-effect"
sed -i "s#<?php return#<?php file_put_contents('$marker', 'executed'); return#" "$integrity_root/plugin/vendor/composer/installed.php"
if integrity_output="$(PATH="$integrity_root/bin:$PATH" bash "$ROOT/scripts/nmkr-ajax-runtime-integrity.sh" "$integrity_root/plugin" 2>&1)"; then fail; fi
[[ -z "$integrity_output" && ! -e "$marker" ]] || fail
sed -i "s#<?php file_put_contents('$marker', 'executed'); return#<?php return#" "$integrity_root/plugin/vendor/composer/installed.php"
printf 'unexpected\n' >"$integrity_root/plugin/vendor/unexpected.php"
if integrity_output="$(PATH="$integrity_root/bin:$PATH" bash "$ROOT/scripts/nmkr-ajax-runtime-integrity.sh" "$integrity_root/plugin" 2>&1)"; then fail; fi
[[ -z "$integrity_output" ]] || fail
rm -rf -- "$integrity_root"

private="$(mktemp -d)"; chmod 700 "$private"
trap 'rm -rf -- "$private"' EXIT
export PLAYWRIGHT_HTML_REPORT="$private/report" PLAYWRIGHT_TEST_OUTPUT_DIR="$private/results" NMKR_AUTH_STATE_ROOT="$private/auth"
setsid bash -c 'mkdir -p "$PLAYWRIGHT_HTML_REPORT" "$PLAYWRIGHT_TEST_OUTPUT_DIR" "$NMKR_AUTH_STATE_ROOT"; bash -c '\''trap "echo stopped >\"$NMKR_AUTH_STATE_ROOT/stopped\"; exit 0" TERM; while :; do sleep 1; done'\'' & echo $! >"$NMKR_AUTH_STATE_ROOT/child"; wait' >/dev/null 2>&1 &
leader=$!
for _ in {1..50}; do [[ -s "$private/auth/child" ]] && break; sleep .02; done
[[ -s "$private/auth/child" ]] || fail
child="$(cat "$private/auth/child")"
kill -TERM -- "-$leader" 2>/dev/null || fail
wait "$leader" 2>/dev/null || true
for _ in {1..50}; do [[ -f "$private/auth/stopped" ]] && break; sleep .02; done
[[ -f "$private/auth/stopped" ]] || fail
for path in "$PLAYWRIGHT_HTML_REPORT" "$PLAYWRIGHT_TEST_OUTPUT_DIR" "$NMKR_AUTH_STATE_ROOT"; do
  [[ "$path" == "$private"/* ]] || fail
done
rm -rf -- "$private"; trap - EXIT
[[ ! -e "$private" ]] || fail
echo 'AJAX security harness regression: PASS'
