# Phase 16B.2 public-safe checks

Phase 16B.2 direct synchronization controls are run-scoped. Start returns a UUIDv4
`run_id`; Stop must include that same value. A queued run is cancelled only when the
exact queued owner still has no history ID. A running run records `stop_requested`
and remains active until the worker reaches a safe checkpoint and writes its canonical
`stopped` history terminal state.

Run `./scripts/nmkr-sync-owner-regression.sh` and
`./scripts/nmkr-sync-terminalization-regression.sh` before a public build. These
synthetic checks do not contact the NMKR API. A deployment must completely uninstall
and reinstall the plugin database: `nmkr_sync_stats.run_id` is a fresh-install schema
change and this phase intentionally supplies no in-place migration.

## Progress and Stop authority

Progress exposes the active direct owner as `activeRunId`. The browser enables Stop
only after attaching that exact ID from a non-mismatched active-owner response. An
owner mismatch or unexpected owner change clears authority; a later clean response is
required to attach again. An active owner suppresses terminal fields so predecessor
terminal data cannot stop a successor's polling. Queued cancellation is terminal only
when its exact Stop response confirms `cancelled`.

The PHP regressions cover synthetic owner and finalization state; Playwright covers
mocked browser authority and polling. Cooperative API throttle/cooldown coverage is
reserved for Commit C.
