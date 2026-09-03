# Milestone 5 traceability

**Status:** IN PROGRESS\
**Document type:** Canonical final-milestone requirement map

This matrix maps the accepted final Statement of Milestones requirements to the current internal work-package plan and eventual evidence. It records status, not achievement by implication. Evidence columns remain `TBD` until evidence actually exists.

Current implementation checkpoint: M5-01 policy/code analysis and owner decisions are complete against baseline `283ccb82a57bdce06d2fc3256caa6d03caa72583`. M5-02 release-architecture remediation is complete: PR #109 head `f40073d9888ef8b2c4b82bf67f352ff57a1c5d09` was reviewed and validated, then merged as `3712406c38f801fa81a30782991a34e4a028b902`; both commits resolve to tree `da32cd2b9c6ab41c53526992506d7bd9555d583e`. Exact-head and post-merge DEV validation and focused release/package checks passed. Package execution was not repeated on DEV because that VM lacked the `zip` command. Final WordPress.org name/slug selection and directory submission remain blocked on the separate M5-03 release gate; no WordPress.org approval or submission is claimed here.

## Requirement-to-delivery matrix

| ID | Final-milestone requirement / acceptance | Expected evidence | Current status | Planned package(s) | Evidence IDs |
| --- | --- | --- | --- | --- | --- |
| `M5-REQ-001` | Launch targeted marketing campaigns across at least two relevant channels | Campaign posts/pages, timestamps, platform analytics/reports | NOT STARTED | M5-04, M5-05, M5-08 | TBD |
| `M5-REQ-002` | Reach at least 25 plugin downloads from WordPress Plugin Directory and GitHub combined | WordPress.org download data and GitHub release-asset counters, with methodology/limitations | NOT STARTED | M5-03, M5-08 | TBD |
| `M5-REQ-003` | Reach at least 10 unique active installations | WordPress.org active-install evidence where available plus privacy-safe corroboration if needed | NOT STARTED | M5-03, M5-08 | TBD |
| `M5-REQ-004` | Reach at least 250 unique visits to the NMKR Connect plugin webpage | Privacy-safe site analytics report for the defined canonical URL/window | NOT STARTED | M5-04, M5-05, M5-08 | TBD |
| `M5-REQ-005` | Conduct community engagement with at least 10 attendees | Event announcement, attendance count, date/platform, public-safe summary | NOT STARTED | M5-05 | TBD |
| `M5-REQ-006` | Collect and analyze detailed feedback from at least 5 users | Distinct-user, public-safe feedback summaries and analysis; private identity mapping retained only when necessary | NOT STARTED | M5-05, M5-06 | TBD |
| `M5-REQ-007` | Collect/analyze adoption metrics, user feedback, and engagement results | Consolidated adoption/community report with source definitions and limitations | NOT STARTED | M5-08 | TBD |
| `M5-REQ-008` | Produce and submit PCR detailing milestones, outputs, and achievements | Submitted PCR link/record accessible to reviewers | NOT STARTED | M5-09, M5-10 | TBD |
| `M5-REQ-009` | Produce and submit public PCV covering journey, technical achievements, learning, challenges, and impact | Public PCV link and Catalyst submission record | NOT STARTED | M5-09, M5-10 | TBD |
| `M5-REQ-010` | Final developer/user handover documentation is public, current, technically reviewed, and maintainable | Public documentation links, final review/validation record, exact source provenance | IN PROGRESS | M5-08 | TBD |
| `M5-REQ-011` | Public source is updated and accessible | Final public repository SHA/tree/release links | IN PROGRESS | M5-02, M5-03, M5-08 | TBD |
| `M5-REQ-012` | Provide initial post-launch support to early adopters and record examples | Public-safe support-case summaries and response/resolution records | NOT STARTED | M5-06, M5-08 | TBD |
| `M5-REQ-013` | Submit NMKR Connect to WordPress Plugin Directory in accordance with official requirements | Submission receipt/status/reviewer correspondence or directory record, with no claim beyond actual status | NOT SUBMITTED | M5-01, M5-02, M5-03 | TBD |
| `M5-REQ-014` | Allocate resources for early bug fixes/minor feature adjustments based on post-launch evidence | Issue/decision trail and selected post-launch work | NOT STARTED | M5-06, M5-07 | TBD |
| `M5-REQ-015` | Early bug fixes/minor adjustments are documented, implemented, and verified for functionality | PR/commit/release/test/DEV evidence for a genuine qualifying adjustment | NOT STARTED | M5-07 | TBD |
| `M5-REQ-016` | Continue monitoring/reporting adoption metrics | Time-stamped metric checkpoints and final close-out report | NOT STARTED | M5-04, M5-08, M5-10 | TBD |
| `M5-REQ-017` | Incorporate user feedback into future-update prioritization | Feedback-to-issue/decision/prioritization mapping | NOT STARTED | M5-06, M5-07, M5-08 | TBD |
| `M5-REQ-018` | Report the impact of implemented adjustments on adoption/usability/performance where evidence permits | Pre/post observation and limitations; no causal overclaim | NOT STARTED | M5-07, M5-08 | TBD |

## Evidence interpretation rules

- A platform counter is evidence of what that platform measures, not automatically evidence of a stronger concept. For example, a GitHub release asset `download_count` is not silently re-labelled as distinct human downloaders.
- WordPress.org active-install figures may be bucketed/rounded; record the public value and its limits rather than inventing precision.
- Site `unique visitors/users` must be defined by the selected analytics source and measurement window before campaign launch.
- Event attendance and user feedback must count distinct humans; repeat participation does not create extra users.
- Public evidence must be aggregate/redacted. Names, emails, site URLs, IPs, customer data, private analytics identifiers, credentials, private VM paths, and raw private support records stay out of the public repository.
- If a contractual metric cannot be perfectly measured by available platforms, document the limitation and use the strongest privacy-safe corroboration available rather than making an unsupported claim.

## Reconciliation rule

Before M5-09 begins, reconcile all **prerequisite** requirements that M5-09 depends on. Those rows should have one of the following dispositions: `COMPLETE`, `PARTIALLY MET`, `NOT MET`, `AWAITING EXTERNAL`, or a clearly justified `NOT APPLICABLE`. `M5-REQ-008` and `M5-REQ-009` are produced by M5-09 itself and may therefore remain `NOT STARTED` (or later `IN PROGRESS`) at the M5-09 entry gate.

After PCR/PCV submission, perform the final all-row reconciliation. At that point every row must have a final disposition, and PCR/PCV plus M5-10 close-out must preserve any unmet or externally pending status instead of converting it into a completion claim.
