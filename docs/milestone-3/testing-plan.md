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

Cover representative critical paths: installation/activation and readiness; settings and authorization; dashboard/project views; synchronization start, stop, failure, recovery, and final state; Cardano and Solana data; Free/Premium shortcode displays as access permits; analytics/privacy settings; roles/capabilities; and safe errors. Existing automated and operational evidence may be assessed together without inventing a duplicate manual matrix; add targeted manual coverage only for a material gap.

[EVD-007](executed-evidence-2026-08-21.md) completes the current M3-01 evidence action with a read-oriented smoke across separate development and staging WordPress installations on one representative self-managed GCP VM stack. Treat it as current-maintained-runtime, multi-installation evidence—not independent-hosting-provider, managed-host, legacy-runtime, or broad cross-platform certification. Retain the record and rerun affected checks when the maintained runtime or relevant hosting assumptions materially change; do not deploy obsolete or older WordPress/PHP versions solely to enlarge an artificial matrix, and keep declared minimum-version metadata separate from executed coverage.

### Usability

A documented maintainer/operator workflow assessment may use representative setup, synchronization-status interpretation, NFT display, analytics, role, troubleshooting, and escalation tasks already exercised in operational evidence and current guides. Clearly label its audience and limitations. Independent participants can strengthen evidence, and any such work should record relevant experience range, completion/obstacles, observations, and resulting fixes or gaps without identifying participants, but a participant study is not mandatory unless the contractual wording requires one. Automated browser tests alone must not be relabelled as participant research.

### Performance, load, and uptime

Before measurement, define the operation, metric, normal/load profile, dataset scale, cache state, client/network conditions, observation window, warm-up/repetition approach, and acceptable errors. Report measured values and distribution/summary rather than only “fast.” Keep the method adjustable but evaluate the fixed contractual thresholds explicitly.

[EVD-008](executed-evidence-2026-08-21.md#evd-008--bounded-repeated-sequential-mixed-chain-traffic) remains validated supporting/partial historical evidence. [EVD-011](executed-evidence-2026-08-26.md) records the subsequently executed larger workload: one cold and one warm sequential `private-2400-v1` campaign, each covering 24 projects, 2,400 unique tokens/details, and 2,497 isolated synthetic provider/API requests. The cold campaign created the expected rows; the warm campaign added no business rows; mixed-chain attribution, history, metrics, terminalization, diagnostics, and cleanup passed.

EVD-011 is substantial supporting/partial evidence for M3-05 and M3-11, not acceptance-closing evidence. It demonstrates bounded higher-volume sequential functional and mixed-chain behaviour, but did not define or measure offered load, request rate, throughput, latency distribution, plugin/runtime resource behaviour, stress, saturation, or a breaking point. M3-05 and M3-11 therefore remain **Implemented, validation pending**. Streaming traversal, deduplication, progress, and metrics remain implementation foundations; uptime still requires its own evidence.

For uptime, define what endpoint and response qualify as available, probe interval/location class, observation start/end, planned-maintenance treatment, calculation, missing-data handling, and incidents. Choose a defensible period appropriate to submission evidence; this plan does not invent an excessive mandatory duration. State whether the above-99.9% target was met during that disclosed period.

### Chain-specific synchronization

Register Cardano and Solana evidence with attributable chain representation, dataset scope, terminal state, integrity checks, cleanup, and limitations. EVD-001 supplies bounded light-profile support and EVD-008 supplies repeated sequential support. EVD-011 additionally preserves direct attribution across 8 Cardano-only, 8 Solana-only, and 8 dual-chain projects and 800/800/800 unique tokens in both cold and warm execution. It supports M3-09 and M3-10 and provides substantial supporting/partial evidence for M3-11; it does not prove stress acceptance, production/upstream capacity, a breaking point, maximum load, arbitrary scale, or universal behavior.

The remaining M3-05/M3-11 action is an executed, bounded, safe load/stress profile with a defined offered-load/request-rate model and duration, throughput and latency/error measurements, appropriate plugin/runtime resource observations, explicit ceilings, stop conditions, recovery, and final-state checks. M3-11 also requires direct Cardano/Solana/dual-chain attribution. Concurrency can be used where suitable but is not the only acceptable method; no profile may create production or uncontrolled live NMKR load. Together with **Planned** M3-08 and M3-21, these are the four open actions, and Milestone 3 remains incomplete.

### Security and negative paths

Use exact-source control review and representative deterministic regressions for implementation-evidence claims, including:

- input type/length/allow-list validation and sanitization;
- output escaping and stored/reflected XSS boundaries;
- prepared SQL and identifier/query boundaries;
- missing, invalid, expired, or replayed nonce behavior (nonces mitigate CSRF; they are not authentication);
- authenticated capability/role allow and deny cases;
- error disclosure, logging redaction, retention, and safe user messages;
- dependency inventory, reproducible install, advisory review, and finding disposition.

Source/control assessment cannot substitute for live execution when a claim specifically depends on runtime authorization or environment behavior. Run mutation or attack-like cases only against disposable/controlled data. A selected negative suite supports a scoped conclusion, not a guarantee that no vulnerability exists.

## Reviewer environment

Provide reviewers a limited, temporary, controlled environment only when ready. Use synthetic/non-customer data, least-privilege accounts, bounded actions, an out-of-band credential channel, and a clear scope/support/revocation plan. Keep its private URL and credentials out of commits and PR discussion. Prevent destructive actions or isolate them in a disposable copy; monitor and revoke access after the defined period.

## Stop conditions

Stop and preserve the current state safely when authorization or commit identity is uncertain; secrets/private data could be exposed; the target is production or outside scope; error/latency/resource thresholds are exceeded; cleanup/recovery fails; unexpected destructive behavior occurs; API limits or upstream instability make the result misleading. Record the interruption and limitation rather than forcing a pass.

## Delivery and follow-up

Limitations must be disclosed. Unfinished optional work may move to **Deferred improvement** without being represented as completed, while submission-critical gaps remain visible. Reports are created only from real registered results and may combine related evidence economically. Post-delivery improvements are expected and should not block a defensible milestone submission; they must not be used to imply that unfinished contractual evidence is already validated.


## M3-06 API response benchmark

The bounded read-only M3-06 profile and its private execution boundary are defined in the [NMKR API response-time benchmark guide](../testing-nmkr-api-response-benchmark.md). [EVD-004 and the 2026-08-18 executed report](executed-evidence-2026-08-18.md) validate M3-06 under the disclosed serial normal-condition profile: one controlled observation window, one authorized non-production environment, and one deterministically selected eligible project/token pair for token-list and token-detail scope. The result does not claim coverage of every NMKR endpoint or every network or service condition, and it does not validate load, display performance, uptime, chain-specific synchronization, or heavier cross-chain traffic.
