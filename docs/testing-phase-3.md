# Phase 3 Playwright regression coverage

Phase 3 adds safe Playwright regression checks for the NMKR Connect WordPress admin pages. These tests verify page structure and important controls without mutating WordPress options, starting synchronization, clearing logs, or exposing private configuration.

## Settings-page regression

The settings regression is implemented in `tests/e2e/nmkr-settings.regression.spec.ts`. It logs in with the existing WordPress admin test environment variables, opens `NMKR_SETTINGS_PATH` (defaulting to `/wp-admin/options-general.php?page=nmkr-connect-settings`), and verifies that the WordPress admin shell, NMKR settings page, settings form, sections, and key controls are present.

The test confirms broad coverage for:

- API Settings
- Synchronization Settings
- Debug Settings
- Analytics & Privacy
- Save Settings and Reset to Defaults controls

### Settings safety guarantees

The settings regression is intentionally non-mutating:

- It does not click **Save Settings**.
- It does not click **Reset to Defaults**.
- It does not click the API key visibility toggle.
- It does not change the synchronization profile.
- It does not fill, clear, check, uncheck, or otherwise change settings controls.
- It does not run real NMKR sync.
- It does not read, print, snapshot, or assert raw API key values.
- It does not read, print, snapshot, or assert raw GA4 API secret values.

The test uses presence and basic attribute assertions for secret fields, such as checking that API key and GA4 API secret inputs exist and remain `type="password"`.

## Dashboard-page regression

The dashboard regression is implemented in `tests/e2e/nmkr-dashboard.regression.spec.ts`. It logs in with the same shared WordPress admin helpers, opens `NMKR_DASHBOARD_PATH` (defaulting to `/wp-admin/admin.php?page=nmkr-connect-dashboard`), and verifies that the WordPress admin shell, NMKR dashboard shell, dashboard panels, status/progress structure, statistics structure, and key controls are present.

The test confirms broad coverage for:

- NMKR Connect Dashboard shell
- API Connection Status panel
- Data Synchronization panel
- Hidden nonce input presence and type
- Sync progress and active metric containers
- Previous Synchronization Statistics panel
- Optional Debug Logs panel structure when dashboard logging is enabled

### Dashboard safety guarantees

The dashboard regression stubs the dashboard page-load `nmkr_check_api_status` AJAX request before navigation so the inline dashboard script does not touch API connectivity logic, sync/recovery state, or log-writing paths in shared validation environments. It also records and fails the test on unexpected dashboard AJAX actions that would store active metrics, read sync statistics, clear logs, or start/stop sync while fulfilling those requests locally so WordPress is not touched.

The dashboard regression is intentionally non-mutating:

- It does not click **Start Synchronization**.
- It does not click **Stop Synchronization**.
- It does not click **Refresh Status**.
- It does not click **Clear Logs**.
- It does not click, fill, or change debug log search/filter controls.
- It treats the Debug Logs panel as optional because it depends on `nmkr_connect_options['log_to_dashboard']`.
- It does not require optional Debug Logs controls when dashboard logging is disabled.
- It does not read, print, snapshot, or assert nonce values.
- It does not inspect debug log entries, messages, payloads, timestamps, counts, or log text.
- It does not assert exact sync metric or performance statistic values, which may vary by environment.

The test uses attachment, role, attribute, and element type assertions for controls that may be hidden by default or may not include explicit `type="button"` attributes.

## Running Phase 3 locally

Both Phase 3 regressions are included in the default Playwright suite:

```bash
npm run test:e2e
```

They can also be listed or run directly with:

```bash
npm run test:e2e:settings -- --list
npm run test:e2e:settings
npm run test:e2e:dashboard -- --list
npm run test:e2e:dashboard
```

Because the Phase 2 runner executes `npm run test:e2e`, both Phase 3 regressions are automatically included in Phase 2 VM validation.

## Artifacts and public safety

Playwright screenshots, traces, and videos remain disabled by default. They are only enabled when `PW_SAVE_ARTIFACTS=true`, and Phase 2 stores reports and test output in the private run directory.

Do not commit or publish secrets, API keys, WordPress admin passwords, Basic Auth credentials, private token URLs, private screenshots, traces, videos, `.env.tests`, private environment files, VM output, Playwright reports, or sensitive logs.
