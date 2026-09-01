# Milestone 4 evidence register

## Purpose and evidence model

This is the canonical public-safe Milestone 4 evidence ledger and the evidence handoff to M4-10. `M4-FND-*` identifies an analysis, implementation, tooling, or documentation foundation and does not, by itself, imply execution. `M4-EVD-*` identifies executed evidence or a completed bounded assessment. Historical package records remain authoritative for their execution details; this register reconciles their current disposition without rewriting them.

Reviewed/source heads, deployed commits, merge commits, evidence-document commits, and Git trees are distinct fields. Package-private M4-02, M4-03, and M4-05 evidence complements, but is not replaced by, cumulative M4-08 evidence. Dependency-audit results are historical, lock-bound, and time-dependent. `Not applicable` means that the field was not part of that evidence class; it does not mean that an unrecorded private activity occurred.

## Evidence ledger

### `M4-FND-001` — M4-01 exact-baseline audit foundation

- **Package; mappings:** M4-01; `S-03`, `S-04`, `S-06`, `D-01`; `M4-T-01` through `M4-T-10`.
- **Class; status:** source analysis and documentation scaffold; complete foundation, not executed runtime evidence.
- **Analysis/implementation baseline:** `354cd5808c8eebf557a8286452752245901cbd50`, tree `fde67f888eab7ffd0cde1ebf0aa0b3a6173fd9e6`.
- **Reviewed/source SHA; deployed SHA:** PR #85 final head `138001227b4139bfb56a82ae7dc79788603dee8f`; Not applicable.
- **Merged commit/tree:** `3e1ded1636cc440865ca69e25d6620fe3a5be0e1`, tree `6dc6cda2366351773cf29d7f8711597ae5efc7dd` (tree-equivalent to the final head).
- **Public method/CI; sanitized private method:** exact-baseline source analysis, scaffold review, and public-safe CI; Not applicable.
- **Date; visibility; retention:** 2026-08-29; public; repository documentation retained.
- **Bounded result:** established the handler/capability/side-effect inventory, findings taxonomy, remediation sequence, traceability, and safety boundary; rejected `S-03` on that exact baseline.
- **Limitations/non-claims; relationship:** analysis did not remediate or execute runtime behavior and is not audit completion. Later packages implement and test bounded controls; this row remains their provenance foundation.

### `M4-EVD-001` — M4-02 dashboard mutation authority

- **Package; mappings:** M4-02; `S-01`, `S-04`, `S-06`; `M4-T-01`, `M4-T-02`, `M4-T-09`.
- **Class; status:** implementation plus public CI and package-private exact-head execution; resolved/validated within M4-02 scope.
- **Analysis/implementation baseline:** M4-01 scaffold state; no separate baseline recorded in the historical package record.
- **Reviewed/source SHA; deployed SHA:** PR #86 head `dcb60de4d86536cef715bff03378a31f1994daf8`; that exact head pre-merge and merged main `a46e78c975647dd9a3398b45c90d602e11249585` post-merge.
- **Merged commit/tree:** `a46e78c975647dd9a3398b45c90d602e11249585`, validated tree `df84512bfe25d9632e58a399e5c737edb32d14d2` (tree-equivalent to the reviewed head).
- **Public method/CI; sanitized private method:** public regressions and successful CI run `33251143441` (run 334); restricted-role/authorized comparisons, targeted AJAX-security, non-mutating browser, readiness, smoke, state, and idle checks.
- **Date; visibility; retention:** 2026-08-29; public record with sanitized private conclusions; private artifacts were removed or excluded as recorded.
- **Bounded result:** view-only callers were denied the affected mutations with no relevant state delta while bounded manager behavior remained available.
- **Limitations/non-claims; relationship:** not every endpoint, universal authorization, complete side-effect absence, production validation, or real synchronization. Complemented by `M4-EVD-007`, not superseded by it. See [M4-02 executed evidence](executed-evidence-m4-02-2026-08-29.md).

### `M4-EVD-002` — M4-03 progress/recovery authority

