# Executed evidence — 2026-08-20

## Evidence identity and scope

This sanitized report registers **EVD-005** for NFT display page-load performance and assesses **M3-07** for a representative single-NFT front-end display under the conditions below. The execution began at `2026-08-20T12:58:11.282Z` against exact source commit and exact deployed commit `5876eb0c68449419be30088c054f5b98a10a79c0`.

The single-token shortcode page is the representative M3-07 display. Results from all five measured pages are retained below as supplementary boundary evidence, not as an expanded claim that every shortcode or dataset meets the target.

## Browser method and conditions

The execution used an authorized non-production WordPress validation environment, Chromium `149.0.7827.55`, Node `v20.20.2`, and a 1440 × 1000 viewport at device scale factor 1. It used an unthrottled VM-browser network profile and serial execution. Browser cache was disabled separately for every measured load.

Each page received one unrecorded warm-up followed by ten measured loads. Readiness was the first visible real NFT image successfully loaded and decoded; local placeholders did not qualify. No real NMKR synchronization ran, and synchronization state remained idle.

## Representative M3-07 decision rule

The representative single-token display passed only if all ten measured loads successfully loaded and decoded a real external NFT image strictly below 2,000 milliseconds, with no request/image failure or fallback sample.

This is intentionally narrower than the original composite runner rule. That runner conservatively required **all measurements on all five pages below 2,000 milliseconds**. The representative requirement assessment and the supplementary composite boundary result must therefore be read separately.

## Sanitized results

| Representative page | Successful loads | Below 2,000 ms | Minimum | Mean | Median | p95 | Maximum | Request/image failures | Fallback samples |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| TOKEN | 10/10 | 10/10 | 1284.98 ms | 1395.45 ms | 1385.24 ms | 1534.47 ms | 1534.47 ms | 0 | 0 |

A real external NFT image loaded and decoded in every TOKEN sample. The representative benchmark result is **PASS**.

## Supplementary five-page boundary result

| Page | Successful image loads | Minimum | Mean | Median | p95 | Maximum | Below 2,000 ms | Failures | Fallback samples |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| GRID | 10/10 | 1479.06 ms | 2146.02 ms | 2008.79 ms | 3285.02 ms | 3285.02 ms | 4/10 | 0 | 0 |
| LIST | 10/10 | 1466.85 ms | 2074.34 ms | 2049.86 ms | 2902.38 ms | 2902.38 ms | 4/10 | 0 | 0 |
| CAROUSEL | 10/10 | 1491.81 ms | 1910.39 ms | 1880.21 ms | 2432.63 ms | 2432.63 ms | 5/10 | 0 | 0 |
| TOKEN | 10/10 | 1284.98 ms | 1395.45 ms | 1385.24 ms | 1534.47 ms | 1534.47 ms | 10/10 | 0 | 0 |
| PROJECT | 10/10 | 1333.03 ms | 1508.28 ms | 1444.56 ms | 2016.56 ms | 2016.56 ms | 9/10 | 0 | 0 |
| Combined | 50 | 1284.98 ms | 1806.90 ms | 1617.25 ms | 2619.17 ms | 3285.02 ms | Not applicable | 0 | 0 |

The execution-summary SHA-256 was `09068086832f24aaef9b382e89117c74d8971ad64ef9376b795ed8e84f14e1c4`.

Because not every measurement on all five pages was below 2,000 milliseconds, the original composite runner printed `M3_07_BENCHMARK_FAIL`. This result is disclosed without concealment or relabeling. The broader execution is supplementary evidence of the prepared-dataset and content-delivery boundary; it is not the decision rule used for the representative single-NFT M3-07 assessment.

## Supporting image-size and concurrency diagnostics

Sanitized diagnostics showed:

- WordPress TTFB medians were approximately 609–695 ms, and first NFT image requests began around 834–976 ms.
- All measured first images were cross-origin. Rendering and decode after image transfer were comparatively small.
- Sampled NFT source images were 1024 × 1024 and approximately 1.6–1.9 MiB.
- GRID, LIST, and CAROUSEL started ten token-image requests before the first NFT became ready, with several requests still in flight at first-image readiness.
- The one-image TOKEN and PROJECT pages started one token-image request.
- All relevant markup reported `loading="lazy"` and `fetchpriority="auto"`.
- No fallback sample occurred.

These observations characterize a prepared-dataset/content-delivery boundary: large externally hosted NFT source assets and concurrent transfers affected the multi-item pages. They do not identify a plugin defect, prove image size was the only possible factor, or establish that an external service was unreliable.

## Requirement assessment

**M3-07 is Validated for a representative single-NFT front-end display under the disclosed conditions.** The representative TOKEN page met the fixed threshold in all ten measurements, with a maximum of 1534.47 ms. The complete five-page execution remains disclosed as supplementary boundary evidence.

This assessment does not claim that every shortcode page, NFT dataset, external gateway, arbitrary image size, network, or device always loads below two seconds. It does not validate load, heavier traffic, or uptime requirements.

EVD-005 also contributes representative display-performance evidence to M3-03 and a reviewer-visible report to M3-19. It does not independently complete unrelated performance work or the full M3-19 reporting requirement.

## Final state and evidence retention

No real NMKR synchronization ran. Final synchronization state was idle. No private raw benchmark artifact was retained, and no benchmark artifact is published.

## Limitations

- This is one observation window in one authorized non-production environment.
- The profile was cache-disabled and unthrottled in a VM browser; it does not represent every network or device.
- The representative decision covers one single-token display and its prepared dataset.
- Multi-item results were affected by large external source assets and concurrent transfers; the diagnostics do not isolate every causal factor.
- The result is not a guarantee for every shortcode, dataset, network, gateway, image size, or device.

## Public/private publication boundary

This report contains only the supplied sanitized aggregate measurements and method. It excludes private URLs and domains, infrastructure identifiers, filesystem paths, project/token/policy identifiers, NFT names, credentials, API keys, cookies, nonces, headers, raw logs, database output, screenshots, traces, videos, generated reports, authentication state, and private evidence locations. No private raw benchmark artifact was retained.
