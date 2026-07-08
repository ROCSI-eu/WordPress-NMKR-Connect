# Phase 1 smoke testing

Phase 1 adds lightweight, public-safe smoke checks for a WordPress site where NMKR Connect has already been deployed. The checks are intended for a private development or staging VM and use environment variables instead of committed secrets.

## What Phase 1 checks

- WordPress admin login works with VM-local credentials.
- The WordPress admin area is reachable.
- NMKR Connect appears active on the Plugins page.
- The NMKR dashboard page loads without obvious WordPress fatal error text.
- The NMKR settings page loads and expected controls exist.
- The NMKR API key field is present and non-empty, without printing the key.
- WP-CLI can confirm WordPress is installed.
- WP-CLI can confirm the plugin is active.
- WP-CLI can confirm required NMKR tables exist.
- WP-CLI can confirm the API key option is non-empty without printing the key.
- WP-CLI can compare sync timestamps for consistency.
- WP-CLI can detect suspicious sync-history mutation patterns.
- WP-CLI can check for fresh fatal, parse, warning, or plugin error markers in `debug.log` without printing log contents.

## What Phase 1 does not check

- It does not deploy the plugin.
- It does not start a real NMKR sync by default.
- It does not validate NMKR API responses or data correctness end-to-end.
- It does not mutate WordPress options.
- It does not mutate database tables.
- It does not clear logs.
- It does not commit or publish Playwright screenshots, videos, traces, HTML reports, private logs, credentials, or private URLs.

`RUN_REAL_SYNC=true` is reserved for a future phase. The current browser test explicitly skips real sync behavior even if that variable is enabled.

## Secret handling

Never commit real values or private artifacts, including:

- API keys
- WordPress admin passwords
- Basic Auth credentials
- Private URLs containing tokens
- wp-admin screenshots, traces, or videos
- UpdraftPlus backup metadata
- Private server logs

Copy the example file to a private VM-local env file and fill it in there:

```bash
cp .env.tests.example .env.tests
chmod 600 .env.tests
```

The committed `.env.tests.example` intentionally contains blank placeholders only.

## Install dependencies

From the repository root on the VM:

```bash
npm install
npx playwright install chromium
```

If the Linux VM is missing browser system dependencies, install them according to Playwright's prompt or run the appropriate Playwright dependency install command for your OS.

## Configure a VM-local environment

Edit `.env.tests` on the VM. Example shape only; do not paste real values into committed files or public logs:

```bash
WP_BASE_URL=
WP_ADMIN_USER=
WP_ADMIN_PASSWORD=
WP_ADMIN_PATH=/wp-admin
NMKR_DASHBOARD_PATH=/wp-admin/admin.php?page=nmkr-connect-dashboard
NMKR_SETTINGS_PATH=/wp-admin/options-general.php?page=nmkr-connect-settings
RUN_REAL_SYNC=false
PW_SAVE_ARTIFACTS=false
WP_PATH=
WP_CLI_BIN=wp
NMKR_PLUGIN_SLUG=nmkr-connect/nmkr-connect.php
NMKR_DEBUG_LOG_RELATIVE_PATH=wp-content/debug.log
NMKR_DEBUG_LOG_LOOKBACK_MINUTES=30
```

The dashboard and settings paths are configurable because admin menu slugs can change between plugin versions or deployments.

## Run the Playwright smoke tests

Load the private env file in your shell, then run the tests:

```bash
set -a
. ./.env.tests
set +a
npm run test:e2e
```

Open the local HTML report after a run:

```bash
npm run test:e2e:report
```

The Playwright HTML report is written to `playwright-report/`, which is ignored by git.

## Artifact safety

By default, Playwright screenshots, videos, and traces are disabled because wp-admin pages may contain sensitive data. For private local debugging only, set:

```bash
PW_SAVE_ARTIFACTS=true
```

Do not commit or share generated artifacts if they contain wp-admin data, private URLs, or secrets. Generated report and artifact directories are ignored by git.

## Run the WP-CLI smoke script

Load the same VM-local env file and run:

```bash
set -a
. ./.env.tests
set +a
bash scripts/nmkr-wpcli-smoke.sh
```

You can also provide `WP_PATH` inline when the other defaults are suitable:

```bash
WP_PATH=/path/to/wordpress bash scripts/nmkr-wpcli-smoke.sh
```

The script is read-only. It queries WordPress options and database tables, but does not mutate plugin state, start a sync, clear logs, or print private log contents.

## Formatting validation

Before publishing changes to these Phase 1 files, verify that they are not collapsed into one or two long lines:

```bash
bash -n scripts/nmkr-wpcli-smoke.sh
wc -l scripts/nmkr-wpcli-smoke.sh
head -n 5 scripts/nmkr-wpcli-smoke.sh
grep -n '^set -Eeuo pipefail$' scripts/nmkr-wpcli-smoke.sh
wc -l .env.tests.example docs/testing-phase-1.md
sed -n '1,20p' .env.tests.example
sed -n '1,40p' docs/testing-phase-1.md
```

The first two lines of `scripts/nmkr-wpcli-smoke.sh` must be exactly:

```bash
#!/usr/bin/env bash
set -Eeuo pipefail
```

## Example VM run pattern

After deploying the plugin by your private VM process, run the smoke checks with private env values already stored in `.env.tests`:

```bash
set -a
. ./.env.tests
set +a
npm install
npx playwright install chromium
npm run test:e2e
bash scripts/nmkr-wpcli-smoke.sh
```

## Troubleshooting

- **Bad login:** confirm `WP_BASE_URL`, `WP_ADMIN_USER`, and `WP_ADMIN_PASSWORD` in the private env file. Do not echo passwords in logs.
- **Wrong admin page slug:** update `NMKR_DASHBOARD_PATH` or `NMKR_SETTINGS_PATH` in `.env.tests`.
- **Plugin inactive:** activate NMKR Connect in WordPress or verify `NMKR_PLUGIN_SLUG`.
- **Missing `WP_PATH`:** set `WP_PATH` to the WordPress install directory on the VM.
- **Missing WP-CLI:** install WP-CLI or set `WP_CLI_BIN` to the correct executable path.
- **Playwright browser dependency issue on Linux:** run `npx playwright install chromium` first, then follow any OS dependency instructions printed by Playwright.
- **Debug log failures:** the WP-CLI script reports only whether matching recent errors exist. Inspect the private VM log directly if more detail is needed, but do not commit or share private logs.
