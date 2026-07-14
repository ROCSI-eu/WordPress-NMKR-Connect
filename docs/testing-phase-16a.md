# Phase 16A guarded controlled real-sync harness

Phase 16A is a private-only harness for one later, explicitly operator-approved real synchronization on a development VM. Installing the harness and running the public regression do not start, stop, restart, clean up, force-stop, or call NMKR.

## Relationship to Phase 15

Phase 15 remains the read-only preflight. Phase 16A initially validates the unchanged version-1 `nmkr-real-sync-preflight` receipt without consuming it. After browser login, dashboard bootstrap, preparatory AJAX interception, and the frozen request window, the driver calls the controller-owned final-authorization operation. That operation validates the same receipt again, verifies its fingerprint is unchanged, checks that sufficient lifetime remains, captures the pre-run aggregate snapshot, and atomically renames the receipt to a consumed file. Any attempted Start consumes the receipt; a failed, interrupted, or ambiguous attempt requires a fresh Phase 15 receipt.

## Private controller sequence

The controller `scripts/nmkr-real-sync-phase16a.sh` refuses CI before env sourcing, requires `RUN_REAL_SYNC=true`, `PW_SAVE_ARTIFACTS=false`, `NMKR_REAL_SYNC_CONFIRM=I_UNDERSTAND_THIS_MUTATES_DEV`, and `NMKR_PHASE16A_CONFIRM=I_AUTHORIZE_EXACTLY_ONE_START`, validates owner-controlled private paths, acquires an owner-only lock, validates source/deployed commits and clean worktrees, checks WordPress/plugin readiness, and invokes the driver exactly once. It does not execute the driver for default-refusal tests.

The required order is: default/CI refusal, private path and lock validation, strict receipt validation without consumption, browser login and dashboard bootstrap, preparatory API-status interception, frozen request mode, final revalidation and pre-run snapshot, atomic receipt consumption, exactly one Start, bounded polling, post-run snapshot, final aggregate validation, DB-state validation, and source/deployment rechecks.

## Browser login, dashboard bootstrap, and nonce handling

The driver `scripts/nmkr-real-sync-phase16a-driver.mjs` dynamically imports Playwright only in the private CLI path, launches headless Chromium without screenshots, traces, videos, reports, downloads, or storage-state persistence, logs in through the real WordPress login form using private environment credentials, handles the administration-email confirmation screen when present, and navigates to the NMKR dashboard. It extracts the localized sync nonce from the loaded page in memory. The nonce is never accepted from an environment variable, never written to disk, and never printed.

Before dashboard navigation, the driver installs admin-AJAX routing. During preparation it synthetically fulfils `nmkr_check_api_status` with a generic successful response so the dashboard's preparatory API-status check cannot contact WordPress/NMKR API code. It blocks mutating synchronization actions and allows only bootstrap traffic needed to render the dashboard and obtain the nonce.

## Frozen window, exactly-once Start, and polling

Before final authorization, the driver enters frozen mode. Frozen mode blocks heartbeat, statistics refreshes, dashboard polling, unrelated admin-AJAX, PHP/admin navigation, and requests capable of dispatching WP-Cron. No WordPress/PHP request is allowed between the final cron guard and the explicit Start except the Start request itself.

After final authorization succeeds, the driver permits one already-authorized `nmkr_start_sync` POST to the production authenticated `admin-ajax.php` endpoint. It uses the in-memory nonce and browser cookies, increments a Start counter, and never retries Start. A timeout or transport failure after dispatch is ambiguous and remains non-retriable. After Start, Stop, force-stop, restart, cleanup, metrics-storage, log cleanup, unrelated admin-AJAX, and any second Start are blocked. Polling uses the same nonce on every `nmkr_sync_progress` request, is non-overlapping, begins near three seconds, backs off retriable failures up to about thirty seconds, rejects 4xx/malformed responses and progress decreases, and requires explicit terminal evidence followed by final database validation. `in_progress=false` without terminal evidence is not success.

## Aggregate snapshots and final validation

The read-only WP-CLI helper `scripts/nmkr-real-sync-phase16a-state.php` emits exact-schema aggregate JSON only: required-table booleans, counts/max IDs, active/terminal history classification, duplicate/relationship/impossible-counter counts, option/transient/stale-recovery/heartbeat-worker evidence, blocked cron hook counts, light-profile validity, API-key presence from `nmkr_connect_options`, timestamp consistency, terminal-history digest, latest history classification, and snapshot epoch. It does not expose UIDs, names, addresses, option values, transient values, cron arguments, credentials, identities, payloads, or raw rows.

Final validation requires one Start, exactly one new completed history row with a valid end time, exactly one new metrics row, unchanged digest for pre-existing terminal history, zero active runtime markers, zero blocked cron hooks, inspectable cron, required tables present, valid light profile, API key present, terminal-or-absent retained sync data, matching latest metrics time and `nmkr_last_sync_time`, nondecreasing project/token/detail counts, zero duplicate/relationship/impossible aggregates, the existing DB-state validator passing with active sync disallowed, WordPress/plugin readiness, and clean matching source/deployed commits. A completed frontend response is insufficient without these assertions.

## Interruption and cleanup boundary

On interrupt, driver error, timeout, or ambiguous Start, Phase 16A stops only local driver/polling activity, preserves any consumed receipt, releases only the controller-owned lock, writes sanitized failure evidence, and performs no WordPress cleanup. Operators must inspect private state through approved procedures and generate a new Phase 15 receipt before another attempt.

## Public-safe regression

Run:

```bash
npm run test:real-sync:phase16a:regression
```

The regression is synthetic and public-safe: it uses fake receipts, fake WP-CLI output, a fake driver, and injected transports. It proves the driver is invoked zero times on preauthorization refusal, exactly once on the authorized synthetic path, not before the consumed-receipt sentinel, and that consumption occurs only during final authorization. It covers receipt failures, route POST-body parsing, frozen-mode blocking, second-Start blocking, nonce propagation to Start and progress without output leakage, no-terminal polling failure, no browser artifacts, and absence from normal Playwright discovery. No real run was performed in PR testing.

## Rollback and deferred hardening

Rollback is a normal Git revert of the Phase 16A commit or branch. Merely installing the harness should not require WordPress rollback because public paths are non-mutating. Production Start idempotency, durable worker locks, scheduled-event result handling, and stricter production active-sync rejection remain deferred to Phase 16B or Phase 16C.
