# Milestone 5 risks and decisions

**Status:** ACTIVE\
**Document type:** Living risk register and decision log

Risks are hypotheses until verified. Decisions should record the evidence/analysis that made them necessary and may be revised when new facts emerge.

## Risk register

| ID | Type | Item | Initial impact | Status | Planned resolution/mitigation |
| --- | --- | --- | --- | --- | --- |
| `M5-R01` | WordPress.org | Current local Premium-gated architecture may conflict with current directory trialware/serviceware rules | Critical | MITIGATED — PR #109 removed the local entitlement/upsell architecture, made all five shipped shortcodes free, and completed review plus exact-head/post-merge validation | Keep future paid features additive and separately distributed |
| `M5-R02` | Naming | `NMKR Connect` / `nmkr-connect` may require trademark/project-name authorization or a safer slug/name strategy | Critical | MITIGATED BY OWNER DECISION — issue #111 selects the non-brand-leading `Connector for NMKR` identity with `connector-for-nmkr` as the candidate slug/text domain; directory assignment remains external | Align the release candidate in PR #112; do not claim the slug is approved/reserved until WordPress.org assigns it |
| `M5-R03` | Licensing | Repository/plugin/dependency license metadata may be inconsistent or require redistribution clarification | High | MITIGATED — PR #109 aligned first-party free-plugin code and release metadata to MIT and completed review/validation | Preserve accurate current notices and verify final package metadata |
| `M5-R04` | Packaging | Raw source archives may not contain runtime Composer dependencies required for an installable plugin | High | MITIGATED — Freemius/runtime Composer packages are removed and purpose-built package tooling verifies extracted contents; PR #109 release/package checks passed | Publish only a verified purpose-built release artifact from the final reviewed commit |
| `M5-R05` | Privacy/policy | NMKR/Freemius/GA4/other external-service behavior/disclosure/consent must be verified for the directory package | High | MITIGATED — Freemius is removed; analytics defaults Off with fail-safe consent defaults and disclosure/privacy guidance; PR #109 exact-head/post-merge validation completed | Preserve deliberate opt-in configuration and accurate external-service disclosures through release review |
| `M5-R06` | External schedule | WordPress.org reviewer backlog/turnaround may extend the launch timeline | High | EXTERNAL | Submit a compliant candidate early; run GitHub release/adoption in parallel where messaging is accurate |
| `M5-R07` | Measurement | GitHub release download counters do not necessarily establish distinct human downloaders | Medium/High | OPEN LIMITATION | Define two-layer evidence/methodology in M5-04/M5-08 |
| `M5-R08` | Measurement | WordPress.org active-install figures may be bucketed/rounded and unavailable until listing is live | Medium | EXTERNAL | Record public bucket plus privacy-safe corroboration if needed |
| `M5-R09` | Measurement | Consent-based analytics may undercount unique visits | Medium | OPEN | Define source/consent/bot limitations before campaign; use operational margin rather than changing definitions later |
| `M5-R10` | Adoption | Contract targets depend on real users and cannot be guaranteed by engineering activity | High | OPEN | Release/measurement readiness, targeted outreach, event, support, truthful reporting |
| `M5-R11` | Post-launch | Acceptance/evidence language expects an implemented early fix/minor adjustment but a genuine issue may not immediately emerge | Medium/High | OPEN | Observe real usage; never manufacture a change; seek Catalyst clarification if necessary |
| `M5-R12` | Close-out | PCR/PCV prepared too early could contain stale or unsupported metrics/status | Medium | CONTROLLED | Gate final drafting/recording on M5-08 evidence maturity |

## Decisions

| ID | Decision | Rationale | Status |
| --- | --- | --- | --- |
| `M5-D01` | Use this directory as a living M5 control workspace; document presence does not imply completion | Prevents planning structure from becoming accidental evidence | ACCEPTED |
| `M5-D02` | Preserve the final Statement of Milestones requirements in traceability even if internal work packages change | Contract is authoritative; package numbering is internal | ACCEPTED |
| `M5-D03` | Do not launch marketing before usable release and measurement baselines are ready | Prevents losing defensible adoption evidence | ACCEPTED |
| `M5-D04` | Do not fabricate users, installs, visits, attendees, feedback, support cases, or post-launch changes | Contract outcomes depend on real adoption/community behavior | ACCEPTED |
| `M5-D05` | Keep personal/private adopter/support/analytics data out of the public repository; publish aggregates/redacted summaries | Public-repository safety and privacy | ACCEPTED |
| `M5-D06` | Defer final PCR/PCV drafting/recording until material M5 results are known | Prevent stale/unsupported close-out claims | ACCEPTED |
| `M5-D07` | Start M5 execution with WordPress.org/release-readiness analysis before modification | Current release architecture has unresolved high-impact policy/packaging questions | ACCEPTED |
| `M5-D08` | Make all five currently shipped shortcodes free in the WordPress.org plugin; future Premium functionality must be new/additive and separately distributed | Avoid local paid gates over shipped directory functionality | ACCEPTED — issue #107 |
| `M5-D09` | Remove Freemius entirely from the free/WordPress.org plugin | Keeps the free package self-contained and avoids directory entitlement/update ambiguity | ACCEPTED — issue #107 |
| `M5-D10` | Defer the final public name/slug decision; prefer `NMKR Connect` / `nmkr-connect` only if WordPress.org eligibility is established, otherwise use a non-brand-leading fallback | WordPress.org slug/brand eligibility cannot be satisfied by ordinary permission alone | SUPERSEDED FOR RELEASE IDENTITY BY M5-D15 — issue #107 accurately records the earlier deferral |
| `M5-D11` | Retain MIT for first-party free-plugin code and reconcile metadata/notices consistently | Preserves permissive first-party licensing while remaining compatible with the intended distribution model | ACCEPTED — issue #107 |
| `M5-D12` | Retain local analytics and optional GA4, but default analytics Off and require deliberate administrator enablement with safe consent/privacy handling | Preserves optional analytics while minimizing default collection and disclosure risk | ACCEPTED — issue #107 |
| `M5-D13` | Clean up plugin-owned roles/capabilities and recurring analytics cron on uninstall, while preserving the explicit analytics uninstall-data choice | Avoid unnecessary plugin-owned residue without inventing broader destructive cleanup | ACCEPTED — issue #107 |
| `M5-D14` | Use `1.0.0` as the first public production/WordPress.org release version | Treats Milestone 5 as the first stable public release rather than an early prototype | ACCEPTED — issue #107 |
| `M5-D15` | Use `Connector for NMKR` as the WordPress.org public display name and `connector-for-nmkr` as the candidate directory slug, gettext text domain, and release-package directory; preserve `nmkr-connect.php` and established `nmkr_*` runtime contracts | Uses a clear non-brand-leading third-party integration identity without forcing a compatibility-breaking rename of established technical contracts | ACCEPTED — owner decision recorded in issue #111; WordPress.org slug assignment remains external |

## Update format

When adding a risk or decision, include date, exact relevant baseline where technical, evidence/findings link, owner decision if required, and the work-package impact. Do not delete resolved risks; mark them resolved/superseded so the rationale remains auditable. Never recycle a risk or decision ID for a different subject.
