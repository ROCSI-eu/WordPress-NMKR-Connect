# Phase 16A guarded controlled real-sync harness

Phase 16A adds private-only tooling for one later, explicitly operator-approved real synchronization on a development VM. Installing this harness does not start, stop, restart, clean up, or force-stop synchronization. Public validation uses only synthetic receipts, fake repositories, aggregate JSON, and injected driver transports.

## Relationship to Phase 15

Phase 15 remains the read-only preflight. Phase 16A consumes the exact version-1 `nmkr-real-sync-preflight` receipt produced by Phase 15 and does not change that schema. The receipt is treated as necessary but insufficient: after the dashboard has bootstrapped, Phase 16A reruns race-sensitive guards, captures the final pre-run aggregate state, verifies that enough receipt lifetime remains, and only then atomically renames the receipt to a consumed file in the same private filesystem. Every attempted Start consumes the receipt; interrupted, failed, or ambiguous attempts require a new Phase 15 receipt.

## Private-only architecture

The controller is `scripts/nmkr-real-sync-phase16a.sh`. It refuses CI before sourcing private environment files, requires `RUN_REAL_SYNC=true`, `PW_SAVE_ARTIFACTS=false`, `NMKR_REAL_SYNC_CONFIRM=I_UNDERSTAND_THIS_MUTATES_DEV`, and `NMKR_PHASE16A_CONFIRM=I_AUTHORIZE_EXACTLY_ONE_START`, validates private paths, acquires an owner-only directory lock, consumes the Phase 15 receipt, invokes the browser driver, records sanitized aggregate snapshots, and runs final validation. Paths in operator configuration must be absolute, owner-controlled, non-symlinked where required, and outside both the source checkout and WordPress root.

The browser-context driver is `scripts/nmkr-real-sync-phase16a-driver.mjs`. It keeps the exactly-once Start and polling state machine exportable for public synthetic tests. The private CLI path dynamically imports Playwright, launches headless Chromium, disables artifact persistence, and is intentionally outside ordinary Playwright test discovery.

The aggregate state helper is `scripts/nmkr-real-sync-phase16a-state.php`. It is WP-CLI-only, requires WordPress to be loaded, and emits exact-schema JSON with counts, booleans, bounded numeric values, timestamps needed for comparison, and a SHA-256 digest of terminal history rows. It does not print UIDs, names, addresses, metadata, option values, transient values, cron arguments, URLs, credentials, users, API payloads, or raw database rows.

## Dashboard preparation and frozen authorization window

Dashboard bootstrap occurs before final revalidation because dashboard loading can perform stale-state recovery and initialize `nmkr_last_sync_time`. During preparation, the driver routes `admin-ajax.php` requests before navigation. `nmkr_check_api_status` is fulfilled with a generic synthetic successful status so the preparatory API-status check cannot contact WordPress or NMKR and the first authorized NMKR interaction remains part of the real synchronization lifecycle.

Before final authorization the driver enters a frozen request mode. WordPress heartbeat, dashboard polling, background statistics refreshes, unrelated PHP/admin-AJAX requests, and all known mutating sync actions are blocked. Static assets may already have loaded. This keeps the final cron guard, final aggregate snapshot, receipt consumption, and explicit Start request in a narrow controlled window.

## Exactly-once Start and polling

Immediately after successful final authorization, the driver permits exactly one authenticated `nmkr_start_sync` request to the production `admin-ajax.php` handler using the real in-memory nonce and browser cookies. It increments an internal Start counter, never logs the nonce or cookies, never retries Start, and treats transport failure or timeout after dispatch as ambiguous. Ambiguous Start is not cleaned up and is not retried.

Polling is a single non-overlapping loop. It starts near a three-second interval, uses bounded request timeouts, backs off retriable transport/server failures up to about thirty seconds, treats 4xx authorization failures and malformed JSON as fatal, rejects progress decreases, and accepts only explicit terminal evidence followed by database validation. Isolated `in_progress=false` is not success. Persisted evidence is sanitized to Start count, poll count, maximum progress, nonterminal observation, live-metrics structure evidence, terminal classification, retry count, and elapsed seconds.

## Final-state assertions

Phase 16A fails unless final validation proves exactly one Start, one new completed sync-history row, one new valid metrics row, unchanged digest for pre-existing terminal history, zero active history, zero active option/transient/object-cache markers, zero stale-recovery or heartbeat markers, zero blocked sync cron events, terminal-only retained sync data if present, nondecreasing project/token/detail counts, zero duplicate/relationship/impossible-counter aggregates, matching `nmkr_last_sync_time` and newest metrics timestamp, a passing existing WP-CLI DB-state validator with active sync disallowed, healthy installed WordPress, active plugin, and clean source/deployed worktrees at the tested commit. A production response that appears completed is not sufficient if metrics or aggregate validation fails.

## Interruption and cleanup boundary

On `SIGINT`, `SIGTERM`, driver error, timeout, or ambiguous Start, Phase 16A stops local driver/polling activity, preserves the consumed receipt, releases only the controller-owned private lock, writes sanitized failure/ambiguous evidence, and performs no WordPress cleanup. Operators must inspect the private VM state using approved procedures and generate a fresh Phase 15 receipt before any later attempt.

## Public-safe regression procedure

Run:

```bash
npm run test:real-sync:phase16a:regression
```

The regression does not contact WordPress, NMKR, private hosts, or external hosts; does not launch a browser executable; and does not create screenshots, traces, videos, HTML reports, private artifacts, credentials, or live DB data. It covers default refusal, CI refusal, artifact refusal, unsafe paths, strict receipt failures, controller lock refusal, atomic consumption, Start-zero preauthorization failures, exactly-one synthetic Start, fatal and ambiguous Start behavior, polling backoff, malformed progress failure, cleanup sentinels, secret-pattern output scanning, and absence from normal Playwright discovery.

## Private validation gates and expected result

A private run should print only generic PASS/FAIL gate names, short commit identifiers where applicable, and private diagnostic paths on failure. The expected successful result is a PASS summary plus sanitized driver and aggregate evidence in the private run directory. Actual real-sync execution remains a separate operator-approved step and must not be performed by public CI or routine test commands.

## Rollback

Rollback of this code change is a normal Git revert of the Phase 16A commit or branch. No WordPress rollback is expected merely from installing the harness, because installation and public regression are non-mutating. If an operator separately authorizes a private real run, operational rollback decisions belong to that private runbook and are not automated by Phase 16A.

## Deferred hardening

Phase 16A is an external harness. Production Start idempotency, durable worker locks, scheduled-event result handling, and stricter production active-sync rejection are intentionally deferred to a later Phase 16B or Phase 16C unless separately approved.
