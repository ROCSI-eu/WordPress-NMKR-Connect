# Milestone 4 executed evidence — M4-02 — 2026-08-29

## Purpose

This public-safe record documents completion of the scoped M4-02 dashboard mutation-authority remediation and its sanitized validation outcomes. It records evidence for PR #86 without publishing private operational inputs, raw diagnostics, or artifact locations.

## Change and provenance

PR #86 restricted the active-metrics and UI-log mutation endpoint to `nmkr_manage_sync`, aligned the affected browser caller and mutation-control visibility, and preserved intended dashboard observation access.

- PR head: `dcb60de4d86536cef715bff03378a31f1994daf8`
- Merged main: `a46e78c975647dd9a3398b45c90d602e11249585`
- Validated tree: `df84512bfe25d9632e58a399e5c737edb32d14d2`

Exact PR-head private pre-merge validation and exact merged-main post-merge validation passed. The deployed merged tree was identical to the validated PR-head tree, and the source and deployed worktrees were clean at the exact tested commits.

## Security behavior established

- The active-metrics and UI-log mutation endpoint requires `nmkr_manage_sync`.
- Intended dashboard observation access remains available without granting synchronization-management authority.
- View-only callers do not receive the affected mutation controls or issue the affected browser mutation requests.
- Restricted-role requests with a valid nonce but without `nmkr_manage_sync` were denied with no relevant state delta.
- The authorized manager path remained available and bounded.

## Public CI evidence

Merged-main Phase 4 CI run `33251143441` (run number 334) completed with result **success**.

## Sanitized private validation summary

Validation used an approved private development environment. The targeted AJAX-security suite passed its preflight, negative-state, authorized, and final-state stages. The complete non-mutating Playwright suite passed all 20 tests. WP-CLI smoke, database-state, synchronization-state, idle-state, PHP, WordPress/plugin-readiness, and service-readiness checks passed. Sanitized debug-log inspection found no fatal errors.

No real NMKR synchronization was performed, and no live NMKR traffic was used. Unrelated reviewer-facing and other environments were not modified.

Private operational inputs and artifacts are deliberately excluded, including environment details, authentication state, logs, database output, screenshots, traces, reports, and artifact locations.

## Cleanup and final state

Temporary synthetic test users and private test artifacts were removed. Final checks confirmed the relevant state, synchronization, and idle invariants and clean exact-commit source and deployed worktrees.

## Limitations and non-claims

Validation of M4-02 is not completion of the entire Milestone 4 audit or delivery. M4-03 remains the next authorization remediation package and must settle progress-polling and recovery authority semantics separately.

This result is not a universal security guarantee and does not establish authorization or no-side-effect behavior for every endpoint. It makes no claim of production validation, real synchronization behavior, live NMKR integration, or completion of future Milestone 4 packages.