- **Package; mappings:** M4-03; `S-02`, `S-04`, `S-06`; `M4-T-01`, `M4-T-03`, `M4-T-09`.
- **Class; status:** implementation plus deterministic/public CI and package-private exact-head execution; resolved/validated within M4-03 scope.
- **Analysis/implementation baseline:** `a3a4fdad90df76fbcef66c929ac4f03a13c5a042`.
- **Reviewed/source SHA; deployed SHA:** PR #88 head `eb1a9d537cae76a220594e0d480fd7eb05e4abe9`; that exact head pre-merge and merged main `c69e4ff109948f871201ee396853cbb9f1716a45` post-merge.
- **Merged commit/tree:** `c69e4ff109948f871201ee396853cbb9f1716a45`, validated tree `8c54e0d4f75e3f0250c8e8a278bed621e18f74b5` (tree-equivalent to the reviewed head).
- **Public method/CI; sanitized private method:** capability/state-delta and lifecycle regressions plus successful CI run `33258466402`; transactional history, rollback/idempotence, terminal-state, readiness, and final-state checks.
- **Date; visibility; retention:** 2026-08-30; public record with sanitized private conclusions; no public retention of private artifacts.
- **Bounded result:** polling remains observational while recovery mutation requires management authority and covered history transitions fail closed.
- **Limitations/non-claims; relationship:** no real synchronization, universal lifecycle proof, or complete side-effect-absence proof. Complemented by `M4-EVD-007`. See [M4-03 executed evidence](executed-evidence-m4-03-2026-08-30.md).

### `M4-EVD-003` — M4-04 rendering/disclosure assessment

- **Package; mappings:** M4-04; `A-01`; `M4-T-07`, `M4-T-08`.
- **Class; status:** completed source-only documentation assessment; no runtime remediation justified.
- **Analysis/implementation baseline:** `fd1ac072aeb37b189c9734e0602a58c8c6b8a30c`, tree `af40fd04381a57883e961b71fddcb2777c1cda50`.
- **Reviewed/source SHA; deployed SHA:** evidence PR #90 head `7d0d23e74b72f0d4710b67968a32e4bd6ef0d3f7`; Not applicable.
- **Merged commit/tree:** `22cb8cee1c35deb7ba18ce1d0ac76b3f738ab52b`, evidence tree `24d82eba13df1ef7e5bfbf6a8b6aa6b6e6e68026`.
- **Public method/CI; sanitized private method:** manual source tracing, targeted repository searches, documentation checks, and public CI; Not applicable.
- **Date; visibility; retention:** 2026-08-30; public; repository evidence retained.
- **Bounded result:** no confirmed unsafe rendering sink, stored/reflected XSS path, or inappropriate client diagnostic disclosure was established on the inspected baseline.
- **Limitations/non-claims; relationship:** not browser execution, dynamic scanning, penetration testing, deployed evidence, or universal XSS/disclosure assurance. Later `M4-EVD-005` narrows `A-01` for three fixtures. See [M4-04 executed evidence](executed-evidence-m4-04-2026-08-30.md).

### `M4-EVD-004` — M4-05 analytics-ingestion evidence

- **Package; mappings:** M4-05; `S-05`; `M4-T-05`, `M4-T-09`.
- **Class; status:** implementation plus public deterministic and package-private execution; resolved/validated within M4-05's bounded contract.
- **Analysis/implementation baseline:** `22cb8cee1c35deb7ba18ce1d0ac76b3f738ab52b`, tree `24d82eba13df1ef7e5bfbf6a8b6aa6b6e6e68026`.
- **Reviewed/source SHA; deployed SHA:** PR #91 head `ba5f934e6285089ba4c4946084d23d877fb122ee`; that exact head pre-merge and merged main `4a48dc50498e5de618c794a32b91fea968487eb3` post-merge.
- **Merged commit/tree:** `4a48dc50498e5de618c794a32b91fea968487eb3`, tree `4b6694520b2e8325c3a4c676a0cefd725f6bfdd8` (tree-equivalent to the reviewed head).
- **Public method/CI; sanitized private method:** deterministic injected interleavings, bounds and sink-suppression checks plus successful CI `33369689169` (run 356); WordPress/database concurrency, quota, contention, recovery, endpoint, cleanup, and final-state checks.
- **Date; visibility; retention:** 2026-08-31; public record with sanitized private conclusions; no public retention of private artifacts.
- **Bounded result:** durable/concurrency-aware admission, bounded payload handling, and fail-closed ambiguous-state behavior were established for the disclosed contract.
- **Limitations/non-claims; relationship:** not universal abuse resistance, exactly-once delivery, every topology, production assurance, or external GA4/NMKR traffic. Complemented by `M4-EVD-007`. See [M4-05 executed evidence](executed-evidence-m4-05-2026-08-31.md).

