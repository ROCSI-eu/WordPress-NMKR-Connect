#!/usr/bin/env bash
set -Eeuo pipefail

# Lightweight PHP 7.4 guardrail for public CI.
# This intentionally combines PHP 7.4 syntax linting with a narrow grep scan for
# common PHP 8+ constructs/functions. It is not a full PHPCompatibility, PHPCS,
# PHPStan, or Psalm replacement.

mapfile -d '' php_files < <(git ls-files -z -- '*.php' \
  ':(exclude)vendor/**' \
  ':(exclude)node_modules/**' \
  ':(exclude)build/**' \
  ':(exclude)dist/**' \
  ':(exclude)playwright-report/**' \
  ':(exclude)test-results/**' \
  ':(exclude).phase2-private/**' \
  ':(exclude)coverage/**' \
  ':(exclude)reports/**')

if (( ${#php_files[@]} == 0 )); then
  echo "No tracked PHP files found."
  exit 1
fi

echo "Running PHP 7.4 syntax checks for ${#php_files[@]} tracked PHP files."
for file in "${php_files[@]}"; do
  php -l "$file"
done

echo "Scanning for narrow PHP 8+ compatibility patterns."
compat_failed=0

# Keep patterns narrow to reduce false positives in public CI output. Matches
# report only repository-relative paths, line numbers, and matching source lines.
if grep -EnH \
  -e '\bmatch[[:space:]]*\(' \
  -e '\?->' \
  -e '^[[:space:]]*#\[' \
  -e '\breadonly\b' \
  -e '\benum[[:space:]]+[A-Za-z_][A-Za-z0-9_]*' \
  -e '\bstr_contains[[:space:]]*\(' \
  -e '\bstr_starts_with[[:space:]]*\(' \
  -e '\bstr_ends_with[[:space:]]*\(' \
  -e '\bfdiv[[:space:]]*\(' \
  -e '\bget_debug_type[[:space:]]*\(' \
  -e '\bget_resource_id[[:space:]]*\(' \
  -- "${php_files[@]}"; then
  compat_failed=1
fi

if (( compat_failed != 0 )); then
  echo "PHP 8+ compatibility patterns were found. Keep runtime-compatible code for the declared PHP >=7.4 floor."
  exit 1
fi

echo "No guarded PHP 8+ compatibility patterns found."
