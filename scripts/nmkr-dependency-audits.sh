#!/usr/bin/env bash
set -Eeuo pipefail

npm audit --audit-level=high
composer validate --strict --no-check-publish

composer_package_count="$(php -r '
$contents = @file_get_contents("composer.lock");
if ($contents === false) {
    fwrite(STDERR, "Unable to read composer.lock.\n");
    exit(1);
}

try {
    $lock = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    fwrite(STDERR, "Unable to parse composer.lock.\n");
    exit(1);
}

if (!is_array($lock)
    || !isset($lock["packages"], $lock["packages-dev"])
    || !is_array($lock["packages"])
    || !is_array($lock["packages-dev"])) {
    fwrite(STDERR, "composer.lock has an invalid package list.\n");
    exit(1);
}

echo count($lock["packages"]) + count($lock["packages-dev"]);
')"

if [[ "$composer_package_count" -eq 0 ]]; then
    echo "Composer audit: SKIP (composer.lock contains no packages)."
else
    composer audit --locked --no-dev --abandoned=report
fi
