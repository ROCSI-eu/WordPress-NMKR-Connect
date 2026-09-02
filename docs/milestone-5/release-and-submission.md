# Release and WordPress.org submission

**Status:** NOT STARTED\
**Document type:** Living release/submission record

No GitHub release or WordPress.org submission is asserted by this scaffold.

## Release prerequisites

Before creating the Milestone 5 release candidate, record:

- resolved M5-01 blocking decisions;
- exact source SHA/tree;
- release version and changelog disposition;
- build/package procedure;
- runtime dependency installation/vendor policy;
- files intentionally excluded from the distributable ZIP;
- third-party license/source requirements;
- external-service/readme disclosures;
- Plugin Check/readme/package validation results;
- risk-based exact-head DEV validation required by runtime changes.

## Release record

Populate only after an actual release candidate exists.

| Field | Value |
| --- | --- |
| Version | TBD |
| Source SHA | TBD |
| Source tree | TBD |
| Package filename | TBD |
| SHA-256 | TBD |
| Build method | TBD |
| Focused checks | TBD |
| Exact-head DEV result | TBD / NOT REQUIRED |
| GitHub Release | TBD |

## GitHub Release

The public release should use an intentionally built installable plugin ZIP when Composer/runtime dependencies are required. Do not use automatic repository source archives as evidence of an installable package unless they have independently been proven complete.

Record release URL, version/tag, exact source commit, asset name/checksum, publication UTC, and initial release-asset counter baseline.

## WordPress.org submission

Populate after actual submission:

| Field | Value |
| --- | --- |
| Candidate version | TBD |
| Submitted UTC | TBD |
| Requested slug | TBD |
| Submission reference/status | TBD |
| Reviewer feedback | TBD |
| Result | TBD |

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
