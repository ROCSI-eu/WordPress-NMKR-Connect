# Early-adopter support and feedback

**Status:** NOT STARTED\
**Document type:** Support/feedback methodology and eventual public-safe record

## Early-adopter definition

For Milestone 5 evidence, an early adopter is a distinct person/site operator who installs/activates the released plugin and attempts genuine use. A webinar spectator who never attempts the plugin is not automatically an adopter.

## Support channels

Use channels that can be maintained and evidenced without leaking private data, such as public GitHub issues and, once available, the WordPress.org support forum. Private email/direct-message support may be provided where necessary but only aggregate/redacted summaries belong in this repository.

## Support-case schema

Use opaque IDs such as `M5-SUP-001` in public records.

| Field | Meaning |
| --- | --- |
| Support ID | Stable opaque ID |
| Opened UTC | Date/time received |
| Channel class | GitHub / WordPress.org / private email / event follow-up / other |
| Category | Install, configuration, sync, rendering, compatibility, performance, billing/licensing, docs, other |
| Problem summary | Public-safe description |
| Response | What guidance/action was provided |
| Resolution | Resolved, workaround, issue opened, deferred, unable to reproduce, etc. |
| Linked issue/PR | Public link where applicable |
| Evidence ID | Supporting `M5-EVD-*` record |

Do not publish names, emails, site URLs, API keys, raw logs, account identifiers, or private transcripts.

## Detailed-feedback definition

A distinct user contributes qualifying detailed feedback when the record contains enough information to influence prioritization, for example:

- user context or intended task;
- observed problem, friction, missing capability, or confusing behavior;
- expected/desired outcome;
- impact/severity or why it matters;
- reproduction detail or concrete suggestion where relevant.

`Looks good`, social reactions, or duplicate comments from the same person do not count as separate detailed-user feedback records.

## Feedback record

Use opaque IDs such as `M5-FBK-001` and keep any identity-to-ID mapping private.

| Feedback ID | Received UTC | Context/problem | Expected outcome | Impact | Decision/issue | Evidence ID |
| --- | --- | --- | --- | --- | --- | --- |
| TBD | TBD | TBD | TBD | TBD | TBD | TBD |

## Preliminary community feedback intake (not yet qualifying detailed-user feedback)

This intake log is **separate from** the `M5-FBK-###` table, which is reserved for feedback meeting the detailed-user definition above.

| Intake ID | Received UTC date | Channel / source class | Public-safe observation | Triage / linked issue | Qualification |
| --- | --- | --- | --- | --- | --- |
| `M5-INTAKE-001` | 2026-11-05 | Maintainer-relayed Cardano/WordPress community observation | General request to evaluate and further modernize plugin WordPress admin UI/UX and the frontend NFT rendering of shortcodes | [Post-release audit #216](https://github.com/ROCSI-eu/WordPress-NMKR-Connect/issues/216); [M5-06/07 #165](https://github.com/ROCSI-eu/WordPress-NMKR-Connect/issues/165); [assessment](ui-ux-community-feedback-2026-11-05.md) | **Pending details; not counted** toward five distinct detailed users |

**Boundary:** no verified direct quote, identity, reproduction steps or impact statement was supplied. An intake is useful for prioritization but cannot be promoted to a qualifying `M5-FBK-###` record without sufficient additional substance. Do not count the same person twice.

Planning target: seek feedback from more than five people so that the contract does not depend on every response qualifying as detailed/actionable. The exact outreach number may change.

## Feedback-to-issue workflow

1. sanitize and classify feedback;
2. determine whether it is a defect, usability/documentation issue, compatibility/performance concern, feature request, or non-actionable preference;
3. reproduce/verify before promising a code change;
4. create/link a public issue when suitable and public-safe;
5. prioritize based on impact, frequency, risk, milestone relevance, and scope;
6. route a selected genuine post-launch adjustment to M5-07;
7. record deferred/rejected requests and rationale without pretending all feedback was implemented.

## Response expectation

The milestone requires responsive initial post-launch support, not an unlimited SLA. Record actual response/resolution timing where available and summarize the support period at M5-08.

## Completion gate

M5-06 should not be marked complete until support activity is truthfully summarized and at least five distinct-user detailed feedback records are analyzed, or any shortfall is explicitly recorded for later Catalyst reporting.
