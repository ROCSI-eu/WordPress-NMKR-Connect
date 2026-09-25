# Repository validation

- Use [`docs/validation-policy.md`](docs/validation-policy.md) as the canonical validation policy and classify risk before selecting checks.
- Use [`docs/deployment-policy.md`](docs/deployment-policy.md) as the canonical DEV/STAGING/PRODUCTION deployment policy.
- Use [`docs/wordpress-org-release.md`](docs/wordpress-org-release.md) as the canonical WordPress.org release/update checklist.
- Preserve exact-head trust and keep all committed and console output public-safe.
- Never run a real NMKR synchronization by default, and never expose private inputs, environment details, authentication state, logs, or test artifacts.
- Use the smallest profile required by the policy; do not repeat full private validation when a targeted profile is sufficient.
