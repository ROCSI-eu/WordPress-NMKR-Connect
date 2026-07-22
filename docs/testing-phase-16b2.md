# Phase 16B.2 public-safe checks

Phase 16B.2 direct synchronization controls are run-scoped. Start returns a UUIDv4
`run_id`; Stop must include that same value. A queued run is cancelled only when the
exact queued owner still has no history ID. A running run records `stop_requested`
and remains active until the worker reaches a safe checkpoint and writes its canonical
`stopped` history terminal state.

Run `./scripts/nmkr-schema-upgrade-regression.sh`,
`./scripts/nmkr-sync-owner-regression.sh`,
`./scripts/nmkr-sync-terminalization-regression.sh`, and
`./scripts/nmkr-sync-api-throttle-regression.sh` before a public build. These
synthetic checks do not contact the NMKR API.

## In-place run_id schema upgrade

Ordinary plugin loading performs a site-scoped, one-time in-place schema upgrade for
`nmkr_sync_stats.run_id`; uninstalling, resetting, recreating, or truncating the
database is not required. Existing synchronization history is preserved exactly as it
is, and legacy rows keep nullable `run_id` values (including multiple `NULL` values).
The upgrade is idempotent: it verifies the table, nullable `char(36)` column, and
single-column unique `run_id` index before recording the separate non-autoloaded
schema version. A non-autoloaded, bounded-stale lock prevents concurrent workers.
If a column/index change or verification fails, the version is not advanced, the lock
is released, and no history is deleted or synthesized; a later request can retry.

## Progress and Stop authority

Progress exposes the active direct owner as `activeRunId`. The browser enables Stop
only after attaching that exact ID from a non-mismatched active-owner response. An
owner mismatch or unexpected owner change clears authority; a later clean response is
required to attach again. An active owner suppresses terminal fields so predecessor
terminal data cannot stop a successor's polling. Queued cancellation is terminal only
when its exact Stop response confirms `cancelled`.

## Cooperative API throttle

Direct API wrappers receive an exact run-scoped checkpoint context. Rate-limit waits
and cooldown sleeps checkpoint at one-second-or-shorter boundaries. An exact Stop,
owner mismatch, checkpoint lock failure, or checkpoint persistence failure returns to
the orchestrator unchanged before another HTTP request or business write can begin.
Legacy context-free API checks remain supported, and the synthetic regression stubs
every HTTP call so it never contacts the NMKR API.

The PHP regressions cover synthetic owner, finalization, and interruptible API throttle
state. Playwright covers mocked browser authority and polling.

## Browser run authority

A Start response supplies only a provisional run ID. Stop remains disabled until a clean
progress response confirms that exact active owner. Terminal and fatal cleanup erase that
browser authority; an unexpected owner change requires a later clean response before the
successor can attach. Recoverable transport failures retain an already trusted run.
Playwright regression tests intercept Start, Stop, and progress AJAX with synthetic data,
so they run no real NMKR synchronization.
