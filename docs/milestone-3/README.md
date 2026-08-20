# Milestone 3 evidence workspace

This directory is the living, delivery-focused workspace for assembling credible Milestone 3 evidence. It connects the contractual subjects to proportionate tests and retained results without creating an exhaustive certification system. These foundation documents are plans and inventories; their presence does **not** claim that Milestone 3 is complete or formally approved.

## Reading the evidence

Keep these concepts separate:

- **Implementation** is product code or documentation that may be tested.
- **Validation** is an executed check against an identified commit, method, and environment.
- **Reviewer evidence** is the retained public result or sanitized summary that lets a reviewer assess the validation and its limitations.

Delivery priority is independent of evidence status. Use only these priorities:

1. **Submission-critical** — needed for a defensible milestone submission.
2. **Useful if time permits** — strengthens the submission after critical work.
3. **Deferred improvement** — worthwhile follow-up that need not block delivery.

Use only these statuses: **Not started**, **Planned**, **Implemented, validation pending**, **Validated**, and **Not applicable**. “Implemented, validation pending” includes available tooling that has not yet produced retained contractual evidence.

## Foundation documents

- [Traceability matrix](traceability-matrix.md) — one primary row for each contractual subject, its minimum credible evidence, and dependencies.
- [Evidence register](evidence-register.md) — the ledger of tooling foundations and, later, executed evidence.
- [Testing plan](testing-plan.md) — adjustable execution boundaries and the minimum test record.
- [Proof of achievement](proof-of-achievement.md) — concise reviewer-facing synthesis under the four contractual evidence groups and EVD-006 exact-source assessment.
- [Executed evidence — 2026-08-17](executed-evidence-2026-08-17.md) — sanitized exact-commit mixed-chain synchronization, recovery, final-state, and existing-readonly functionality results.
- [Executed evidence — 2026-08-18](executed-evidence-2026-08-18.md) — sanitized exact-commit guarded NMKR API response-time benchmark results; M3-06 is validated under the report's defined normal conditions.
- [Executed evidence — 2026-08-20](executed-evidence-2026-08-20.md) — sanitized exact-commit NFT display page-load results; M3-07 is validated for the representative single-NFT display under the report's defined conditions, with the broader multi-item prepared-dataset boundary disclosed.
- [User guide](../user-guide.md) and [troubleshooting guide](../troubleshooting.md) — current documentation foundations for M3-16 and M3-18.
- [Developer guide](../developer-guide.md) — current implementation documentation for M3-17, assessed and indexed by EVD-006.
- This landing page defines the shared vocabulary and publication boundary.

Reports should be added only when real results exist, then linked from the matrix and register. An adjustable final set may contain concise functionality, usability, performance/load, cross-chain, and security summaries plus reviewer-environment instructions. Subjects may be combined where one reproducible report serves several rows; no empty reports are needed.

The [consolidated proof package](proof-of-achievement.md) maps the executed reports, current implementation controls, regressions, CI foundations, and existing guides into the four contractual evidence groups. Scoped requirements are validated only within the reports' disclosed limits: the mixed-chain execution is bounded light-profile operational evidence rather than high-traffic, heavy-request, stress, or capacity certification, and the benchmark boundaries remain unchanged. M3-01, M3-05, M3-08, M3-11, M3-13, M3-14, and M3-21 remain open for the exact operational or security actions listed in the proof document, so Milestone 3 as a whole is not complete. EVD-006 is a documentation/source assessment and created no new runtime evidence.

## Current synchronization foundation

PR #56, **“Stream synchronization pagination, progress, and final metrics,”** merged into main as `3c61a928e5ae9c9459b9c108f1187bc6ca742afc` (final PR head `12d6a4f56f5577a9d877ec7c8a4275a5830f10e4`). The project owner confirms exact-head private pre-merge and merged-main private post-merge operational validation. No private location, output, or infrastructure detail is published here.

The merged implementation establishes streaming numbered-page traversal, run-scoped UID deduplication, provisional bounded progress, authoritative unique final totals, pagination safety failures, and canonical final metrics as current foundations. EVD-001 separately supplies a bounded light-profile operational synchronization and directly attributable Cardano, Solana, and mixed-chain evidence; it is only supporting evidence for M3-05 and M3-11, not their required high-traffic or heavy-request execution. EVD-004 and EVD-005 provide scoped API-response and representative single-NFT display evidence respectively. None supplies uptime or broad hosting certification, and Milestone 3 is not claimed complete.

## Public and private boundary

Assume commits and PR discussion are public. Public-safe automation, generic methods, and sanitized summaries may be committed. Credentials, private endpoints or infrastructure identifiers, customer/project/token identifiers, raw logs or database output, populated environment files, cookies/nonces, and artifacts or participant details containing private data must not be committed. Private raw evidence may be retained under project controls and referenced by a sanitized public summary; the register records that retention only as **yes**, **no**, or **not applicable**, never as a private location.
