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
npm run test:e2e -- --list

printf '\n== Public-safe PHP 7.4 compatibility guard ==\n'
npm run test:php74
