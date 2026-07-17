# Phase 16A guarded controlled real-sync harness

Phase 16A is a private-only harness for one later, explicitly operator-approved real synchronization on a development VM. Installing the harness and running the public regression do not start, stop, restart, clean up, force-stop, or call NMKR.

## Relationship to Phase 15

Phase 15 remains the read-only preflight. Operators must run Phase 15 successfully, record the exact receipt in that run directory, and export it explicitly, for example `export NMKR_PHASE16A_RECEIPT="/private/state/runs/<phase15-run>/real-sync-preflight.receipt.json"`. Phase 16A does not search for or automatically select the latest Phase 15 receipt. It initially validates the unchanged version-1 `nmkr-real-sync-preflight` receipt without consuming it. After browser login, dashboard bootstrap, preparatory AJAX interception, and the frozen request window, the driver calls the controller-owned final-authorization operation. That operation validates the same receipt again, verifies its fingerprint is unchanged, checks that sufficient lifetime remains, captures the pre-run aggregate snapshot, and atomically renames the receipt to a consumed file. Any failed, interrupted, ambiguous, or completed Start attempt consumes the receipt and requires a fresh Phase 15 run before another attempt.

## Private controller sequence

The controller `scripts/nmkr-real-sync-phase16a.sh` refuses CI before env sourcing, requires `RUN_REAL_SYNC=true`, `PW_SAVE_ARTIFACTS=false`, `NMKR_REAL_SYNC_CONFIRM=I_UNDERSTAND_THIS_MUTATES_DEV`, and `NMKR_PHASE16A_CONFIRM=I_AUTHORIZE_EXACTLY_ONE_START`, validates owner-controlled private paths, acquires an owner-only lock, validates source/deployed commits and clean worktrees, recomputes the normalized HTTPS origin digest across the allowed origin, base URL, WordPress home, and siteurl, checks WordPress/plugin readiness, verifies the named administrator has `nmkr_manage_sync`, and invokes the driver exactly once. It does not execute the driver for default-refusal tests.

The required order is: default/CI refusal, private path and lock validation, strict receipt validation without consumption, browser login and dashboard bootstrap, preparatory API-status interception, frozen request mode, final revalidation and pre-run snapshot, atomic receipt consumption, exactly one Start, bounded polling, post-run snapshot, final aggregate validation, DB-state validation, and source/deployment rechecks.

The supplied private-state and receipt paths must be absolute and may not contain `.` or `..`. The controller walks their existing lexical components with non-following metadata checks before canonicalization, so an intermediate or leaf symlink cannot be hidden by `realpath`. Generic ancestors above the configured private-state boundary may use conventional system ownership and permissions (for example, mode `0755`); strict current-user, owner-only control begins at the private-state directory. The `runs` directory, every Phase 15 or Phase 16A run directory, the `0600` receipt, and the controller lock remain sensitive nodes. Unsafe existing sensitive nodes are rejected and never repaired. Supported missing sensitive directories are created individually with mode `0700`, with the private immediate parent revalidated before creation and the new directory post-validated afterward.

Canonical bidirectional separation from both the source repository and WordPress installation remains mandatory. Receipt confinement remains exactly `<private-state>/runs/<one-run-directory>/real-sync-preflight.receipt.json`; its schema, mode, initial fingerprint, final fingerprint equality, consumed-destination check, and same-directory atomic consumption are unchanged.

## Browser login, dashboard bootstrap, and nonce handling

The driver `scripts/nmkr-real-sync-phase16a-driver.mjs` dynamically imports Playwright only in the private CLI path, launches headless Chromium without screenshots, traces, videos, reports, downloads, or storage-state persistence, logs in through the real WordPress login form using private environment credentials, handles the administration-email confirmation screen when present, and navigates to the NMKR dashboard. It extracts the localized sync nonce from the loaded page in memory. The nonce is never accepted from an environment variable, never written to disk, and never printed.

Before dashboard navigation, the driver installs admin-AJAX routing. During preparation it synthetically fulfils `nmkr_check_api_status` with a generic successful response so the dashboard's preparatory API-status check cannot contact WordPress/NMKR API code. It blocks mutating synchronization actions and allows only bootstrap traffic needed to render the dashboard and obtain the nonce.

## Frozen window, exactly-once Start, and polling

Before final authorization, the driver enters frozen mode. Frozen mode blocks heartbeat, statistics refreshes, dashboard polling, unrelated admin-AJAX, PHP/admin navigation, and requests capable of dispatching WP-Cron. No WordPress/PHP request is allowed between the final cron guard and the explicit Start except the Start request itself.

