# WordPress.org readiness

## M5-01 decision baseline

Issue #107 completed the exact-code analysis on baseline `283ccb82a57bdce06d2fc3256caa6d03caa72583`. The owner resolved that all five shipped shortcodes are free, Freemius is removed, first-party source remains MIT, analytics remains available but defaults Off, uninstall cleanup is targeted, and the first public production version is `1.0.0`.

## M5-02 implementation candidate

Issue #108 implements those decisions: entitlement checks and licensing bootstrap/dependency paths are removed; analytics activation, sanitizer, reset, frontend, ingestion, and GA4 paths use an Off/safe default; the Privacy Policy Guide receives suggested text; deactivation/uninstall clear the analytics cron; uninstall removes the two plugin roles and exact `nmkr_*` capabilities; metadata/readme are aligned; and a clean-tree package builder emits one configurable top-level directory, a sorted file manifest, and SHA-256 provenance.

External-service disclosures cover administrator-triggered NMKR Studio API synchronization, optional Google Analytics 4 transmission, and visitor requests for remote NMKR/IPFS/gateway media. The validation path is `npm run test:release`, the PHP 7.4/static checks, and `npm run build:package` from a clean exact commit.

This is implemented readiness work, not directory acceptance. **The final WordPress.org display name and slug remain unresolved release gates** pending NMKR outreach and WordPress.org eligibility. The working identity is not a claim that `NMKR Connect` or `nmkr-connect` is approved, reserved, submitted, or published.
