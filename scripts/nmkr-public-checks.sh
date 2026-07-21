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


printf '\n== Public-safe Phase 16A controlled real-sync regression checks ==\n'
bash scripts/nmkr-real-sync-phase16a-regression.sh

printf '\n== Canonical synchronization terminalization regression ==\n'
bash scripts/nmkr-sync-terminalization-regression.sh

printf '\n== Atomic synchronization ownership regression ==\n'
bash scripts/nmkr-sync-owner-regression.sh

printf '\n== Run-scoped API throttle regression ==\n'
bash scripts/nmkr-sync-api-throttle-regression.sh

printf '\n== Public-safe PHP 7.4 compatibility guard ==\n'
npm run test:php74
