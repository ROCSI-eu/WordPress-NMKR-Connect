# Milestone 4 security audit plan

## Scope and objectives

This plan governs the public-safe Milestone 4 review of administrative and public request boundaries, their authorization controls, input handling, side effects, response sensitivity, output contexts, and supporting documentation. Its objectives are to preserve the M4-01 analysis, implement the smallest compatible remediations in later pull requests, establish targeted regression evidence, and document limitations without overstating assurance.

The M4-01 analysis baseline is exactly `354cd5808c8eebf557a8286452752245901cbd50`. Analysis completion is not remediation, validation, audit completion, or delivery completion.

## Selected OWASP verification baseline

For Catalyst Milestone 4 reporting, the selected verification baseline is **OWASP Application Security Verification Standard 5.0.0**. The selected requirements cover the milestone's administrative authorization, SQL-injection, XSS/final-rendering, input-validation, error-disclosure, and fail-secure obligations. The exact versioned requirement mapping, evidence IDs, bounded dispositions, and non-claims are recorded in the [Catalyst submission alignment](catalyst-submission-alignment.md).

A selected requirement is described as supported only for the exact boundaries and evidence cited there. This is not complete ASVS Level 1 or Level 2 verification, exhaustive OWASP coverage, penetration testing, formal certification, or application-wide security assurance.

## Trust rules

- Baseline-dependent analysis must identify and verify the exact baseline commit before conclusions are reused.
- Every implementation or evidence pull request must be reviewed and validated at its exact PR head. A follow-up that changes the affected security or runtime boundary invalidates earlier exact-head validation to the extent required by the [validation policy](../validation-policy.md).
- Source and, when required, deployed worktrees must satisfy the policy's identity and cleanliness rules. SHA or cleanliness mismatches are stop conditions.
- Public results identify only sanitized commands, outcomes, limitations, and reviewable evidence; private environment details and artifacts remain private.
- Merge equivalence and post-merge checks follow the policy rather than assuming that a previously reviewed head represents a different tree.

## Request-boundary methodology

For each in-scope boundary, review and trace:

1. **Registrations** — enumerate public and authenticated hooks, routes, form actions, and aliases.
2. **Authentication** — establish whether the boundary is intentionally public or requires an authenticated session.
3. **Nonce** — identify verification, action scope, failure behavior, and whether nonce protection matches the request type.
4. **Capability** — map the required capability to the permitted observation or mutation and inspect least-privilege semantics.
5. **Input handling** — trace normalization, validation, sanitization, allow-lists, bounds, and SQL construction.
6. **Side effects** — identify database, option, transient, log, synchronization, recovery, and other state changes, including helper effects.
7. **Response sensitivity** — inspect errors, notices, progress data, identifiers, and diagnostic content for inappropriate disclosure.
8. **Aliases and common guards** — confirm every alias reaches equivalent authentication, nonce, capability, and validation controls.
9. **Tests and runtime evidence** — require negative and authorized cases appropriate to risk, including state-delta or side-effect-absence assertions where needed.

Public documentation will describe controls and conclusions at a defensive level. It will not include exploit payloads, operational reproduction recipes, or private diagnostics.

## Principal handler groups

| Group | Principal review focus |
| --- | --- |
| Dashboard | Observation authority, active metrics, UI logging, response sensitivity, and control visibility |
| Synchronization | Start/stop authority, polling, ownership, recovery mutations, lifecycle state, and cleanup |
| Analytics administration | Administrative authorization, nonce coverage, parameter bounds, SQL construction, exports, and sensitive responses |
| Public analytics ingestion | Intentional public boundary, payload bounds, validation, sampling, rate limiting, deduplication, concurrency, and disclosure |
| Settings save | Form registration, nonce and capability enforcement, option allow-lists, sanitization, and mutation scope |

## Risk and validation expectations

The canonical [pull-request validation policy](../validation-policy.md) determines the profile for each package:

