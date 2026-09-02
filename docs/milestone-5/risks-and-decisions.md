# Milestone 5 risks and decisions

**Status:** ACTIVE\
**Document type:** Living risk register and decision log

Risks are hypotheses until verified. Decisions should record the evidence/analysis that made them necessary and may be revised when new facts emerge.

## Initial risk register

| ID | Type | Item | Initial impact | Status | Planned resolution/mitigation |
| --- | --- | --- | --- | --- | --- |
| `M5-R01` | WordPress.org | Current local Premium-gated architecture may conflict with current directory trialware/serviceware rules | Critical | OPEN — requires exact-code/current-rule analysis | M5-01 |
| `M5-R02` | Naming | `NMKR Connect` / `nmkr-connect` may require trademark/project-name authorization or a safer slug/name strategy | Critical | OPEN | M5-01 owner/product/legal decision after current-rule verification |
| `M5-R03` | Licensing | Repository/plugin/dependency license metadata may be inconsistent or require redistribution clarification | High | OPEN | M5-01 exact distribution audit |
| `M5-R04` | Packaging | Raw source archives may not contain runtime Composer dependencies required for an installable plugin | High | OPEN | M5-01/M5-02 reproducible package path |
| `M5-R05` | Privacy/policy | NMKR/Freemius/GA4/other external-service behavior/disclosure/consent must be verified for the directory package | High | OPEN | M5-01 trace external calls and disclosures |
| `M5-R06` | External schedule | WordPress.org reviewer backlog/turnaround may extend the launch timeline | High | EXTERNAL | Submit a compliant candidate early; run GitHub release/adoption in parallel where messaging is accurate |
| `M5-R07` | Measurement | GitHub release download counters do not necessarily establish distinct human downloaders | Medium/High | OPEN LIMITATION | Define two-layer evidence/methodology in M5-04/M5-08 |
| `M5-R08` | Measurement | WordPress.org active-install figures may be bucketed/rounded and unavailable until listing is live | Medium | EXTERNAL | Record public bucket plus privacy-safe corroboration if needed |
| `M5-R09` | Measurement | Consent-based analytics may undercount unique visits | Medium | OPEN | Define source/consent/bot limitations before campaign; use operational margin rather than changing definitions later |
| `M5-R10` | Adoption | Contract targets depend on real users and cannot be guaranteed by engineering activity | High | OPEN | Release/measurement readiness, targeted outreach, event, support, truthful reporting |
| `M5-R11` | Post-launch | Acceptance/evidence language expects an implemented early fix/minor adjustment but a genuine issue may not immediately emerge | Medium/High | OPEN | Observe real usage; never manufacture a change; seek Catalyst clarification if necessary |
| `M5-R12` | Close-out | PCR/PCV prepared too early could contain stale or unsupported metrics/status | Medium | CONTROLLED | Gate final drafting/recording on M5-08 evidence maturity |

## Initial decisions

| ID | Decision | Rationale | Status |
| --- | --- | --- | --- |
| `M5-D01` | Use this directory as a living M5 control workspace; document presence does not imply completion | Prevents planning structure from becoming accidental evidence | ACCEPTED |
| `M5-D02` | Preserve the final Statement of Milestones requirements in traceability even if internal work packages change | Contract is authoritative; package numbering is internal | ACCEPTED |
| `M5-D03` | Do not launch marketing before usable release and measurement baselines are ready | Prevents losing defensible adoption evidence | ACCEPTED |
| `M5-D04` | Do not fabricate users, installs, visits, attendees, feedback, support cases, or post-launch changes | Contract outcomes depend on real adoption/community behavior | ACCEPTED |
| `M5-D05` | Keep personal/private adopter/support/analytics data out of the public repository; publish aggregates/redacted summaries | Public-repository safety and privacy | ACCEPTED |
| `M5-D06` | Defer final PCR/PCV drafting/recording until material M5 results are known | Prevent stale/unsupported close-out claims | ACCEPTED |
| `M5-D07` | Start M5 execution with WordPress.org/release-readiness analysis before modification | Current release architecture has unresolved high-impact policy/packaging questions | ACCEPTED |

## Update format

When adding a risk or decision, include date, exact relevant baseline where technical, evidence/findings link, owner decision if required, and the work-package impact. Do not delete resolved risks; mark them resolved/superseded so the rationale remains auditable.
