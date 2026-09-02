# Milestone 5 evidence register

**Status:** PLANNED\
**Document type:** Evidence schema and canonical index

No execution evidence is created merely by this scaffold. Add evidence entries only after an actual event, measurement, review, submission, support interaction, release, validation, or other qualifying activity occurs.

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

## Initial foundation entries

### M5-FND-001 — Milestone 5 delivery scaffold

- **Requirements:** all, as planning/traceability only
- **Work package:** pre-M5-01 scaffold
- **Description:** establishes the living delivery plan, requirement matrix, evidence schema, domain workspaces, risk/decision log, and close-out gates.
- **Source/platform:** public GitHub repository
- **Relevant SHA/version:** to be filled with the exact scaffold PR head/merge after review
- **Public/private class:** public
- **Status:** PLANNED until this scaffold is merged and provenance is recorded
- **Limitations/non-claims:** planning foundation only; proves no adoption, submission, support, post-launch adjustment, or Catalyst completion outcome.

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
