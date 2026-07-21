#!/usr/bin/env bash
set -euo pipefail
php "$(dirname "$0")/nmkr-sync-api-throttle-regression.php"
