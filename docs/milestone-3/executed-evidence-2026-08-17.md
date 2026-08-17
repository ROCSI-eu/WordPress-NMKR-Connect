# Milestone 3 executed evidence — 2026-08-17

This public-safe report registers one authorized controlled mixed-chain synchronization and one existing-state read-only validation execution. It contains sanitized aggregates only. The retained private sanitized evidence has SHA-256 `d90c40b0e5ec4e8c834695413ef578adcfc5d9307b358a62753b8a09d1ff2a3d`.

## Identity and public validation profile

- Exact source commit: `aadc7d2a0c404fcae51fe1df0cef75e859adccf1` (clean worktree).
- Exact deployed commit: `aadc7d2a0c404fcae51fe1df0cef75e859adccf1` (clean worktree).
- Environment profile: authorized non-production WordPress validation environment. Identifying hosting and software-stack details remain in the private evidence.
- Synchronization settings: light profile, batch size 3, and batch delay 3 seconds.

## EVD-001 — controlled mixed-chain synchronization and final state

The controlled dataset contained 10 projects, 100 tokens, and 100 token-detail rows. Project classifications were four Cardano-only, four Solana-only, two Cardano and Solana, and zero other/unknown. All 10 project blockchain values were valid JSON; none were invalid. No project name, UID, token identifier, identifying connection detail, or private record is retained publicly.

The successful attempt completed with a 28-second history duration: 100 items processed, 100 successful, zero failed, and no error present. Its stored metrics were:

| Metric | Recorded value |
| --- | --- |
| Timestamp | 2026-08-17 10:44:31 |
| Total projects | 10 |
| Total tokens | 100 |
| Total synchronization duration | 27.2997 seconds |
| Total API time | 22.2128 seconds |
| Average recorded API response | 0.1836 seconds |
| API requests | 121 |
| Memory usage | 36 MB |

Final verification found 10 projects, 100 tokens, 100 token details, two history rows, two terminal history rows, zero active history rows, and one metrics row. The latest history row was `completed` with a valid end time; the last-sync timestamp matched the latest metrics. No active option markers or blocked synchronization cron events remained. The light-profile guard and final aggregate-state check passed.

## EVD-002 — exact-commit existing-state functionality

The full existing-readonly Phase 2 suite passed against the exact source and deployed commit. Playwright, WP-CLI, database-state checks, runtime integrity, final integrity, source integrity, and deployed integrity all passed. Read-only policy was enforced, and this suite performed no synchronization or intentional data mutation.

This execution provides exact-commit existing-state functional evidence for the covered automated browser, command-line, database-state, and integrity paths. It does not by itself supply the concise manual feature matrix, participant usability evidence, multiple-hosting coverage, display performance measurements, or a full security assessment.

## EVD-003 — terminal failure and recovery

The initial controlled attempt terminated safely as `failed` after 1 second with an error present. It processed zero items, with zero successful and zero failed items, and ended cleanly without project, token, or detail writes. The raw cause and remediation details remain private.

After private remediation, a second authorized attempt produced the successful EVD-001 result. Final-state verification preserved both attempts as terminal history, with no active history. This is scoped recovery and terminalization evidence for the observed controlled-failure path; the failed attempt alone does not validate a success requirement or every failure mode.

## Interpretation and remaining evidence

This was one mixed-chain synchronization containing real Cardano and Solana project data. It was not two separately executed and independently measured chain-specific runs, and it does not establish heavy cross-chain traffic. The recorded 0.1836-second average supports later performance work but does not validate the contractual below-one-second NMKR API target: a controlled benchmark must still define its sample, repetitions, failures, median, p95, maximum, and conditions.

The evidence does not validate the below-two-second NFT display target, above-99.9% uptime, bounded load/stress behaviour, multiple hosting environments, participant usability, a full security assessment, or documentation review. Existing external uptime monitoring remains in progress and must later be assessed over a disclosed observation period. Future display measurements should use existing shortcode index/display pages. Bounded load work must remain moderate and state ceilings, stop conditions, recovery, and authorized-environment limitations. Any further real synchronization requires separate explicit authorization.

Before the 2026-09-01 delivery target, the proportionate next actions are the defined API benchmark, existing-page display measurements, assessment of existing uptime monitoring, moderate bounded-load evidence, separate authorized chain-specific executions if needed to satisfy their criteria, and the remaining functionality, compatibility, usability, security, and documentation reviews. Reviewer access details and Free/Premium boundaries should be disclosed through public-safe summaries and private out-of-band access where applicable.
