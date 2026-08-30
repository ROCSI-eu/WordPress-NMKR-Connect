# Milestone 4 executed evidence — M4-03 — 2026-08-30

## Purpose and scope

This public-safe record documents completion of the scoped M4-03 progress-recovery authorization remediation and its sanitized validation outcomes. It records evidence for PR #88 without publishing private operational inputs, raw diagnostics, or artifact locations. The evidence is limited to this implementation, its public tests and CI, and the disclosed private validation profile.

## Implementation summary

PR #88 preserved ordinary `nmkr_sync_progress` reads for callers with `nmkr_view_dashboard` while requiring `nmkr_manage_sync` for recovery mutations. Crafted `recovery` or `check_stalled` inputs from a view-only caller do not authorize lifecycle mutation.

Nonterminal history failure uses prepared atomic conditional SQL, so existing terminal history and concurrent terminalization are preserved. Cleanup requires verification of the exact history transition. Database or authoritative-read ambiguity preserves recoverable state and fails closed. An exact previously committed recovery failure is recognized safely and idempotently on retry.

## Exact public identifiers

- Implementation PR: #88
- PR base: `a3a4fdad90df76fbcef66c929ac4f03a13c5a042`
- Final validated PR head: `eb1a9d537cae76a220594e0d480fd7eb05e4abe9`
- Merged main: `c69e4ff109948f871201ee396853cbb9f1716a45`
- Validated PR-head and merged-main tree: `8c54e0d4f75e3f0250c8e8a278bed621e18f74b5`
- Successful Phase 4 CI run: `33258466402`
- Evidence date: `2026-08-30`

## Public tests and CI

The focused public checks passed:

- PHP syntax checks for the affected PHP files;
- `php scripts/nmkr-ajax-guard-regression.php`;
- `bash scripts/nmkr-sync-terminalization-regression.sh`;
- `bash scripts/nmkr-sync-owner-regression.sh`;
- `git diff --check`; and
- Phase 4 CI run `33258466402`.

The capability and state-delta regression harness is synthetic and public-safe. These results do not imply that every check used a browser session or real HTTP request.

## Private exact-head pre-merge validation

Exact head `eb1a9d537cae76a220594e0d480fd7eb05e4abe9` was deployed to the approved private DEV environment. Source and deployed worktrees were clean; WordPress and the plugin were healthy; synchronization was idle before and after validation; and no active recovery, finalization, history, or cron residue remained.

Real WordPress/MariaDB transactional checks passed for the atomic nonterminal-to-failed transition, committed-retry idempotence, terminal-history immutability, and transaction rollback. No new severe log entries were detected. No real NMKR synchronization was run.

Private validation did not use a real restricted-role browser session. The view-only authority boundary was exercised by the synthetic public-safe regression harness.

## Merge and post-merge validation

Expected-head protection merged the exact validated head. Merged main is `c69e4ff109948f871201ee396853cbb9f1716a45`, and its tree equals the validated PR-head tree `8c54e0d4f75e3f0250c8e8a278bed621e18f74b5`.

Merged main was deployed to private DEV. The affected AJAX guard regression passed, as did PHP, WordPress, and plugin readiness. Final synchronization, history, recovery, and cron state was clean, and no new severe log entries were detected. No real NMKR synchronization was run.

## Result

M4-03 resolved the scoped S-02 recovery-authority finding at the exact validated PR head and equivalent merged-main tree. Ordinary progress observation remains available under its view capability, while recovery lifecycle mutations require synchronization-management authority and the affected history transition fails closed.

Milestone 4 remains in progress, with M4-04 as the next planned technical package.

## Limitations and explicit non-claims

This record does not claim completion of Milestone 4, the full security audit, documentation delivery, Catalyst delivery or approval, penetration testing, formal certification, or production security assurance. It does not establish universal authorization or side-effect-absence coverage for every endpoint, capability, or future interface.

No browser validation is claimed where none was performed. No real NMKR synchronization or live NMKR traffic was used. No private evidence manifest or hash is claimed.

## Public/private evidence boundary

Public evidence consists of sanitized source behavior, synthetic regression conclusions, public CI identifiers, exact Git provenance, and the conclusions above. Private environment details, domains, paths, credentials, authentication state, customer or synchronization data, raw logs, database output, screenshots, traces, videos, reports, and artifact locations remain excluded from this repository.
