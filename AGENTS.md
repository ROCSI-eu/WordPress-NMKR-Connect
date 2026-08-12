# Repository validation

- Use [`docs/validation-policy.md`](docs/validation-policy.md) as the canonical validation policy and classify risk before selecting checks.
- Preserve exact-head trust and keep all committed and console output public-safe.
- Never run a real NMKR synchronization by default, and never expose private inputs, environment details, authentication state, logs, or test artifacts.
- Use the smallest profile required by the policy; do not repeat full private validation when a targeted profile is sufficient.
