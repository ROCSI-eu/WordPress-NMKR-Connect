# Milestone 4 security audit and documentation

## Purpose and current status

Milestone 4 is **in progress**. It will assess and strengthen the plugin's security boundaries and expand user, developer, FAQ, and troubleshooting documentation. The M4-01 authorization and side-effect analysis is complete against exact baseline `354cd5808c8eebf557a8286452752245901cbd50`. M4-02 and M4-03 are implemented, merged, and validated within their disclosed scopes. M4-03 resolved the scoped S-02 recovery-authority finding; M4-04 is the next planned technical package, and the remaining packages require separate implementation, review, and evidence.

M4-01 did not implement remediation or complete validation. The scoped M4-02 and M4-03 resolutions do not complete the Milestone 4 audit or delivery, and other findings remain pending where stated.

## Navigation

- [Security audit plan](security-audit-plan.md) — scope, methodology, trust rules, validation expectations, and package sequence.
- [Traceability](traceability.md) — contract requirements mapped to request boundaries and evidence targets.
- [Findings register](findings-register.md) — stable, public-safe classifications and planned responses.
- [M4-02 executed evidence](executed-evidence-m4-02-2026-08-29.md) — sanitized implementation provenance and validation outcomes for the dashboard mutation-authority remediation.
- [M4-03 executed evidence](executed-evidence-m4-03-2026-08-30.md) — sanitized implementation provenance and validation outcomes for the progress-recovery authority remediation.

## Work-package overview

| Package | Scope | Status/order |
| --- | --- | --- |
| `M4-01` | Exact-baseline handler inventory, capability map, side-effect tracing, findings, and remediation decisions | Analysis complete; scaffold established |
| `M4-02` | Restrict active-metrics and UI-log writes to `nmkr_manage_sync`; align relevant caller/control visibility; add view-only no-side-effect tests | Implemented, merged by PR #86, and validated on merged main `a46e78c975647dd9a3398b45c90d602e11249585` |
| `M4-03` | Preserve read polling while requiring `nmkr_manage_sync` for recovery mutations; add lifecycle and state-delta tests | Implemented by PR #88, merged, and validated on merged main `c69e4ff109948f871201ee396853cbb9f1716a45` |
| `M4-04` | Inspect output contexts and error/notice rendering; change only confirmed unsafe sinks | Next planned technical package |
| `M4-05` | Test analytics rate-limit/deduplication concurrency and payload-abuse bounds before deciding on code changes | Planned, test-first |
| `M4-06` | Consolidate targeted public-safe security regressions and dependency/static checks | Planned |
| `M4-07` | Expand user/developer/troubleshooting navigation and produce comprehensive FAQ content | Planned |
| `M4-08` | Perform private exact-head validation using risk-appropriate profiles, with no live NMKR traffic by default | Planned |
| `M4-09` | Consolidate sanitized audit evidence, findings, traceability, limitations, and non-claims | Planned |
| `M4-10` | Produce the final Milestone 4 report and Proof of Achievement after all required work and evidence | Planned |

## Evidence boundary

Public evidence is limited to sanitized source changes, public-safe tests and CI outcomes, reviewable documentation, and conclusions that do not reveal attack recipes or operational details. Private exact-head validation may use approved prepared environments and owner-private inputs, but private URLs, paths, credentials, authentication state, logs, database output, screenshots, traces, reports, and other artifacts must not enter the public repository. The [validation policy](../validation-policy.md) controls check selection and exact-head trust. A real NMKR synchronization is excluded by default.

## Explicit non-claims

This milestone record:

- does **not** claim that the security audit is complete;
- claims resolution only for S-01 and S-02 within the respective implemented and validated M4-02 and M4-03 scopes;
- does **not** provide a production security guarantee;
- does **not** claim Catalyst approval; and
- records that no real NMKR synchronization or live NMKR traffic was performed for M4-01, M4-02, or M4-03.
