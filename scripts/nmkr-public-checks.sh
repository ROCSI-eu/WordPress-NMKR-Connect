#!/usr/bin/env bash
set -Eeuo pipefail

printf '\n== Public-safe Bash syntax checks ==\n'
shopt -s nullglob
scripts=(scripts/*.sh)
if (( ${#scripts[@]} == 0 )); then
  echo "No scripts/*.sh files found."
  exit 1
fi

for script in "${scripts[@]}"; do
  echo "Checking ${script}"
  bash -n "${script}"
done

printf '\n== Public-safe Playwright test discovery ==\n'
npm run test:e2e -- --list --reporter=list

printf '\n== Public-safe Phase 15 preflight regression checks ==\n'
bash scripts/nmkr-real-sync-preflight-regression.sh

printf '\n== Public-safe Phase 2 readonly profile regression checks ==\n'
bash scripts/nmkr-phase2-profile-regression.sh


printf '\n== Public-safe Phase 16A controlled real-sync regression checks ==\n'
bash scripts/nmkr-real-sync-phase16a-regression.sh

printf '\n== Canonical synchronization terminalization regression ==\n'
bash scripts/nmkr-sync-terminalization-regression.sh

printf '\n== Isolated synthetic provider regression ==\n'
php scripts/nmkr-synthetic-provider-regression.php

printf '\n== Isolated synthetic controller regression ==\n'
bash scripts/nmkr-synthetic-run-regression.sh

printf '\n== Privileged AJAX guard regression ==\n'
php scripts/nmkr-ajax-guard-regression.php

printf '\n== AJAX error-disclosure regressions ==\n'
php scripts/nmkr-ajax-error-disclosure-regression.php
node scripts/nmkr-transport-error-disclosure-regression.js

printf '\n== AJAX security private-boundary regression ==\n'
bash scripts/nmkr-ajax-security-public-regression.sh

printf '\n== AJAX security idle-state regression ==\n'
bash scripts/nmkr-ajax-security-idle-regression.sh

printf '\n== Debug-log lookback regression ==\n'
bash scripts/nmkr-debug-log-regression.sh

printf '\n== In-place run_id schema upgrade regression ==\n'
bash scripts/nmkr-schema-upgrade-regression.sh

printf '\n== Atomic synchronization ownership regression ==\n'
bash scripts/nmkr-sync-owner-regression.sh

printf '\n== Run-scoped API throttle regression ==\n'
bash scripts/nmkr-sync-api-throttle-regression.sh

printf '\n== Exact database-write regression ==\n'
php scripts/nmkr-database-write-regression.php

printf '\n== HTTP and metric correctness regression ==\n'
php scripts/nmkr-http-metrics-regression.php

printf '\n== NMKR API response benchmark regression ==\n'
php scripts/nmkr-api-response-benchmark-regression.php

printf '\n== Streaming synchronization pagination regression ==\n'
php scripts/nmkr-sync-pagination-regression.php

printf '\n== Public-safe PHP 7.4 compatibility guard ==\n'
npm run test:php74

printf '\n== Shortcode image-source regression ==\n'
php scripts/nmkr-shortcode-image-regression.php
node scripts/nmkr-token-image-fallback-regression.js
