# Phase 3 settings-page regression coverage

Phase 3 adds the first safe Playwright regression check for the NMKR Connect settings page. The goal is to verify the admin settings page structure and important controls without mutating WordPress options or exposing private configuration.

## What the settings regression covers

The settings regression is implemented in `tests/e2e/nmkr-settings.regression.spec.ts`. It logs in with the existing WordPress admin test environment variables, opens `NMKR_SETTINGS_PATH` (defaulting to `/wp-admin/options-general.php?page=nmkr-connect-settings`), and verifies that the WordPress admin shell, NMKR settings page, settings form, sections, and key controls are present.

The test confirms broad coverage for:

- API Settings
- Synchronization Settings
- Debug Settings
- Analytics & Privacy
- Save Settings and Reset to Defaults controls

## Non-mutating safety guarantees

The Phase 3 settings regression is intentionally non-mutating:

- It does not click **Save Settings**.
- It does not click **Reset to Defaults**.
- It does not click the API key visibility toggle.
- It does not change the synchronization profile.
- It does not fill, clear, check, uncheck, or otherwise change settings controls.
- It does not run real NMKR sync.
- It does not read, print, snapshot, or assert raw API key values.
- It does not read, print, snapshot, or assert raw GA4 API secret values.

The test uses presence and basic attribute assertions for secret fields, such as checking that API key and GA4 API secret inputs exist and remain `type="password"`.

## Running Phase 3 locally

The settings regression is included in the default Playwright suite:

```bash
npm run test:e2e
```

It can also be listed or run directly with:

```bash
npm run test:e2e:settings -- --list
npm run test:e2e:settings
```

Because the Phase 2 runner executes `npm run test:e2e`, the Phase 3 settings regression is automatically included in Phase 2 VM validation.

## Artifacts and public safety

Playwright screenshots, traces, and videos remain disabled by default. They are only enabled when `PW_SAVE_ARTIFACTS=true`, and Phase 2 stores reports and test output in the private run directory.

Do not commit or publish secrets, API keys, WordPress admin passwords, Basic Auth credentials, private token URLs, private screenshots, traces, videos, `.env.tests`, private environment files, VM output, Playwright reports, or sensitive logs.
