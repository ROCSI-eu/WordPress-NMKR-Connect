# Milestone 4 security audit and documentation

## Purpose and current status

Milestone 4 is **in progress**. It will assess and strengthen the plugin's security boundaries and expand user, developer, FAQ, and troubleshooting documentation. The M4-01 authorization and side-effect analysis is complete against exact baseline `354cd5808c8eebf557a8286452752245901cbd50`. M4-02 and M4-03 are implemented, merged, and validated within their disclosed scopes. M4-04 source analysis and evidence are complete against exact baseline `fd1ac072aeb37b189c9734e0602a58c8c6b8a30c` (tree `af40fd04381a57883e961b71fddcb2777c1cda50`); no confirmed unsafe rendering or disclosure defect was established, so no runtime remediation was required. M4-05 public-ingestion hardening was implemented by PR #91 against baseline `22cb8cee1c35deb7ba18ce1d0ac76b3f738ab52b`, reviewed and validated at exact head `ba5f934e6285089ba4c4946084d23d877fb122ee`, and post-merge validated on main `4a48dc50498e5de618c794a32b91fea968487eb3`; the validated tree is `4b6694520b2e8325c3a4c676a0cefd725f6bfdd8`. M4-06 public-safe security, rendering, static, and dependency-check consolidation was implemented by PR #93 and completed within its disclosed test/tooling scope on merged main `fecac597ee7260659b1772de0a8c9128aad30770`, tree `cc5c1f3f219069d259ab22cf217df6eca2f4c0ac`.

M4-01 did not implement remediation or complete validation. The scoped M4-02, M4-03, M4-05, and M4-06 outcomes do not complete the Milestone 4 audit or delivery, and other findings and work packages remain pending where stated. M4-07 is the next planned package.

## Navigation

- [Security audit plan](security-audit-plan.md) — scope, methodology, trust rules, validation expectations, and package sequence.
- [Traceability](traceability.md) — contract requirements mapped to request boundaries and evidence targets.
- [Findings register](findings-register.md) — stable, public-safe classifications and planned responses.
- [M4-02 executed evidence](executed-evidence-m4-02-2026-08-29.md) — sanitized implementation provenance and validation outcomes for the dashboard mutation-authority remediation.
- [M4-03 executed evidence](executed-evidence-m4-03-2026-08-30.md) — sanitized implementation provenance and validation outcomes for the progress-recovery authority remediation.
- [M4-04 executed evidence](executed-evidence-m4-04-2026-08-30.md) — public-safe source analysis of rendering contexts and client-facing diagnostic disclosure, with no runtime remediation required.
- [M4-05 executed evidence](executed-evidence-m4-05-2026-08-31.md) — sanitized implementation, review, exact-head validation, merge-equivalence, and post-merge outcomes for public analytics ingestion hardening.
- [M4-06 executed evidence](executed-evidence-m4-06-2026-08-31.md) — public-safe test/tooling implementation, exact-head CI, merge equivalence, limitations, and evidence closure for the consolidated security and dependency checks.

## Work-package overview

| Package | Scope | Status/order |
| --- | --- | --- |
| `M4-01` | Exact-baseline handler inventory, capability map, side-effect tracing, findings, and remediation decisions | Analysis complete; scaffold established |
| `M4-02` | Restrict active-metrics/UI-log writes to `nmkr_manage_sync`; align relevant caller/control visibility; add view-only no-side-effect tests | Implemented, merged by PR #86, and validated on merged main `a46e78c975647dd9a3398b45c90d602e11249585` |
| `M4-03` | Preserve read polling while requiring `nmkr_manage_sync` for recovery mutations; add lifecycle and state-delta tests | Implemented by PR #88, merged, and validated on merged main `c69e4ff109948f871201ee396853cbb9f1716a45` |
| `M4-04` | Inspect output contexts and error/notice rendering; change only confirmed unsafe sinks | Source analysis and evidence complete; no confirmed unsafe sink and no runtime remediation required |
| `M4-05` | Harden analytics rate-limit/deduplication concurrency, payload bounds, storage alignment, retry ordering, and sampling semantics | Implemented by PR #91, reviewed and exact-head validated, merged, and post-merge validated on main `4a48dc50498e5de618c794a32b91fea968487eb3` |
| `M4-06` | Consolidate targeted public-safe security regressions and dependency/static checks | Implemented by PR #93; [executed evidence](executed-evidence-m4-06-2026-08-31.md), exact-head CI, resolved findings, and merge equivalence complete within the disclosed public-only test/tooling scope |
| `M4-07` | Expand user/developer/troubleshooting navigation and produce comprehensive FAQ content | Next planned package |
| `M4-08` | Perform private exact-head validation using risk-appropriate profiles, with no live NMKR traffic by default | Planned milestone-wide package; package-specific validation already exists where recorded |
| `M4-09` | Consolidate sanitized audit evidence, findings, traceability, limitations, and non-claims | Planned |
| `M4-10` | Produce the final Milestone 4 report and Proof of Achievement after all required work and evidence | Planned |

## Evidence boundary

Public evidence is limited to sanitized source changes, public-safe tests and CI outcomes, reviewable documentation, and conclusions that do not reveal attack recipes or operational details. Private exact-head validation may use approved prepared environments and owner-private inputs, but private URLs, paths, credentials, authentication state, logs, database output, screenshots, traces, reports, and other artifacts must not enter the public repository. The [validation policy](../validation-policy.md) controls check selection and exact-head trust. A real NMKR synchronization is excluded by default.

## Explicit non-claims

This milestone record:

- does **not** claim that the security audit is complete;
- claims resolution only for S-01, S-02, and S-05 within the respective implemented and validated M4-02, M4-03, and M4-05 scopes;
- records only targeted assurance narrowing for S-06 and A-01 through M4-06, not universal closure;
- does **not** provide a production security guarantee;
- does **not** claim Catalyst approval; and
- records that no real NMKR synchronization or live NMKR traffic was performed for M4-01 through M4-06; M4-04 and M4-06 required no private deployment, and M4-05 used no external GA4 traffic.

## M4-06 targeted security-check package

M4-06 adds public-safe deterministic contracts for the classified privileged AJAX registration surface and representative guard families, the plugin settings capability/sanitizer boundary, hostile analytics SQL inputs, and three exercised final-rendering sinks. It also adds lightweight tracked-source/static gates and a separate lock-bound dependency-audit job. These are targeted executable assertions, not universal authorization, SQL-injection, XSS, WordPress `options.php`, or dependency-safety guarantees.

The package changed tests, tooling, CI workflow behavior, and planning documentation only. It did not change plugin runtime behavior or dependency declarations or lockfiles. Dependency results describe committed locks against advisory data available when the job ran, so they are time-dependent. The [M4-06 executed evidence](executed-evidence-m4-06-2026-08-31.md) records the exact implementation head, final CI, resolved findings, merge equivalence, validation classification, and remaining limitations.
