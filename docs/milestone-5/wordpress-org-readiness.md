# WordPress.org readiness

## M5-01 decision baseline

Issue #107 completed the exact-code analysis on baseline `283ccb82a57bdce06d2fc3256caa6d03caa72583`. The owner resolved that all five shipped shortcodes are free, Freemius is removed, first-party source remains MIT, analytics remains available but defaults Off, uninstall cleanup is targeted, and initially selected `1.0.0` as the first public production version. That version decision is now superseded by M5-D16 / issue #111; the other M5-01 decisions remain in force.

## M5-02 validated implementation

Issue #108 and PR #109 implemented those decisions: entitlement checks and licensing bootstrap/dependency paths are removed; analytics activation, sanitizer, reset, frontend, ingestion, and GA4 paths use an Off/safe default; the Privacy Policy Guide receives suggested text; deactivation/uninstall clear the analytics cron; uninstall removes the two plugin roles and exact `nmkr_*` capabilities; metadata/readme are aligned; and a clean-tree package builder emits one configurable top-level directory, a sorted file manifest, and SHA-256 provenance. PR #109 completed review, exact-head validation, merge, and post-merge validation before M5-02 was closed.

External-service disclosures cover administrator-triggered NMKR Studio API synchronization, optional Google Analytics 4 transmission, and visitor requests for remote NMKR/IPFS/gateway media. The validation path is `npm run test:release`, the PHP 7.4/static checks, and `npm run build:package` from a clean exact commit.

## M5-03 release identity

Issue #111 resolved the initial release identity as **Connector for NMKR** / **`connector-for-nmkr`**, and issue #126 aligned the initial canonical public main plugin file to `connector-for-nmkr.php`. That exact `0.25.0` candidate was subsequently submitted to WordPress.org. The first human review accepted the `for NMKR` relationship framing but found the generic leading term insufficiently distinctive.

Issue #153 records the owner-approved review-remediation identity **ROCSI Connector for NMKR** with directory slug, gettext text domain, and release package directory **`rocsi-connector-for-nmkr`**, plus canonical main plugin file `rocsi-connector-for-nmkr.php`. Existing `nmkr_*` database/options/transient/capability/AJAX/shortcode/internal-admin contracts remain stable. WordPress.org subsequently approved and assigned that slug.

The submitted source is SHA `1ac89ffca2b0c853c2c81b70d585d6107d0ff3aa`, tree `61243015eac15b04f919c9af7f11e127ce661f1b`. Before submission, the exact package passed WordPress 7.1.1 compatibility validation, focused install/activation and runtime smoke, release/package integrity checks, Plugin Check 2.1.0 with 0 errors and 310 audited residual warnings, and the official Readme Validator with 0 errors and 0 warnings.

The first-review remediation in issues #153–#157 completed successfully. WordPress.org approved the corrected identity **ROCSI Connector for NMKR** and assigned the final slug `rocsi-connector-for-nmkr`. Version `0.25.0` was published through SVN revision `3708672` on 23 September 2026, and issue #128 verified the public directory, Plugins API, and versioned download path.

M5-03 is therefore complete. The WordPress.org contributor/SVN account is `cyberspaceinitiative`, and `Tested up to: 7.1` remains evidence-backed by the exact candidate's WordPress 7.1.1 compatibility validation. Future releases follow `docs/wordpress-org-release.md`; WordPress.org SVN remains a release repository rather than a development branch.
