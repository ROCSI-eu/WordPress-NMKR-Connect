# Phase 2 VM test runner

Phase 2 adds a VM-local orchestration runner around the existing Phase 1 automated checks. It is intended for maintainers who validate a deployed WordPress NMKR Connect installation from a private, user-owned checkout.

The runner does not change plugin runtime behavior. It coordinates deployment, dependency and browser setup, a WordPress readiness gate, Playwright admin smoke checks, WP-CLI smoke checks, and WP-CLI database-state validation, then writes detailed output to private local files while printing only a concise public-safe summary.

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

Recommended Linux/private-VM layout: create `$HOME/.config/nmkr-connect` with owner-only permissions, store `phase2.env` there owned by the invoking user with mode `600`, and make the ignored checkout `.env.tests` a symlink to it. Verify the link resolves to the intended external file. No populated env file belongs in Git or in the deployed plugin directory. Systems without safe symlink support should use `NMKR_PHASE2_ENV_FILE`; it remains fully supported.

Use placeholders like this and replace values only on your VM:

```bash
WP_BASE_URL=https://example.test
WP_ADMIN_USER=wordpress-admin-user
WP_ADMIN_PASSWORD=wordpress-admin-password
WP_PATH=/path/to/wordpress

NMKR_PHASE2_SKIP_DEPLOY=true
NMKR_PHASE2_INSTALL_DEPS=false
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
- `NMKR_PHASE2_WP_READY_TIMEOUT_SECONDS=120`
- `NMKR_PHASE2_WP_READY_INTERVAL_SECONDS=5`
- `NMKR_PHASE2_WP_READY_HTTP_TIMEOUT_SECONDS=10`
- `NMKR_PHASE2_LOG_DIR=$REPO_ROOT/.phase2-private`

`NMKR_PHASE2_INSTALL_DEPS` accepts `auto`, `true`, or `false`. `auto` runs `npm ci` only when `node_modules` is missing.

`NMKR_PHASE2_INSTALL_BROWSER=true` runs `npx playwright install chromium`; otherwise browser installation is skipped.

The WordPress readiness timeout, retry interval, and per-request HTTP timeout can be tuned with the `NMKR_PHASE2_WP_READY_*` variables. They must be positive integers.

## Running Phase 2

From the user-owned checkout:

```bash
NMKR_PHASE2_ENV_FILE=/path/to/private-phase2.env npm run test:phase2
```

The npm script runs:

```bash
bash scripts/nmkr-phase2-test-runner.sh
```

### Existing deployed commit (readonly)

`existing-readonly` is for validating an already deployed exact commit. The caller-selected profile is captured before the private env file is sourced; a conflicting file profile is rejected. After sourcing, readonly authority overrides mutable values: deployment, dependency/browser installation, real synchronization, and browser artifacts are disabled, and any deploy command is ignored. A full SHA is deliberately required from the maintainer and is not derived by the command.

```bash
NMKR_PHASE2_ENV_FILE="$HOME/.config/nmkr-connect/phase2.env" \
NMKR_PHASE2_EXPECTED_SOURCE_SHA=<40-character-commit-sha> \
npm run test:phase2:existing-readonly
```

Use the same placeholder form for exact-main, pre-merge, and post-merge checks after supplying the reviewed 40-character SHA. Stop before live validation if the profile, booleans, source/deployed worktree integrity, Node dependencies, or Chromium prerequisite fails.

The runner performs these validation stages in order:

1. Deployment, unless `NMKR_PHASE2_SKIP_DEPLOY=true`.
2. Dependency installation, when enabled or needed.
3. Browser installation, when enabled.
4. WordPress readiness gate.
5. Playwright admin smoke checks.
6. WP-CLI smoke checks.
7. WP-CLI database-state validation via `scripts/nmkr-wpcli-db-state.sh`.

## WordPress readiness gate

Before Playwright starts, the runner checks that WordPress is ready to serve the admin login page. The `wordpress-ready` gate runs after deployment, dependency setup, and optional browser installation, and before the Playwright suite.

The readiness gate detects temporary WordPress maintenance mode by checking for `WP_PATH/.maintenance` and known maintenance-page text from `wp-login.php`. It also verifies that `wp-login.php` returns HTTP 200 and contains the login form marker. If WordPress is still in maintenance mode or not ready, the runner waits and retries until the configured timeout, then fails the `wordpress-ready` step instead of reporting a misleading Playwright regression.

The gate writes detailed diagnostics to `wordpress-ready.log` in the private run directory. Public console output remains concise and safe: it does not print private URLs, response bodies, cookies, nonces, secrets, screenshots, traces, videos, or private logs.

The readiness gate only detects and waits for `.maintenance`; it does not remove `.maintenance`. It also does not retry the full Playwright suite. Bad credentials and real UI regressions still fail in Playwright.

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
- `NMKR_AUTH_STATE_PATH=<run-dir>/auth-state.json`

Authentication state is sensitive session material. It is mode-restricted where supported, is not printed, reported, committed, or uploaded, and is removed after each run (including ordinary interruption). Direct Playwright runs use an ignored temporary state path and the teardown project removes it. `NMKR_RETAIN_AUTH_STATE=true` is private-diagnostics-only and should be avoided.

## Expected success summary shape

A successful run prints a concise summary like:

```text
Phase 2 summary
  deploy: PASS
  dependencies: SKIPPED
  browser: SKIPPED
  wordpress-ready: PASS
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
