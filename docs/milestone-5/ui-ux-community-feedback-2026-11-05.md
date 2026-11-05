# M5 community UI/UX intake and post-0.26.0 assessment

**Status:** CODE REVIEW AND BOUNDED FRONTEND BROWSER QA COMPLETED / AUTHENTICATED ADMIN AND ACCESSIBILITY QA PENDING  
**Intake:** `M5-INTAKE-001` (2026-11-05), tracked in [issue #216](https://github.com/ROCSI-eu/WordPress-NMKR-Connect/issues/216) and parent M5-06/M5-07 [issue #165](https://github.com/ROCSI-eu/WordPress-NMKR-Connect/issues/165)  
**Repository baseline reviewed:** `main@939c7f775d47bfa593f1089823f51cc9788d0e09`; public plugin release `0.26.0`  
**Status of changes:** no UI implementation or WordPress.org release is authorized by this assessment.

> **Update (2026-11-05):** A bounded Gate A audit was performed after this preliminary intake. See [Gate A visual audit](ui-ux-gate-a-visual-audit-2026-11-05.md) for actual screenshot/layout results on all five shortcodes at 320/390/768/1365px, findings about mobile carousel guidance and Solana-sample explorer links, and explicitly limited WordPress Admin HTML-fixture checks. Real authenticated admin interactions, cross-theme/accessibility checks, and owner Gate B selection remain pending. No changes to runtime code or release are authorized.

## Source and faithful interpretation of the feedback

The maintainer relayed a general observation from **one person involved in the Cardano and WordPress community** that the plugin's UI/UX would benefit from a more modern presentation. The reported scope covers WordPress admin screens generally and NFT presentation on the public frontend via shortcodes.

This is a **maintainer-relayed qualitative intake**, not a verbatim quotation, user survey, a reproducible bug report, nor a detailed-user feedback record. The source's identity and correspondence are not stored in this public repository.

To qualify for `M5-FBK-###` counting under the Catalyst milestone's **five distinct detailed users** requirement, seek (if feasible) the user's task/context, specific friction or example, expected outcome, and impact. **Count currently: zero new qualifying detailed-user records established by this intake alone**. Do not count this again if the same contributor later provides more detailed feedback.

## Reconciliation with shipped visual work

A substantial pre-exposure facelift had **already shipped** through [#194](https://github.com/ROCSI-eu/WordPress-NMKR-Connect/issues/194) and [#195–#200](https://github.com/ROCSI-eu/WordPress-NMKR-Connect/issues/200) as part of release `0.26.0`:

- shared WordPress admin visual foundation across Dashboard, Projects, Shortcodes, Analytics and Settings;
- coherent style/focus/responsive conventions for all five public renderers;
- improved carousel keyboard controls, reduced motion, fallback states, semantic status and basic regression protection;
- integrated QA and release gate recorded in #200, with campaign handoff to #164.

This is a **post-facelift independent assessment**, not a claim that those improvements are missing or that previous acceptance was invalidated.

## Preliminary source inspection (2026-11-05)

| Surface | Verified from source | Improvement hypothesis / what needs visual confirmation | Disposition |
| --- | --- | --- | --- |
| Shared admin | `css/admin/nmkr-admin-foundation.css` defines scoped panels, status, spacing, focus and responsive treatment. | Compare task hierarchy, density, labels and primary actions in current screenshots rather than restyling globally. | Audit |
| Dashboard | `includes/pages/dashboard/nmkr-dashboard-ui.php` has connection/sync state, progress, latest result and collapsible performance/log diagnostics. | See whether first-time operators can distinguish setup, normal sync and technical recovery without excess scrolling. | Audit |
| Projects | Existing selector, project/token detail and filtering layouts use dedicated responsive CSS. | Observe a realistic project with both Cardano/Solana sample content, missing images, and long token names. | Audit |
| Admin Shortcodes | `includes/pages/shortcodes/nmkr-shortcodes.php` documents all five shortcodes, with sample UIDs in select/copyable examples. | Assess whether users can go from a synchronized project to a **valid, copied shortcode** without manual identifier transfer. A project-aware code generator is a candidate, not an agreed feature. | High-priority evaluation |
| Analytics/Settings | They have page-level style foundations and organized sections. | Identify any actual onboarding density and visual/interaction friction; retain privacy controls and diagnostics. | Audit |
| Grid / Carousel | Scoped responsive NFT cards, selectors/filters, status and prices; carousel uses non-inline controls and reduced motion. | Compare artwork size, card density, metadata legibility, purchase CTA prominence and multi-theme presentation. | High-priority evaluation |
| Token list | `css/nmkr-shortcode-list.css` sets a `760px` minimum-width table in an overflow-scrolling region; renderer makes it keyboard-focusable. | At 320–390px widths, compare current deliberate horizontal-scroll approach with a possible card/row alternate, **without losing dense data**. | Medium-priority evaluation |
| Single token/project | Separate responsive layouts reuse frontend primitives. | Review artwork composition (preserve full art), clear unavailable states, pricing/blockchain semantics and single primary action. | Medium-priority evaluation |
| Shared frontend | `css/nmkr-shortcode-foundation.css` scopes styling, responsive breakpoints, status treatments and `prefers-reduced-motion`. | Check actual theme conflicts, image fallback, keyboard paths and contrast. Avoid globally overriding the host theme. | Regression gate |

**Evidence limit:** Source inspection and the existing historical #194–#200 records do not prove the *current* frontend/admin visual quality, current user friction or WCAG conformance. No screenshots, browser runtime, independently observed customer reaction or new visual regression tests were completed for this intake. The above candidates are **not confirmed defects**.

## Recommended Gate A: independent visual/usability audit

1. Capture sanitized **current release** admin and frontend screenshots at desktop, tablet and narrow mobile widths; avoid real credentials and customer data.
2. Review each of the five admin screens, all five shortcode outputs, their empty/error/filtered/buyability/image states, and the complete setup → synchronize → publish workflow.
3. Use a default/neutral WordPress theme and another substantially different theme if feasible. Include keyboard/focus, zoom, touch, reduced-motion and contrast checks following current WordPress accessibility guidance.
4. For each issue, record screen, evidence type, actual vs expected, user impact, frequency if observed, regression risk and a proposed small change. Distinguish objective problems from subjective taste.
5. Preserve existing shortcode attributes, `nmkr_project` URL selection, `nmkr_*` persistence and caps, analytics event markers, privacy/security handling and NMKR Pay link behavior. Do not invoke real NMKR sync without explicit authorization.

## Candidate prioritization (pending Gate A)

| Priority | Candidate | Scope limit / test of value |
| --- | --- | --- |
| P1 | Shortcodes admin workflow | Make project selection and copy/paste reliable and intuitive with current synchronized data; no new block editor or page-builder requirement. |
| P1 | NFT presentation refinement | Improve real gallery/artwork/card/readability/action presentation only where screenshots show a benefit; keep complete NFT imagery and correct statuses. |
| P2 | Token-list mobile legibility | Compare intentional horizontal scroll with a narrower mobile alternative; preserve table semantics/accessibility/data. |
| P2 | Admin task hierarchy | Reduce cognitive load in setup/analytics/dashboard when actual task tests indicate friction; advanced tools remain available. |
| P2 | Cross-theme, contrast, keyboard polish | Repair verified conflicts; no global CSS or unneeded JS framework. |
| Defer | Gutenberg/Elementor integrations, major React/DataForm migration, new shortcode families or data/sync changes | Larger product scope; not justified by the currently general community observation. |

Design guidance: stay recognizably WordPress-native, lightweight and interoperable; consider WordPress Core UI components when warranted rather than automatically migrating legacy settings. Accessibility checks should align with [WordPress accessibility coding standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/accessibility/) (WCAG 2.2 AA), without making a conformance claim before testing.

## Decision, validation and close-out evidence

**Gate A:** deliver the actual browser/visual assessment and small numbered proposals in issue #216.  
**Gate B:** obtain owner approval of an exact bounded implementation subset; otherwise record deferrals without manufacturing work.  
**Gate C:** if approved, focused PRs plus relevant admin/shortcode/browser/security regressions, risk-based DEV QA, exact release/tag/SHA, WordPress.org publication verification and sanitized before/after captures.  

Link actual results into:
- `support-and-feedback.md` for intake and any later genuinely qualifying `M5-FBK-###`;
- `post-launch-enhancements.md` for the M5-07 decision, implementation and verification;
- `evidence-register.md` and `traceability.md` only when supported actual evidence is captured;
- `handover.md`, PCR and PCV with an accurate summary of what was observed and changed.

Relevant contractual paths: `M5-REQ-006`, `M5-REQ-014` to `M5-REQ-015`, `M5-REQ-017` to `M5-REQ-018`. None are completed by the current intake alone. Historical accepted milestone/0.26.0 evidence must remain unchanged.