After final authorization succeeds, the driver permits one already-authorized `nmkr_start_sync` POST to the production authenticated `admin-ajax.php` endpoint. It uses the in-memory nonce and browser cookies, increments a Start counter, and never retries Start. A timeout or transport failure after dispatch is ambiguous and remains non-retriable. After Start, Stop, force-stop, restart, cleanup, metrics-storage, log cleanup, unrelated admin-AJAX, and any second Start are blocked. Start and polling send the localized AJAX nonce only in the production `nonce` form field. Polling uses the same nonce on every `nmkr_sync_progress` request, is non-overlapping, begins near three seconds, backs off retriable failures up to about thirty seconds, rejects 4xx/malformed responses and progress decreases, and requires explicit terminal evidence followed by final database validation. `in_progress=false` without terminal evidence is not success.

## Aggregate snapshots and final validation

The read-only WP-CLI helper `scripts/nmkr-real-sync-phase16a-state.php` emits exact-schema aggregate JSON only: required-table booleans, counts/max IDs, active/terminal history classification, duplicate/relationship/impossible-counter counts, option/transient/stale-recovery/heartbeat-worker evidence, blocked cron hook counts, light-profile validity, API-key presence from `nmkr_connect_options`, timestamp consistency, terminal-history digest, latest history classification, and snapshot epoch. It does not expose UIDs, names, addresses, option values, transient values, cron arguments, credentials, identities, payloads, or raw rows.

Post-run target integrity checks do not reapply receipt expiry, remaining-lifetime, backup freshness, unconsumed-receipt, or final-authorization fingerprint timing rules. Final validation requires one Start, exactly one new completed history row with a valid end time, exactly one new metrics row, unchanged digest for pre-existing terminal history, zero active runtime markers, zero blocked cron hooks, inspectable cron, required tables present, valid light profile, API key present, terminal-or-absent retained sync data, matching latest metrics time and `nmkr_last_sync_time`, nondecreasing project/token/detail counts, zero duplicate/relationship/impossible aggregates, the existing DB-state validator passing with active sync disallowed, WordPress/plugin readiness, and clean matching source/deployed commits. A completed frontend response is insufficient without these assertions.

## Interruption and cleanup boundary

On interrupt, driver error, timeout, or ambiguous Start, Phase 16A stops only local driver/polling activity, preserves any consumed receipt, releases only the controller-owned lock, writes sanitized failure evidence, and performs no WordPress cleanup. Operators must inspect private state through approved procedures and generate a new Phase 15 receipt before another attempt.

## Public-safe regression

Run:

```bash
npm run test:real-sync:phase16a:regression
```

The regression is synthetic and public-safe: it uses fake receipts, fake WP-CLI output, a fake driver, and injected transports. It proves the driver is invoked zero times on preauthorization refusal, exactly once on the authorized synthetic path, not before the consumed-receipt sentinel, and that consumption occurs only during final authorization. Private-path cases cover a conventional mode-`0755` generic ancestor, safe component-by-component creation, unsafe sensitive-node modes, receipt shape and confinement, and state/receipt intermediate and leaf symlink refusals. It also covers origin digest mismatches, capability failures, receipt failures, real form-builder nonce field shape, route POST-body parsing, frozen-mode blocking, second-Start blocking, nonce propagation to Start and progress without output leakage, no-terminal polling failure, no browser artifacts, and absence from normal Playwright discovery. No real run was performed in PR testing.

## Rollback and deferred hardening

Rollback is a normal Git revert of the Phase 16A commit or branch. Merely installing the harness should not require WordPress rollback because public paths are non-mutating. Optional lock device/inode/token identity hardening is a narrowly scoped follow-up; this correction retains atomic acquisition, owner-only post-validation, and controller-owned cleanup. Production Start idempotency, durable worker locks, scheduled-event result handling, and stricter production active-sync rejection remain deferred to Phase 16B or Phase 16C.

## Canonical terminalization regression

Successful direct and batch work now converges through `nmkr_sync_data_complete()`. A run-owned database advisory lock spans receipt lookup or creation, history verification, marker and cron cleanup, backend metrics cleanup, and terminal sync-data persistence. The narrower InnoDB transaction atomically persists the metrics row and a small durable run-owned receipt without encompassing transient, cron, or object-cache work. A retry can validate that receipt and its referenced metrics row, then resume history and cleanup without live metrics input. Terminal sync data is written only after cleanup. The finalizer fails closed for missing or conflicting history, invalid receipts, missing metrics rows, or nontransactional metrics/options tables.

The progress endpoint is a read-only observer of live metrics. A raw 100% value remains nonterminal while sync data is active; `finished` requires the canonical completed record and inactive run marker. The dashboard follows that server predicate rather than treating 100% as success. This models the Phase 16A defect where business writes had finished while durable runtime markers remained active.

Run the public-safe synthetic check with `bash scripts/nmkr-sync-terminalization-regression.sh`. It uses in-memory fixtures only and neither starts WordPress nor contacts NMKR.

The fixture deterministically interleaves two complete finalizer contenders by capturing both active entry snapshots before allowing their lock-owned sections to run. It proves the application-level re-read and idempotent transition contract, but it does not substitute for integration validation of `GET_LOCK()`, InnoDB rollback, connection loss, or object-cache behavior on the target MySQL/MariaDB deployment.
