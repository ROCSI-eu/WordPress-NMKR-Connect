# Milestone 4 security audit and documentation

## Purpose and current status

Milestone 4 is **in progress**. It assesses and strengthens the plugin's security boundaries and expands user, developer, FAQ, and troubleshooting documentation. The M4-01 authorization and side-effect analysis is complete against exact baseline `354cd5808c8eebf557a8286452752245901cbd50`. M4-02 and M4-03 are implemented, merged, and validated within their disclosed scopes. M4-04 source analysis and evidence are complete against exact baseline `fd1ac072aeb37b189c9734e0602a58c8c6b8a30c` (tree `af40fd04381a57883e961b71fddcb2777c1cda50`); no confirmed unsafe rendering or disclosure defect was established, so no runtime remediation was required. M4-05 public-ingestion hardening was implemented by PR #91 against baseline `22cb8cee1c35deb7ba18ce1d0ac76b3f738ab52b`, reviewed and validated at exact head `ba5f934e6285089ba4c4946084d23d877fb122ee`, and post-merge validated on main `4a48dc50498e5de618c794a32b91fea968487eb3`; the validated tree is `4b6694520b2e8325c3a4c676a0cefd725f6bfdd8`. M4-06 public-safe security, rendering, static, and dependency-check consolidation was implemented by PR #93 and completed within its disclosed test/tooling scope on merged main `fecac597ee7260659b1772de0a8c9128aad30770`, tree `cc5c1f3f219069d259ab22cf217df6eca2f4c0ac`. M4-07 documentation was implemented by PR #96, reviewed and exact-head CI validated at `c6f2bc2057e3034d2d218d70aee9d1817897ccb7`, and merged on main `2b32a6213c38018bc8e013f7f948c08b1b6a9151`; the final and merged tree is `9a48e61ef8b2025a54f58098403202184ef5039c`.

M4-07 provides the comprehensive FAQ, documentation hub, role/capability matrix, task-oriented cross-links, and clarified synchronization-recovery and public/unauthenticated analytics-ingestion boundaries. The [M4-07 executed evidence](executed-evidence-m4-07-2026-08-31.md) records the review correction, final exact-head CI, merge equivalence, Docs/metadata classification, and remaining limitations. M4-08 through M4-10 remain pending as stated below.

The scoped M4-02, M4-03, M4-05, M4-06, and M4-07 outcomes do not complete the Milestone 4 audit or delivery. Other findings and work packages remain pending where stated.

## Navigation

### Reader documentation

