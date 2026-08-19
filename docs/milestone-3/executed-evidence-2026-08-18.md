# Executed evidence — 2026-08-18

## Evidence identity and scope

This sanitized report registers **EVD-004** for the guarded read-only NMKR API response-time benchmark and assesses **M3-06** under the defined normal conditions in the [benchmark guide](../testing-nmkr-api-response-benchmark.md). The benchmark executed at `2026-08-18T15:03:10+00:00` against exact source commit and exact deployed commit `36bc8cd65218cb4bb0e39750bbdf0215e78a5432`.

The reviewed tree was later squash-merged from PR #68 into main as `8e43a174a6ba4bb2db2a507086e7fbc93b0f44cd`. The reviewed PR tree and merged-main tree were verified equivalent; these commits identify the actual benchmark execution and the later merged-main identity separately.

## Bounded read-only method and normal conditions

The execution used an authorized non-production WordPress validation environment with configured API access. Synchronization state was idle and unambiguous, observation conditions were stable and normal, and no concurrent settings or administrative mutation occurred during the benchmark window.

Requests were serial, with at least 500 milliseconds between dispatch starts. The bounded profile performed one projects warm-up, deterministic eligible project/token discovery through unmeasured token-list probes, one token-detail warm-up, and ten measured logical requests for each of `projects`, `token_list`, and `token_detail`. It allowed at most 45 HTTP attempts and imposed a five-minute helper ceiling. The selected identifiers, request and response content, and private environment details are not published.

## Strict decision rule

M3-06 passed only if all of the following were true:

- exactly ten valid measured samples existed for each endpoint class;
- there were zero failed HTTP attempts and zero measured logical failures;
- captured WordPress state was unchanged;
- the execution limits were respected; and
- every endpoint-class maximum was strictly below `1.000` seconds.

## Sanitized results

| Class | Count | Minimum | Mean | Median | p95 | Maximum |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| projects | 10 | 0.085155 s | 0.112483 s | 0.090024 s | 0.196377 s | 0.196377 s |
| token_list | 10 | 0.086739 s | 0.110942 s | 0.091766 s | 0.175453 s | 0.175453 s |
| token_detail | 10 | 0.084951 s | 0.114858 s | 0.090945 s | 0.185806 s | 0.185806 s |
| combined | 30 | 0.084951 s | 0.112761 s | 0.090411 s | 0.185806 s | 0.196377 s |

| Check | Result |
| --- | --- |
| HTTP attempts | 33 |
| Failed HTTP attempts | 0 |
| Measured logical failures | 0 |
| WordPress state equal before and after | PASS |
| Safety limits respected | PASS |
| M3-06 result | **PASS** |
| Private evidence retained | yes |
| Private evidence SHA-256 | `a48ca44f94725349d34554063dd4b8f2676629aa2ec44c405f6c671785fa648e` |
| Diagnostic output size | 0 bytes |

All three endpoint-class maxima were strictly below `1.000` seconds. Therefore, **M3-06 is Validated and the benchmark result is PASS under the defined normal conditions and disclosed scope**.

## Merged-main verification

The exact merged main commit `8e43a174a6ba4bb2db2a507086e7fbc93b0f44cd` was subsequently deployed clean and active. It passed post-merge public-safe testing and the full existing-readonly Phase 2 validation, including Playwright, WP-CLI, database-state checks, runtime integrity, final integrity, source integrity, and deployed integrity.

The live API benchmark was not repeated after merge because exact reviewed-to-merged tree equivalence was proven. A rerun is needed only if the relevant implementation or benchmark assumptions change.

## Limitations

- This is one controlled observation window in one authorized non-production environment.
- Traffic was serial under normal conditions, not concurrent, load, or stress traffic.
- Token-list and token-detail scope used one deterministically selected eligible project/token pair.
- The result makes no claim about every NMKR endpoint or every network or service condition.
- It does not validate NFT page-load performance or uptime.
- It does not provide chain-specific synchronization validation or heavier cross-chain traffic validation.
- It validates M3-06 only and partially supports the broader M3-03 and M3-19 reporting requirements; their remaining evidence stays open.

The public record intentionally excludes private URLs or hostnames, filesystem paths, credentials or API keys, API headers, project or token identifiers, request or response payloads, database output, raw logs, private diagnostics, and customer- or environment-specific data.
