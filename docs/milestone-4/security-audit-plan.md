# Milestone 4 security audit plan

## Scope and objectives

This plan governs the public-safe Milestone 4 review of administrative and public request boundaries, their authorization controls, input handling, side effects, response sensitivity, output contexts, and supporting documentation. Its objectives are to preserve the M4-01 analysis, implement the smallest compatible remediations in later pull requests, establish targeted regression evidence, and document limitations without overstating assurance.

The M4-01 analysis baseline is exactly `354cd5808c8eebf557a8286452752245901cbd50`. Analysis completion is not remediation, validation, audit completion, or delivery completion.

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

## Work-package sequence

| Package | Scope | Status/order |
| --- | --- | --- |
| `M4-01` | Exact-baseline handler inventory, capability map, side-effect tracing, findings, and remediation decisions | Analysis complete; scaffold now being added |
| `M4-02` | Restrict active-metrics/UI-log writes to `nmkr_manage_sync`; align relevant caller/control visibility; add view-only no-side-effect tests | Next implementation PR |
| `M4-03` | Preserve read polling while requiring `nmkr_manage_sync` for recovery mutations; add lifecycle/state-delta tests | Separate PR after M4-02 |
| `M4-04` | Inspect output contexts and error/notice rendering; change only confirmed unsafe sinks | Planned |
| `M4-05` | Test analytics rate-limit/deduplication concurrency and payload-abuse bounds before deciding on code changes | Planned, test-first |
| `M4-06` | Consolidate targeted public-safe security regressions and dependency/static checks | Planned |
| `M4-07` | Expand user/developer/troubleshooting navigation and produce comprehensive FAQ content | Planned |
| `M4-08` | Private exact-head validation using risk-appropriate profiles; no live NMKR traffic by default | Planned |
| `M4-09` | Consolidate sanitized audit evidence, findings, traceability, limitations, and non-claims | Planned |
| `M4-10` | Final Milestone 4 report and Proof of Achievement after all required work and evidence | Planned |

Packages proceed separately so review and evidence stay attributable. Future packages remain open until their own exact-head work and required checks exist.

## Stop conditions and public-repository safety

Stop the relevant work when the expected baseline or reviewed/deployed head does not match, a required worktree is not clean, a required control or invariant cannot be established, validation is ambiguous or fails, or safe publication would require exposing private material. Do not silently move analysis to a newer baseline.

Do not run real NMKR synchronization by default. Do not commit private URLs, host or VM paths, credentials, nonces, API keys, customer or synchronization data, authentication state, raw logs, database output, screenshots, traces, videos, reports, or private evidence locations. Public reporting must remain defensive, concise, and free of detailed exploit instructions.
