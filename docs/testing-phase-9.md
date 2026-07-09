# Phase 9 sync AJAX/network resilience simulation

Phase 9 adds Playwright coverage for the NMKR Connect dashboard sync polling UI under controlled AJAX/network failures. The regression exercises the browser-side polling behavior without starting a real NMKR synchronization job and without allowing mutating dashboard or sync AJAX actions to reach WordPress.

## Purpose

The test validates that `window.NMKRProgress.startPolling()` can handle both retriable and hard `nmkr_sync_progress` failures safely:

- Soft, transient polling failures keep the active sync UI in a non-fatal retry state and recover when polling succeeds again.
- Hard security/capability failures stop polling, show an error state, hide Stop, and restore Start.
- Every expected read-only AJAX response is served by the shared Playwright route helper.
- Mutating or unsafe AJAX actions are blocked locally and recorded as test failures.

## Non-mutating model

The spec installs the shared `tests/e2e/helpers/admin-ajax.ts` `wp-admin/admin-ajax.php` route before any WordPress admin navigation. The route fulfills known read-only requests with public-safe test JSON and blocks unsafe actions with harmless local JSON. The test triggers polling only through `window.NMKRProgress.startPolling()` and never clicks Start or Stop.

## Stubbed read actions

These expected non-mutating/read AJAX actions are fulfilled locally:

- `nmkr_check_api_status`
- `nmkr_sync_progress`
- `nmkr_get_sync_statistics`

## Blocked unsafe actions

These mutating or unsafe AJAX actions are blocked and recorded if attempted:

- `nmkr_start_sync`
- `nmkr_stop_sync`
- `nmkr_force_stop_sync`
- `nmkr_restart_sync_batch`
- `nmkr_cleanup_sync_jobs`
- `nmkr_store_active_metrics`
- `nmkr_clear_all_logs`
- `nmkr_clear_section_logs`

Any blocked action causes the test to fail at the end while still receiving only a harmless local JSON response.

## Soft failure expectations

The soft-failure test makes the first `nmkr_sync_progress` response a transient HTTP 503, then returns a successful in-progress response. It expects Stop to stay visible and enabled, Start to remain hidden as the primary active action, at least one cache-busted progress request with the `_` parameter, and later recovery of the progress UI.

## Hard failure expectations

The hard-failure test returns a public-safe HTTP 403 security error for `nmkr_sync_progress`. It expects the UI to enter an error state, Stop to be hidden or inactive, Start to become visible and enabled, and polling not to continue indefinitely after the fatal failure.

## Commands

List the targeted Phase 9 spec:

```bash
npm run test:e2e:sync-resilience -- --list
```

Run the targeted Phase 9 spec in a private WordPress VM test environment:

```bash
npm run test:e2e:sync-resilience
```

Run public-safe checks:

```bash
npm run test:public
```

Run Phase 2 validation compatibility checks:

```bash
npm run test:phase2
```

## Public-safety guarantees

Phase 9 is designed to avoid public artifacts and sensitive output:

- No secrets, API keys, admin passwords, private URLs, nonce values, or full request bodies are printed.
- No screenshots, traces, videos, logs, or private artifacts are enabled or committed by default.
- No real NMKR sync is started.
- No settings, options, transients, database rows, logs, or plugin state are written by the test route.

## Explicit non-goals

Phase 9 intentionally does not cover:

- Real NMKR synchronization.
- Start or Stop button clicks.
- Settings persistence.
- Option, transient, or database writes.
- Public test artifacts such as screenshots, traces, videos, or debug logs.
