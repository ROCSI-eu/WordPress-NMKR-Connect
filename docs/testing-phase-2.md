# Phase 2 VM test runner

Phase 2 adds a VM-local orchestration runner around the existing Phase 1 automated checks. It is intended for maintainers who validate a deployed WordPress NMKR Connect installation from a private, user-owned checkout.

The runner does not change plugin runtime behavior. It coordinates deployment, Playwright admin smoke checks, WP-CLI smoke checks, and WP-CLI database-state validation, then writes detailed output to private local files while printing only a concise public-safe summary.

## Public-safety rules

Do not commit or paste secrets, API keys, WordPress admin passwords, Basic Auth credentials, private token URLs, private screenshots, traces, videos, `.env.tests`, private environment files, private VM output, or sensitive logs.

The Phase 2 runner captures command output in a private run directory and never tails logs automatically. If a step fails, it prints only the failed step, the private local log path, and one diagnostic command for local use.

## Recommended VM layout

Run Phase 2 from a user-owned checkout, for example:

```bash
~/src/WordPress-NMKR-Connect
```

Do not run dependency installation from the webserver-owned deployed plugin directory. The runner may need to run `npm ci`; if dependencies are needed and the checkout is not writable, it fails with guidance to use a user-owned checkout.

## Private env file setup

Create a private env file that is ignored by Git. The default local filename is `.env.tests`, or you may point to another private file with `NMKR_PHASE2_ENV_FILE`.

Use placeholders like this and replace values only on your VM:

```bash
WP_BASE_URL=https://example.test
WP_ADMIN_USER=wordpress-admin-user
WP_ADMIN_PASSWORD=wordpress-admin-password
WP_PATH=/path/to/wordpress

NMKR_PHASE2_SKIP_DEPLOY=false
NMKR_DEPLOY_COMMAND='your-private-deploy-command-here'
NMKR_PHASE2_INSTALL_DEPS=auto
NMKR_PHASE2_INSTALL_BROWSER=false
```

Never commit the populated file.

## Required variables

After loading the private env file, these variables must be non-empty:

- `WP_BASE_URL`
- `WP_ADMIN_USER`
- `WP_ADMIN_PASSWORD`
- `WP_PATH`

## Optional variables

The runner supplies safe defaults when these are unset:

- `RUN_REAL_SYNC=false`
- `PW_SAVE_ARTIFACTS=false`
- `WP_CLI_BIN=wp`
- `NMKR_PLUGIN_SLUG=nmkr-connect/nmkr-connect.php`
- `NMKR_DEBUG_LOG_RELATIVE_PATH=wp-content/debug.log`
- `NMKR_DEBUG_LOG_LOOKBACK_MINUTES=30`
- `NMKR_PHASE2_INSTALL_DEPS=auto`
- `NMKR_PHASE2_INSTALL_BROWSER=false`
- `NMKR_PHASE2_LOG_DIR=$REPO_ROOT/.phase2-private`

`NMKR_PHASE2_INSTALL_DEPS` accepts `auto`, `true`, or `false`. `auto` runs `npm ci` only when `node_modules` is missing.

`NMKR_PHASE2_INSTALL_BROWSER=true` runs `npx playwright install chromium`; otherwise browser installation is skipped.

## Running Phase 2

From the user-owned checkout:

```bash
NMKR_PHASE2_ENV_FILE=/path/to/private-phase2.env npm run test:phase2
```

The npm script runs:

```bash
bash scripts/nmkr-phase2-test-runner.sh
```

The runner performs these validation stages in order:

1. Deployment, unless `NMKR_PHASE2_SKIP_DEPLOY=true`.
2. Playwright admin smoke checks.
3. WP-CLI smoke checks.
4. WP-CLI database-state validation via `scripts/nmkr-wpcli-db-state.sh`.

## Deployment wiring

When `NMKR_PHASE2_SKIP_DEPLOY` is not `true`, the runner requires `NMKR_DEPLOY_COMMAND` and runs it with:

```bash
bash -lc "$NMKR_DEPLOY_COMMAND"
```

The command value is never printed. Deploy output is written only to the private deploy log.

To skip deployment:

```bash
NMKR_PHASE2_SKIP_DEPLOY=true npm run test:phase2
```

## Private logs and reports

By default, private logs and reports are stored under:

```text
.phase2-private/runs/<YYYYmmddTHHMMSSZ>-<pid>/
```

The runner sets Playwright paths inside the private run directory:

- `PLAYWRIGHT_HTML_REPORT=<run-dir>/playwright-report`
- `PLAYWRIGHT_TEST_OUTPUT_DIR=<run-dir>/test-results`

## Expected success summary shape

A successful run prints a concise summary like:

```text
Phase 2 summary
  deploy: PASS
  dependencies: SKIPPED
  browser: SKIPPED
  playwright: PASS
  wpcli: PASS
  db-state: PASS
  result: PASS
  commit: abc1234
  private run dir: /path/to/checkout/.phase2-private/runs/20260708T120000Z-12345
```

## Failure handling

On failure, the runner exits non-zero and prints the failed step, the private log path, and one local diagnostic command, for example:

```text
  failed step: playwright
  private log: /path/to/private/playwright.log
  next diagnostic command: less /path/to/private/playwright.log
```

Review the private log locally. Do not paste sensitive log contents into public issues or pull requests.

## Real NMKR sync note

Real NMKR sync remains disabled by default with `RUN_REAL_SYNC=false`. The current Playwright smoke test does not implement real NMKR sync even if `RUN_REAL_SYNC=true`.

## Do not commit private artifacts

Do not commit `.env.tests`, private env files, `.phase2-private/`, Playwright reports, traces, screenshots, videos, logs, or VM output.