- Documentation-only scaffold changes are **Docs / metadata** risk: run public CI, `git diff --check`, and deterministic changed-link, table, path, command, and public-safety inspection. Private deployment is normally excluded.
- Test-only packages are **Test / tooling only** unless they alter private validation semantics or span a higher-risk boundary.
- Ordinary runtime changes require exact-reviewed-head validation and the policy's targeted readonly profile, expanded only when persistence or breadth requires it.
- Capability, nonce, authorization, security, synchronization lifecycle, concurrency, or persistence changes are **High-risk** and require the policy's deeper exact-head review and affected private validation. The specialized AJAX security runner is preferred for narrowly scoped privileged AJAX authorization changes.

Validation must use the smallest sufficient profile. Routine runs keep real synchronization and artifact saving disabled. Failures, ambiguity, identity mismatches, or required-invariant failures block advancement; diagnosis remains in approved private locations.

## Canonical evidence ledger

The [Milestone 4 evidence register](evidence-register.md) is the canonical ledger for stable evidence IDs, exact package provenance, evidence classes, bounded results, limitations, and the M4-10 handoff. Historical executed-evidence records remain authoritative inputs and are not rewritten by consolidation.

## Work-package sequence

| Package | Scope | Status/order |
| --- | --- | --- |
| `M4-01` | Exact-baseline handler inventory, capability map, side-effect tracing, findings, and remediation decisions | Analysis complete; scaffold established |
| `M4-02` | Restrict active-metrics/UI-log writes to `nmkr_manage_sync`; align relevant caller/control visibility; add view-only no-side-effect tests | Implemented, merged, and validated by PR #86 |
| `M4-03` | Preserve read polling while requiring `nmkr_manage_sync` for recovery mutations; add lifecycle/state-delta tests | Implemented by PR #88, merged, and validated |
| `M4-04` | Inspect output contexts and error/notice rendering; change only confirmed unsafe sinks | Source analysis and [evidence](executed-evidence-m4-04-2026-08-30.md) complete; no confirmed unsafe sink and no runtime change |
| `M4-05` | Harden analytics admission concurrency and compact payload bounds while preserving intentional public ingestion | Implemented by PR #91; [executed evidence](executed-evidence-m4-05-2026-08-31.md), exact-head review/validation, merge equivalence, and post-merge validation complete |
| `M4-06` | Consolidate targeted public-safe security regressions and dependency/static checks | Implemented by PR #93; [executed evidence](executed-evidence-m4-06-2026-08-31.md), exact-head CI, resolved findings, and merge equivalence complete within the disclosed public-only test/tooling scope |
| `M4-07` | Expand user/developer/troubleshooting navigation and produce comprehensive FAQ content | Implemented by PR #96; [executed evidence](executed-evidence-m4-07-2026-08-31.md), review, exact-head CI, and merge equivalence complete |
| `M4-08` | Private exact-head validation using risk-appropriate profiles; no live NMKR traffic by default | Complete; [executed evidence](executed-evidence-m4-08-2026-09-01.md) records cumulative private DEV validation at exact SHA `204cb395e1a4c9b8de63659a56d9a63dc082ecd8`, tree `54c3c377405803a5f36453a55d81814b5de59fcb` |
| `M4-09` | Consolidate sanitized audit evidence, findings, traceability, limitations, and non-claims | Evidence consolidation complete in this documentation-only package; see [`M4-EVD-008`](evidence-register.md#m4-evd-008--m4-09-evidence-reconciliation-and-m4-10-handoff) |
| `M4-10` | Final Milestone 4 report, Proof of Achievement, and provenance seal after all required work and evidence | Complete within its documented evidence/reporting scope; PR #102 provenance sealed by `M4-EVD-009` |

Packages proceeded separately so review and evidence remain attributable. M4-01 through M4-10 are complete within their disclosed scopes; the documentation-only M4-10 seal records PR #102 provenance without creating an M4-11 package or a recursive sealing requirement.

## Stop conditions and public-repository safety

Stop the relevant work when the expected baseline or reviewed/deployed head does not match, a required worktree is not clean, a required control or invariant cannot be established, validation is ambiguous or fails, or safe publication would require exposing private material. Do not silently move analysis to a newer baseline.

Do not run real NMKR synchronization by default. Do not commit private URLs, host or VM paths, credentials, nonces, API keys, customer or synchronization data, authentication state, raw logs, database output, screenshots, traces, videos, reports, or private evidence locations. Public reporting must remain defensive, concise, and free of detailed exploit instructions.

## M4-05 implementation boundary

M4-05 confirmed that public analytics deduplication and rate limiting used non-atomic state transitions, deduplication names included request identifiers, compact payload dimensions were not comprehensively bounded, accepted sessions exceeded the `CHAR(36)` contract, and a pre-insert marker could suppress a valid retry. The correction retains intentional public/unauthenticated ingestion while using fixed digest names, option-level compare-and-swap deduplication state, database-advisory-lock serialization for durable per-anonymized-IP counters, plugin-level body and recursive metadata bounds, canonical UUID validation, and durable pre-sink deduplication. Rate admission waits briefly for the per-source lock. Only a counter over the configured threshold produces `429`; lock timeout, storage failure, and ambiguous state fail closed with `503` before deduplication or sinks. The 8 KiB plugin check bounds JSON decoding and sink work, not PHP or WordPress request buffering. Web-server and PHP request limits remain the transport-memory boundary. Rate limiting remains before deduplication because every public request consumes endpoint capacity. Server sampling is authoritative; the browser retains mode, consent, and DNT gates without independently applying the configured rate.

Admission becomes durable before either sink is invoked. Consequently a crash or sink failure can lose an analytics event, but a retry cannot duplicate a committed local row or repeat a GA4 dispatch. This is at-most-once invocation, not transactional or exactly-once delivery across WordPress and GA4. Public synthetic tests use injected barriers to deterministically exercise competing option creation, stale-takeover compare-and-swap ownership, advisory-lock contention, quota exhaustion, storage ambiguity, and sink suppression. Exact-head private validation added real WordPress/MariaDB concurrency, quota, lock-contention, malformed-state recovery, transport-boundary, cleanup, and merged-main equivalence evidence; the sanitized conclusions are recorded in the [M4-05 executed evidence](executed-evidence-m4-05-2026-08-31.md). These results do not establish every worker topology, persistent-cache backend, proxy, or hostile traffic pattern. No real NMKR synchronization, NMKR API traffic, external GA4 request, customer data, or private artifacts were used. M4-05 resolves S-05 within this disclosed scope and is not a claim of universal abuse resistance.

## M4-06 implementation and evidence boundary

The `test:security` path inventories exact production AJAX registrations, classifies the intentional public analytics-ingestion exception, fails on unsupported registration forms, executes focused settings and analytics contracts, checks SQL placeholder/value-count consistency, and retains existing error-disclosure regressions. Representative denial ledgers consolidate guard-family no-downstream-effect assurance around existing M4-02/M4-03/M4-05 checks rather than duplicating them.

`test:security-rendering` executes production synchronization and analytics dashboard JavaScript in a local synthetic DOM and proves only that supplied hostile-shaped fixtures remain literal text at the exercised current-item, project-UID, and token-UID sinks. `test:static` checks tracked shell/JavaScript syntax, PHP 7.4 compatibility, Playwright discovery, and a narrow filename/path generated/private-artifact denylist. `test:dependencies` is read-only, lock-bound, network-dependent, and fails closed; advisory results may change without a source change.

PR #93 completed this public-only test/tooling package at final head `c50c15770f93dd7c5d7ae2508f0d3be20fc57c0c`. Phase 4 CI run `33397493179` passed all three exact-head jobs, and merged main `fecac597ee7260659b1772de0a8c9128aad30770` has the same tree `cc5c1f3f219069d259ab22cf217df6eca2f4c0ac`. The [M4-06 executed evidence](executed-evidence-m4-06-2026-08-31.md) records the exact provenance, review history, validation classification, and remaining limitations. No private deployment was required because runtime, persistence, private orchestration, and dependency locks were unchanged.

M4-06 narrows S-06 and A-01 only within the targeted registration, representative denial, and three exercised final-rendering paths. It does not, by itself, establish universal authorization, complete side-effect absence, universal SQL-injection or XSS resistance, complete settings or disclosure assurance, dependency safety, penetration testing, formal certification, production security assurance, external Catalyst assessment or approval, or Milestone 4 completion.
