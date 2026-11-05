# Milestone 5 — UI/UX Gate C implementation and validation register

**Updated:** 2026-11-06  
**State:** **IN IMPLEMENTATION / DRAFT PRs — no merge, deployed code or release**  
**Mandate:** Owner approved Gate B packages B1, B2 and B3, each on a separate branch. B4 remains conditional; B5 deferred.  
**Baseline:** `main@939c7f775d47bfa593f1089823f51cc9788d0e09`, release 0.26.0.  
**Evidence links:** [Gate A](ui-ux-gate-a-visual-audit-2026-11-05.md), [Gate B proposal](ui-ux-gate-b-proposal-2026-11-05.md), [intake #216](https://github.com/ROCSI-eu/WordPress-NMKR-Connect/issues/216), [feedback parent #165](https://github.com/ROCSI-eu/WordPress-NMKR-Connect/issues/165).

## Three separately scoped implementation PRs

| Package | Issue and Draft PR | Implemented branch behavior | Validation completed (not final acceptance) | Outstanding gate |
| --- | --- | --- | --- | --- |
| **B1: chain-safe explorer** | [#218](https://github.com/ROCSI-eu/WordPress-NMKR-Connect/issues/218), [PR #221](https://github.com/ROCSI-eu/WordPress-NMKR-Connect/pull/221) | CardanoScan URL only for explicitly Cardano projects with nonempty policy ID; no misleading CardanoScan for Solana/unknown. No invented Solana explorer destination. | Targeted PHP helper tests and shortcode contract PASS; isolated exact-branch WordPress browser smoke **4/4 PASS** (Cardano Project/Carousel keep link, Solana Project/Carousel omit link), no horizontal overflow. Existing escaping regression updated to validate shared URL helper + final escaped URL. | Latest exact-head Phase 4 CI; current Codex review; no merge approved. |
| **B2: NFT-first mobile** | [#219](https://github.com/ROCSI-eu/WordPress-NMKR-Connect/issues/219), [PR #222](https://github.com/ROCSI-eu/WordPress-NMKR-Connect/pull/222) | Grid/Carousel filters and project context become accessible progressive mobile disclosures; desktop and no-JS remain expanded, active search/filter stays open. Carousel mobile arrows visible and usable with truthful instructions. | PHP/JS syntax + static regressions PASS. Isolated Hello Elementor 320/390/768/1365 browser checks PASS without document/root overflow. At **390×844px**, first Grid NFT moved from **Y=1012 to Y=455** (**557px earlier**), Carousel from **Y=1183 to Y=603** (**580px earlier**). Both elements enter first viewport. Disclosures opened and active-filter case kept open. Isolated Twenty Twenty-Five passed four widths; mobile Grid **Y=447**, Carousel **Y=582**. **Phase 4 CI on `22ec0646...`: SUCCESS.** | Latest Codex review; focused accessibility/interaction acceptance in DEV before merge. |
| **B3: quick publish Shortcodes Admin** | [#220](https://github.com/ROCSI-eu/WordPress-NMKR-Connect/issues/220), [PR #223](https://github.com/ROCSI-eu/WordPress-NMKR-Connect/pull/223) | Capability-gated read-only local project/type/token selector; verified token UID ownership; ready-to-paste shortcode with Clipboard API/manual fallback; full five-shortcode reference behind accessible disclosure; no-JS GET form supported. Single-token dropdown/manual UID values mutually exclusive; conflicting supplied values fail closed. | PHP/JS lint, PHP 7.4 scanner, generator and Projects/Shortcodes UI and public shortcode regressions PASS. Isolated exact-branch authenticated browser PASS: 2 projects, 5 tokens within selected test project, Grid and single-token shortcode valid, one-click copy + feedback, collapsed/expandable reference, no-JS preview, 390px no overflow. **Five dedicated conflict/ownership scenarios PASS**. Codex initial two review findings addressed in branch. | Latest exact-head Phase 4 CI; fresh Codex review; E2E regression acceptance; no merge approved. |

**Validation boundaries:** Isolated local WordPress with synthetic tokens, loopback-only PHP server and private file permissions; not a real production deployment, release, live NMKR synchronization, customer installation, full WCAG 2.2 AA conformance, or WordPress.org publication. Screenshots/logs remain private until inspected/sanitized for public Catalyst evidence.

## Verification, decisions and release gates

- [x] Maintainer approved **implementation only** of B1+B2+B3; PRs opened as Draft, independent of M5 docs PR #217.
- [x] Main baseline pinned and preliminary source contracts tested; private exact-branch WordPress smoke for B1/B2/B3 executed.
- [x] B2 measurable visual before/after improvement captured on two themes in isolated environments.
- [x] B3 conflicting manual/dropdown UID issue found by Codex and handled with independent browser checks; existing E2E test modified to open the reference disclosure.
- [ ] **All** latest exact-head CI statuses verified PASS (including public-safe checks, dependency audits, synthetic security rendering).
- [ ] Latest Codex reviews reconciled; unresolved blocking comments addressed; no stale review claim.
- [ ] Exact-head premerge DEV acceptance and risk-based test closure as required by owner development workflow.
- [ ] Explicit owner approval for **each merge**; then separately authorized DEV deployment/verification, release/version management, and WordPress.org publication if intended.
- [ ] M5 final reporting should link **actual merged/released** change details, sanitized before/after, post-release impact and exact evidence; never count a Draft change as delivered.

## Feedback count and Catalyst claims

The initiating Cardano/WordPress community remark remains an **unqualified qualitative intake `M5-INTAKE-001`**, not a detailed, distinct `M5-FBK-###` record. A UX PR approved and developed because of this intake is evidence of triage and engineering work but **not** evidence that the five-detailed-users requirement has been met. The project must maintain the original accepted milestones and avoid implying WordPress.org approval, download/install growth, real-user adoption, or release of these Draft changes.
