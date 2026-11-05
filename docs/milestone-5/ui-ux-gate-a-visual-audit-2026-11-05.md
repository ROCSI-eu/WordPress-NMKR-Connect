# M5 Gate A — post-0.26.0 UI/UX visual audit (2026-11-05)

**Status:** PARTIALLY EXECUTED — frontend visual/layout inspection completed; authenticated WordPress-admin runtime and interaction acceptance remains OPEN.  
**Origin:** [M5-INTAKE-001 / issue #216](https://github.com/ROCSI-eu/WordPress-NMKR-Connect/issues/216); feedback workflow [#165](https://github.com/ROCSI-eu/WordPress-NMKR-Connect/issues/165).  
**Implementation/release:** NONE. This report documents audit evidence and candidates, not a decision to redesign or release an update.

## Precisely what was exercised

### Frontend: live public DEV render

- Entry URL: `https://dev.nmkrconnect.io/` (legacy host). **HTTP redirects** to `https://connector-for-nmkr.rocsi.eu/wp-dev/`; the frontend screenshots are of the **redirect destination**, not of an independently rendered legacy-host site.
- Target DEV plugin at redirect destination: installed source header **0.26.0** (verified by read-only filesystem inspection).
- Browser: isolated headless Chrome 156 on the authorized WordPress VM. Public WordPress pages were loaded by HTTP GET, without login, mutation, NMKR sync or buy-button clicks.
- Captured 10 private screenshots, all five frontend shortcodes at **1365px desktop** and **390px mobile**, each HTTP 200 and each rendering a `.nmkr-shortcode` root:
  - `[nmkr-grid]` → `nmkr-connect-grid-shortcode`
  - `[nmkr-token-list]` → `nmkr-connect-project-token-list`
  - `[nmkr-carousel]` → `nmkr-connect-project-carousel`
  - `[nmkr-token]` → `nmkr-connect-single-token-details`
  - `[nmkr-project]` → `nmkr-connect-project-summary`
- Additional DOM/layout checks at **320px** and **768px** for all five rendered pages (10 more view-width observations).
- No page-level or `.nmkr-shortcode` root horizontal overflow was detected at 320, 390, 768, or 1365px for the actual fixtures checked.
- The token-list table **does** require *local* horizontal scrolling: approximately **462px** extra internal scroll at the 320px viewport, **14px** at 768px; the local scroll region had `tabindex="0"`. This is expected from current `760px` minimum-width table styling, **not a whole-page overflow bug**.
- Browser automation blocked non-GET requests and known analytics/AJAX calls to avoid counting synthetic audit interactions as actual engagement. The evaluation does **not** verify tracking/analytics processing, live buying, filter submissions or live sync.

These are representative **synthetic/staging collection pages**, including `ADA1 Elemental Realms Water` and `SOL1 Neon Metropolis`. Do not interpret sample sales, prices, counts, project links or names as verified blockchain production data. Some images were offscreen/lazy at the time of full-page capture; no inference of broken image loading is made without a scrolled, settled rendering check.

### WordPress Admin: source-backed fixtures (not authenticated browser pages)

- Rendered the actual plugin PHP callbacks via **WP-CLI** from the separate legacy DEV WordPress root `/var/www/dev.nmkrconnect.io/public_html`, whose installed plugin is also `0.26.0`.
- Existing administrator capabilities were used **inside WP-CLI for read-only template rendering**; the browser did not receive a real WordPress login session. Form `value` attributes, textarea contents, and diagnostic log content were redacted in the private static fixtures.
- Captured Dashboard, NFT Projects, Shortcodes, Analytics and Settings PHP markup with plugin CSS in isolated **1365px and 390px** browser views (10 private fixture screenshots). None exhibited page or plugin-root horizontal overflow in the resulting static fixture.
- The **legacy-root database had zero projects** in this CLI context, while the redirected `/wp-dev` frontend rendered sample projects. Consequently, the Projects admin fixture could show only the **empty** state. The rendered data sets are not interchangeable and no cross-environment consistency claim is made.
- The PHP-rendered fixtures cannot show real logged-in menu chrome, JavaScript/AJAX populated states, live interaction, all form control behavior, exact WordPress-admin browser authentication state, or server-side permission/capability outcomes. Analytics and Dashboard loading placeholders in these fixtures **must not be misclassified as runtime bugs**.
- No real credentials, API keys or private logs were published. Audit scripts/HTML/screenshots/metrics were retained as private temporary artifacts on the authorized VM, not committed to the public repository.

**Evidence boundary:** This is a genuine frontend browser + an administrative markup/styling review, **not** an independent authenticated end-to-end usability or accessibility pass. Gate A remains OPEN for the latter.

## Visual findings: current behavior vs targeted opportunity

| Ref | Surface | Observation supported by current evidence | Suggested disposition | Required confirmation |
| --- | --- | --- | --- | --- |
| `UX-01` | Admin Shortcodes | Five complete reference cards, each with repeated attributes/selection explanations; examples use sample UIDs and require manual replacement. The long static mobile page is dense. | **P1 candidate**: small project/token-aware shortcode generator with ready-to-copy valid example and collapsible advanced reference; no Gutenberg migration. | Authenticated workflow test: synchronized project → copy shortcode → display on a page, with and without JS. |
| `UX-02` | Grid + Carousel mobile | The project selector, filter panel and five project summary counters appear **before** the NFT imagery; artwork can start far below the first mobile viewport. Card art and purchase actions themselves are coherent. | **P1 candidate**: prioritize artwork/browse action with a shorter default summary, compact counters, or optional progressive disclosure; preserve important context. | Screenshot comparison and basic task test on narrow phone + desktop, with data/state variants. |
| `UX-03` | Token list mobile | Table keeps useful density but most columns lie to the right of viewport. Local overflow is measured, not conjectural. | **P2 candidate**: make horizontal-scroll discoverability more explicit, then compare optional compact-card mobile presentation; do not discard data or degrade table semantics. | Mobile keyboard, touch, 320/390px, screen reader and contrast checks. |
| `UX-04` | Carousel mobile instruction | At 390px and 320px, CSS hides both previous/next buttons (`display:none` for <=640px), while visible descriptive text still mentions arrow-button navigation. Native touch/trackpad scroll remains. | **P1 small consistency fix**: adapt instruction to visible control mode and/or expose clearly labelled mobile controls while preserving horizontal touch browsing. | Touch and keyboard navigation checks on current and proposed layouts. |
| `UX-05` | SOL example / external blockchain explorer | Live frontend sample project `SOL1 Neon Metropolis`, displaying `0.0300 SOL`, also displays `View on CardanoScan`. Project shortcode source constructs CardanoScan link whenever `policy_id` exists without an explicit blockchain gate in that code branch. | **P1 investigation**: verify underlying mock blockchain/policy data and ensure explorer links are context-correct for Cardano versus Solana. Do not invent or link a Solana explorer without reviewing correct identifiers and semantics. | Read-only project chain-field confirmation and exact URL expectation; focused renderer regression with Cardano and Solana fixtures. |
| `UX-06` | Single NFT | Public mobile screenshot shows prominent artwork, status, price and NMKR Pay CTA in one clear composition. | **Keep / P3**: no new redesign solely for visual novelty. | Representative long text, missing-image and non-buyable states. |
| `UX-07` | Dashboard | Source-backed admin fixture shows status → sync action → summary hierarchy with subordinate diagnostics, consistent with completed #194–#200 facelift. | **Keep / P3** absent real user friction. | Authenticate in actual DEV admin, check active/finished/error states and role behavior. |
| `UX-08` | Settings | Source-backed 390px fixture shows WordPress-native form sections and advanced synchronization/debug disclosure; many settings remain visible. JS was not exercised. | **P2 evaluation**: assess first-time configuration flow; consider GA4-specific progressive help when mode is Off, only if real interaction validates the issue. | Real session, value retention, disabled/conditional state and assistive tech. |
| `UX-09` | Projects + Analytics admin | Structural empty-state/analytics-shell fixture exists but lacked live project data and JavaScript-populated analytics. | **Unverified**: do not claim defects or success. | Authenticated real page with prepared dataset, no real sync. |
| `UX-10` | Overall visual system | Shared admin/frontend design foundations are visibly more coherent than old pre-0.26 fragmented styles. | **Retain design system**; avoid broad rewrite. | Full authenticated cross-theme/accessibility acceptance. |

## Verification checklist and open gates

- [x] Reconcile with prior 0.26.0 UI/UX program (#194–#200), avoiding duplicate speculative rework.
- [x] Confirm active DEV 0.26.0 source at the frontend redirect destination and distinguish the legacy WordPress root.
- [x] Screenshot all five actual public shortcode outputs at desktop/mobile using synthetic data.
- [x] Check all five at 320/390/768/1365px for page/root overflow; identify token-list internal scroll accurately.
- [x] Inspect actual source for carousel mobile controls and CardanoScan project-link conditions.
- [x] Create privacy-redacted structural admin fixtures for all five main screens and mark limitations.
- [ ] Authenticate and interact with **real** WordPress Admin at the **same** chosen DEV site; verify Dashboard, Projects, Shortcodes, Analytics, Settings, normal/capability-limited roles, live AJAX states and project data.
- [ ] Focused keyboard, touch, screen-reader/focus, zoom, and measured WCAG contrast checks for the selected candidates.
- [ ] Validate frontend against at least one additional WordPress theme, state variants and real project chain metadata.
- [ ] Present bounded Gate B proposal for owner selection and GO/NO-GO after remaining Gate A evidence; no implementation is presumed.

## Preliminary implementation sequence (not approved)

1. Correct verified **inaccurate visible instructions** in the mobile carousel and reconcile chain-appropriate explorer links after data verification (small fixes; high signal).
2. Improve **Shortcodes Admin onboarding** to produce usable project-/token-specific examples, if authenticated task testing confirms friction.
3. Improve **mobile NFT discovery**, moving useful artwork toward first viewport and making list interaction clear without changing shortcode contracts.
4. Only then consider minor polishing of Settings/Analytics, subject to actual runtime findings.

**Safety gate:** Do not change existing shortcode names/attributes, `nmkr_project` query precedence, buyability or linked NFT meaning, analytics event attributes/consent, credentials, sync/API/database logic or WordPress package/release identity. Approval for **audit** is not permission for implementation, merge, deployment or WordPress.org publication.

## Milestone 5 evidence usage

- The original qualitative intake remains **`M5-INTAKE-001`**, **not** a qualifying detailed-user `M5-FBK-###`; no new distinct-user count inferred.
- The dated audit, bounded screenshots and source findings can substantiate **assessment and prioritization**, but not implemented-change, user-adoption, WCAG conformance or Catalyst close-out success.
- If a selected improvement is approved, subsequently record the PR, review/CI, DEV exact-head validation, released version and before/after observation under #165, `post-launch-enhancements.md`, and the M5 evidence/traceability registers.
- Private capture directory (internal reference only, **not a public evidence artifact**): `/home/mihai/.local/state/nmkr-connect/m5-uiux-gatea-20261105/`. Do not commit or expose runtime files/captured page data without independent review and sanitization.
