# Milestone 5 evidence register

**Status:** IN PROGRESS\
**Document type:** Evidence schema and canonical index

Add evidence entries only after an actual event, measurement, review, submission, support interaction, release, validation, or other qualifying activity occurs. Planning and implementation foundations may be recorded, but they do not by themselves prove a contractual outcome.

## Evidence namespace

Use stable IDs:

- `M5-FND-###` — analysis, planning, methodology, or implementation foundations that do not by themselves prove a contractual outcome;
- `M5-EVD-###` — executed evidence, observed metrics, submissions, support/community activity, validated releases, or completed bounded assessments.

Do not recycle IDs. If an entry becomes obsolete, mark it `SUPERSEDED` and link the successor.

## Entry schema

Each evidence record should include:

| Field | Meaning |
| --- | --- |
| Evidence ID | Stable `M5-FND-*` or `M5-EVD-*` identifier |
| Requirement(s) | `M5-REQ-*` rows supported |
| Work package | Package that produced or consumed the evidence |
| Description | What actually happened/was observed |
| Source/platform | GitHub, WordPress.org, site analytics, event platform, support channel, Catalyst, private DEV, etc. |
| Captured UTC | Time evidence was captured |
| Relevant SHA/version | Exact repository/release/runtime identity where applicable |
| Public/private class | What may be published and what corroboration remains private |
| Public reference | Public URL/file/PR/commit/report when available |
| Private corroboration | `YES`, `NO`, or `NOT NEEDED`; never include secrets/private paths |
| Status | `PLANNED`, `CAPTURED`, `VERIFIED`, `SUPERSEDED`, or `REJECTED` |
| Limitations/non-claims | Measurement, scope, causality, privacy, or external-status limitations |

## Foundation entries

### M5-FND-001 — Milestone 5 delivery scaffold

- **Requirements:** all, as planning/traceability only
- **Work package:** pre-M5-01 scaffold
- **Description:** establishes the living delivery plan, requirement matrix, evidence schema, domain workspaces, risk/decision log, and close-out gates.
- **Source/platform:** public GitHub repository
- **Captured UTC:** 2026-09-02
- **Relevant SHA/version:** PR #105 merge `2c746d39b0b785e12e0d6898b2805859915dee36`; tree `884f3c5b7034048878214536728c99a15a42962e`
- **Public/private class:** public
- **Public reference:** PR #105 and `docs/milestone-5/`
- **Private corroboration:** NOT NEEDED
- **Status:** VERIFIED
- **Limitations/non-claims:** planning foundation only; proves no adoption, submission, support, post-launch adjustment, or Catalyst completion outcome.

### M5-FND-002 — M5-01 release-readiness analysis and owner decisions

- **Requirements:** `M5-REQ-010`, `M5-REQ-011`, `M5-REQ-013` as release-planning foundations only
- **Work package:** M5-01
- **Description:** records the WordPress.org/release-readiness analysis and owner decisions covering free shortcode availability, Freemius removal, MIT licensing, analytics defaults/privacy direction, uninstall cleanup, release version, and the deferred name/slug gate.
- **Source/platform:** public GitHub issue #107
- **Captured UTC:** 2026-09-02
- **Relevant SHA/version:** analysis baseline `283ccb82a57bdce06d2fc3256caa6d03caa72583`; tree `9d2bf30c729253b6a8fbc70c5d7b76fe77c27285`
- **Public/private class:** public
- **Public reference:** issue #107
- **Private corroboration:** NOT NEEDED
- **Status:** VERIFIED
- **Limitations/non-claims:** analysis and decision evidence only; no WordPress.org submission, acceptance, release validation, or runtime validation claim.

### M5-FND-003 — M5-02 validated release architecture

- **Requirements:** `M5-REQ-010`, `M5-REQ-011`, `M5-REQ-013` as implementation foundations only
- **Work package:** M5-02
- **Description:** records the reviewed and merged implementation of the free release architecture, dependency removal, privacy/lifecycle changes, metadata, and package tooling, together with its sanitized validation result.
- **Source/platform:** public GitHub issue #108, PR #109, and private DEV validation
- **Captured UTC:** 2026-09-03
- **Relevant SHA/version:** reviewed PR #109 head `f40073d9888ef8b2c4b82bf67f352ff57a1c5d09`; merged main `3712406c38f801fa81a30782991a34e4a028b902`; shared tree `da32cd2b9c6ab41c53526992506d7bd9555d583e`
- **Public/private class:** public
- **Public reference:** issue #108 and PR #109
- **Private corroboration:** YES
- **Status:** VERIFIED
- **Limitations/non-claims:** exact-head and post-merge DEV validation and focused release/package checks passed without real NMKR synchronization. Package execution was not repeated on DEV because that VM lacked the `zip` command. This foundation establishes M5-02 completion only; final release publication, name/slug selection, WordPress.org submission, and directory acceptance remain pending under M5-03.

