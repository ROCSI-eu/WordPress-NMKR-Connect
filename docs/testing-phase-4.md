# Phase 4 CI v1

Phase 4 CI v1 is a conservative GitHub Actions skeleton for the public/open-source WordPress NMKR Connect plugin repository. It is intentionally limited to checks that are safe to run in a public repository and do not require a live WordPress install, private VM, deployment target, or credentials.

## CI scope

The workflow runs on pull requests to `main` and pushes to `main`. It performs only these public-safe checks:

- `npm ci`
- Playwright test discovery/listing only with `npm run test:e2e -- --list`
- Bash syntax checks for `scripts/*.sh`
- PHP syntax checks for tracked plugin PHP files while excluding dependency, build, report, coverage, and private artifact directories

The workflow sets `CI=true` and keeps `PW_SAVE_ARTIFACTS=false` so public CI does not collect Playwright artifacts.

## Intentional non-goals

Phase 4 CI v1 intentionally does not:

- use WordPress credentials
- log into WordPress
- run live browser tests against WordPress
- run WP-CLI
- run Phase 2 VM validation
- deploy
- upload artifacts
- collect screenshots, traces, videos, Playwright reports, VM logs, or private deploy output

Full VM validation remains Phase 2. Run Phase 2 only in a private environment with `NMKR_PHASE2_ENV_FILE`; never run it in public CI and never publish its private inputs or outputs.

## Local validation commands

Run the same public-safe checks locally with:

```bash
npm ci
npm run test:e2e -- --list
```

For Bash syntax checks, use:

```bash
shopt -s nullglob
scripts=(scripts/*.sh)
if (( ${#scripts[@]} == 0 )); then
  echo "No scripts/*.sh files found."
  exit 1
fi
for script in "${scripts[@]}"; do
  bash -n "$script"
done
```

For PHP syntax checks, use the same tracked-file lint command as the workflow:

```bash
set -euo pipefail
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
for file in "${php_files[@]}"; do
  php -l "$file"
done
```

The workflow uses PHP 7.4 for this syntax smoke check because `composer.json` declares PHP `>=7.4`. This is not a full PHP-version compatibility matrix.

## Public-safety rules

- Never commit `.env.tests` or any populated environment file.
- Never publish Playwright reports, screenshots, traces, videos, VM logs, private deploy output, or secrets.
- Keep `PW_SAVE_ARTIFACTS=false` in CI.
- Do not add GitHub secrets, WordPress admin credentials, Basic Auth credentials, private URLs, deployment commands, or VM-specific paths to public CI.
