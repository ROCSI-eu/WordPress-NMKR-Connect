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
- [Executed evidence — 2026-08-21](executed-evidence-2026-08-21.md) — EVD-007 records sanitized exact-commit current-runtime compatibility across separate installations; [EVD-008](executed-evidence-2026-08-21.md#evd-008--bounded-repeated-sequential-mixed-chain-traffic) is validated executed evidence for a bounded nine-cycle sequential mixed-chain traffic campaign and supporting/partial evidence for M3-05 and M3-11.
- [Executed evidence — 2026-08-22](executed-evidence-2026-08-22.md) — EVD-009 is the sanitized exact-commit M3-13 privileged error-disclosure, browser-formatting, failed-terminal, and affected logging-response validation record.
- [User guide](../user-guide.md) and [troubleshooting guide](../troubleshooting.md) — current documentation foundations for M3-16 and M3-18.
- [Developer guide](../developer-guide.md) — current implementation documentation for M3-17, assessed and indexed by EVD-006.
- This landing page defines the shared vocabulary and publication boundary.

Reports should be added only when real results exist, then linked from the matrix and register. An adjustable final set may contain concise functionality, usability, performance/load, cross-chain, and security summaries plus reviewer-environment instructions. Subjects may be combined where one reproducible report serves several rows; no empty reports are needed.

The [consolidated proof package](proof-of-achievement.md) maps the executed reports, current implementation controls, regressions, CI foundations, and existing guides into the four contractual evidence groups. Scoped requirements are validated only within the reports' disclosed limits: EVD-007 is multi-installation evidence on one representative GCP-hosted stack rather than broad hosting or legacy-runtime certification; EVD-008 validates its bounded nine-cycle sequential results but is only supporting/partial evidence for M3-05 and M3-11, which remain **Implemented, validation pending** because the light-profile campaign did not establish high traffic, heavier API traffic, or stress; and the benchmark boundaries remain unchanged. EVD-009 validates M3-13 within its disclosed boundary. M3-05 and M3-11 remain pending for the bounded larger synthetic workload, while M3-08, M3-14, and M3-21 remain open for the exact operational or security actions listed in the proof document, so Milestone 3 as a whole is not complete. EVD-006 is a documentation/source assessment and created no new runtime evidence.

## Current synchronization foundation

PR #56, **“Stream synchronization pagination, progress, and final metrics,”** merged into main as `3c61a928e5ae9c9459b9c108f1187bc6ca742afc` (final PR head `12d6a4f56f5577a9d877ec7c8a4275a5830f10e4`). The project owner confirms exact-head private pre-merge and merged-main private post-merge operational validation. No private location, output, or infrastructure detail is published here.

The merged implementation establishes streaming numbered-page traversal, run-scoped UID deduplication, provisional bounded progress, authoritative unique final totals, pagination safety failures, and canonical final metrics as current foundations. EVD-001 supplies the bounded light-profile foundation, and EVD-008 supplies validated repeated sequential mixed-chain results that partially support M3-05 and M3-11. Closing those requirements needs a bounded plugin-level higher-pressure test over a defined larger synthetic mixed-chain API workload, covering pagination, processing, database writes, deduplication, memory, metrics, terminalization, and cleanup without loading the live NMKR API or treating production, upstream, unlimited-scale, or external infrastructure capacity as plugin deliverables. EVD-004 and EVD-005 provide scoped API-response and representative single-NFT display evidence respectively. None supplies uptime, concurrent or maximum-capacity evidence, universal guarantees, or broad hosting certification, and Milestone 3 is not claimed complete.

## Public and private boundary

Assume commits and PR discussion are public. Public-safe automation, generic methods, and sanitized summaries may be committed. Credentials, private endpoints or infrastructure identifiers, customer/project/token identifiers, raw logs or database output, populated environment files, cookies/nonces, and artifacts or participant details containing private data must not be committed. Private raw evidence may be retained under project controls and referenced by a sanitized public summary; the register records that retention only as **yes**, **no**, or **not applicable**, never as a private location.
