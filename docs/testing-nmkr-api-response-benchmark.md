# M3-06 NMKR API response-time benchmark

## Purpose and boundary

This tooling makes the Milestone 3 M3-06 **below 1.000 second** decision for three production API wrapper classes without running synchronization or writing WordPress state. It is implementation/tooling only until a separately authorized private execution is reviewed. It neither consumes a Phase 15 receipt nor invokes, changes, or substitutes for the Phase 16A real-synchronization procedure.

The runtime seam in `includes/api/nmkr-api-functions.php` selects local recorders only when an opaque `NMKR_API_Benchmark_Authority` object is minted by the exact guarded CLI helper. Issuance requires CLI and WP-CLI, a helper-private authorization constant, and the exact caller file. Context keys, callbacks, web/AJAX/cron callers, synchronization workers, and arbitrary CLI wrappers cannot mint or forge the object. Without that identity, the existing attempt and wait recorders remain the default, including durable run evidence and its fail-closed persistence errors.

## Private authorization and normal conditions

Execution is private only. The controller defaults to refusal, rejects truthy `CI`, and requires all of:

- `NMKR_API_BENCHMARK_CONFIRM=I_AUTHORIZE_PRIVATE_READ_ONLY_NMKR_API_BENCHMARK` (exact literal);
- full 40-character `NMKR_API_BENCHMARK_SOURCE_SHA` and `NMKR_API_BENCHMARK_DEPLOYED_SHA` values that are equal to their clean worktree heads;
- absolute, owner-controlled `NMKR_API_BENCHMARK_RESULT_DIR` outside both repository and WordPress roots, mode `0700`;
- explicit `WP_PATH` and `NMKR_DEPLOYED_PLUGIN_PATH`, with the latter exactly bound to the active plugin directory.

Do not put private values in shell history, CI, issues, PRs, or public logs. “Normal conditions” means an idle, unambiguous site; safely inspectable cron; no persistent object cache whose relevant state cannot be compared exactly; an already configured API key; one deterministically selected eligible project/token; stable network/service conditions; serial requests; and unchanged clean exact source/deployed heads. The controller checks identity and binding before dispatch and identity again afterward.

## Profile, sequencing, and stops

The helper retrieves the configured key only through the production wrapper and never outputs key-derived metadata. It performs one successful warm-up in order for `projects`, `token_list`, then `token_detail`; project selection follows the projects warm-up, and token selection follows token-list warm-up. It then performs ten rounds in fixed `projects`, `token_list`, `token_detail` rotation. Identifiers exist only in memory and are never output.

Dispatch starts are at least 500 ms apart and execution is serial. All warm-up and retry dispatches count toward the maximum 45 HTTP attempts and five-minute monotonic duration. The production timeouts, transport, endpoint construction, headers, shape/JSON validation, throttle, retry classification, Retry-After handling, and backoff remain in force. Transport failures, 408, 429, and 5xx may retry within production limits. Non-retriable HTTP/shape failures stop the valid sample. Any ceiling, signal, ambiguous state, inspection failure, failed attempt, incomplete sample, dirty/mismatched head, changed transient, or integrity anomaly invalidates the run.

## Statistics and strict decision

Warm-ups are excluded from latency distributions but included in attempt/failure accounting. Latency distributions use the production executor's HTTP dispatch-to-response duration for the successful attempt belonging to each valid measured logical request; each retry is recorded separately, and any failed measured attempt invalidates the run. For sorted samples `x` of size `n`, the output contains:

- minimum `x[1]` and maximum `x[n]`;
- arithmetic mean `sum(x) / n`;
- median: middle observation for odd `n`, or the mean of the two middle observations for even `n`;
- nearest-rank p95 `x[ceil(0.95n)]`.

Full-precision values are calculated per endpoint and for the 30-sample combined set. Passing requires exactly ten valid measured logical responses in every class, zero measured logical failures, zero failed measured HTTP attempts, no stop/anomaly, no state change, and each class maximum **strictly less than 1.000 seconds**. A value equal to 1.000 seconds fails even if its mean is lower.

## Read-only proof and evidence boundary

The independent M3-06 snapshot helper captures table existence/count/maximum ID/content digests for projects, tokens, and token details; synchronization history and terminal content; metrics/latest content; relevant options, runtime markers, transients, synchronization cron, and source/deployment identity. Pre-existing transient disappearance or change fails. Cron inspection or persistent-cache ambiguity fails closed. The helper compares snapshots but never restores, cleans, schedules, updates, or deletes anything.

Private result and diagnostic files are owner-only and remain outside public roots. Public console output contains generic stage outcomes only. The sanitized result schema contains endpoint/combined aggregate statistics, counts, booleans, and the final decision—never URLs, headers, keys, authentication state, identifiers, payloads, bodies, raw errors, paths, hostnames, database values, or infrastructure fingerprints. Review private evidence before producing any separately approved public summary.

On failure, the controller removes only its own owner-only lock. It performs no WordPress cleanup, restoration, synchronization lifecycle action, or automatic rollback. Operators must diagnose privately and use a separately validated rollback procedure if deployment state—not benchmark state—requires intervention. No Phase 15 receipt, Phase 16A command, real synchronization, or live benchmark belongs in public CI. Run `npm run test:nmkr-api-benchmark:regression` for fake-transport public coverage.
