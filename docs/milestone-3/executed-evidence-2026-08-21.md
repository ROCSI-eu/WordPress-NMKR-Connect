# Executed evidence — 2026-08-21

## Evidence identity and scope

This sanitized report registers **EVD-007** primarily for **M3-01**, with **M3-19** indexing the report, and records a bounded compatibility execution across two separately configured WordPress installations on the project’s maintained runtime. The exact source commit and deployed plugin commit on both executed profiles was `bbf87e351a118ff67d5b57c2c20dfcf4e936b477`.

The execution covered one development installation and one staging installation. A production installation on the same host was inventoried only and was not modified, authenticated against, or used for intentional runtime mutation.

## Tested environment

The tested installations were hosted on a conventional self-managed Google Cloud Compute Engine WordPress stack with:

- Ubuntu 24.04.4 LTS;
- Apache 2.4.58 using the prefork MPM and Apache PHP module;
- PHP 8.3.6;
- MariaDB 10.11.14;
- WordPress 7.1;
- Hello Elementor 3.4.9;
- NMKR Connect 0.1.

Both installations used the same operating-system, web-server, PHP, database-server, WordPress, theme, and plugin versions. They remained separate WordPress sites with separate databases and environment classifications. The development database used `utf8mb4_unicode_ci`; staging used `utf8mb4_unicode_520_ci`.

## Method

The compatibility execution verified the following on both development and staging:

- exact deployed commit and clean worktree;
- site identity and WordPress environment type;
- required NMKR database tables;
- API-key presence without exposing the value;
- idle synchronization and passing database health;
- the repository's `scripts/nmkr-wpcli-smoke.sh` result;
- representative shortcode runtime registration;
- unauthenticated homepage HTTP readiness;
- unchanged fingerprint of selected synchronization options and NMKR table counts before and after the smoke.

No real NMKR synchronization ran. No intentional option, transient, database, history, content, or production mutation occurred.

## Results

| Profile | WordPress environment type | Database collation | Exact commit | Repository WP-CLI smoke | Runtime registration | Homepage readiness | State preservation |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Development | `development` | `utf8mb4_unicode_ci` | PASS | PASS | PASS | PASS | PASS |
| Staging | `staging` | `utf8mb4_unicode_520_ci` | PASS | PASS | PASS | PASS | PASS |

The cross-environment execution result was **PASS**. Both installations ran the same exact clean plugin build on the project’s current maintained WordPress/PHP stack, and the bounded read-oriented checks completed without detected state mutation.

The private compatibility execution record SHA-256 was `603a7bb9b234bd1fa411628802ff8156d69558bf7bf68221f6fbf819b1473b53`.

## Requirement assessment

**M3-01 is Validated within the disclosed compatibility boundary.** NMKR Connect was exercised on separate development and staging WordPress installations using a representative real-world self-managed Google Cloud VM stack and the current maintained runtime used by the project.

This result demonstrates compatibility with the recorded WordPress environment and with separately configured WordPress installations. It does not claim certification for every hosting provider, managed WordPress platform, operating system, web server, PHP version, database version, theme, plugin combination, or network topology.

Runtime evidence was intentionally limited to the project's maintained, current real-world WordPress and PHP environment rather than an artificial legacy matrix. Obsolete or older runtime versions were not deployed solely to expand the matrix; declared minimum-version metadata is separate from this executed runtime evidence and does not establish executed coverage for older WordPress or PHP versions.

## Final state and evidence retention

Development and staging remained healthy, active, idle, and database-ready after the smoke. The production installation was not modified or authenticated against. Private raw logs and the private evidence manifest remain outside the public repository. Only the sanitized method, aggregate result, exact commit, and evidence hash are published here.

## Limitations

- Both tested WordPress installations shared one Google Cloud VM and the same underlying software stack.
- The result is multi-installation WordPress compatibility evidence, not independent-provider or broad cross-hosting certification.
- Only WordPress 7.1 and PHP 8.3.6 were executed for this evidence item.
- The smoke was read-oriented and representative rather than exhaustive.
- No real synchronization, destructive action, production deployment, authenticated production test, browser matrix, or third-party hosting comparison was part of this execution.
- The result does not guarantee compatibility with every theme, extension, managed host, runtime combination, or future release.

## Public/private publication boundary

This report excludes private URLs and domains, infrastructure identifiers, filesystem paths, credentials, API keys, cookies, nonces, database contents, project/token identifiers, raw logs, screenshots, traces, videos, generated reports, authentication state, and private evidence locations. The published SHA-256 identifies the retained private compatibility execution record without exposing its contents.

---

## EVD-008 — bounded repeated sequential mixed-chain traffic

### Evidence identity and scope

This sanitized report registers **EVD-008** for **M3-05**, **M3-11**, and **M3-19**. The controlled-mutation campaign ran on 2026-08-21 in an authorized non-production WordPress validation environment. Its exact source commit and exact deployed commit were both `bbf87e351a118ff67d5b57c2c20dfcf4e936b477`; this documentation change is based on later commit `12737990da042279d8db7b10f3c37ac767f705bd`, which was not deployed or executed for EVD-008.

The campaign was bounded repeated sequential traffic over the same stable mixed-chain dataset. It comprised three separately authorized stages of three cycles each, for nine complete synchronization cycles. Execution was never concurrent: at most one synchronization was active, successful cycles had a 15-second cooldown, and no automatic retry followed an attempted synchronization Start.

### Method, profile, and safeguards

