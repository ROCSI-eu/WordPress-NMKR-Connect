#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
php "$ROOT/scripts/nmkr-sync-terminalization-regression.php"
