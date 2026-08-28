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
- [Testing-environment access guide](testing-environment-access.md) — public-safe procedure for a public four-document evidence folder, an operational project-selected support route, and temporary access available on request from three revalidated generic staging profiles; its presence and the private preflight are not evidence that the folder was submitted or access was requested or supplied.
- [Executed evidence — 2026-08-17](executed-evidence-2026-08-17.md) — sanitized exact-commit mixed-chain synchronization, recovery, final-state, and existing-readonly functionality results.
- [Executed evidence — 2026-08-18](executed-evidence-2026-08-18.md) — sanitized exact-commit guarded NMKR API response-time benchmark results; M3-06 is validated under the report's defined normal conditions.
- [Executed evidence — 2026-08-20](executed-evidence-2026-08-20.md) — sanitized exact-commit NFT display page-load results; M3-07 is validated for the representative single-NFT display under the report's defined conditions, with the broader multi-item prepared-dataset boundary disclosed.
- [Executed evidence — 2026-08-21](executed-evidence-2026-08-21.md) — EVD-007 records sanitized exact-commit current-runtime compatibility across separate installations; [EVD-008](executed-evidence-2026-08-21.md#evd-008--bounded-repeated-sequential-mixed-chain-traffic) is validated executed evidence for a bounded nine-cycle sequential mixed-chain traffic campaign and supporting/partial evidence for M3-05 and M3-11.
- [Executed evidence — 2026-08-22](executed-evidence-2026-08-22.md) — EVD-009 is the sanitized exact-commit M3-13 privileged error-disclosure, browser-formatting, failed-terminal, and affected logging-response validation record; [EVD-010](executed-evidence-2026-08-22.md#evd-010--restricted-role-and-privileged-ajax-security-validation) is the separate sanitized exact-commit M3-14 restricted-role and privileged-AJAX security validation record.
- [Executed evidence — 2026-08-26](executed-evidence-2026-08-26.md) — EVD-011 records the bounded 2,400-unique-token cold/warm sequential synthetic mixed-chain campaign and validates M3-05 and M3-11 only within that disclosed profile.
- [Executed evidence — 2026-08-27](executed-evidence-2026-08-27.md) — EVD-012 records a 16.14-day external public staging-service availability observation and validates M3-08 only within its disclosed window and monitoring profile.
- [User guide](../user-guide.md) and [troubleshooting guide](../troubleshooting.md) — current documentation foundations for M3-16 and M3-18.
- [Developer guide](../developer-guide.md) — current implementation documentation for M3-17, assessed and indexed by EVD-006.
- This landing page defines the shared vocabulary and publication boundary.

Reports should be added only when real results exist, then linked from the matrix and register. An adjustable final set may contain concise functionality, usability, performance/load, cross-chain, and security summaries plus reviewer-environment instructions. Subjects may be combined where one reproducible report serves several rows; no empty reports are needed.

The [consolidated proof package](proof-of-achievement.md) maps the executed reports, current implementation controls, regressions, CI foundations, and existing guides into the four contractual evidence groups. Scoped requirements are validated only within the reports' disclosed limits: EVD-007 is current-runtime multi-installation evidence rather than broad hosting certification; EVD-008 remains historically accurate supporting/partial evidence; EVD-009 and EVD-010 validate their disclosed security boundaries; EVD-011 validates M3-05 and M3-11 only for its sequential synthetic 2,400-unique-token cold/warm profile; and EVD-012 validates M3-08 only for its disclosed 16.14-day public staging-service observation window and external monitor profile. Concurrency, saturation, breaking-point, production/upstream capacity, unlimited scale, plugin or origin-host uptime, and external infrastructure certification are not claimed. M3-21 now has an implemented public-safe delivery procedure and a completed private preflight, but actual Catalyst submission and evidence registration remain pending. Only M3-21 remains open, so Milestone 3 is not complete. EVD-006 created no new runtime evidence, and EVD-013 has not been created.

## Current synchronization foundation

PR #56, **“Stream synchronization pagination, progress, and final metrics,”** merged into main as `3c61a928e5ae9c9459b9c108f1187bc6ca742afc` (final PR head `12d6a4f56f5577a9d877ec7c8a4275a5830f10e4`). The project owner confirms exact-head private pre-merge and merged-main private post-merge operational validation. No private location, output, or infrastructure detail is published here.

The merged implementation establishes streaming traversal, run-scoped deduplication, bounded progress, pagination safety, and canonical final metrics. EVD-001 supplies the light foundation, EVD-008 supplies repeated sequential supporting evidence, and [EVD-011](executed-evidence-2026-08-26.md) supplies the executed bounded higher-volume sequential synthetic result for M3-05 and M3-11. It made 4,994 isolated synthetic provider/API requests and 4,800 processing operations over 2,400 unique tokens without duplicate warm-run business rows. This is not uptime, concurrent, saturation, maximum-capacity, production/upstream, unlimited-scale, or broad infrastructure evidence. [EVD-012](executed-evidence-2026-08-27.md) separately records environment availability rather than synchronization or plugin execution. M3-08 is Validated only within that report's disclosed window and profile; M3-21 is **Implemented, validation pending**, and Milestone 3 is not claimed complete.

## Public and private boundary

Assume commits and PR discussion are public. Public-safe automation, generic methods, and sanitized summaries may be committed. Credentials, private endpoints or infrastructure identifiers, customer/project/token identifiers, raw logs or database output, populated environment files, cookies/nonces, and artifacts or participant details containing private data must not be committed. Private raw evidence may be retained under project controls and referenced by a sanitized public summary; the register records that retention only as **yes**, **no**, or **not applicable**, never as a private location.
