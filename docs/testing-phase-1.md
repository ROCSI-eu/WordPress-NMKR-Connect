# Phase 1 automated testing proof of concept (historical)

> **Historical implementation record.** Phase 1 established the original VM-local Playwright smoke test and WP-CLI checks. Deeper Playwright coverage now exists, and [`testing-playwright.md`](testing-playwright.md) is the authoritative operational guide.

## Original purpose

Phase 1 proved that Playwright and WP-CLI could quickly verify a prepared WordPress test site without changing plugin runtime behavior. Its browser spec confirms that an administrator can authenticate, that the plugin is active, that the dashboard and settings pages load, and that basic safe controls are present. Its WP-CLI checks confirm WordPress/plugin availability, required tables, selected database-state relationships, and the absence of fresh matching errors in a bounded log tail.

At the time, this was intentionally a small smoke-test workflow. The repository now also contains focused settings, dashboard, projects, shortcodes, analytics, synchronization-state, synchronization-resilience, synchronization-final-state, and run-authority specs. Those later specs do not turn the suite into comprehensive product coverage.

## Phase 1 boundaries that still apply

- The smoke spec does not perform a real NMKR synchronization or require live NMKR API calls.
- It does not verify Cardano transaction data, mutate WordPress options or database tables, or clear/rotate logs.
- Screenshots, traces, and videos are disabled by default.
- These checks do not replace unit, integration, server-side, or broader end-to-end coverage.
- Credentials, URLs, API keys, environment files, logs, reports, and browser artifacts from a private environment must not be committed or published.

## Current setup and execution

Use the lockfile for repeatable dependency installation:

```bash
npm ci
npx playwright install chromium
```

For a private VM, copy `.env.tests.example` to the ignored `.env.tests` file and supply private values locally. Keep `RUN_REAL_SYNC=false` and `PW_SAVE_ARTIFACTS=false` for ordinary execution.

```bash
cp .env.tests.example .env.tests
$EDITOR .env.tests
```

`npm run test:e2e` no longer means only the original Phase 1 smoke spec. It runs the **current default Playwright suite**, including authentication setup/cleanup and all implemented specs:

```bash
npm run test:e2e
```

For current targeted commands, environment handling, and troubleshooting, use the [Playwright testing guide](testing-playwright.md).

## Historical WP-CLI component

The read-only WP-CLI smoke script remains available against a prepared private WordPress test environment:

```bash
WP_PATH=/path/to/wordpress \
WP_CLI_BIN=wp \
NMKR_PLUGIN_SLUG=nmkr-connect/nmkr-connect.php \
NMKR_DEBUG_LOG_RELATIVE_PATH=wp-content/debug.log \
NMKR_DEBUG_LOG_LOOKBACK_MINUTES=30 \
bash scripts/nmkr-wpcli-smoke.sh
```

Use only generic placeholders in shared material. Review any failure details locally and redact sensitive context rather than sharing raw logs.

## Reports and troubleshooting

`npm run test:e2e:report` opens an already-generated HTML report. Although screenshots, traces, and videos are off by default, an HTML report from authenticated private-environment execution can still contain private URLs, test names, errors, and operational context. Treat it as private diagnostic output; do not assume it is safe to share or publish.

Begin troubleshooting with non-mutating checks: discover tests with `npm run test:e2e -- --list --reporter=list`, verify required variable names without printing their values, confirm the intended private target, and then run the smallest relevant targeted spec. If WP-CLI fails, confirm `WP_PATH`, plugin activation, and initialization locally without dumping database contents or full logs.
