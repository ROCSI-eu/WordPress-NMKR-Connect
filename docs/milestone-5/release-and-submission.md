# Release and WordPress.org submission

**Status:** COMPLETE — WORDPRESS.ORG APPROVED, SVN-PUBLISHED, AND PUBLICLY AVAILABLE\
**Document type:** Finalized first-release/submission record

Milestone 5 M5-03 is complete for the first public WordPress.org release.

WordPress.org approved **ROCSI Connector for NMKR** with final slug/text-domain/package identity **`rocsi-connector-for-nmkr`**. Version **`0.25.0`** was published through WordPress.org SVN at revision **`3708672`** on **2026-09-23 07:04:31Z**, and the public directory/API/download path was verified afterward.

The historical first submission used **Connector for NMKR** / `connector-for-nmkr` and was pended for changes. Issues #153–#157 record that review/remediation history; issues #111 and #128 are the canonical final release/publication control records.

## Final public release identity

- display name: `ROCSI Connector for NMKR`;
- final WordPress.org slug: `rocsi-connector-for-nmkr`;
- text domain/package directory: `rocsi-connector-for-nmkr`;
- canonical main plugin file: `rocsi-connector-for-nmkr.php`;
- public stable version: `0.25.0`;
- public directory: https://wordpress.org/plugins/rocsi-connector-for-nmkr/;
- SVN repository: https://plugins.svn.wordpress.org/rocsi-connector-for-nmkr;
- WordPress.org contributor/SVN account: `cyberspaceinitiative`.

## Final release seal

| Field | Value |
| --- | --- |
| Version | `0.25.0` |
| Exact Git source SHA | `5b12e674e6312170cae132f40c710e97fe29dfdf` |
| Exact Git tree | `941b7f21ecd67a22a0ae84f90cc700fb88cbceb3` |
| Historical reviewed package | `rocsi-connector-for-nmkr-0.25.0.zip` |
| Historical reviewed ZIP SHA-256 | `23878cc4f8e1734b8fc01784d2a072b4124cfdcd9becdf856f49d6389a3f25a2` |
| WordPress.org SVN revision | `3708672` |
| Publication UTC | `2026-09-23 07:04:31Z` |
| Public directory/API/download verification | PASS |
| Public versioned download verification | PASS |

The historical reviewed ZIP hash is preserved as evidence of the package reviewed by WordPress.org. WordPress.org generates its own public ZIP from SVN; that generated artifact is not substituted for the historical reviewed ZIP hash.

## Review and publication history

1. The initial `0.25.0` candidate was submitted under `Connector for NMKR` / `connector-for-nmkr`.
2. The first human review requested changes, including a more distinctive leading identifier.
3. Issues #153–#157 completed the reviewer-remediation set.
4. The owner-approved identity became `ROCSI Connector for NMKR` / `rocsi-connector-for-nmkr`.
5. WordPress.org approved the corrected submission and assigned the final slug.
6. Issue #128 performed the guarded first SVN publication and verified public availability.

No real NMKR synchronization was required for submission or publication.

## Ongoing release policy

GitHub remains the development source of truth; WordPress.org SVN is a release repository.

For every release after `0.25.0`:

- use the maintained procedure in [../wordpress-org-release.md](../wordpress-org-release.md);
- keep plugin `Version`, `readme.txt` `Stable tag`, SVN numeric tag, package identity, changelog, and Git provenance coherent;
- publish only a purpose-built, validated installable package/payload;
- preserve the fixed public slug `rocsi-connector-for-nmkr`;
- record release URL/revision, exact Git SHA/tree, package/checksum provenance, publication UTC, and post-publication verification;
- do not treat SVN as a development branch.

## Measurement handoff

M5-03 publication completion makes the public distribution path usable for M5-04/M5-05 adoption measurement. It does **not** establish any download, active-install, visit, attendee, or feedback threshold by itself.

The current adoption/measurement contract is maintained in [adoption-and-metrics.md](adoption-and-metrics.md).