### `M4-FND-002` — M4-06 security/static/dependency tooling foundation

- **Package; mappings:** M4-06; `S-06`, `A-01`; `M4-T-01`, `M4-T-04`, `M4-T-06`, `M4-T-07`, `M4-T-08`.
- **Class; status:** test/tooling implementation foundation; complete, but the foundation alone does not imply execution.
- **Analysis/implementation baseline:** `820b2cfeae0ea26de8078a9ccf3d7fe0a5ca14bd`, tree `26560e630f096bc3e4d6827843aacaeeb2b0a742`.
- **Reviewed/source SHA; deployed SHA:** PR #93 final head `c50c15770f93dd7c5d7ae2508f0d3be20fc57c0c`; Not applicable.
- **Merged commit/tree:** `fecac597ee7260659b1772de0a8c9128aad30770`, tree `cc5c1f3f219069d259ab22cf217df6eca2f4c0ac` (tree-equivalent to the final head).
- **Public method/CI; sanitized private method:** implementation/review of security contracts, synthetic DOM runner, static gates, and dependency jobs; Not applicable.
- **Date; visibility; retention:** 2026-08-31; public; tracked tooling retained.
- **Bounded result:** established maintainable targeted public-safe checks without changing runtime or dependency locks.
- **Limitations/non-claims; relationship:** tooling is not universal proof. Its historical execution is separately recorded as `M4-EVD-005`.

### `M4-EVD-005` — M4-06 public execution and exact-head CI

- **Package; mappings:** M4-06; `S-06`, `A-01`; `M4-T-01`, `M4-T-04`, `M4-T-06`, `M4-T-07`, `M4-T-08`, `M4-T-09`.
- **Class; status:** public deterministic/synthetic execution, static checks, lock-bound dependency audits, and exact-head CI; passed within exercised scope.
- **Analysis/implementation baseline:** same as `M4-FND-002`.
- **Reviewed/source SHA; deployed SHA:** `c50c15770f93dd7c5d7ae2508f0d3be20fc57c0c`; Not applicable.
- **Merged commit/tree:** `fecac597ee7260659b1772de0a8c9128aad30770`, tree `cc5c1f3f219069d259ab22cf217df6eca2f4c0ac`.
- **Public method/CI; sanitized private method:** all three exact-head CI jobs passed in run `33397493179` (run 372), covering public-safe contracts, synthetic rendering, static checks, and dependency audits; Not applicable.
- **Date; visibility; retention:** 2026-08-31; public; CI result and repository record retained, dependency result historically time-bound.
- **Bounded result:** narrowed `S-06` for classified registrations/representative denials and `A-01` for three exercised final-DOM sinks.
- **Limitations/non-claims; relationship:** `S-06` and `A-01` remain open; no full dispatch/state-delta, universal SQL-injection/XSS, dependency-safety, penetration-test, or production claim. Complemented by `M4-EVD-007`. See [M4-06 executed evidence](executed-evidence-m4-06-2026-08-31.md).

### `M4-EVD-006` — M4-07 documentation assessment and closure

- **Package; mappings:** M4-07; `S-07`, `D-02`, `D-03`, `D-04`; `M4-T-10`.
- **Class; status:** completed documentation-only implementation, assessment, correction, and exact-head CI.
- **Analysis/implementation baseline:** `72944bdb7848cb16fc0eb3d108e5602034153aef`, tree `de7b13e9b960491f8896e388670e2852b63e7f92`.
- **Reviewed/source SHA; deployed SHA:** PR #96 final reviewed head `c6f2bc2057e3034d2d218d70aee9d1817897ccb7`; Not applicable.
- **Merged commit/tree:** `2b32a6213c38018bc8e013f7f948c08b1b6a9151`, tree `9a48e61ef8b2025a54f58098403202184ef5039c` (tree-equivalent to final head).
- **Public method/CI; sanitized private method:** focused accuracy/link/anchor review, correction, `git diff --check`, and three successful exact-head CI jobs in run `33411893816` (run 382); Not applicable.
- **Date; visibility; retention:** 2026-08-31; public; repository record retained.
- **Bounded result:** current capability, recovery, public-ingestion, FAQ, and navigation documentation gaps were resolved within scope.
- **Limitations/non-claims; relationship:** no runtime behavior or security property was changed or newly proven; future behavior changes require revalidation. See [M4-07 executed evidence](executed-evidence-m4-07-2026-08-31.md).

