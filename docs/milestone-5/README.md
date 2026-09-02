# Milestone 5 project close-out and marketing

## Purpose and current status

Milestone 5 is the project's final delivery stage: **Project Close-Out and Marketing**.

**Status:** PLANNING

This directory is a living Milestone 5 delivery workspace. Its presence does not mean the milestone, any work package, any adoption threshold, any WordPress.org submission, or any Catalyst close-out requirement has been completed.

Only explicitly recorded executed evidence may support completion claims. Planning entries may be revised when implementation, WordPress.org review, adoption data, community feedback, support activity, or current Catalyst requirements provide new information.

The planning baseline for this scaffold is repository commit `c375ddf76dfb8965f568253f2e3563eb3c339f6f`, tree `e3962fe11e39ac6bd06561f9b891aed198056b2f`.

## Final-milestone contract summary

The accepted final Statement of Milestones requires, in substance:

- targeted marketing/community outreach across at least two channels;
- at least 25 plugin downloads from WordPress Plugin Directory and GitHub combined;
- at least 10 active installations;
- at least 250 unique visits to the NMKR Connect plugin webpage;
- at least one community event with at least 10 attendees;
- detailed feedback from at least 5 users, collected and analyzed;
- Project Completion Report (PCR) and Project Close-out Video (PCV);
- final public source and handover documentation;
- initial post-launch support for early adopters;
- submission to the WordPress Plugin Directory in line with its requirements;
- documented, implemented, and verified early bug fixes or minor adjustments arising from real post-launch use where required by the acceptance/evidence contract;
- continued adoption monitoring and evidence that user feedback informs future prioritization.

The [traceability matrix](traceability.md) is the canonical requirement-to-work-package map for this workspace.

## Navigation

- [Delivery plan](delivery-plan.md) — work packages, prerequisites, risk, validation, external dependencies, gates, and critical path.
- [Traceability](traceability.md) — final Statement of Milestones outputs, acceptance criteria, evidence expectations, and current status.
- [Evidence register](evidence-register.md) — stable evidence namespace and public/private evidence rules.
- [WordPress.org readiness](wordpress-org-readiness.md) — directory-policy, packaging, licensing, privacy, and architecture readiness.
- [Release and submission](release-and-submission.md) — release package, GitHub Release, WordPress.org submission/review/SVN workflow.
- [Adoption and metrics](adoption-and-metrics.md) — metric definitions, baselines, measurement sources, privacy, and limitations.
- [Marketing and community](marketing-and-community.md) — launch channels, messaging, outreach, community event, and campaign evidence.
- [Support and feedback](support-and-feedback.md) — early-adopter support, feedback definitions, privacy, and feedback-to-issue flow.
- [Post-launch enhancements](post-launch-enhancements.md) — genuine feedback/usage-driven fixes or minor adjustments and their validation.
- [Handover](handover.md) — final user/developer/release/support documentation checklist.
- [PCR / PCV](pcr-pcv.md) — current close-out requirements and preparation gates.
- [Risks and decisions](risks-and-decisions.md) — living risk register and decision log.
- [Close-out](closeout.md) — final completion checklist and eventual proof-of-achievement summary.

## Work-package sequence

| Package | Purpose | Initial status |
| --- | --- | --- |
| `M5-01` | WordPress.org and release-readiness analysis/decision | PLANNED |
| `M5-02` | Compliant release/distribution preparation | NOT STARTED |
| `M5-03` | GitHub release and WordPress.org submission/reviewer loop | NOT STARTED |
| `M5-04` | Landing-page and measurement readiness | NOT STARTED |
| `M5-05` | Marketing and community campaign | NOT STARTED |
| `M5-06` | Early-adopter support and actionable feedback | NOT STARTED |
| `M5-07` | Genuine post-launch fix/minor adjustment | NOT STARTED |
| `M5-08` | Adoption evidence consolidation and final handover | NOT STARTED |
| `M5-09` | PCR and PCV preparation/submission | NOT STARTED |
| `M5-10` | Final Catalyst close-out/evidence seal | NOT STARTED |

Work-package numbering is a project-management device, not part of the Catalyst contract. It may be split, merged, or reordered when evidence justifies a safer or clearer path, provided the contractual traceability remains intact.

