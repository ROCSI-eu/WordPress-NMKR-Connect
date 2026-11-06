# Milestone 5 — UI/UX Gate C implementation and validation register

**Updated:** 2026-11-06\
**State:** **B1+B2+B3 MERGED / DEV DEPLOYED / DEV ACCEPTED — NOT PUBLICLY RELEASED**\
**Original implementation baseline:** `main@939c7f775d47bfa593f1089823f51cc9788d0e09` (public 0.26.0).\
**Merged main:** `42ef2a44e6a3515e84797b6e76220c7b76233e01` (6 November 2026 checkpoint).\
**Parent:** [M5-06/07 feedback triage #165](https://github.com/ROCSI-eu/WordPress-NMKR-Connect/issues/165); [community intake #216](https://github.com/ROCSI-eu/WordPress-NMKR-Connect/issues/216).\
**Earlier decisions:** [Gate A audit](ui-ux-gate-a-visual-audit-2026-11-05.md) and [Gate B approval](ui-ux-gate-b-proposal-2026-11-05.md).

## Implemented scope and GitHub provenance

| Package | Issue / merged PR | Shipped to `main` (not WordPress.org) | Validation / outcome |
| --- | --- | --- | --- |
| **B1 — chain-safe explorer** | [#218](https://github.com/ROCSI-eu/WordPress-NMKR-Connect/issues/218) / [#221](https://github.com/ROCSI-eu/WordPress-NMKR-Connect/pull/221) | Squash merge `ab8c28920677678baa4453007e4b8b0abc919979` | CardanoScan shown only for explicit Cardano chain with policy ID. Solana/unknown omitted; isolated WordPress browser **4/4 PASS**. |
| **B2 — NFT-first mobile Grid/Carousel** | [#219](https://github.com/ROCSI-eu/WordPress-NMKR-Connect/issues/219) / [#222](https://github.com/ROCSI-eu/WordPress-NMKR-Connect/pull/222) | Merge `3c95557d1f750b47c89116d195a202aead4f3071` | Mobile disclosures reduce visual overhead; carousel controls remain visible. In isolated Hello Elementor at 390px, Grid first NFT **Y=1012→455** and Carousel **Y=1183→603**; isolated Twenty Twenty-Five passed at 320/390/768/1365px. |
| **B3 — project-aware shortcode quick publish** | [#220](https://github.com/ROCSI-eu/WordPress-NMKR-Connect/issues/220) / [#223](https://github.com/ROCSI-eu/WordPress-NMKR-Connect/pull/223) | Merge `42ef2a44e6a3515e84797b6e76220c7b76233e01`; reviewed head `2f41b340a48c0769bb796a990ae4574941ac2a1a` | Capability-gated read-only generator uses locally validated project/token IDs, safe copy fallback and collapsible reference. Token dropdown/manual input conflicts fail closed; project changes clear stale token choices. Private browser and targeted regression checks PASS. |

**Codex and CI:** B1/B2/B3 completed the relevant code reviews and Phase 4 CI prior to merge. B3's initial review findings were resolved; final exact-head Codex review on `2f41b34...` completed with no new or unresolved findings. B1's final post-review follow-up affected a test, not implementation behavior.

**Combined-source integration:** B1 and B2 overlapped in the Carousel renderer. They were integrated sequentially B1→B2→B3, with clean combined Git merge-tree checks and targeted B1/B2/B3/public-shortcode/security-contract regressions passing on merged `main`.

## Exact-commit WordPress DEV deployment and acceptance

The owner separately approved the controlled DEV deployment and validation after merge. On 2026-11-06:

- Fetched the exact `main` SHA `42ef2a44...`; used the canonical clean-tree package builder to create the **0.26.0-development-candidate payload** (0.26.0 is still the public release version, **not** a new release tag).
- Verified a purpose-built package manifest (**79 files**), without Git metadata, repository-only test tools, or documentation. Verified package checksum and runtime file readability.
- Verified site identity, active plugin, WordPress DEV environment, and idle synchronization before changing files. Kept a private file rollback and a private database backup. WordPress remained installed and plugin active.
- Post-deploy: manifest verification, PHP lint across installed plugin files, five focused B1/B2/B3/public shortcode/security regressions, canonical WP-CLI smoke and read-only DB-state checks **PASS**.
- Browser acceptance: **five published frontend shortcode pages × 390px/1365px = 10/10 PASS** (HTTP 200, rendered shortcode roots, no page-wide overflow); mobile disclosures and Carousel controls present.
- Authenticated scoped QA browser acceptance **PASS** for Shortcodes Admin (generator, project preview, reference disclosure, and project-switch stale-token clearing).
- Final source exact-ref cleanliness, installed package manifest, plugin state, NMKR configuration-options equality and idle synchronization checks **PASS**. A temporary QA login failure was resolved by a fresh successful browser run; no product defect was asserted from that preliminary attempt.
- Sanitized completion receipt: [PR #223 final DEV acceptance comment](https://github.com/ROCSI-eu/WordPress-NMKR-Connect/pull/223#issuecomment-6673230095). Private screenshots, credentials, database backup and diagnostic logs remain off GitHub.

**Scope boundary:** This is acceptance on the project's DEV WordPress instance using available local/QA data, **not** a WordPress.org release, Production installation, adopter observation, full WCAG certification or proof of audience/metric thresholds. No real NMKR synchronization was executed.

## Remaining release and Catalyst gates

- [x] B1/B2/B3 implementation, code review, CI, GitHub merges, exact-commit DEV deployment and post-merge acceptance.
- [ ] Select and approve an independent release candidate/version per [versioning policy](../versioning.md) and [WordPress.org release checklist](../wordpress-org-release.md). B3 adds visible functionality; `0.27.0` is the **proposed**, not yet approved, next minor version.
- [ ] Prepare and review the release-version/changelog PR; validate exact package, update/install behavior and STAGING release gate before separately approved WordPress.org SVN/GitHub publication.
- [ ] Obtain genuine post-publication usage/support observations, adoption measurements and qualifying detailed feedback. An unqualified community intake is **not** one of the five distinct detailed user records.
- [ ] Reconcile resulting release/evidence with the M5 traceability, handover, PCR/PCV and final close-out material; do not infer adoption impact from the code change alone.

**Not selected:** B4 remains conditional on real mobile List friction; B5 is deferred. Avoid broad redesign or additional feature work without evidence.
