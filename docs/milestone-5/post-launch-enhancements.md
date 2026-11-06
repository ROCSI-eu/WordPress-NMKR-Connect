# Post-launch enhancements

**Status:** B1+B2+B3 MERGED / DEV ACCEPTED — AWAITING VERSIONED PUBLIC RELEASE; DETAILED USER FEEDBACK STILL PENDING\
**Document type:** Post-launch adjustment selection and validation record

The final Statement of Milestones acceptance/evidence language expects early bug fixes or minor adjustments to be documented, implemented, and verified. The project will not manufacture an artificial change solely to satisfy that wording.

## Qualifying adjustment

A qualifying M5-07 change should arise from legitimate post-launch evidence such as:

- a real defect found by an early adopter;
- onboarding/configuration friction;
- a usability or accessibility problem;
- a compatibility issue;
- an observed performance problem;
- a small behavior/documentation-interface mismatch requiring code or packaged-product correction.

A documentation-only typo, arbitrary refactor, synthetic defect, or unrelated feature expansion is weak evidence and should not be selected merely to create a post-launch PR.

## Candidate findings

Populate from M5-06/support/adoption evidence.

| Candidate | Origin evidence | Impact | Scope/risk | Decision | Rationale |
| --- | --- | --- | --- | --- | --- |
| `M5-INTAKE-001` — post-release admin/frontend UI/UX review | Maintainer-relayed qualitative Cardano/WordPress community observation (2026-11-05); [#216](https://github.com/ROCSI-eu/WordPress-NMKR-Connect/issues/216) | Gate A confirmed mobile NFT-first layout friction, Solana project CardanoScan mismatch and manual shortcode authoring overhead | Three bounded P1 packages; verified on DEV, **not yet shipped to WordPress.org** | **B1+B2+B3 MERGED / DEV ACCEPTED** at `main@42ef2a44...`; B4 conditional, B5 deferred | [Gate C register](ui-ux-gate-c-implementation-2026-11-06.md), merged [#221](https://github.com/ROCSI-eu/WordPress-NMKR-Connect/pull/221), [#222](https://github.com/ROCSI-eu/WordPress-NMKR-Connect/pull/222), [#223](https://github.com/ROCSI-eu/WordPress-NMKR-Connect/pull/223). This intake **does not** establish five qualifying detailed users or a released user-facing adjustment. |

## Selected adjustment

Owner initially selected **B1 (chain-safe explorer links), B2 (NFT-first mobile Grid/Carousel), and B3 (project-aware Shortcodes Admin quick-publish)** on 2026-11-06. All three were later **merged** and **validated on DEV** under separate authorizations; see the [Gate C implementation and validation register](ui-ux-gate-c-implementation-2026-11-06.md). **No WordPress.org 0.27.0 release, public update installation, adoption outcome, or qualifying five-user feedback is established.** A public versioned release and post-release observation remain separate gates.

Record:

- originating support/feedback/usage evidence ID;
- exact baseline SHA/tree;
- root cause;
- intended scope/files;
- acceptance criteria;
- risk classification;
- focused tests;
- DEV validation requirement;
- PR/review/CI state;
- exact release containing the adjustment.

Broad, security-sensitive, sync-heavy, database-sensitive, authorization, persistent-state, or external-API work should receive `CODEX — ANALYSIS ONLY` before modification. Narrow compatible fixes may proceed through the repository's standard modification/review path once the problem is understood.

## Verification

Verification must match risk. Runtime-sensitive work requires exact-head private DEV validation under the normal safety rules. Documentation/UI/test-only adjustments without runtime impact may not require DEV.

## Post-change observation

After release, record what can actually be observed:

- affected user/support outcome;
- recurrence or absence of the original symptom;
- relevant usability/performance measure where available;
- adoption/support metric checkpoint before and after where meaningful.

Do not claim that one adjustment **caused** broader download/install growth without evidence capable of supporting that inference.

## No-genuine-change contingency

If no legitimate qualifying adjustment emerges after a reasonable early-adopter/support period, document that fact and seek current Catalyst clarification/change handling rather than inventing work or retroactively labelling an unrelated change as feedback-driven.
