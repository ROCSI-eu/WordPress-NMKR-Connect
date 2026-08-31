# Milestone 4 executed evidence — M4-07 — 2026-08-31

## Purpose and scope

This public-safe record documents completion of the scoped M4-07 documentation package. It records the exact implementation provenance, review history, exact-head CI, merge equivalence, validation classification, and limitations for PR #96 without publishing private operational details, credentials, authentication state, raw diagnostics, database output, screenshots, traces, reports, or artifact locations.

M4-07 changed public documentation only. It did not change plugin runtime code, tests, scripts, workflows, dependency declarations or lockfiles, schema, options, transients, cron, roles, capabilities, nonces, endpoints, shortcodes, synchronization, analytics, deployment behavior, or private-environment state.

## Implementation summary

PR #96 added one comprehensive role-oriented FAQ, a compact README documentation hub, task-oriented cross-links across the user, developer, and troubleshooting guides, and the exact seven-capability action matrix. It clarifies menu visibility versus server authorization, nonce/CSRF protection versus capability authorization, observation under `nmkr_view_dashboard`, and synchronization or recovery mutation under `nmkr_manage_sync`.

The package documents cooperative Stop behavior, provisional progress, canonical completion, polling backoff, safe escalation, and the intentional public/unauthenticated analytics-ingestion boundary. It also records the bounded consent, DNT, logged-in-user, sampling, origin/host, input, rate-admission, deduplication, retention, sink, and upstream-buffering limitations already established by the implemented runtime and prior evidence.

## Exact public identifiers

- Implementation PR: #96
- Implementation baseline: `72944bdb7848cb16fc0eb3d108e5602034153aef`
- Implementation baseline tree: `de7b13e9b960491f8896e388670e2852b63e7f92`
- First reviewed PR head: `63d2c353cc80fbe168fa4a8ccde8d80510376860`
- Corrected content head: `0ad484533e8d6af53d3e005f26be4ac52687e31a`
- Final reviewed PR head: `c6f2bc2057e3034d2d218d70aee9d1817897ccb7`
- Final PR-head and merged-main tree: `9a48e61ef8b2025a54f58098403202184ef5039c`
- Merged main: `2b32a6213c38018bc8e013f7f948c08b1b6a9151`
- Successful Phase 4 CI run: `33411893816` (run 382)
- Evidence date: `2026-08-31`

The final head is tree-equivalent to corrected content head `0ad484533e8d6af53d3e005f26be4ac52687e31a`; the final no-op commit changed no repository content.

## Review and correction history

The first full Codex review inspected head `63d2c353cc80fbe168fa4a8ccde8d80510376860` and raised one P2 documentation-accuracy finding. The FAQ had described a configured API key as a prerequisite for Start admission, while source inspection showed that the Start handler can authorize and admit ownership, initialize state, queue the background worker, and return success before `nmkr_sync_data()` validates API configuration.

The FAQ was corrected to distinguish successful Start admission from later worker-time API-key validation, failure recording, and cleanup invocation. The net post-review content delta was one paragraph in `docs/faq.md`. The original review thread was replied to and resolved after exact-head CI passed.

A second full Codex review was requested while the PR remained at exact final head `c6f2bc2057e3034d2d218d70aee9d1817897ccb7`. Codex returned a positive review reaction and no additional findings or review threads.

## Public checks and exact-head CI

The final exact-head Phase 4 CI run passed:

- `Public-safe checks`;
- `Synthetic security rendering`; and
- `Dependency audits`.

The exact-checkout gate passed in all three jobs. Focused inspection also confirmed the exact eight-file documentation allowlist, complete PR hunks, the one-paragraph post-review correction, public-safe wording, repository-relative links, and intended GitHub-style anchors.

The required `git diff --check 72944bdb7848cb16fc0eb3d108e5602034153aef...c6f2bc2057e3034d2d218d70aee9d1817897ccb7` check was subsequently run in a managed checkout containing the exact implementation baseline and final reviewed head. It returned no output and exited successfully. This closes the Docs/metadata pre-merge-minimum evidence gap identified during review of this closure record.

## Merge equivalence

Expected-head protection merged exact reviewed head `c6f2bc2057e3034d2d218d70aee9d1817897ccb7` as main commit `2b32a6213c38018bc8e013f7f948c08b1b6a9151`. The final PR-head and merged-main Git trees are identical: `9a48e61ef8b2025a54f58098403202184ef5039c`.

## Validation classification

Under `docs/validation-policy.md`, M4-07 is a Docs/metadata package. It introduced no runtime, persistence, authorization, deployment, external-API, or private-environment behavior. Public CI and focused source/document inspection were therefore sufficient.

No private DEV deployment, VM execution, WordPress execution, WP-CLI, database access, authenticated browser execution, real NMKR synchronization, NMKR API traffic, external GA4 request, customer data, credentials, authentication state, private logs, screenshots, traces, reports, manifests, or private artifacts were used or required.

## Result

M4-07 is complete within its disclosed documentation scope. The current capability/action, synchronization-recovery, nonce, public-ingestion, navigation, FAQ, troubleshooting, and limitation documentation gaps represented by `S-07`, `D-02`, `D-03`, and `D-04` are addressed for the behavior and evidence cited by this package.

`M4-T-10` remains open at milestone level because M4-09 evidence consolidation and M4-10 final reporting are still pending. M4-07 documentation completion does not complete the Milestone 4 audit or delivery.

## Limitations and explicit non-claims

This record does not claim universal authorization coverage, complete side-effect absence, universal SQL-injection resistance, universal XSS resistance, dependency safety, production security, legal compliance, penetration testing, formal certification, Catalyst delivery or approval, completed audit, or completed Milestone 4 delivery.

The package documents current behavior and bounded prior evidence; it does not create new runtime controls or independently revalidate every implementation path. Link and anchor review was focused manual inspection rather than a claim of a comprehensive automated documentation validator. Future runtime, capability, endpoint, synchronization, recovery, analytics, or guide-structure changes require the relevant documentation and evidence to be revisited.

## Public/private evidence boundary

Public evidence consists of sanitized documentation changes, public CI and review outcomes, exact Git provenance, resolved thread status, merge equivalence, validation classification, and the limitations above. Private URLs, host or VM paths, credentials, nonces, API keys, customer or synchronization data, authentication state, raw logs, database output, screenshots, traces, videos, reports, manifests, and private artifact locations remain excluded from the repository.