### `M4-EVD-007` — M4-08 cumulative private exact-head validation

- **Package; mappings:** M4-08; `S-01`, `S-02`, `S-04`, `S-05`, `S-06`, `A-01`, `D-05`; `M4-T-01` through `M4-T-09`.
- **Class; status:** cumulative private exact-head readonly execution; passed within the recorded DEV scope.
- **Analysis/implementation baseline:** post-M4-07 runtime state at the exact validated source identity below.
- **Reviewed/source SHA; deployed SHA:** runtime SHA `204cb395e1a4c9b8de63659a56d9a63dc082ecd8`, tree `54c3c377405803a5f36453a55d81814b5de59fcb`; deployed at that same SHA.
- **Merged commit/tree:** runtime target `204cb395e1a4c9b8de63659a56d9a63dc082ecd8`, tree `54c3c377405803a5f36453a55d81814b5de59fcb`. The later evidence-document PR #98 head was `c299db20d99306e3f5d828ff0a5afff3212e054f`; it merged as `aae16a77deb08cf7341ba8104f94df6f4928fe74`, tree `5e9b3cfc4259e1528939142c0b78cfcae9cd8602`.
- **Public method/CI; sanitized private method:** sanitized evidence-document review/public CI; readonly Phase 2, complete browser suite, smoke, database state, runtime/source/deployed integrity, specialized AJAX-security, and final idle-state checks.
- **Date; visibility; retention:** 2026-09-01; public sanitized conclusions from private execution; artifacts disabled and none retained.
- **Bounded result:** cumulative checks passed at the exact runtime head; tracked executable-mode drift was stopped by integrity, corrected, and reconfirmed before runtime checks.
- **Limitations/non-claims; relationship:** the private run did **not** validate the later documentation-only PR #98 merge. It does not replace package-specific evidence or establish production, external-API, universal security, or milestone completion. See [M4-08 executed evidence](executed-evidence-m4-08-2026-09-01.md).

### `M4-EVD-008` — M4-09 evidence reconciliation and M4-10 handoff

- **Package; mappings:** M4-09; all findings, especially `D-05`; `M4-T-01` through `M4-T-10`.
- **Class; status:** completed documentation-only evidence reconciliation in this package; M4-10 final reporting remains pending.
- **Analysis/implementation baseline:** `aae16a77deb08cf7341ba8104f94df6f4928fe74`, tree `5e9b3cfc4259e1528939142c0b78cfcae9cd8602`.
- **Reviewed/source SHA; deployed SHA:** this M4-09 PR's exact documentation head, recorded by the PR and exact-head CI; Not applicable.
- **Merged commit/tree:** Not applicable until merge; merge identity must be recorded by the PR/CI history and must not be treated as privately validated runtime evidence.
- **Public method/CI; sanitized private method:** exact-baseline provenance reconciliation, object/tree verification, changed-link/anchor/table/status/public-safety checks, `git diff --check`, and public CI; Not applicable.
- **Date; visibility; retention:** 2026-09-01; public; this repository ledger and PR/CI history are retained.
- **Bounded result:** stable IDs now connect M4-01 through M4-09 provenance, findings, traceability, evidence classes, current dispositions, limitations, and the M4-10 handoff; `D-05` is resolved for evidence governance/consolidation.
- **Limitations/non-claims; relationship:** this reconciliation does not re-execute historical validation, validate a later merge, complete M4-10, or complete Milestone 4. M4-10 must consume this ledger for the final report and Proof of Achievement without broadening its claims.

## M4-10 handoff

M4-10 is the sole pending Milestone 4 package. It must use the ledger rows and linked historical records as bounded inputs, preserve `S-06` and `A-01` as open assurance gaps, keep historical dependency results time-dependent, and keep the M4-08 runtime identity separate from its later evidence-document merge. M4-10 may produce the final report and Proof of Achievement; M4-09 does not do so and does not claim Milestone 4 delivery, approval, certification, penetration testing, production security, or universal assurance.
