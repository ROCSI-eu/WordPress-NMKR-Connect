# Pull-request validation policy

## Purpose and safety boundary

This is the canonical policy for selecting pre-merge review and validation and proportional post-merge checks. Classify the change before choosing commands; do not infer risk mechanically from diff size. When a change spans classes, use the highest applicable class.

Public CI runs the public-safe `npm run test:public` umbrella. It must not receive WordPress credentials, private endpoints, deployment configuration, authentication state, or private artifacts. Private browser, WP-CLI, and deployment validation runs only in an approved prepared environment with owner-private inputs and output. Keep `RUN_REAL_SYNC=false` and `PW_SAVE_ARTIFACTS=false` for routine validation. A real NMKR synchronization is never part of these profiles and requires separate explicit authorization and the guarded real-sync procedure.

## Risk classes

| Class | Pre-merge minimum | Review | Post-merge |
| --- | --- | --- | --- |
| **Docs / metadata** | Public CI and validation of changed links, tables, paths, and commands. Run `git diff --check`. Normally no private deployment. | Inspect affected documentation and confirm that no private information or unsafe operational guidance was introduced. | Confirm the merged ref and CI where relevant. Normally do not deploy to a private VM. |
| **Test / tooling only** | Public CI plus the affected synthetic regression. Private validation is required only when private orchestration, browser, WP-CLI, deployment, authentication-state, artifact, or trust semantics changed. Public-only tooling does not require full WordPress or Playwright execution. | Review the affected test boundary for false passes, unsafe defaults, and private-output leakage. | Confirm merged CI. Run only the affected private profile when private machinery changed. |
| **Ordinary runtime** | Validate the exact reviewed PR head. Use the Phase 2 `targeted-readonly` profile for an allowlisted affected browser suite, WordPress readiness, and WP-CLI smoke. Add DB/state checks only when the change can affect persistence or runtime state; use the full readonly profile when necessary. Full Playwright is not automatic. | Review the complete affected runtime path, input validation, escaping, and relevant WordPress boundary. | When the merged tree is equivalent to the reviewed head, confirm the exact merged deployment, readiness/smoke, and affected regression instead of automatically repeating all Playwright suites. Escalate if the merge changed the tested tree. |
| **High-risk** | Required for changes to synchronization lifecycle/concurrency/finalization; database/schema/options/transients/persistence; capabilities/nonces/authentication/authorization/security; Composer dependency trust; or deployment/runtime integrity. Validate exact reviewed and deployed heads, perform deeper affected review, run runtime integrity where applicable, and run specialized security/state plus DB/idle/final-state checks as applicable. Run full Playwright when the boundary is broad enough to justify it. | Review state ownership, failure/cleanup paths, authorization, persistence, concurrency, and trust assumptions as applicable. | Revalidate merged main at depth proportional to release/environment risk. Synchronization, schema/persistence, security/auth, dependency-trust, and deployment-integrity changes normally retain deep affected or full merged-main validation. |

The specialized `npm run test:ajax-security` runner remains the preferred one-command validation for narrowly scoped privileged AJAX authorization changes because it supplies negative/authorized state comparisons and final idle checks. Phase 2 does not duplicate that runner.

## Phase 2 selection

- `npm run test:phase2` preserves the general deploy-and-full-validation workflow.
- `npm run test:phase2:existing-readonly` validates an existing exact deployment with full Playwright, WP-CLI smoke, and DB-state checks.
- `NMKR_PHASE2_TARGET_SUITE=<allowlisted-suite> npm run test:phase2:targeted-readonly` validates an existing exact deployment with readiness, one targeted Playwright suite, and WP-CLI smoke; DB-state is intentionally skipped.
- Set `NMKR_PHASE2_RUNTIME_INTEGRITY=true` only when the profile must verify Composer/runtime deployment integrity. A deployed plugin path is then mandatory, and the gate runs once.

Readonly profiles require `NMKR_PHASE2_EXPECTED_SOURCE_SHA` as a full 40-character reviewed SHA, require a clean source worktree, and verify a supplied deployed plugin worktree at that same SHA. They repeat source/deployed SHA and cleanliness checks after successful validation.

## Blockers and investigation

Block on:

- source or deployed SHA mismatch, dirty tracked state, or failed final integrity;
- failed runtime integrity when required;
- PHP fatal, parse, or uncaught failures regardless of origin;
- fresh first-party NMKR Connect warnings or errors;
- failed authorization, nonce, capability, security, functional, persistence, DB, idle, final-state, or cleanup assertions required by the selected class;
- ambiguous results that prevent establishing the required invariant.

Investigate the smallest failing stage and inspect private diagnostics only in the approved private location. Do not publish raw logs, DB output, credentials, nonces, URLs, paths, screenshots, traces, videos, reports, or authentication state.

### Third-party vendor warnings

A fresh warning attributable only to third-party vendor code may be reported as non-blocking when dependency/runtime-integrity files did not change, runtime integrity passes where applicable, and relevant functional/state validation passes. It must be reported and must not be silently ignored. It is blocking when dependency, build, or deployment state changed, another relevant validation failed, its origin is ambiguous, or it is fatal, parse, or uncaught severity. The current log classifier remains fail-closed; this policy permits a documented maintainer disposition after private inspection rather than automatic suppression.

## Follow-up commits and review invalidation

Repeat full affected review and exact-head validation after a follow-up changes synchronization lifecycle, DB/persistence, authorization/security, runtime-state classification, deployment integrity, dependency trust, secret/private-artifact boundaries, or broad runtime behavior.

For documentation, metadata, test labels, comments, evidence pointers, or narrow non-semantic corrections, inspect the new hunk, rerun affected checks, and rely on CI. Do not automatically restart an unrelated full review or private VM cycle. Record why previous substantive review remains applicable.

## Exact-ref deployment contract

Use this host-neutral contract for deployment-integrity validation:

1. Fetch the exact ref.
2. Materialize and build that exact ref in a private temporary location outside the repository and WordPress root.
3. Run `composer install --no-dev --prefer-dist --no-interaction` from that ref's exact lockfile.
4. Deploy tracked source and generated `vendor/` together while preserving WordPress database, options, uploads, and private configuration.
5. Verify the exact deployed ref and clean tracked state.
6. Run the runtime-integrity gate once before affected functional validation.

Host-specific paths, domains, credentials, and deployment commands belong only in approved private configuration and must not be committed or printed.
