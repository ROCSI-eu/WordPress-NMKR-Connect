#!/usr/bin/env bash
set -Eeuo pipefail

npm audit --audit-level=high
composer validate --strict --no-check-publish
composer audit --locked --no-dev --abandoned=report
