# WordPress.org readiness

## M5-01 decision baseline

Issue #107 completed the exact-code analysis on baseline `283ccb82a57bdce06d2fc3256caa6d03caa72583`. The owner resolved that all five shipped shortcodes are free, Freemius is removed, first-party source remains MIT, analytics remains available but defaults Off, uninstall cleanup is targeted, and the first public production version is `1.0.0`.

## M5-02 validated implementation

Issue #108 and PR #109 implemented those decisions: entitlement checks and licensing bootstrap/dependency paths are removed; analytics activation, sanitizer, reset, frontend, ingestion, and GA4 paths use an Off/safe default; the Privacy Policy Guide receives suggested text; deactivation/uninstall clear the analytics cron; uninstall removes the two plugin roles and exact `nmkr_*` capabilities; metadata/readme are aligned; and a clean-tree package builder emits one configurable top-level directory, a sorted file manifest, and SHA-256 provenance. PR #109 completed review, exact-head validation, merge, and post-merge validation before M5-02 was closed.

External-service disclosures cover administrator-triggered NMKR Studio API synchronization, optional Google Analytics 4 transmission, and visitor requests for remote NMKR/IPFS/gateway media. The validation path is `npm run test:release`, the PHP 7.4/static checks, and `npm run build:package` from a clean exact commit.

## M5-03 release identity

Issue #111 resolved the owner identity decision after current WordPress.org naming-policy analysis. The owner-selected public display name is **Connector for NMKR**. The candidate WordPress.org directory slug, gettext text domain, and release package directory are **`connector-for-nmkr`**. The established main plugin filename remains `nmkr-connect.php`, and existing `nmkr_*` database/options/transient/capability/AJAX/shortcode/internal-admin contracts remain stable unless a separate compatibility requirement justifies a change.

Draft PR #112 aligns the release candidate to that identity. This is release-preparation evidence, not directory acceptance: WordPress.org has not approved, assigned, reserved, or published the `connector-for-nmkr` slug, and no `1.0.0` GitHub release or WordPress.org submission has yet been made.

Two metadata gates remain external to the code edits: `Contributors: rocsi-eu` must be verified as the intended WordPress.org account before submission, and `Tested up to` must only be advanced from 6.8 after the candidate is actually tested on the corresponding current WordPress release.
