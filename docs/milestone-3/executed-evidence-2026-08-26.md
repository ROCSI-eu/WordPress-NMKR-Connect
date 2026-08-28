# Executed evidence — 2026-08-26

## EVD-011 — bounded higher-volume sequential synthetic mixed-chain synchronization

### Evidence identity and scope

EVD-011 records one authorized cold campaign and one authorized warm campaign using the fixed `private-2400-v1` profile on 2026-08-26. It validates M3-05 and M3-11 only within the bounded plugin-level sequential synthetic profiles defined in the requirement assessment below and provides supporting evidence for M3-09, M3-10, and M3-19.

### Implementation and exact commits

The isolated synthetic synchronization harness was implemented in [PR #77](https://github.com/ROCSI-eu/WordPress-NMKR-Connect/pull/77). Its exact final PR head was `8ace8ff4c39d1d0bfc908e56f6895c18b53af7d7`; the exact merged and post-merge validated main commit was `ec337531d5c40ba0e5a53cdad6d1e526000742d1`.

### Safety and isolation boundary

Execution used a separate synthetic WordPress installation and database on one existing private validation VM. Provider execution was loopback-only and isolated. Each campaign was separately authorized, sequential, and had no automatic retry. There was no live NMKR API traffic, production execution, customer data, external request, or real NMKR synchronization.

### Defined workload

Each campaign contained 24 projects, 2,400 unique tokens, 2,400 token-detail records, 3,600 list appearances, and 1,200 controlled duplicate appearances across project lists. It made one project-list request, 96 token-list requests, and 2,400 token-detail requests: 2,497 synthetic provider/API requests per campaign.

Across cold and warm execution, the bounded campaign made 4,994 synthetic provider/API requests and performed 4,800 token-processing operations over the same 2,400 unique tokens. The 4,800 figure is an operation count, not a unique-token count.

### Cold campaign result

The synthetic business state transitioned from 0/0/0 to 24/2,400/2,400 projects/tokens/details, exactly matching the expected rows. Project classification was 8 Cardano-only, 8 Solana-only, and 8 dual-chain; token attribution was 800/800/800. History and metrics each increased by one row. Provider counters were `projects=1`, `token_lists=96`, `details=2400`, `external=0`, `violations=0`, and `total=2497`. Diagnostics were `clean=14`, `vendor-only=0`, and `blocking=0`. The run reached its completed terminal state and cleanup passed.

### Warm campaign result

The business state remained 24/2,400/2,400, with a 0/0/0 synthetic business-row delta and no duplicate business rows. Project classification remained 8/8/8 and token attribution remained 800/800/800. History and metrics each increased by one row. Provider counters again were `projects=1`, `token_lists=96`, `details=2400`, `external=0`, `violations=0`, and `total=2497`. Diagnostics again were `clean=14`, `vendor-only=0`, and `blocking=0`. The run reached its completed terminal state and cleanup passed.

### Cross-chain attribution

Both campaigns directly exercised 8 Cardano-only, 8 Solana-only, and 8 dual-chain projects, with 800 Cardano-only, 800 Solana-only, and 800 dual-chain unique tokens. Attribution was preserved through the warm repeat; success for one chain is not substituted for the other.

### Lifecycle, metrics, history, and cleanup

Both runs completed terminally with the expected one-row history and metrics deltas. The cold run created the defined business rows; the warm run repeated processing without adding business rows. Counters matched the fixed request model, no external requests or provider violations occurred, diagnostics were non-blocking, and cleanup passed after each run.

### Private evidence retention identifiers

Private evidence is retained under project controls. Only these approved integrity identifiers are public:

| Retained item | SHA-256 |
| --- | --- |
| Rollback backup | `06996dac5183c3cf8de997e434eb285b7b8dbabf27d302585b27d5827cc88e46` |
| Cold evidence manifest | `f9305f7527d706db0fe4b0e8507e6a1488d13a9183e354802d86f7bb320d3c72` |
| Cold seal record | `567ae570b5f1fb6c720c4990eb1bfb5b9df5c733f2f126fcca2651bb165c88c6` |
| Warm evidence manifest | `8b818deb7088420eb5c34cc56b9d2f07bf68754656f2db179f530632cd39ec75` |
| Warm seal record | `634c920dde0b1ab0194da5e2fb5e86f1a6b99f629f72250bfb9af6888477a70e` |
| Post-merge staging deployment receipt | `283469d961ef53d1f7fe3ac3fef66866775484de0e97e06698f52aaaddfe20f1` |
| Post-merge staging validation receipt | `596bb680140fbb719ee318f03124c04543d72ee1aac630a17626869cbb60a126` |

### Post-merge development and staging validation

The exact merged main commit was subsequently deployed to development and staging. Development passed the full Playwright suite, WP-CLI and database-state checks, runtime and final integrity, and readonly policy. The API-key fingerprint and NMKR table counts were preserved; synchronization stayed idle; authentication state was cleaned; and no real or synthetic synchronization ran during post-deployment checks.

Staging received the exact manifest-bound release. Manifest, file count, ownership, executable modes, the full Playwright suite, WP-CLI database-state checks, and runtime dependency integrity passed. API fingerprint, NMKR table counts, and synchronization state were preserved. Authentication state and generated Playwright output were cleaned, and no real or synthetic synchronization ran during post-deployment checks.

### Contractual and plugin-boundary interpretation

The Milestone 3 statement is outcome-based. It does not prescribe concurrency, requests per second, saturation testing, breaking-point discovery, CPU or memory thresholds, throughput targets, latency percentiles, or a particular load-testing tool. Where exact numeric targets were intended, the milestone states them separately for API response time, NFT display page load, and uptime under M3-06, M3-07, and M3-08.

For this WordPress plugin, M3-05 is interpreted as reliable plugin-controlled behavior under a materially enlarged synchronization request and data workload through pagination, project, token, and detail processing, persistence, deduplication, progress, metrics, history, terminalization, and cleanup. M3-11 is interpreted as reliable mixed-chain synchronization under a bounded heavy cross-chain request workload directly representing Cardano-only, Solana-only, and dual-chain data.

Concurrent visitor capacity, arbitrary requests-per-second capacity, host saturation, maximum server throughput, every hosting configuration, production infrastructure scalability, and upstream NMKR capacity depend substantially on the hosting, runtime, and upstream environment and are outside this bounded plugin-level claim.

### Requirement assessment

- **M3-05 — Validated**, only within the bounded plugin-level high-volume sequential synthetic synchronization profile represented by EVD-008 and EVD-011.
- **M3-11 — Validated**, only within the bounded heavy sequential mixed-chain request profile represented by EVD-008 and EVD-011, with direct Cardano-only, Solana-only, and dual-chain attribution.
- **M3-09, M3-10, and M3-19 — supporting scope only.** EVD-011 strengthens their existing evidence without redefining their primary assessments.

### Limitations

This was sequential, synthetic, non-production execution in one controlled environment. EVD-011 does **not** certify or claim concurrent visitor capacity; arbitrary requests-per-second capacity; saturation or a breaking point; maximum throughput or maximum server capacity; every shared-hosting, VM, dedicated-server, PHP, database, cache, network, or WordPress configuration; production infrastructure scalability; live NMKR/upstream capacity; unlimited datasets; universal behavior; formal certification; or penetration testing.

M3-08 remains **Planned** because the uptime monitoring record is outstanding. M3-21 remains **Planned** because actual private reviewer-access creation, delivery, support, expiry, and revocation remain outstanding. Those two operational actions remain open, and Milestone 3 remains incomplete until they are completed.

> **Later evidence (2026-08-28)**
>
> [EVD-012](executed-evidence-2026-08-27.md) subsequently validated M3-08 within its disclosed public staging-service monitoring scope, and [EVD-013](executed-evidence-2026-08-28.md) subsequently validated M3-21 within its controlled, on-request, non-production access-delivery scope. The [Proof of Achievement](proof-of-achievement.md) was submitted at 2026-08-28 11:53 UTC, and the current consolidated status is recorded there and in the [evidence register](evidence-register.md). This note supersedes only the later status, not the historical EVD-011 execution record.

### Public/private boundary

This report publishes only sanitized aggregate results, public commit and PR references, disclosed methods and limitations, and approved SHA-256 identifiers. Private locations, URLs, hostnames, infrastructure identifiers, accounts, credentials, authentication material, raw logs, database output, non-synthetic identifiers, screenshots, traces, videos, HTML reports, and private artifacts remain private.
