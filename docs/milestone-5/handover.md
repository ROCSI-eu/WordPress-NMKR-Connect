# Maintenance and handover

**Status:** IN PLANNING\
**Document type:** Final handover completeness checklist

Milestone 5 should consolidate and validate existing documentation rather than duplicate mature user/developer/security material unnecessarily.

## Existing public documentation to revalidate

- repository README/documentation hub;
- user guide;
- comprehensive FAQ;
- troubleshooting guide;
- developer guide;
- validation policy;
- prior milestone security/testing/evidence documentation.

M5-08 should verify that these still match the final release and update only what the release actually changes.

## Final handover areas

| Area | Expected final state | Status |
| --- | --- | --- |
| User installation/configuration | Accurate for the released package and dependencies | TO REVIEW |
| NMKR API setup/use | Current and public-safe | TO REVIEW |
| Supported shortcodes/features | Matches free/released behavior | TO REVIEW |
| Troubleshooting | Covers common install/sync/render/support paths | TO REVIEW |
| Developer architecture | Current flows, data model, hooks, authorization, analytics/privacy, tests | TO REVIEW |
| Release/build/package procedure | Reproducible process documented | NOT STARTED |
| WordPress.org maintenance | Submission/SVN/version/readme/assets process documented after actual workflow is known | NOT STARTED |
| Dependency maintenance | Update/audit expectations documented | TO REVIEW |
| Support route | GitHub/WordPress.org/private escalation routes accurately stated | NOT STARTED |
| Known limitations | Final release limitations explicit | NOT STARTED |
| Security reporting | Existing route reviewed; add narrow guidance only if genuinely missing | TO REVIEW |
| Public source provenance | Final SHA/tree/tag/release recorded | NOT STARTED |

## Release-maintainer handover

Do not invent a large maintainer manual if existing developer documentation already explains architecture. Add the narrow operational material that is genuinely missing, especially:

- how to build an installable release from an exact commit;
- how runtime Composer dependencies are included;
- package validation/checksum procedure;
- version/readme/changelog alignment;
- WordPress.org SVN publication workflow once learned through actual approval;
- dependency/static-check cadence;
- support/issue triage and privacy boundary.

## Documentation validation

M5-08 should perform a focused documentation review against the final release. For each linked document, confirm technical accuracy, navigation, stale milestone wording, current file/command names, external-service behavior, and public/private safety.

## Handover completion record

Populate only after validation:

| Item | Evidence ID / reference | Disposition |
| --- | --- | --- |
| User docs reviewed | TBD | TBD |
| Developer docs reviewed | TBD | TBD |
| Release procedure validated | TBD | TBD |
| Support path documented | TBD | TBD |
| WordPress.org maintenance path documented | TBD | TBD |
| Final public source/release recorded | TBD | TBD |

This file supports handover evidence; it does not itself prove that the final documentation is accurate until the final review is executed.
