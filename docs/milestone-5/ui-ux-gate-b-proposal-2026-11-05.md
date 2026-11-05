# Milestone 5 — UI/UX Gate B decision proposal

**Date:** 2026-11-05  
**Status:** OWNER DECISION REQUIRED — NO CODE CHANGE APPROVED  
**Source:** [community feedback intake M5-INTAKE-001 / issue #216](https://github.com/ROCSI-eu/WordPress-NMKR-Connect/issues/216)  
**Evidence:** [Gate A report](ui-ux-gate-a-visual-audit-2026-11-05.md), [initial assessment](ui-ux-community-feedback-2026-11-05.md), pre-existing 0.26.0 facelift #194–#200  
**Parent delivery:** [M5-06/M5-07 feedback and post-launch adjustment #165](https://github.com/ROCSI-eu/WordPress-NMKR-Connect/issues/165)

## Decision context

The maintainer relayed **one general community observation** about improving the plugin's WordPress Admin and shortcode-rendered NFT UI/UX. It is **not** detailed qualifying `M5-FBK-###` feedback toward the Catalyst five-distinct-users target. It justified an independent audit, not unbounded feature work.

The 0.26.0 facelift already established coherent CSS primitives and improved **all five admin screens and all five public shortcodes**. The new audit found no reason to replace this system. Modernization should emphasize real user journeys and NFT-first presentation, avoiding a large redesign, framework migration or change to NMKR integration.

## Evidence behind this proposal

### Verified live WordPress DEV, plugin 0.26.0 / Hello Elementor

- Dedicated, approved **plugin-scoped QA account** successfully authenticated in a real WordPress admin browser; it lacks global `manage_options` and did not use sync, settings-save, delete, or elevated WordPress actions.
- **5 admin screens × 2 sizes (1365/390px)**: HTTP 200, actual WordPress navigation shell, no document/root overflow. A nonce-protected read-only **NFT Projects** selection displayed a project summary, filters, and ten sample token results.
- **5 frontend shortcode pages × 4 widths (320/390/768/1365px)**: HTTP 200 with shortcode markup and no page-wide overflow. The list's *internal* horizontal scroll is ≈462px at 320px and ≈392px at 390px; keyboard ArrowRight moved it 80px. Desktop carousel Next button and mobile keyboard region scrolling both work.
- Grid and List: first NFT content appears around **Y=942px** on a **390×844px** mobile viewport, beyond the first screen. This is a measurable first-impression/design issue, not a data-loss bug.
- Carousel: mobile previous/next controls are **CSS-hidden** while visible guidance still instructs using arrow buttons. Native touch/keyboard scrolling remains available.
- **Chain presentation defect confirmed:** canonical synthetic DEV project `SOL1 Neon Metropolis` has `blockchain=Solana` and a populated `policy_id`, yet project/carousel renderers output **CardanoScan** links based on policy-ID presence instead of chain context.

### Isolated second-theme run and bounded accessibility checks

- Created a **disposable, loopback-only WordPress installation** with version 0.26.0 and actual **Twenty Twenty-Five** active, seeded with **two synthetic projects and ten tokens**, with external payment links neutralized. **All five shortcode outputs at 390/1365px: HTTP 200, shortcode root present, no document/root overflow.** Actual TT5 theme CSS and the plugin CSS were loaded. The isolated server, database, database user, files and temporary credentials were cleaned after evidence capture; no canonical site theme/data changed.
- Authentic browser UI at mobile width ran with `prefers-reduced-motion: reduce`. Bounded DOM inspection across 5 admin + 5 frontend views found **no missing accessible name among sampled visible controls, no missing `alt` on sampled visible images, no sub-4.5 contrast result among sampled simple opaque text/background pairs**, and the initial Tab focus sample displayed visible focus. One short inline external link in Settings measured 77×15px, which **is not** enough alone to claim a touch-target failure without spacing/equivalent-target assessment.
- Dynamic Dashboard + Analytics UI was exercised with **locally mocked, public-safe AJAX responses only**. Dashboard displayed a generic test status; Analytics constructed filters, KPIs, charts, tables and the empty state. Five named actions were stubbed; no unexpected actions were attempted; **no live NMKR API call, sync, analytics ingestion or private report export** occurred.
- **Limitations:** This was not a WCAG certification, full manual TalkBack/VoiceOver test, comprehensive target-size/focus/contrast audit, active theme-switch test on the canonical site, real analytics backend proof, or direct end-user usability interview. These are implementation acceptance gates rather than blockers to deciding the scope.

## Recommended Gate B packages (owner may approve independently)

| Gate B ID | Priority | User-visible change | Evidence and bounded implementation | Risk |
| --- | --- | --- | --- | --- |
| **B1 — chain-safe explorer links** | **P1 — correctness** | Cardano projects retain accurate CardanoScan links; Solana projects do **not** advertise a CardanoScan destination. Offer a relevant Solana explorer link only if supported by verified project ID/URL semantics. | Current project and carousel renderers link from nonempty `policy_id` alone. Add explicit chain-aware conditional checks and Cardano/Solana regression cases, including missing/unknown chain. | Low–medium |
| **B2 — NFT-first mobile collection UX** | **P1 — visual experience** | Grid and Carousel put artwork/browsing earlier on narrow screens. Reduce repetitive selection/filter/summary vertical footprint through compact, clearly labelled disclosure; preserve access to filters, stats and project links. Harmonize mobile carousel instructions with actual touch/keyboard/visible controls. | Live 390px first NFT at Y≈942px; isolated TT5 Grid first item at Y≈1027px, Carousel at Y≈1188px. Style/markup refinements scoped to shortcode classes, not host theme. | Medium |
| **B3 — Shortcodes Admin quick-publish experience** | **P1 — usability** | Choose a synchronized project (and token for `[nmkr-token]`), choose one of five shortcode types, **generate a valid ready-to-copy shortcode**, retain the full reference in progressive disclosure. Clear no-project/invalid selection guidance; keyboard-accessible copy fallback. | Current page repeats five technical examples with sample UIDs and manually transferred identifiers; mobile reference is long. Use existing local data and shortcode contracts, not a page-builder or Gutenberg rewrite. | Medium |
| **B4 — NFT list mobile discoverability** | **P2 — optional after B2** | Introduce an explicit horizontal-scroll affordance, or compare a compact stacked mobile list while retaining all status/price/CTA information and table/keyboard access. | Existing 760px table is deliberately horizontally scrollable. Avoid treating the implementation as a defect or replacing table semantics without acceptance evidence. | Low–medium |
| **B5 — admin settings/analytics polishing** | **P3 — defer** | Minor wording/section density only if further real-user feedback identifies a task obstacle. | Existing 0.26.0 UI is coherent; screen-level review found no compelling general redesign need. | Low |

### Recommended implementation order

1. **B1** as a narrow correctness patch (confirmed chain-context defect). Do not invent an unsupported Solana explorer URL; first review source data and semantics.
2. **B2** with before/after mobile screenshots in both Hello Elementor and Twenty Twenty-Five. Keep complete NFT art, purchase CTA, blockchain/price/status labels, all original filter behaviors and shortcode identifiers. Target: noticeably earlier gallery visibility at 390×844 rather than arbitrary animation or broad CSS changes. Capture first-card Y before/after.
3. **B3** focused WordPress Admin improvement with existing local project/token data. Validate empty state, long UIDs, accessibility, copy behavior, and no Javascript fallback. No persistence/model/API changes.
4. Decide on **B4** only after B2 observation; **defer B5** absent a demonstrated issue.

**Recommended initial approval subset:** **B1 + B2 + B3** as separate, reviewable issues/PRs; B4 conditional; B5 deferred. These are **proposals, not approved changes**.

## Acceptance and safety conditions for each selected PR

- Scope to the chosen UI/UX behavior; no opportunistic synchronization redesign, schema/API changes, credentials exposure, new shortcode families, platform identity changes or framework migration.
- Preserve all five shortcode names, public attributes, `nmkr_project` URL precedence, project selection, token status/price and NMKR Pay semantics, analytics markup/consent, role/capability boundaries and privacy.
- Validate **320/390/768/1365px**, mobile scrolling, mouse/touch, keyboard and visible focus, accessible labels, reduced motion, image fallback, long names and empty/error cases.
- Run targeted PHP/static/Playwright/JS/WordPress regressions, review CI and perform exact-head DEV checks with synthetic data. Apply accessibility checks appropriate to the change; **do not claim WCAG conformance without full evidence**.
- For cross-theme CSS changes, test actual shortcode output in Hello Elementor and an isolated Twenty Twenty-Five instance as performed at Gate A.
- Every PR must capture sanitized **before/after images** and measurable effect on the particular user journey, not just claim that the UI “looks modern”.
- No real NMKR synchronization, WordPress.org SVN release, production/deployment action or main merge without separate explicit owner authorization.

## Catalyst Milestone 5 evidence trail

Record the intake and the audit under `M5-INTAKE-001` / #216, link each **approved and actually executed** improvement to its issue, PR, validation, exact release/version and observed result. Reconcile to `post-launch-enhancements.md`, `support-and-feedback.md`, `evidence-register.md`, `traceability.md`, then handover/PCR/PCV.

These artifacts support **feedback triage and prioritization**, not automatically qualifying five distinct detailed users, 25 downloads, 10 installs, WCAG AA conformance, or a completed post-launch adjustment. Do not retroactively revise already approved M1–M4 deliverables.

## Gate B owner decision to collect

- **B1:** approve / defer / reject
- **B2:** approve / defer / reject
- **B3:** approve / defer / reject
- **B4:** conditional / approve / defer / reject
- **B5:** recommend defer

**No code implementation is initiated by this proposal.** Authorize only the selected packages when ready, then open focused implementation issues and keep each PR Draft until reviewed.