- Each of the nine cycles had one synchronization Start and processed the same 10 projects, 100 unique tokens, and 100 token-detail records.
- Each cycle performed 121 NMKR API requests and 100 token-processing operations.
- Authorization was separately established for each stage; execution remained sequential with a one-active-synchronization ceiling.
- The campaign used controlled mutation in non-production, with terminal-state, database-integrity, worker/marker, dataset, source-worktree, and deployed-worktree checks.
- Cooldown was enforced after successful cycles, and an attempted Start was not automatically retried.

### Aggregate results

| Measure | Result |
| --- | --- |
| Stages and cycles | 3 stages × 3 cycles; 9 complete cycles |
| Synchronization Starts | 9 |
| Cycle outcomes | 9 successful; 0 failed; every cycle completed terminally |
| Per-cycle item outcome | 100 processed; 100 successful; 0 failed |
| NMKR API requests | 121 per cycle; 1,089 total |
| Token processing | 100 operations per cycle; 900 repeated operations total |
| Stable dataset | 10 projects; 100 unique tokens; 100 token-detail records in every cycle |
| New persistence records | Exactly 9 distinct completed history records and 9 distinct metrics records |
| Campaign status | Completed |
| Validation result | **PASS** |

The 900 figure is repeated processing of 100 unique tokens over nine cycles, not 900 unique tokens and not nine different datasets.

### Cross-chain attribution

The execution-time profile was stable across Cycles 1–9:

| Profile | Cardano-only | Solana-only | Dual-chain | Other/unknown |
| --- | ---: | ---: | ---: | ---: |
| Projects per cycle | 4 | 4 | 2 | 0 |
| Unique tokens per cycle | 40 | 40 | 20 | 0 |
| Repeated token-processing operations across the campaign | 360 | 360 | 180 | 0 |

Cardano and Solana were directly represented in every cycle. The campaign totals preserve direct attribution to both chains without treating success on one chain as evidence for the other.

### Performance ranges

| Recorded measure across nine cycles | Minimum | Median | Maximum |
| --- | ---: | ---: | ---: |
| Synchronization duration | 24.6503 s | 29.6331 s | 45.4955 s |
| Total API time | 18.5278 s | 22.3653 s | 34.7862 s |
| Stored average response time | 0.1531 s | 0.1848 s | 0.2875 s |
| Recorded memory usage | 50 MB | 50 MB | 50 MB |

### Final state and integrity

The final dataset remained 10 projects, 100 tokens, and 100 details. The runtime was clean, terminal, idle, and internally consistent. Checks detected no duplicate project, token, or token-detail identifiers; invalid relationships; or impossible counters. No active synchronization history, owner/option/transient/finalization/recovery/heartbeat marker, duplicate worker, or blocked synchronization cron remained. The exact source and deployed worktrees remained unchanged and clean.

### Classification-snapshot anomaly

The pre-campaign classification snapshot differed from the execution-time classification and is not authoritative for chain attribution. The execution-time project and token profiles were stable throughout Cycles 1–9, and the final sealed manifest's 360 Cardano-only, 360 Solana-only, and 180 dual-chain operation totals match the cycle evidence. This classification-snapshot anomaly did not affect the stable execution-time profile, cycle success, request totals, database integrity, or final state. No cause is asserted.


> **Later evidence (2026-08-26):** The statements below preserve EVD-008's earlier-state assessment. The separately authorized larger sequential campaign later ran and is registered as [EVD-011](executed-evidence-2026-08-26.md), providing further supporting/partial evidence without closing M3-05 or M3-11.

### Requirement assessment

- **M3-05 remains Implemented, validation pending.** EVD-008 is supporting/partial evidence: it supplies measured aggregate repeated volume, explicit ceilings and cooldown, successful outcomes, performance ranges, integrity checks, and a clean final state, but it retained the guarded light profile and did not establish materially higher processing pressure.
- **M3-11 remains Implemented, validation pending.** EVD-008 is supporting/partial evidence because Cardano and Solana were directly and stably represented during all nine cycles and across the 900 repeated token-processing operations, but the sequential light-profile campaign did not establish heavier API traffic or stress.
- **M3-19 is supported** by this reviewer-visible sanitized report and its register entry.

The validated EVD-008 execution boundary is the disclosed nine-cycle sequential repeated-traffic profile; it does not by itself validate the high-traffic or stress criteria in M3-05 or M3-11. It is not concurrent load, production traffic, a maximum-capacity, saturation, breaking-point, or unlimited-scale test, and it is not formal certification.

The next required action identified at the EVD-008 date was a bounded plugin-level higher-pressure test using synthetic mixed-chain API data, without overloading the live NMKR API. EVD-011 later supplied a larger sequential functional-volume campaign, but current acceptance still requires a defined, measured load/stress profile rather than dataset volume alone. It must not treat production, upstream, unlimited-scale, or external infrastructure capacity as a plugin deliverable.

### Evidence retention and limitations

Private raw evidence is retained. Its final evidence-seal SHA-256 is `7f743f8aa9fc4ec78e07dd0d1cab38ec1102dc9f5d4d6bb48002f87ddddb7341`.

- Results cover one authorized non-production environment, one stable dataset, and the exact executed source/deployed commit only.
- Nine sequential cycles do not establish concurrent behavior, production capacity, maximum load, or a breaking point.
- The campaign does not guarantee behavior for arbitrary account sizes, datasets, hosting environments, upstream conditions, future releases, or unlimited scale.
- The evidence does not claim independent hosting-provider coverage or broad stress certification.
- Private URLs, paths, usernames, infrastructure identifiers, project/token identifiers, API responses, raw database rows, screenshots, credentials, API keys, cookies, nonces, logs, and artifact locations remain outside the public repository.
