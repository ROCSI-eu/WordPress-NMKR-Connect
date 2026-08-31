# Milestone 4 executed evidence — M4-05 — 2026-08-31

## Purpose and scope

This public-safe record documents completion of the scoped M4-05 public analytics ingestion hardening and its sanitized review and validation outcomes. It records evidence for PR #91 without publishing private operational inputs, raw diagnostics, request fixtures, database output, or artifact locations. The evidence is limited to this implementation, its public tests and CI, the disclosed private exact-head validation profile, and equivalent merged-main validation.

## Implementation summary

PR #91 preserved intentional anonymous REST and AJAX analytics ingestion while replacing non-atomic admission state with durable option-backed state and explicit failure semantics. Per-anonymized-IP rate counters are serialized with a MariaDB advisory lock. Durable deduplication claims use fixed-length digest keys, value-sensitive compare-and-swap recovery, and pre-sink ownership so a retry cannot duplicate a committed local row or repeat a GA4 dispatch.

The endpoint validates canonical UUIDv4 sessions, compact identifiers, JSON body size, recursive metadata depth, node count, individual string length, and aggregate metadata size. Server-side sampling is authoritative. Quota exhaustion returns an empty `429`; lock timeout, storage failure, malformed or ambiguous admission state, and uncertain ownership fail closed with an empty `503` before deduplication or sinks.

The implementation also persists and wraps bounded cleanup progress, removes expired admission state in bounded batches, repairs malformed rate and claim state through the locked or compare-and-swap admission paths, and includes dynamic admission options in configured uninstall cleanup.

## Exact public identifiers

- Implementation PR: #91
- Implementation baseline: `22cb8cee1c35deb7ba18ce1d0ac76b3f738ab52b`
- Baseline tree: `24d82eba13df1ef7e5bfbf6a8b6aa6b6e6e68026`
- Final reviewed PR head: `ba5f934e6285089ba4c4946084d23d877fb122ee`
- Validated PR-head and merged-main tree: `4b6694520b2e8325c3a4c676a0cefd725f6bfdd8`
- Merged main: `4a48dc50498e5de618c794a32b91fea968487eb3`
- Successful Phase 4 CI run: `33369689169` (run 356)
- Evidence date: `2026-08-31`

## Public review, tests, and CI

The focused public checks passed:

- `git diff --check`;
- PHP syntax checks for affected PHP files;
- `bash -n scripts/nmkr-public-checks.sh`;
- `php scripts/nmkr-analytics-ingestion-regression.php`;
- `npm run test:analytics-ingestion`;
- `npm run test:php74`; and
- Phase 4 CI run `33369689169`.

The public-safe regression harness exercises deterministic injected interleavings for competing rate updates and claims, exact quota thresholds, lock and storage ambiguity, stale and malformed state recovery, sink suppression, payload bounds, mode and policy behavior, cleanup, and uninstall state. The final full Codex review inspected exact head `ba5f934e6285089ba4c4946084d23d877fb122ee`, all inline review threads were resolved, and no major issue remained before merge.

Synthetic injected interleavings prove only the modeled cases. They are complemented, but not replaced, by the private real WordPress/MariaDB concurrency checks summarized below.

## Private exact-head pre-merge validation

Exact head `ba5f934e6285089ba4c4946084d23d877fb122ee` and tree `4b6694520b2e8325c3a4c676a0cefd725f6bfdd8` were deployed to the approved private DEV environment. The deployed worktree was clean, WordPress and the plugin were healthy, synchronization was idle, analytics was temporarily enabled only under a controlled local profile with external GA4 disabled, and the complete original plugin option was restored exactly after each mutating profile.

The following affected behaviors passed:

- valid REST and AJAX acceptance with cross-transport deduplication;
- DNT, malformed-session, and cross-origin policy handling with no sink side effects;
- plugin-level JSON decode and sink bounds plus the deployment transport request limit;
- eight concurrent identical tuples producing one local row, one durable done claim, and all rate increments;
- eight concurrent distinct tuples producing eight rows, eight done claims, and no lost rate increments;
- exact concurrent quota behavior admitting one request at count 120 and returning an empty `429` for count 121 without a rejected-request claim or sink row;
- real advisory-lock contention returning an empty `503`, creating no admission state or sink row, and releasing the lock;
- malformed deduplication state recovery through value-sensitive compare-and-swap;
- malformed fixed-window rate-state repair for negative or non-integer counters, non-integer or future starts, and non-integer or incoherent expiries;
- coherent expired-window rollover and coherent active-window quota behavior; and
- cleanup leaving no synthetic analytics rows, admission options, held advisory lock, option changes, synchronization markers, or unhealthy service state.

A private web-application-firewall rule initially treated the legitimate analytics session field as a false positive. Validation proceeded only after a narrowly scoped endpoint-and-field exclusion was installed and proven not to weaken the rule for unrelated paths, actions, or session parameter names. Global web-application-firewall protection remained enabled. No private rule text or infrastructure configuration is published here.

No real NMKR synchronization, NMKR API request, customer data, or external GA4 request was used.

## Merge and post-merge validation

Expected-head protection merged exact reviewed head `ba5f934e6285089ba4c4946084d23d877fb122ee` as main commit `4a48dc50498e5de618c794a32b91fea968487eb3`. The merged-main tree is exactly the validated PR-head tree `4b6694520b2e8325c3a4c676a0cefd725f6bfdd8`.

Merged main was deployed to private DEV. Affected PHP syntax checks and the focused analytics ingestion regression passed. REST and AJAX DNT smoke requests returned empty `204` responses without admission or sink state. The complete plugin option, analytics row count, and admission-state count were unchanged; the advisory lock was free; synchronization remained idle; and WordPress, the database, Apache, and the plugin remained healthy.

## Result

M4-05 resolves the scoped S-05 concurrency and input-bound weakness at the exact reviewed PR head and equivalent merged-main tree. Public analytics ingestion remains intentional and anonymous, but rate admission, deduplication ownership, payload validation, persistent-state recovery, cleanup, and failure behavior now follow the bounded and fail-closed contracts described above.

Milestone 4 remains in progress. M4-06 is the next planned package for consolidated public-safe security regressions and dependency/static checks; M4-08 remains a later milestone-wide validation package rather than a prerequisite that was completed by this package-specific validation.

## Limitations and explicit non-claims

This record does not claim universal abuse resistance, denial-of-service immunity, transactional or exactly-once delivery across WordPress and GA4, penetration testing, formal certification, production security assurance, Catalyst delivery or approval, or completion of Milestone 4.

Admission is durable before either sink is invoked. This provides at-most-once sink invocation for a tuple, so a crash or sink failure after admission can lose an analytics event. The plugin's 8 KiB check bounds JSON decoding and sink work but cannot prevent PHP, WordPress, a proxy, or the web server from buffering some or all of a request first. Deployment transport limits remain an independent operational control.

Private validation exercised the approved DEV stack and its MariaDB behavior. It does not establish every persistent-object-cache backend, database engine, proxy, extension, deployment topology, future code path, or hostile traffic pattern.

## Public/private evidence boundary

Public evidence consists of sanitized source behavior, public-safe regression conclusions, public CI and review outcomes, exact Git provenance, and the defensive conclusions above. Private environment details, domains, paths, credentials, authentication state, raw request fixtures, logs, database output, screenshots, traces, reports, manifests, and artifact locations remain excluded from this repository.