## Status vocabulary

Statuses are namespaced by what they describe; a document/readiness qualifier is not automatically a final requirement disposition.

### Work/package/document lifecycle

- `ACTIVE` — living register or control document is in ongoing use.
- `PLANNING` / `IN PLANNING` — scope or completeness is still being shaped; neither implies execution.
- `PLANNED` — intended work/structure is defined but execution has not begun.
- `NOT STARTED` — required execution has not begun.
- `TO REVIEW` — existing material is present but awaits the planned review/revalidation step.
- `IN ANALYSIS` — active analysis is underway before an implementation or release decision.
- `BLOCKED` — progress cannot safely continue until the stated blocker is resolved.
- `IN PROGRESS` — execution has begun but the relevant completion gate is not met.
- `WAITING FOR REAL-WORLD FEEDBACK` — intentionally paused pending genuine external use/feedback rather than synthetic work.
- `AWAITING EXTERNAL` — project-side work may be complete enough for the step, but an external reviewer/platform/event is pending.
- `READY FOR VALIDATION` — implementation/work is ready for the required validation gate but is not yet complete.
- `COMPLETE` — the applicable internal completion gate has been met with supporting evidence; external approval is stated separately.
- `NOT APPLICABLE` — the item is demonstrably outside the applicable scope, with justification recorded.

### Readiness/submission/campaign qualifiers

- `NOT READY` — the document or close-out gate is intentionally not ready to complete.
- `NOT READY FOR FINAL DRAFT` — neutral structure may exist, but final claims/figures must not yet be drafted.
- `NOT LAUNCHED` — the planned campaign exists but has not begun.
- `NOT SUBMITTED` — the relevant external submission has not occurred.
- `READY FOR DRAFT` — the stated evidence prerequisites for final drafting have been met; this is a gate label, not completion.

Compound labels such as `PLANNED / NOT LAUNCHED` combine the applicable meanings above rather than creating a separate lifecycle.

### Final requirement dispositions

Final reconciliation of `M5-REQ-*` rows uses: `COMPLETE`, `PARTIALLY MET`, `NOT MET`, `AWAITING EXTERNAL`, or justified `NOT APPLICABLE`. `PARTIALLY MET` means some but not all of the contractual requirement is evidenced; `NOT MET` means the requirement was evaluated and the required threshold/output was not achieved. Before final reconciliation, ordinary lifecycle states such as `NOT STARTED`, `IN PROGRESS`, `IN PLANNING`, or `NOT SUBMITTED` may still appear.

### Evidence status

Evidence uses: `PLANNED`, `CAPTURED`, `VERIFIED`, `SUPERSEDED`, and `REJECTED`.

- `CAPTURED` means an evidence item exists but has not completed independent verification.
- `VERIFIED` means the source, time/window, provenance where applicable, public/private boundary, and limitations have been checked.
- `SUPERSEDED` preserves historical provenance while pointing to a newer evidence item.
- `REJECTED` means the item was evaluated and is not acceptable evidence for the claimed purpose.

### Risk/decision register status

Risk rows use `OPEN`, `OPEN LIMITATION`, `EXTERNAL`, or `CONTROLLED`; decisions may use `ACCEPTED`. `OPEN` requires follow-up, `OPEN LIMITATION` records a known unresolved evidence/measurement constraint, `EXTERNAL` is primarily dependent on an outside party/platform, `CONTROLLED` has an active mitigation/gate, and `ACCEPTED` records an adopted project decision. More specific explanatory text may accompany these labels without changing their meaning.

## Critical ordering rule

Marketing must not launch before a defensible installable release path and measurement baseline are ready. PCR/PCV final drafting/recording must not begin before the material Milestone 5 outcomes and final figures are known.

## Explicit non-claims at scaffold creation

This scaffold does **not** claim that the plugin has been submitted to or approved by WordPress.org; that a compliant production release package exists; that the 25-download, 10-active-install, 250-unique-visit, 10-attendee, or 5-user-feedback thresholds have been reached; that early-adopter support has been delivered; that a post-launch adjustment has been implemented; or that PCR/PCV have been submitted or approved.
