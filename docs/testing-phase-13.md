# Phase 13 sync final-state UI regression coverage

Phase 13 adds focused, non-mutating Playwright coverage for NMKR Connect dashboard synchronization final states that are distinct from the previously covered active-progress, successful-completion, and HTTP-failure paths. The regression lives in `tests/e2e/nmkr-sync-final-state.regression.spec.ts`.

## Purpose

The purpose of this phase is to verify that the browser UI safely returns to an idle state when the server declares that sync polling has reached a final state other than successful completion.

## What it covers

- Server-declared stopped/aborted final state, using a local `nmkr_sync_progress` sequence that first reports active progress and then reports `aborted: true` with `finished: false`.
- Payload-level sync failure over HTTP 200, using a local `nmkr_sync_progress` response that includes an application-level error message instead of relying on an HTTP failure status.
- Continued polling when `in_progress: false` appears without an explicit final marker such as `aborted: true`, a non-empty `error`, `finished: true`, or 100% progress.
- Restoration of the Start control and hiding/inactivation of the Stop control after each final state.
- Polling shutdown after the final state so the browser does not continue indefinitely.
- Shared admin-AJAX harness enforcement that no mutating sync AJAX action reaches WordPress.

## What it intentionally does not cover

- Real NMKR synchronization.
- Real Start/Stop endpoint mutation.
- Dashboard structure checks already covered by the dashboard regression.
- The Phase 7 37% → 100% happy completion path.
- The Phase 9 HTTP 503 retry path.
- The Phase 9 HTTP 403 fatal progress failure path.
- WP-CLI database-state invariants.

## Public-safety guarantees

The tests install the shared Playwright admin-AJAX harness before admin navigation. Read-only sync AJAX actions are fulfilled locally, and mutating sync actions such as `nmkr_start_sync`, `nmkr_stop_sync`, forced stop/restart/cleanup actions, active metric storage, and log clearing are blocked by the harness if attempted. The tests do not click Start or Stop, do not run real NMKR sync, and do not write options, transients, database rows, logs, API keys, or sync state.

The test assertions only inspect public-safe dashboard UI state. They must not print or expose secrets, API keys, admin passwords, private URLs, nonce values, request bodies, cookies, logs, screenshots, traces, or videos.

## Commands

```bash
npm run test:e2e:sync-final-state -- --list
npm run test:e2e:sync-final-state
npm run test:public
npm run test:e2e
npm run test:phase2
```
