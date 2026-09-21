# Release and WordPress.org submission

**Status:** IN PROGRESS — FINAL EXACT-CANDIDATE PREFLIGHT**Document type:** Living release/submission record

No GitHub release or WordPress.org submission is asserted by this document. Issue #111 records the owner-selected public identity **Connector for NMKR** and `connector-for-nmkr` as the candidate WordPress.org slug/text domain/package directory. The release-identity implementation is merged; issue #127 is now the final exact-candidate preflight before any immutable tag or external submission.

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

- display name: `Connector for NMKR`;
- candidate directory slug/text domain/package directory: `connector-for-nmkr`;
- earlier M5-D15 filename decision: retain `nmkr-connect.php` for compatibility — superseded before submission by M5-D17 / issue #126;
- current canonical public main plugin filename: `connector-for-nmkr.php`;
- version: `0.25.0` under the repository [versioning policy](../versioning.md);
- established `nmkr_*` runtime/data contracts remain stable;
- `connector-for-nmkr` is not claimed approved, assigned, reserved, or published by WordPress.org.

## Preflight baseline

Issue #127 started from verified clean `main` source SHA `195e3d15494959bf4471bec18b1341686710bd6e`, tree `2d0f1b1eabf8501124ff87282a367a2904f68295`. Final package provenance must be recorded from the post-preflight reviewed source, not copied from an earlier candidate or PR.

## Release record

Populate only after the exact final release candidate exists.

| Field | Value |
| --- | --- |
| Version | `0.25.0` |
| Source SHA | PENDING #127 final candidate freeze |
| Source tree | PENDING #127 final candidate freeze |
| Package filename | `connector-for-nmkr-0.25.0.zip` |
| SHA-256 | PENDING exact candidate build |
| Build method | `npm run build:package` / `scripts/nmkr-build-package.sh` from an exact clean tree |
| Focused checks | PENDING #127 exact-candidate validation |
| Exact-head DEV result | PENDING #127 risk classification / validation |
| GitHub Release | NOT CREATED |

## GitHub Release

The public release should use the intentionally built installable plugin ZIP. Do not use automatic repository source archives as evidence of an installable package unless they have independently been proven complete.

Record release URL, version/tag, exact source commit, asset name/checksum, publication UTC, and initial release-asset counter baseline.

## WordPress.org submission

Populate after actual submission:

| Field | Value |
| --- | --- |
| Candidate version | `0.25.0` |
| Submitted UTC | NOT SUBMITTED |
| Requested slug | `connector-for-nmkr` planned; not assigned/reserved |
| Submission reference/status | NOT SUBMITTED |
| Reviewer feedback | NONE YET |
| Result | NOT SUBMITTED |

Before submission, verify that `Contributors: rocsi-eu` names the intended WordPress.org account and only advance `Tested up to` after actual testing against the corresponding current WordPress release.

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
