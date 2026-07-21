#!/usr/bin/env bash
set -euo pipefail
php "$(dirname "$0")/nmkr-schema-upgrade-regression.php"
