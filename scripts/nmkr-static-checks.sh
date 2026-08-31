#!/usr/bin/env bash
set -Eeuo pipefail

printf '\n== Tracked shell syntax ==\n'
while IFS= read -r -d '' file; do bash -n "$file"; done < <(git ls-files -z '*.sh')

printf '\n== Tracked JavaScript syntax ==\n'
while IFS= read -r -d '' file; do node --check "$file"; done < <(git ls-files -z '*.js' '*.mjs')

printf '\n== PHP 7.4 compatibility ==\n'
bash scripts/nmkr-php74-compat-scan.sh

printf '\n== Playwright discovery ==\n'
npm run test:e2e -- --list --reporter=list
npx playwright test --config=playwright.security.config.ts --list --reporter=list

printf '\n== Tracked private/generated artifact names ==\n'
bad=0
while IFS= read -r -d '' file; do
  case "$file" in
    .env.tests.example|*.env.example|*.env.sample) ;;
    .env|.env.*|*/.env|*/.env.*|*auth-state*.json|playwright-report/*|*/playwright-report/*|test-results/*|*/test-results/*|private-validation/*|*/private-validation/*|*.trace.zip|*.webm|*.mp4|*.log|*private-manifest*.json)
      bad=1 ;;
  esac
done < <(git ls-files -z)
if (( bad )); then echo 'Tracked private/generated artifact class detected (filenames withheld).'; exit 1; fi
echo 'Tracked artifact-name gate: PASS'
