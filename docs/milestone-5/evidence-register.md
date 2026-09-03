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

### M5-FND-003 — M5-02 implementation-stage release architecture

- **Requirements:** `M5-REQ-010`, `M5-REQ-011`, `M5-REQ-013` as implementation foundations only
- **Work package:** M5-02
- **Description:** tracks the implementation of the free release architecture, dependency removal, privacy/lifecycle changes, metadata, and package tooling.
- **Source/platform:** public GitHub issue #108 and PR #109
- **Captured UTC:** 2026-09-02
- **Relevant SHA/version:** final reviewed PR #109 head to be recorded after review completes
- **Public/private class:** public
- **Public reference:** issue #108 and PR #109
- **Private corroboration:** YES
- **Status:** CAPTURED
- **Limitations/non-claims:** implementation-stage foundation only; exact-head private runtime validation is still pending where required, and independent review, merge, final artifact validation, naming, submission, and directory acceptance remain pending.

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
