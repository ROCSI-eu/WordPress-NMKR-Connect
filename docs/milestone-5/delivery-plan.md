# Milestone 5 delivery plan

**Status:** IN PROGRESS\
**Document type:** Living execution plan\
**Planning baseline:** `c375ddf76dfb8965f568253f2e3563eb3c339f6f`

This plan may change as exact-code analysis, WordPress.org reviewer feedback, real adoption, user feedback, support cases, or current Catalyst requirements provide new evidence. Changes to this plan do not alter the final Statement of Milestones contract; update [traceability](traceability.md) whenever sequencing or scope changes.

## Work packages

| Package | Objective | Prerequisites | Expected Codex mode | Initial risk | DEV/runtime expectation | External dependency | Completion gate | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `M5-01` | Audit current code/distribution against WordPress.org release requirements and choose the smallest compliant architecture | Exact current `main`; clean/verified source state | `CODEX — ANALYSIS ONLY` | High | None for analysis | Current WordPress.org rules; possible product/legal decision | Blocking/non-blocking findings, decisions, exact scope, and later acceptance checks recorded | COMPLETE — issue #107 records the analysis and owner decisions; name/slug remains an explicit deferred release gate |
| `M5-02` | Implement the smallest coherent free/release packaging changes required by M5-01 | M5-01 decisions | `CODEX — CODE MODIFICATION` | High until scope known | Risk-based exact-head DEV/package validation; no real NMKR traffic by default | Dependency/package tooling | Installable package passes focused checks and M5-01 acceptance gates | IN PROGRESS — implemented on PR #109; independent review and required exact-head validation remain pending |
| `M5-03` | Publish an installable GitHub release and submit the WordPress.org candidate; handle reviewer feedback | M5-02 validated release candidate | Code/docs only if reviewer feedback requires it | Medium/High | Exact-head validation for runtime-affecting reviewer changes | GitHub Releases; WordPress.org review queue/SVN | Release provenance captured; submission evidence recorded; reviewer loop tracked accurately | NOT STARTED |
| `M5-04` | Prepare the canonical plugin landing page and measurement contract/baselines | Release identity/URL decisions from M5-01/02 | External website work; repo docs as needed | Medium | No plugin DEV unless plugin code changes | Website analytics/consent configuration | Exact landing URL, metric definitions, baseline timestamp/window, privacy boundary, and evidence capture method recorded | NOT STARTED |
| `M5-05` | Launch targeted marketing across at least two channels and conduct community engagement | M5-03 usable release path; M5-04 measurement ready | No Codex unless supporting repo changes are needed | Medium | None normally | Community channels, campaign accounts, event platform | Campaign launched with evidence; >=10 event attendees targeted and measured without fabricated counts | NOT STARTED |
| `M5-06` | Provide initial support and collect/analyze detailed feedback from distinct users | Real early adopters/community participants | Code only for issues selected from real feedback | Medium | Depends on reported issue | Users/support channels | Support log exists; >=5 distinct users provide actionable feedback or gap is truthfully disclosed | NOT STARTED |
| `M5-07` | Implement and verify at least one legitimate early bug fix or minor adjustment if supported by post-launch evidence | M5-06/usage evidence; selected real issue | `CODEX — ANALYSIS ONLY` when broad/risky, then `CODEX — CODE MODIFICATION` | Risk-based | Exact-head DEV required for sync/DB/auth/security/external-API/persistent-state changes; otherwise risk-based | Real-world observation/feedback | Genuine change documented, reviewed, tested, released/observed, and linked to originating evidence | NOT STARTED |
| `M5-08` | Consolidate adoption metrics, support/feedback outcomes, post-launch impact, and handover completeness | Sufficient measurement/support period; M5-07 disposition known | Primarily documentation | Low | No DEV for documentation-only work | WordPress.org/GitHub/site analytics/support records | Contract metrics evaluated, limitations disclosed, handover validated, evidence register reconciled | NOT STARTED |
| `M5-09` | Prepare and submit PCR and PCV using actual final results | M5-08 evidence sufficiently mature | Primarily documentation/media | Medium | None | Catalyst close-out portal; public video host | PCR/PCV meet current requirements, use supported claims, and submission/public links are recorded | NOT STARTED |
| `M5-10` | Seal final Catalyst-facing close-out record and unresolved limitations | M5-09 submission evidence | Documentation-only | Low | None | Catalyst assessment/approval is external | Final traceability/evidence/provenance reconciled; no unsupported completion claims | NOT STARTED |

## Critical path

Current expected critical path:

`M5-01 → M5-02 → M5-03 usable release + M5-04 measurement readiness → M5-05 campaign/community launch → real adoption/support period (M5-06 runs throughout as adopters and participants appear) → M5-07 disposition → M5-08 → M5-09 → M5-10`

M5-04 can run partly in parallel with M5-02 after M5-01 establishes naming/release decisions, but its measurement baseline must be fixed before M5-05 starts. M5-05 is what begins the targeted outreach intended to create adoption/community activity; it therefore precedes the main observation period. M5-06 begins as soon as real adopters or participants need support or provide feedback and continues within that period rather than waiting until it ends.

WordPress.org review can run in parallel with GitHub-based adoption once a compliant GitHub release exists, but campaign messaging must state the actual directory status rather than imply approval.

## Parallel work boundaries

Safe parallel work may include landing-page copy, measurement methodology, event logistics, and handover indexing after relevant naming/release decisions are stable. Do not parallelize changes that create conflicting release identities, duplicate measurement baselines, or simultaneous Codex modification tasks on overlapping code.

## Real-world elapsed-time dependencies

The following cannot be synthetically compressed into code execution:

- WordPress.org reviewer turnaround;
- real downloads and active installations;
- real unique webpage visits;
- real event attendance;
- distinct-user feedback;
- early-adopter support interactions;
- observation of the effect of a genuine post-launch adjustment.

No synthetic test, automated browser run, seeded database row, or internal team action may be counted as a real adopter, real visitor, real attendee, or independent user-feedback record.

## Validation policy

Documentation-only planning/scaffold changes require no DEV deployment. Runtime-affecting packages follow the repository's risk-based exact-head validation policy. Real NMKR synchronization remains excluded unless separately and explicitly authorized with controlled data, preflight, rollback, stop/recovery checks, and final-state validation.

## Plan-change rule

When a new finding changes sequencing or scope:

1. record the finding/risk/decision in [risks-and-decisions.md](risks-and-decisions.md);
2. update this delivery plan;
3. update [traceability.md](traceability.md) if the contractual mapping changes;
4. update [wordpress-org-readiness.md](wordpress-org-readiness.md), [adoption-and-metrics.md](adoption-and-metrics.md), or another domain file as appropriate;
5. never rewrite historical evidence to make the earlier plan appear correct.
