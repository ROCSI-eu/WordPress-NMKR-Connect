# Phase 1 Automated Testing Proof of Concept

Phase 1 provides a small, VM-local smoke-test workflow for the NMKR Connect WordPress plugin.
It is intended to prove that Playwright and WP-CLI can quickly verify a prepared WordPress test site without mutating plugin runtime behavior.

## Purpose

Use these checks before deeper automated coverage exists.
They confirm that a known local WordPress install can load the plugin admin screens and expose the expected safe controls.
They also create a Playwright HTML report that can be shared without screenshots, traces, videos, logs, or secrets.

## What Phase 1 Checks

- WordPress admin login works with credentials supplied through environment variables.
- The WordPress admin area loads after login.
- The NMKR Connect plugin appears active in the plugin list.
- The NMKR dashboard page loads.
- The NMKR settings page loads.
- The API key field exists and is non-empty when configured, without printing the value.
- A sync profile control exists.
- WP-CLI can confirm that WordPress is installed.
- WP-CLI can confirm that the plugin is active.
- WP-CLI can confirm that required NMKR tables exist.
- WP-CLI can compare the latest sync timestamp option with metrics when metrics exist.
- WP-CLI can scan recent debug log tails for fresh PHP or plugin errors without dumping full logs.

## What Phase 1 Does Not Check

- It does not perform a real NMKR API sync by default.
- It does not require live NMKR API calls.
- It does not verify Cardano transaction data.
- It does not mutate WordPress options.
- It does not mutate database tables.
- It does not clear or rotate logs.
- It does not collect screenshots, videos, or traces by default.
- It does not replace dedicated unit, integration, or end-to-end test coverage.

## Public-Safety Notes

This repository is public and open source.
Do not commit real credentials, private URLs, API keys, backup metadata, screenshots, traces, videos, or private server logs.
Keep secrets in the VM-local `.env.tests` file only.
Use blank placeholders in examples and redact operational output before sharing it.

## Install Dependencies

From the repository root, install Node dependencies and the Chromium browser used by Phase 1:

```bash
npm install
npx playwright install chromium
```

## Configure VM-Local Environment

Copy the example file and edit the VM-local copy:

```bash
cp .env.tests.example .env.tests
$EDITOR .env.tests
```

Set the following values for your local WordPress VM only:

```bash
WP_BASE_URL=https://example.test
WP_ADMIN_USER=
WP_ADMIN_PASSWORD=
WP_PATH=/path/to/wordpress
```

Leave `RUN_REAL_SYNC=false` for Phase 1 unless you are intentionally testing real sync behavior in a private environment.
Leave `PW_SAVE_ARTIFACTS=false` unless you need local debugging artifacts.
Do not commit `.env.tests`.

## Run the Playwright Smoke Test

Run the Phase 1 browser smoke test:

```bash
npm run test:e2e
```

Open the HTML report after a run:

```bash
npm run test:e2e:report
```

The report is written to `playwright-report/` and test output is written to `test-results/`.
Both directories are ignored so local artifacts do not get committed.

## Run the WP-CLI Smoke Script

Run the read-only WP-CLI checks against the same VM-local WordPress install:

```bash
WP_PATH=/path/to/wordpress \
WP_CLI_BIN=wp \
NMKR_PLUGIN_SLUG=nmkr-connect/nmkr-connect.php \
NMKR_DEBUG_LOG_RELATIVE_PATH=wp-content/debug.log \
NMKR_DEBUG_LOG_LOOKBACK_MINUTES=30 \
bash scripts/nmkr-wpcli-smoke.sh
```

The script prints status messages only.
It does not print API keys or full logs.
It fails fast if required checks are not satisfied.

## HTML Report Usage

Use the HTML report as a quick demo artifact for Phase 1.
Before sharing any report, verify that it does not contain secrets or private operational details.
By default screenshots, videos, and traces are disabled.
If you enable artifacts locally with `PW_SAVE_ARTIFACTS=true`, do not commit or publish them.

## Troubleshooting

- If Playwright cannot log in, verify `WP_BASE_URL`, `WP_ADMIN_USER`, and `WP_ADMIN_PASSWORD` in `.env.tests`.
- If the plugin row is missing, confirm the plugin is installed and active in the VM.
- If settings controls are missing, confirm the configured admin paths match the plugin pages in that WordPress install.
- If WP-CLI cannot find WordPress, set `WP_PATH` to the WordPress document root.
- If table checks fail, confirm that the plugin has been activated and initialized in the VM.
- If debug-log checks fail, inspect the VM-local `debug.log` manually and redact details before sharing.
- If you need screenshots, traces, or videos for local debugging, set `PW_SAVE_ARTIFACTS=true` and keep artifacts private.
