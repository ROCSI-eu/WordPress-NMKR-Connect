# Milestone 4 executed evidence — M4-08 — 2026-09-01

## Purpose and scope

This public-safe record closes the cumulative M4-08 private DEV validation package. Validation was performed against exact commit `204cb395e1a4c9b8de63659a56d9a63dc082ecd8`, tree `54c3c377405803a5f36453a55d81814b5de59fcb`, in an approved private DEV environment. It did not use production, perform a real NMKR synchronization, retain Playwright artifacts, or publish private commands, paths, URLs, usernames, credentials, authentication state, logs, database output, or infrastructure details.

M4-08 validates the disclosed cumulative runtime and security boundaries at that exact head. It does not replace the package-specific public CI, synthetic coverage, source review, or evidence for M4-01 through M4-07, and it does not complete M4-09 evidence consolidation or M4-10 final reporting.

## Exact public identifiers and controls

- Validation date: `2026-09-01`
- Exact validated SHA: `204cb395e1a4c9b8de63659a56d9a63dc082ecd8`
- Exact validated tree: `54c3c377405803a5f36453a55d81814b5de59fcb`
- Environment boundary: private DEV only
- Real NMKR synchronization: disabled and not performed
- Playwright artifacts: disabled and none retained
- Source and deployed worktrees: clean at the final seal
- Active plugin: bound to the exact deployed worktree
- Policy: read-only validation enforced
- Rollback: not required

## Executed validation outcome

The following cumulative private DEV checks passed:

- WordPress and database readiness;
- the high-risk `existing-readonly` Phase 2 profile, including the complete Playwright suite, WP-CLI smoke, and database-state checks;
- runtime integrity before functional validation;
- final, source, and deployed integrity, including exact identity and clean tracked state;
- the specialized AJAX-security runner's preflight, negative-state, authorized, and final-state stages; and
- final idle-state checks.

The final state contained no detected active owner, worker, recovery, finalizer, or synchronization-cron residue. It also contained no detected synthetic provider or damaged terminal/history state. These are bounded results for the executed suites and inspected state at the exact validated head, not proof that every current or future path is free of defects or side effects.

## Non-blocking private deployment-process finding

The private deployment copied the exact target commit but initially stripped executable bits from tracked scripts in the deployed working tree. The integrity gate stopped validation before browser or WP-CLI execution. Public-safe inspection confirmed that the drift was limited to tracked `100755`-to-`100644` mode changes.

The exact tracked executable modes were restored, after which the deployed worktree was reconfirmed exact and clean. The full Phase 2 and specialized AJAX-security validations then passed. This was a non-blocking private deployment-process finding, not a plugin runtime defect, and no rollback was required. Private deployment commands, locations, endpoints, identities, credentials, logs, and infrastructure details remain intentionally excluded.

## Evidence boundaries and limitations

Public CI and synthetic tests remain distinct from this private DEV runtime evidence. Public evidence is reviewable without private inputs; synthetic coverage exercises bounded deterministic contracts; this record publishes only sanitized conclusions from runtime validation in the prepared private environment.

No real NMKR traffic, production validation, exhaustive external API coverage, penetration test, formal certification, universal authorization or side-effect-absence proof, universal XSS or SQL-injection resistance, dependency-safety guarantee, or universal security assurance is claimed. M4-08 is complete within this disclosed cumulative private-validation scope. M4-09 is the next package, followed by M4-10 final reporting.
