# Release and WordPress.org submission

**Status:** IN PROGRESS — WORDPRESS.ORG REVIEW CHANGES REQUESTED\
**Document type:** Living release/submission record

The exact `0.25.0` candidate has been submitted to WordPress.org and the first human review has pended it for changes. The first submitted identity was **Connector for NMKR** / `connector-for-nmkr`. Issue #153 records the owner-approved distinctive replacement **ROCSI Connector for NMKR** / `rocsi-connector-for-nmkr`; issues #154–#157 track the other first-review findings. Do not upload an intermediate ZIP or reply to the reviewer until the complete remediation set is finished and one coherent replacement candidate is validated. No immutable GitHub release, directory approval, or WordPress.org SVN publication is asserted here.

## Release prerequisites

Before creating the Milestone 5 release candidate, record:

- resolved M5-01/M5-03 blocking decisions;
- exact source SHA/tree;
- release version and changelog disposition;
- build/package procedure;
- runtime dependency installation/vendor policy;
- files intentionally excluded from the distributable ZIP;
- third-party license/source requirements;
- external-service/readme disclosures;
- Plugin Check/readme/package validation results;
- risk-based exact-head DEV validation required by runtime changes.

Current release-identity decisions:

- first submitted display name: `Connector for NMKR`;
- first submitted/initially assigned slug: `connector-for-nmkr`;
- owner-approved review-remediation display name: `ROCSI Connector for NMKR`;
- requested replacement directory slug/text domain/package directory: `rocsi-connector-for-nmkr`;
- canonical public main plugin filename for the replacement candidate: `rocsi-connector-for-nmkr.php`;
- version: `0.25.0` under the repository [versioning policy](../versioning.md);
- established `nmkr_*` runtime/data contracts remain stable;
- `rocsi-connector-for-nmkr` is not claimed assigned, reserved, approved, or published until WordPress.org confirms it.

## Preflight baseline

Issue #127 began from verified preflight source and drove the submission candidate through the Plugin Check remediation and WordPress 7.1.1 compatibility gate. The exact submitted package came from source SHA `1ac89ffca2b0c853c2c81b70d585d6107d0ff3aa`, tree `61243015eac15b04f919c9af7f11e127ce661f1b`, and passed Plugin Check 2.1.0 with 0 errors and 310 audited residual warnings plus the official Readme Validator with 0 errors and 0 warnings. Replacement-package provenance must be recorded from the later post-remediation reviewed source; do not reuse the submitted candidate's checksum as evidence for the replacement.

## Release record

Populate only after the exact final release candidate exists.

| Field | Value |
| --- | --- |
| Version | `0.25.0` |
| Replacement source SHA | PENDING first-review remediation completion |
| Replacement source tree | PENDING first-review remediation completion |
| Replacement package filename | `rocsi-connector-for-nmkr-0.25.0.zip` |
| Replacement SHA-256 | PENDING exact candidate build |
| Build method | `npm run build:package` / `scripts/nmkr-build-package.sh` from an exact clean tree |
| Focused checks | PENDING final replacement-candidate validation |
| Exact-head DEV result | Risk-based; mandatory for any runtime/security-sensitive remediation |
| GitHub Release | NOT CREATED |

## GitHub Release

The public release should use the intentionally built installable plugin ZIP. Do not use automatic repository source archives as evidence of an installable package unless they have independently been proven complete.

Record release URL, version/tag, exact source commit, asset name/checksum, publication UTC, and initial release-asset counter baseline.

## WordPress.org submission

Current review state:

| Field | Value |
| --- | --- |
| Candidate version | `0.25.0` |
| First submitted identity | `Connector for NMKR` / `connector-for-nmkr` |
| Current submission status | PENDED FOR CHANGES |
| Requested replacement slug | `rocsi-connector-for-nmkr` — owner-approved, not yet allocated |
| Reviewer feedback | First-review remediation tracked by #153–#157 |
| Result | CHANGES REQUESTED — replacement ZIP and reply pending full remediation |

The intended WordPress.org contributor/submitter account is verified as `cyberspaceinitiative`. `Tested up to` was advanced to `7.1` only after the exact `0.25.0` candidate passed WordPress 7.1.1 compatibility validation under issue #127.

A submission receipt proves submission, not approval. `AWAITING EXTERNAL` should be used while review is pending.

## Reviewer-feedback loop

For material reviewer feedback:

1. verify the feedback applies to the exact current candidate/head;
2. classify whether it requires code, packaging, documentation, or product/name decisions;
3. use the repository's normal Codex/review/CI/risk-based validation workflow for changes;
4. record each reviewer request and disposition without publishing private account information;
5. update the candidate version/package if required;
6. never claim directory approval until WordPress.org actually publishes/accepts the plugin.

## SVN/directory release

After approval, document the exact SVN layout/procedure, stable tag/version alignment, `trunk`, version tags, directory assets, and release publication record actually used. Do not pre-claim this workflow before access is granted.

## Completion gate

M5-03 is not complete merely because a ZIP exists. At minimum the GitHub release path and WordPress.org submission/reviewer state must be recorded with exact provenance, and any known blocker must be truthfully represented.
