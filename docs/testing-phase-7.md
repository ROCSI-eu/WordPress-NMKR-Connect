# Phase 7 non-mutating sync-state regression coverage

Phase 7 adds a targeted Playwright regression for the NMKR Connect dashboard synchronization state UI. The test is implemented in `tests/e2e/nmkr-sync-state.regression.spec.ts` and exercises the client-side dashboard behavior with local Playwright AJAX stubs instead of real synchronization.

## Purpose

The regression verifies that the dashboard can render a realistic sync lifecycle without changing WordPress state. It covers the deeper UI state that is not exercised by the structural dashboard regression:

- Active sync progress.
- Live active metrics updates.
- Completion handling.
- Past/completed synchronization statistics refresh.

## What the test simulates

The test logs in with the shared WordPress admin helpers, opens `NMKR_DASHBOARD_PATH` (defaulting to `/wp-admin/admin.php?page=nmkr-connect-dashboard`), and routes dashboard AJAX calls through the shared Playwright helper `tests/e2e/helpers/admin-ajax.ts` to local JSON responses.

Locally stubbed AJAX actions are:

- `nmkr_check_api_status`, returning a connected, non-error dashboard status.
- `nmkr_sync_progress`, returning an active 37% progress response followed by a completed 100% response.
- `nmkr_get_sync_statistics`, returning fixed completed synchronization statistics for the past-statistics panel.

The test calls the dashboard's public `window.NMKRProgress.startPolling()` hook to trigger client polling. It does not click synchronization controls.

## Safety guarantees

The Phase 7 regression is intentionally non-mutating:

- It does not click **Start Synchronization**.
- It does not click **Stop Synchronization**.
- It does not allow real `nmkr_start_sync` requests to reach WordPress.
- It does not allow real `nmkr_stop_sync` requests to reach WordPress.
- It does not allow real `nmkr_store_active_metrics` requests to reach WordPress.
- It does not clear logs or allow real log-clearing requests to reach WordPress.
- It does not mutate options, transients, database rows, logs, API keys, or sync state.
- It does not assert, print, snapshot, or expose nonce values.
- It does not assert, print, snapshot, or expose API keys, credentials, private URLs, environment values, or logs.

The shared Playwright route guard in `tests/e2e/helpers/admin-ajax.ts` records blocked mutating actions and fulfills them locally with harmless JSON if they are attempted, then fails the test if any were observed. Sync/dashboard AJAX actions used by the regression are stubbed locally in Playwright and do not reach WordPress.

## Commands

Run the targeted Phase 7 regression locally with a private WordPress test environment loaded:

```bash
npm run test:e2e:sync-state
```

Run full private Phase 2 validation with the private VM environment:

```bash
npm run test:phase2
```

Run public-safe validation:

```bash
npm run test:public
```

Public CI should only list/discover the Playwright regression. It must not execute this sync-state regression against live WordPress credentials.