- [Repository documentation hub](../../README.md#documentation)
- [User guide](../user-guide.md)
- [Comprehensive FAQ](../faq.md)
- [Troubleshooting guide](../troubleshooting.md)
- [Developer guide](../developer-guide.md)
- [Validation policy](../validation-policy.md)

### Audit and evidence

- [Security audit plan](security-audit-plan.md) — scope, methodology, trust rules, validation expectations, and package sequence.
- [Traceability](traceability.md) — contract requirements mapped to request boundaries and evidence targets.
- [Findings register](findings-register.md) — stable, public-safe classifications and planned responses.
- [M4-02 executed evidence](executed-evidence-m4-02-2026-08-29.md) — sanitized implementation provenance and validation outcomes for the dashboard mutation-authority remediation.
- [M4-03 executed evidence](executed-evidence-m4-03-2026-08-30.md) — sanitized implementation provenance and validation outcomes for the progress-recovery authority remediation.
- [M4-04 executed evidence](executed-evidence-m4-04-2026-08-30.md) — public-safe source analysis of rendering contexts and client-facing diagnostic disclosure, with no runtime remediation required.
- [M4-05 executed evidence](executed-evidence-m4-05-2026-08-31.md) — sanitized implementation, review, exact-head validation, merge-equivalence, and post-merge outcomes for public analytics ingestion hardening.
- [M4-06 executed evidence](executed-evidence-m4-06-2026-08-31.md) — public-safe test/tooling implementation, exact-head CI, merge equivalence, limitations, and evidence closure for the consolidated security and dependency checks.
- [M4-07 executed evidence](executed-evidence-m4-07-2026-08-31.md) — public-safe documentation implementation, review correction, exact-head CI, merge equivalence, validation classification, and limitations.

## Work-package overview

| Package | Scope | Status/order |
| --- | --- | --- |
| `M4-01` | Exact-baseline handler inventory, capability map, side-effect tracing, findings, and remediation decisions | Analysis complete; scaffold established |
| `M4-02` | Restrict active-metrics/UI-log writes to `nmkr_manage_sync`; align relevant caller/control visibility; add view-only no-side-effect tests | Implemented, merged by PR #86, and validated on merged main `a46e78c975647dd9a3398b45c90d602e11249585` |
| `M4-03` | Preserve read polling while requiring `nmkr_manage_sync` for recovery mutations; add lifecycle and state-delta tests | Implemented by PR #88, merged, and validated on merged main `c69e4ff109948f871201ee396853cbb9f1716a45` |
| `M4-04` | Inspect output contexts and error/notice rendering; change only confirmed unsafe sinks | Source analysis and evidence complete; no confirmed unsafe sink and no runtime remediation required |
| `M4-05` | Harden analytics rate-limit/deduplication concurrency, payload bounds, storage alignment, retry ordering, and sampling semantics | Implemented by PR #91, reviewed and exact-head validated, merged, and post-merge validated on main `4a48dc50498e5de618c794a32b91fea968487eb3` |
| `M4-06` | Consolidate targeted public-safe security regressions and dependency/static checks | Implemented by PR #93; [executed evidence](executed-evidence-m4-06-2026-08-31.md), exact-head CI, resolved findings, and merge equivalence complete within the disclosed public-only test/tooling scope |
| `M4-07` | Expand user/developer/troubleshooting navigation and produce comprehensive FAQ content | Implemented by PR #96; [executed evidence](executed-evidence-m4-07-2026-08-31.md), final-head review, exact-head CI, and merge equivalence complete within the Docs/metadata scope |
| `M4-08` | Perform private exact-head validation using risk-appropriate profiles, with no live NMKR traffic by default | Planned milestone-wide package; package-specific validation already exists where recorded |
| `M4-09` | Consolidate sanitized audit evidence, findings, traceability, limitations, and non-claims | Planned |
| `M4-10` | Produce the final Milestone 4 report and Proof of Achievement after all required work and evidence | Planned |

## M4-07 documentation package

M4-07 is a Docs/metadata package. It adds one role-oriented FAQ and routes site owners, administrators, NMKR Marketing users, developers, maintainers, and support readers to the authoritative detailed procedures. The package also:

- records the exact seven plugin capabilities and distinguishes menu visibility from server authorization;
- distinguishes nonce/CSRF protection from capability authorization;
- preserves observational polling under `nmkr_view_dashboard` while documenting `nmkr_manage_sync` for start, Stop, cleanup, and recovery mutation;
- documents cooperative Stop, provisional progress, canonical completion, bounded polling backoff, and safe escalation;
- uses **public/unauthenticated analytics ingestion** rather than unqualified anonymous-ingestion wording and explains logged-in-user behavior;
- records bounded consent, DNT, sampling, origin/host, input, rate, deduplication, retention, and sink controls together with their limitations; and
- keeps detailed procedures in the focused guides rather than creating divergent copies.

No runtime code, tests, workflow, dependency, schema, role, capability, endpoint, synchronization, shortcode, analytics, deployment, or private-environment behavior is changed by M4-07.

The implementation was reviewed at final head `c6f2bc2057e3034d2d218d70aee9d1817897ccb7`, passed Phase 4 CI run `33411893816` (run 382), and was merged with expected-head protection as main commit `2b32a6213c38018bc8e013f7f948c08b1b6a9151`. The final and merged trees are identical at `9a48e61ef8b2025a54f58098403202184ef5039c`. See the [M4-07 executed evidence](executed-evidence-m4-07-2026-08-31.md).

## Evidence boundary

Public evidence is limited to sanitized source changes, public-safe tests and CI outcomes, reviewable documentation, and conclusions that do not reveal attack recipes or operational details. Private exact-head validation may use approved prepared environments and owner-private inputs, but private URLs, paths, credentials, authentication state, logs, database output, screenshots, traces, reports, and other artifacts must not enter the public repository. The [validation policy](../validation-policy.md) controls check selection and exact-head trust. A real NMKR synchronization is excluded by default.

M4-07 is classified as Docs/metadata. Focused link, anchor, terminology, changed-file, public-safety, and status checks plus public CI are appropriate; private DEV deployment, WordPress execution, WP-CLI, database access, authenticated browser execution, real synchronization, NMKR traffic, GA4 traffic, and private artifact generation are not selected.

## Explicit non-claims

This milestone record:

- does **not** claim that the security audit is complete;
- claims resolution only for S-01, S-02, and S-05 within the respective implemented and validated M4-02, M4-03, and M4-05 scopes;
- records only targeted assurance narrowing for S-06 and A-01 through M4-06, not universal closure;
- records M4-07 documentation improvements without converting them into universal authorization, XSS, SQL-injection, side-effect-absence, dependency-safety, production-security, or legal-compliance claims;
- does **not** provide a production security guarantee;
- does **not** claim formal certification, penetration testing, Catalyst approval, completed audit, or completed Milestone 4 delivery; and
- records that no real NMKR synchronization or live NMKR traffic was performed for M4-01 through M4-07; M4-04, M4-06, and M4-07 required no private deployment, and M4-05 used no external GA4 traffic.

## M4-06 targeted security-check package

M4-06 adds public-safe deterministic contracts for the classified privileged AJAX registration surface and representative guard families, the plugin settings capability/sanitizer boundary, hostile analytics SQL inputs, and three exercised final-rendering sinks. It also adds lightweight tracked-source/static gates and a separate lock-bound dependency-audit job. These are targeted executable assertions, not universal authorization, SQL-injection, XSS, WordPress `options.php`, or dependency-safety guarantees.

The package changed tests, tooling, CI workflow behavior, and planning documentation only. It did not change plugin runtime behavior or dependency declarations or lockfiles. Dependency results describe committed locks against advisory data available when the job ran, so they are time-dependent. The [M4-06 executed evidence](executed-evidence-m4-06-2026-08-31.md) records the exact implementation head, final CI, resolved findings, merge equivalence, validation classification, and remaining limitations.
