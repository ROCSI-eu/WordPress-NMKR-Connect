# WordPress.org readiness

## M5-01 decision baseline

Issue #107 completed the exact-code analysis on baseline `283ccb82a57bdce06d2fc3256caa6d03caa72583`. The owner resolved that all five shipped shortcodes are free, Freemius is removed, first-party source remains MIT, analytics remains available but defaults Off, uninstall cleanup is targeted, and initially selected `1.0.0` as the first public production version. That version decision is now superseded by M5-D16 / issue #111; the other M5-01 decisions remain in force.

## M5-02 validated implementation

Issue #108 and PR #109 implemented those decisions: entitlement checks and licensing bootstrap/dependency paths are removed; analytics activation, sanitizer, reset, frontend, ingestion, and GA4 paths use an Off/safe default; the Privacy Policy Guide receives suggested text; deactivation/uninstall clear the analytics cron; uninstall removes the two plugin roles and exact `nmkr_*` capabilities; metadata/readme are aligned; and a clean-tree package builder emits one configurable top-level directory, a sorted file manifest, and SHA-256 provenance. PR #109 completed review, exact-head validation, merge, and post-merge validation before M5-02 was closed.

External-service disclosures cover administrator-triggered NMKR Studio API synchronization, optional Google Analytics 4 transmission, and visitor requests for remote NMKR/IPFS/gateway media. The validation path is `npm run test:release`, the PHP 7.4/static checks, and `npm run build:package` from a clean exact commit.

## M5-03 release identity

Issue #111 resolved the owner identity decision after current WordPress.org naming-policy analysis. The owner-selected public display name is **Connector for NMKR**. The candidate WordPress.org directory slug, gettext text domain, and release package directory are **`connector-for-nmkr`**. The earlier M5-D15 decision retained `nmkr-connect.php`; M5-D17 / issue #126 superseded only that filename-retention clause before submission, making `connector-for-nmkr.php` the canonical public main plugin file. Existing `nmkr_*` database/options/transient/capability/AJAX/shortcode/internal-admin contracts remain stable.

The release-identity implementation is merged. Issue #127 now owns the final exact-candidate submission preflight for `0.25.0`. The verified preflight baseline is source SHA `195e3d15494959bf4471bec18b1341686710bd6e`, tree `2d0f1b1eabf8501124ff87282a367a2904f68295`, with no open PR at the start of #127. This remains release-preparation evidence, not directory acceptance: no `0.25.0` GitHub release or WordPress.org submission has yet been made.

The first official Plugin Check 2.1.0 execution under #127 found 86 errors and 569 warnings. Issue #132 now tracks the release-blocking code remediation, split into focused child issues #133–#136. Submission remains blocked until those code-level findings are resolved, a new exact candidate is rebuilt, and Plugin Check is rerun. The `Tested up to` error is intentionally not folded into those code tasks because it must reflect the later exact-candidate WordPress 7.1.x compatibility result.

Two metadata gates remain external to the source edits: `Contributors: rocsi-eu` must be verified as the intended usable WordPress.org account before submission, and `Tested up to` must only be advanced from 6.8 after the exact candidate is actually tested on the corresponding current WordPress release.
