# Milestone 4 executed evidence — M4-06 — 2026-08-31

## Purpose and scope

This public-safe record documents completion of the scoped M4-06 test, tooling, CI, and evidence package. It records the exact implementation provenance, public checks, final exact-head CI, merge equivalence, and limitations for PR #93 without publishing private operational details, raw diagnostics, exploit payloads, authentication state, or artifact locations.

M4-06 changed tests, tooling, CI behavior, and planning documentation only. It did not change plugin runtime behavior, dependency declarations, or lockfiles.

## Implementation summary

PR #93 added a consolidated public-safe security contract that inventories production AJAX registrations, distinguishes authenticated handlers from the intentional public analytics-ingestion boundary, and fails when a registration cannot be classified. Representative denial ledgers cover prohibited writer and downstream operations without claiming complete WordPress-dispatch or endpoint coverage.

The package also added focused contracts for the settings capability registration and dispatch boundary, settings sanitization, bounded hostile analytics SQL construction, and SQL placeholder/value-count consistency. Existing error-disclosure regressions remain part of the consolidated security path.

A dedicated synthetic browser check executes the production synchronization and analytics dashboard JavaScript in a local DOM. It verifies that hostile-shaped synchronization current-item text and analytics project/token identifiers remain literal text at the three exercised sinks, without creating active unexpected elements or executing marker callbacks.

The static checks cover tracked shell and JavaScript syntax, PHP 7.4 compatibility, Playwright discovery, and a narrow tracked-filename/path denylist for established generated or private artifact conventions. A separate read-only dependency job validates and audits the committed npm and Composer lockfiles. Dependency findings are time-dependent and reflect advisory data available when the CI run executed.

## Exact public identifiers

- Implementation PR: #93
- Implementation baseline: `820b2cfeae0ea26de8078a9ccf3d7fe0a5ca14bd`
- Implementation baseline tree: `26560e630f096bc3e4d6827843aacaeeb2b0a742`
- Final PR head: `c50c15770f93dd7c5d7ae2508f0d3be20fc57c0c`
- Final PR-head tree: `cc5c1f3f219069d259ab22cf217df6eca2f4c0ac`
- Merged main: `fecac597ee7260659b1772de0a8c9128aad30770`
- Merged-main tree: `cc5c1f3f219069d259ab22cf217df6eca2f4c0ac`
- Successful Phase 4 CI run: `33397493179` (run 372)
- Evidence date: `2026-08-31`

## Public checks and exact-head CI

The final exact-head Phase 4 CI run passed:

- `Public-safe checks`;
- `Synthetic security rendering`; and
- `Dependency audits`.

The exact-checkout gate passed in all three jobs. The public package exercised the consolidated security contract, existing public regressions, the synthetic final-rendering assertions, static/source checks, and lock-bound npm and Composer audits.

Successive Codex findings on PR #93 were addressed and all eight inline threads were resolved. The last full Codex inspection targeted head `3185a4034aa743fec8192b22f89a9582ff210768`. The final head `c50c15770f93dd7c5d7ae2508f0d3be20fc57c0c` added only a narrow one-line tracked-artifact pattern correction; that hunk was independently inspected and the exact final head passed CI. This record does not claim that another full Codex inspection ran on the final head.

## Merge equivalence

Expected-head protection merged exact PR head `c50c15770f93dd7c5d7ae2508f0d3be20fc57c0c` as main commit `fecac597ee7260659b1772de0a8c9128aad30770`. The final PR-head and merged-main Git trees are identical: `cc5c1f3f219069d259ab22cf217df6eca2f4c0ac`.

## Validation classification

Under the repository validation policy, this package remained public-only test/tooling plus documentation. It did not change private orchestration, deployment behavior, authentication-state handling, runtime code, persistent state, dependency locks, or plugin behavior. Public CI and focused source/document inspection were therefore sufficient; no private DEV deployment, VM execution, WordPress, WP-CLI, database, or authenticated browser validation was required.

No real NMKR synchronization, NMKR API traffic, external GA4 request, customer data, credentials, authentication state, private logs, database output, screenshots, traces, reports, or private artifacts were used.

## Result

M4-06 is complete within its disclosed public-only test, tooling, CI, and evidence scope. It adds maintainable targeted assertions for the classified registration surface, representative denial behavior, settings boundaries, analytics SQL construction, existing disclosure regressions, three exercised final-rendering sinks, tracked-source/static checks, and lock-bound dependency audits.

M4-06 narrows `S-06` but does not resolve it: unexercised callbacks, complete side-effect absence, and full WordPress dispatch remain outside the proof. It also narrows `A-01` only for the three exercised synthetic sinks; broader adversarial rendering assurance remains open. Milestone 4 remains in progress, M4-07 is the next planned package, and M4-08 remains a later milestone-wide validation package.

## Limitations and explicit non-claims

This record does not claim universal administrative authorization coverage, complete side-effect absence, universal SQL-injection resistance, universal XSS resistance, complete settings safety, complete error-disclosure assurance, dependency safety beyond the committed locks and advisory data available at run time, penetration testing, formal certification, production security assurance, Catalyst delivery or approval, or completion of Milestone 4.

The production-registration inventory and representative denial ledgers are targeted contracts, not a full WordPress request-dispatch emulator. The SQL contract covers the exercised construction path and placeholder consistency, not every future query. The synthetic browser checks cover only the three exercised sinks and fixtures. Filename/path checks are intentionally narrow and are not content-based secret scanning. Network-dependent dependency results may change without a source change.

## Public/private evidence boundary

Public evidence consists of sanitized source behavior, public-safe regression conclusions, CI outcomes, exact Git provenance, resolved thread status, and the limitations above. Private URLs, host or VM paths, credentials, nonces, API keys, customer or synchronization data, authentication state, raw logs, database output, screenshots, traces, videos, reports, manifests, and private artifact locations remain excluded from the repository.
