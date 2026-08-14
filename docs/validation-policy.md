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
- `NMKR_DEPLOYED_PLUGIN_PATH=/path/to/deployed/plugin-worktree NMKR_PHASE2_TARGET_SUITE=<allowlisted-suite> npm run test:phase2:targeted-readonly` validates an existing exact deployment with readiness, one targeted Playwright suite, and WP-CLI smoke; DB-state is intentionally skipped.
- Set `NMKR_PHASE2_RUNTIME_INTEGRITY=true` only when the profile must verify Composer/runtime deployment integrity. A deployed plugin path is then mandatory, and the gate runs once.

Readonly profiles require `NMKR_PHASE2_EXPECTED_SOURCE_SHA` as a full 40-character reviewed SHA and a clean source worktree. `targeted-readonly` also requires a deployed plugin worktree at that same SHA with a clean tracked state; `existing-readonly` continues to verify it when supplied. They repeat source/deployed SHA and cleanliness checks after successful validation.

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

A fresh PHP warning or notice with a conventional source location classified inside a `vendor/` path segment is surfaced generically by WP-CLI smoke but does not block validation. Fatal and parse errors remain blocking regardless of origin, as do NMKR fatal/error/exception diagnostics, first-party warnings/notices, and warnings/notices whose source cannot be safely classified. The classifier remains fail-closed for ambiguous diagnostics and never prints log content or source paths.

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

## Unified Phase 2 operator contract

The opt-in operator form is `npm run test:phase2 -- --stage <pre-merge|post-merge> --class <class> --profile <profile> --reviewed-sha <sha> --deployed-sha <sha> --target-suite <suite> --deploy-mode <run|skip> --runtime-integrity <true|false>`. Every selection is explicit; no profile is inferred. The compatibility matrix is:

| Validation class | Operator profile |
| --- | --- |
| `docs-metadata` | Not applicable: reject before private inputs and use public CI. |
| `test-tooling` | Targeted or full readonly only for explicitly required private orchestration/runtime-semantic validation. Public-only tooling remains CI-only. |
| `ordinary-runtime` | `targeted-readonly` normally; `existing-readonly` only as an explicit escalation. |
| `high-risk` | `existing-readonly`, including full Playwright, WP-CLI smoke, and DB-state. |

Pre-merge requires reviewed SHA = deployed SHA, source HEAD = reviewed SHA, and deployed HEAD = deployed SHA. Post-merge requires both source and deployed HEAD at the deployed merged-main SHA and identical source-repository Git tree objects for reviewed and deployed commits. Unresolvable or unequal trees invalidate reviewed-tree equivalence and require deeper review/validation.

Deployment `run` verifies source integrity before executing the opaque private command once, then verifies deployed identity, cleanliness, and WordPress active-plugin binding. Deployment `skip` does not execute it and verifies the existing deployment to the same standard. Both choices enter readonly functional validation with real synchronization, artifact saving, dependency installation, and browser installation disabled. Runtime integrity, when selected, runs once before readiness; targeted runs one allowlisted suite and smoke without DB-state, while full runs all Playwright, smoke, and DB-state. Separate source and deployed expectations are checked again at final integrity.

No automatic rollback is attempted. A successful deployment followed by failure is reported publicly as `deploy: PASS`, `result: FAIL`, and `rollback: NOT_ATTEMPTED`; diagnosis and the validated private rollback procedure remain owner responsibilities. Existing environment-only Phase 2 commands remain backward compatible. The specialized restricted-account AJAX security runner stays separate and its credentials are not Phase 2 inputs.
