# Phase 17 isolated synthetic synchronization harness

## Purpose and boundary

Phase 17 implements test tooling only for a later, separately authorized validation. It accepts an independently prepared disposable WordPress root and separate disposable database on the existing VM. It neither provisions WordPress nor creates users or databases. The database must use the local MariaDB Unix socket; WordPress must be non-production, use `DISABLE_WP_CRON`, and be served only by the harness at a configured `127.0.0.1` high port.

The controller refuses by default and in CI. It uses an ephemeral `unshare` user/network namespace, raises loopback, proves that loopback is the only interface, proves there is no IPv4 or IPv6 default route, and checks that a reserved `.invalid` request cannot leave. It never falls back to host networking and makes no global firewall, Apache, MariaDB, or host-network changes.

The operator must also provide `NMKR_SYNTHETIC_DEPLOYED_PLUGIN_PATH`, `NMKR_SYNTHETIC_PLUGIN_SLUG`, and exactly one `NMKR_SYNTHETIC_RUN_MODE=cold|warm`. Source and deployed worktrees must be at `NMKR_SYNTHETIC_EXPECTED_SHA`, have no tracked or untracked changes, and contain only the narrowly integrity-validated ignored Composer dependency tree. The plugin slug must resolve to the active main file inside that deployed worktree. The controller refuses a stale, dirty, unrelated, or inactive deployment.

## Lifecycle and intended mutations

After installing the temporary MU provider into the disposable installation, the private driver obtains an authenticated dashboard session and in-memory nonce, sends exactly one real `nmkr_start_sync` AJAX request, stores the admitted `run_id` in an owner-private receipt, and proves a duplicate Start is refused. It uses the exact repository-owned WP-CLI `eval-file` helper to verify that the sole scheduled background event has that exact argument and is due before running the hook with WP-CLI in a separate process. The loopback server and read-only event-identity inspection keep the installed provider inert; only the background worker receives its activation environment. While the temporary MU provider is installed, the server process removes only WordPress's three `admin_init` core-update callbacks so unrelated WordPress.org checks cannot contaminate the isolated campaign; the worker does not receive this suppression. Identity-inspection diagnostics are retained in a classified owner-private file alongside the existing worker and server diagnostics. `DISABLE_WP_CRON` means HTTP loopback spawning is not relied upon. Polling is non-overlapping at approximately three seconds and requires monotonic progress and explicit canonical completion. Exact-run history and metrics, the unchanged fingerprint of earlier terminal history, complete canonical cleanup, and the bounded WordPress debug-log delta are checked before PASS. This exercises owner admission, worker claim, pagination, deduplication, persistence, HTTP/sync metrics, history, terminalization, and cleanup. It never calls `nmkr_sync_data()` directly.

Expected mutations are limited to synthetic project/token/detail rows, one history and metrics row per run, and the production lifecycle's temporary options, transients, owner, and exact cron event. Unexpected pre-existing activity, ambiguous Start, provider/state error, PHP diagnostic, timeout, ceiling breach, or cleanup residue is a stop condition; the harness does not repair or force-delete unexpected state.


> **Later evidence (2026-08-26):** The statements below describe the earlier execution state. The separately authorized campaign later ran and is registered as [EVD-011](milestone-3/executed-evidence-2026-08-26.md).

## Fixed profiles

`public-v1` is the public deterministic regression: three projects (Cardano, Solana, dual-chain), 12 unique tokens, three same-project duplicate appearances, nine list requests, 12 details, and 22 total synthetic API requests.

`private-2400-v1` is definition-only in this PR: 24 projects, 2,400 unique tokens, 3,600 list appearances including 1,200 same-project duplicates, 96 list requests, 2,400 details, and 2,497 requests per run. A future sequential cold/warm campaign totals 4,994 API requests and 4,800 token-processing operations over the same 2,400 unique tokens; 4,800 is not a unique-token count. Cold expectations are +24 projects, +2,400 tokens/details, and one history/metrics row. Warm expectations are stable business rows and one additional history/metrics row. Both require zero errors, retries, violations, or residue, with 20 minutes per run and 45 minutes combined as configurable bounded conditions, not universal guarantees.

## Cleanup and evidence

On clean terminal completion the controller removes only its copied MU provider and stops its local server. Failure preserves WordPress evidence for private diagnosis while stopping controller-owned processes; no raw rows, identifiers, credentials, URLs, paths, cookies, nonces, logs, or payloads are public output.

Only public deterministic regressions were executed for this PR. No private synthetic synchronization or real synchronization was executed, no live NMKR request was made, no NMKR Studio project or token was created, and the 2,400-token cold/warm campaign remains unexecuted. M3-05 and M3-11 remain **Implemented, validation pending**. Future validation requires separately authorized exact-head VM execution; executed evidence belongs in a later documentation PR. Do not claim validation from this harness implementation alone. **Later evidence:** the separately authorized campaign subsequently ran on 2026-08-26 and is registered as [EVD-011](milestone-3/executed-evidence-2026-08-26.md); the preceding statements remain the historical PR #77 implementation state.
