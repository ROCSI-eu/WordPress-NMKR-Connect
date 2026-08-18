# Lean Milestone 3 testing plan

This plan turns rows in the [traceability matrix](traceability-matrix.md) into records in the [evidence register](evidence-register.md). It favors limited, reproducible evidence with honest boundaries over an unfinishable catalogue. Exact tools and environment profiles may be adjusted; representative tests are preferable to every theoretical combination. The contractual benchmarks—NMKR API response below one second, NFT display page load below two seconds, and uptime above 99.9%—remain fixed.

## Common test record

For each execution intended as evidence, retain at minimum:

- evidence and requirement ID(s), delivery priority, owner, and execution date/time;
- exact source commit and exact deployed commit, if deployment applies;
- generic environment profile (WordPress/PHP/browser/hosting class as relevant) without private identifiers;
- test class, method/tool version, dataset description, conditions, and expected outcome;
- actual result and measurements, pass/fail/partial decision, cleanup/final state, and anomalies;
- known limitations, public/private classification, sanitized public location, and whether private raw evidence is retained.

Use only the workspace status vocabulary. A planned test becomes **Validated** evidence only when it was executed, assessed, retained, sanitized as needed, and entered in the register. Failed or partial executions can still be credible evidence when clearly labelled; they do not validate the requirement by themselves.

## Execution boundary and safety classes

Public-safe work must use no secrets or private services. It may include static checks, test discovery, synthetic regressions, and sanitized reports. Private execution may use authorized WordPress/API access and retain raw artifacts under project controls; a submission may reference that evidence through a public sanitized summary.

Classify a test before running it:

1. **Read-only** — observes pages, configuration presence, or database invariants without intended state changes.
2. **Controlled mutation** — changes known test records/settings or starts a bounded synchronization; record the initial state, authorization, cleanup, and expected final state.
3. **Destructive/disposable** — load, fault, deletion, reinstall, or recovery work that may disrupt data/service; use only an isolated disposable environment with explicit authorization and recovery/teardown steps.

Never publish credentials, endpoints, infrastructure names/paths, customer or private identifiers, populated environment files, raw logs/database output, cookies/nonces, private artifacts, or participant information. Record exact SHAs rather than “latest.” After mutation, stop background work, remove test data when safe, restore intended configuration, check synchronization/queue/database terminal state, and note any residue. Do not hide an incomplete cleanup.

## Proportionate coverage

### Functionality and compatibility

Cover representative critical paths: installation/activation and readiness; settings and authorization; dashboard/project views; synchronization start, stop, failure, recovery, and final state; Cardano and Solana data; Free/Premium shortcode displays as access permits; analytics/privacy settings; roles/capabilities; and safe errors. Combine targeted automation with a concise manual matrix. Run the critical subset on more than one representative WordPress hosting/environment profile; disclose combinations not tested.

### Usability

Use a short neutral task script for representative setup, synchronization status interpretation, and NFT display tasks. A small practical participant sample is acceptable: record relevant experience range, completion/obstacles, observations, and resulting fixes or open gaps without identifying participants. Automated browser tests may prepare flows but do not replace participant evidence. Avoid arbitrary large participant commitments.

### Performance, load, and uptime

Before measurement, define the operation, metric, normal/load profile, dataset scale, cache state, client/network conditions, observation window, warm-up/repetition approach, and acceptable errors. Report measured values and distribution/summary rather than only “fast.” Keep the method adjustable but evaluate the fixed contractual thresholds explicitly.

Use bounded stepped load in an authorized non-production environment. Set concurrency/rate ceilings, resource and error monitoring, stop thresholds, cooldown, and recovery/final-state checks. Do not extrapolate unlimited scale from a small test. Streaming numbered-page traversal, run-scoped deduplication, bounded provisional progress, and canonical final metrics are current implementation foundations. Their operational validation does not replace separately measured bounded load, latency, uptime, or heavier cross-chain evidence.

For uptime, define what endpoint and response qualify as available, probe interval/location class, observation start/end, planned-maintenance treatment, calculation, missing-data handling, and incidents. Choose a defensible period appropriate to submission evidence; this plan does not invent an excessive mandatory duration. State whether the above-99.9% target was met during that disclosed period.

### Chain-specific synchronization

Register Cardano and Solana executions separately, with chain, dataset scope, terminal state, integrity checks, API timing, cleanup, and limitations. Only after both exist may a combined comparison reference them. A single synchronization does not prove heavier traffic, uptime, or full cross-chain performance. Streaming traversal, run-scoped deduplication, unique final totals, provisional progress, pagination failure handling, and final metrics are current implementation foundations. Register separate measured evidence for each chain and for representative high volume or heavier cross-chain traffic; the foundation alone does not validate those contractual claims.

### Security and negative paths

Use focused control review and representative negative tests for:

- input type/length/allow-list validation and sanitization;
- output escaping and stored/reflected XSS boundaries;
- prepared SQL and identifier/query boundaries;
- missing, invalid, expired, or replayed nonce behavior (nonces mitigate CSRF; they are not authentication);
- authenticated capability/role allow and deny cases;
- error disclosure, logging redaction, retention, and safe user messages;
- dependency inventory, reproducible install, advisory review, and finding disposition.

Run mutation or attack-like cases only against disposable/controlled data. A selected negative suite supports a scoped conclusion, not a guarantee that no vulnerability exists.

## Reviewer environment

Provide reviewers a limited, temporary, controlled environment only when ready. Use synthetic/non-customer data, least-privilege accounts, bounded actions, an out-of-band credential channel, and a clear scope/support/revocation plan. Keep its private URL and credentials out of commits and PR discussion. Prevent destructive actions or isolate them in a disposable copy; monitor and revoke access after the defined period.

## Stop conditions

Stop and preserve the current state safely when authorization or commit identity is uncertain; secrets/private data could be exposed; the target is production or outside scope; error/latency/resource thresholds are exceeded; cleanup/recovery fails; unexpected destructive behavior occurs; API limits or upstream instability make the result misleading. Record the interruption and limitation rather than forcing a pass.

## Delivery and follow-up

Limitations must be disclosed. Unfinished optional work may move to **Deferred improvement** without being represented as completed, while submission-critical gaps remain visible. Reports are created only from real registered results and may combine related evidence economically. Post-delivery improvements are expected and should not block a defensible milestone submission; they must not be used to imply that unfinished contractual evidence is already validated.


## M3-06 API response benchmark

The bounded read-only M3-06 profile and its private execution boundary are defined in the [NMKR API response-time benchmark guide](../testing-nmkr-api-response-benchmark.md). Tooling readiness and synthetic regression results are not executed API evidence; M3-06 remains validation pending until separately authorized exact-head private VM execution.
