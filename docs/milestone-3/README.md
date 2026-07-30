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
- This landing page defines the shared vocabulary and publication boundary.

Reports should be added only when real results exist, then linked from the matrix and register. An adjustable final set may contain concise functionality, usability, performance/load, cross-chain, and security summaries plus reviewer-environment instructions. Subjects may be combined where one reproducible report serves several rows; no empty reports are needed.

## Parallel synchronization work

PR #56, **“Stream synchronization pagination, progress, and final metrics,”** is open from baseline `aef735064c1aefd83d8cd816e58e9fdd60600c6b` with the supplied head `12d6a4f56f5577a9d877ec7c8a4275a5830f10e4`. It is neither merged nor runtime-validated by this workspace. Evidence involving traversal beyond the first token page, representative high token volume, run-scoped deduplication, unique final totals, provisional progress, pagination failures, volume metrics, or related cross-chain volume claims remains pending PR #56 **and** subsequent private validation. This documentation does not copy or reimplement that work.

## Public and private boundary

Assume commits and PR discussion are public. Public-safe automation, generic methods, and sanitized summaries may be committed. Credentials, private endpoints or infrastructure identifiers, customer/project/token identifiers, raw logs or database output, populated environment files, cookies/nonces, and artifacts or participant details containing private data must not be committed. Private raw evidence may be retained under project controls and referenced by a sanitized public summary; the register records that retention only as **yes**, **no**, or **not applicable**, never as a private location.
