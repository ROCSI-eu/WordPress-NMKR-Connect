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

---

# Phase 13B read-only WP-CLI sync-state invariants

## Purpose

Phase 13B expands the existing WP-CLI database-state validation with read-only detection for orphaned or stale synchronization state that could leave NMKR Connect stuck between runs. The checks are intentionally aggregate-only and run in `scripts/nmkr-wpcli-db-state.sh` without starting, stopping, cleaning, or recovering a sync.

## Covered invariants

- Unknown, `NULL`, or empty `nmkr_sync_stats.status` values.
- Terminal `failed`, `error`, `stopped`, or `cancelled` sync rows missing `end_time`.
- Active sync statuses that also carry an `end_time`.
- Active sync rows that remain open beyond the configured stale threshold.
- More than one open active sync row.
- Active option, transient, or progress markers when no active sync row exists.
- Expired transient rows are treated as absent and are not deleted.

## Explicit non-goals

Phase 13B does not:

- Start a real sync.
- Stop or clean a sync.
- Invoke recovery.
- Parse `nmkr_sync_data`.
- Validate heartbeat-only residue.
- Inspect cron state.
- Integrate with an external object cache.
- Mutate database or WordPress state.
- Print row contents or option values.

## Threshold

`NMKR_DB_STATE_STALE_SYNC_MINUTES` controls the stale active-row threshold. The default is `180` minutes.

The value must be a positive integer. The cutoff is calculated through WP-CLI using the same WordPress site-local clock model used by plugin sync timestamps, instead of comparing directly against database `NOW()` or `UTC_TIMESTAMP()`.

## Active-sync configuration

`NMKR_DB_STATE_ALLOW_ACTIVE_SYNC` controls whether a fresh and structurally coherent active row is allowed after all invariant checks pass:

- `false`: any otherwise valid active row fails.
- `true`: one fresh and coherent active row may pass.
- Stale, duplicated, malformed, terminally inconsistent, or orphaned state still fails.

A fresh active row does not require every transient to exist, and transient absence alone is never treated as failure evidence.

## Persistent object-cache limitation

The DB-state script intentionally inspects database-backed transient rows directly in `wp_options` to avoid mutating reads. Persistent object-cache deployments can store transient values outside `wp_options`, so database inspection can provide positive evidence for database-backed transient markers but cannot prove global transient absence.

## Public safety

Phase 13B output is aggregate-only and does not print:

- API keys.
- Option values.
- Serialized data.
- Row IDs.
- Individual statuses from offending rows.
- Error messages.
- Logs.
- Private environment values.

## Commands

```bash
bash -n scripts/nmkr-wpcli-db-state.sh
bash -n scripts/nmkr-wpcli-smoke.sh
npm run test:public
npm run test:wpcli:db-state
npm run test:phase2
nmkr-dev-validate all
```

The last three commands are environment-dependent private VM validation commands and are not run by public CI without a configured WordPress environment.
