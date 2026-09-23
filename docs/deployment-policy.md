# Deployment policy

This document is the canonical deployment policy for ROCSI Connector for NMKR. GitHub is the source of truth for development, while each WordPress environment has a distinct role and allowed deployment source.

## DEV — active development validation

DEV is the only environment used for routine development validation.

- Before merge, deploy the exact reviewed pull-request head SHA when private runtime validation is required.
- After merge, deploy the exact merged `main` SHA for post-merge validation.
- Verify the intended SHA/tree and a clean source state before deployment.
- Select validation depth using the repository's risk-based validation policy.
- Do not use an ambiguous moving ref where an exact SHA is required.
- Do not run a real NMKR synchronization unless it is separately and explicitly authorized.

## STAGING — WordPress.org release preparation

STAGING is reserved for preparing and validating releases intended for the WordPress.org Plugin Directory.

- Do not use STAGING for routine feature development.
- Deploy the exact release-candidate SHA/package intended for the upcoming WordPress.org SVN update.
- Validate release identity, plugin version, `Stable tag`, package contents, permissions, migration/update behavior, and release-specific regressions before publication.
- Keep WordPress.org SVN as a release repository, not a development branch.
- After publication, STAGING may be used for a bounded verification of the WordPress.org-distributed install/update path when useful.
- Staging activity is operational validation and must not be counted as real adoption evidence.
- Do not run a real NMKR synchronization unless it is separately and explicitly authorized.

## PRODUCTION — WordPress.org consumer path

PRODUCTION should behave like an ordinary WordPress installation consuming the public plugin.

- Install and update `rocsi-connector-for-nmkr` from the official WordPress.org Plugin Directory.
- Do not deploy Git branches, pull-request heads, arbitrary commit SHAs, or private build artifacts directly to production.
- Before the first migration from a legacy plugin installation, inspect persistent state and cleanup behavior read-only.
- Preserve existing `nmkr_*` options, tables, history, and configuration across the migration.
- Do not run a destructive uninstall or cleanup path merely to replace the legacy plugin. Deactivate and replace only through a controlled migration with a defined rollback.
- Verify the installed public version, retained state, site health, and update metadata after migration or update.
- Do not run a real NMKR synchronization unless it is separately and explicitly authorized.

## Common rules

- Keep deployment commands and evidence public-safe: never commit secrets, credentials, private URLs, customer data, sensitive logs, or unnecessary infrastructure details.
- Stop on repository/SHA mismatch, dirty or ambiguous source state, unhealthy WordPress, active synchronization, unexpected persistent-state changes, or unclear rollback.
- Use Git to roll back source-controlled development changes; use validated backups/snapshots only when persistent or irreversible state is at risk.
- Development and release validation must not silently change the role of an environment.

For validation depth and exact-head requirements, see [validation-policy.md](validation-policy.md).