## Executed evidence entries

### M5-EVD-001 — synchronized pre-campaign adoption baseline

- **Requirements:** `M5-REQ-002`, `M5-REQ-003`, `M5-REQ-004`, `M5-REQ-016`
- **Work package:** M5-04
- **Description:** synchronized T0 baseline. Cloudflare showed **10 Visits / 11 Page views** for the canonical filtered page. WordPress.org reported **42** cumulative downloads and the public active-install bucket **Fewer than 10**. GitHub Releases was empty, so no installable GitHub Release asset existed at T0.
- **Source/platform:** Cloudflare Web Analytics; WordPress.org Plugins API/public directory; GitHub Releases API/repository
- **Captured UTC:** `2026-09-27T05:21:31Z` (Europe/Bucharest `2026-09-27 08:21:31`, UTC+03:00)
- **Relevant SHA/version:** website `3671f1bd24646385b316fa84ab621b8e53356e8a`; plugin main `f14d53dd80405c8a1062dabafdb7b7cee56d7f2f`; WordPress.org `0.25.0`; SVN r`3708672`
- **Public/private class:** public aggregate values and methodology; authenticated Cloudflare screenshot retained separately as corroboration
- **Public reference:** canonical landing page, WordPress.org plugin page, GitHub repository, issue #163, and website Phase 6 issue #11
- **Private corroboration:** YES
- **Status:** VERIFIED
- **Limitations/non-claims:** Cloudflare `Visits` are not deduplicated people. Filters were Site `rocsi.eu`, Exclude bots `Yes`, Host `connector-for-nmkr.rocsi.eu`, Path `/`, with dashboard window `Last 24 hours (GMT+3)`. That is a rolling pre-campaign snapshot, not a cumulative counter. WordPress.org `downloaded=42` is cumulative and not a distinct-human count. Active installs are publicly bucketed as `Fewer than 10`; do not infer an exact count. GitHub asset baseline is not applicable because no Release asset existed. Pre-baseline verification traffic is excluded from campaign growth claims.

### M5-EVD-002 — formal GitHub 0.25.0 release publication

- **Requirements:** `M5-REQ-002`, `M5-REQ-011`, `M5-REQ-016`
- **Work package:** M5 pre-launch distribution provenance / issue #188
- **Description:** formal GitHub Release `0.25.0` was published for the already-public WordPress.org stable version. The tag resolves exactly to the approved source commit. The installable asset and checksum asset were published, and their initial GitHub download counters were captured at zero.
- **Source/platform:** GitHub Release/tag/assets API and public repository
- **Captured UTC:** `2026-10-05T13:47:12Z`
- **Relevant SHA/version:** tag `0.25.0` -> commit `5b12e674e6312170cae132f40c710e97fe29dfdf`; tree `941b7f21ecd67a22a0ae84f90cc700fb88cbceb3`; installable asset SHA-256 `d0f80324736a4e91b21bb512e22ee03c249c2b730076a35bafa29a70daf36ef6`
- **Public/private class:** public
- **Public reference:** https://github.com/ROCSI-eu/WordPress-NMKR-Connect/releases/tag/0.25.0 and issue #188
- **Private corroboration:** NOT NEEDED
- **Status:** VERIFIED
- **Limitations/non-claims:** this release was created after the sealed M5 T0 checkpoint, where no GitHub Release asset existed. Initial `download_count=0` is a platform counter baseline, not a human-adopter count. The GitHub asset is the public WordPress.org-generated versioned ZIP whose extracted payload was independently verified identical to a package built from the exact approved source SHA; its archive hash remains distinct from the historical reviewer-submission ZIP hash. This evidence does not by itself establish a new synchronized Cloudflare/WordPress.org/GitHub adoption checkpoint or complete the campaign download requirement.

## Public/private evidence boundary

### Suitable for the public repository

- aggregate counts and date windows;
- platform/source and measurement methodology;
- public campaign URLs/posts;
- public WordPress.org/GitHub/Catalyst references;
- redacted screenshots or exports where actually useful;
- exact public Git SHAs, release versions, and checksums;
- public-safe support/feedback summaries using opaque identifiers;
- documented limitations and non-claims.

### Keep private

- names, emails, direct-message identifiers, private site URLs, IPs, raw client identifiers;
- credentials, tokens, API keys, analytics secrets, private infrastructure details;
- raw private logs, database content, customer/adopter records, support transcripts, registration exports;
- private VM paths, deployment credentials, authenticated screenshots, or private evidence manifests.

## Verification rule

An entry becomes `VERIFIED` only after its source, timestamp/window, relevant SHA/version where applicable, public/private boundary, and limitations have been independently checked. A planning target or placeholder is never `VERIFIED` evidence.
