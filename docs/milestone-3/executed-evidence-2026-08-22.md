# Executed evidence — 2026-08-22

## Evidence identity and scope

This sanitized report registers **EVD-009** primarily for **M3-13 — Secure errors and logging**. It also supports the **M3-19** evidence index and **M3-20** bounded security implementation evidence. It records the completed privileged error-disclosure hardening and its exact pre-merge and post-merge validation.

The implementation was [PR #76](https://github.com/ROCSI-eu/WordPress-NMKR-Connect/pull/76). The exact reviewed and pre-merge-validated head was `90523640e5d9199f94c5a6988fc0c3dae9f1de59`. The exact merged and post-merge-validated main commit was `d84a193877ea84f79be94d435b9b7191d1c9a5df`.

## Implemented security boundary

The merged change:

- replaced privileged synchronization AJAX exposure of internal exception, `WP_Error`, stored synchronization-error, unsuccessful-result, and technical-detail text with fixed translated public-safe messages and stable error codes;
- sanitizes canonically verified failed-terminal polling responses, including non-empty private error and current-item diagnostic fields;
- retains private server-side diagnostic paths rather than publishing their details in browser responses;
- returns generic fixed error messages for the affected log-clearing exception paths;
- separates browser application errors from actual HTTP/transport errors;
- excludes raw `responseText`, `statusText`, arbitrary unknown messages, and parsed response objects from the affected browser formatting and console paths;
- retains useful fixed or backend-controlled messages only for explicitly allowlisted error codes; and
- adds focused PHP and Node disclosure regressions and wires them into public checks.

This is a bounded statement about the affected paths, not a claim that every possible log or every application error is universally redacted.

## Review and public CI

The initial implementation received full Codex review. Multiple corrective commits addressed all reported findings, all inline review threads were resolved, and a final full Codex review on the final PR head reported no major issue. Phase 4 CI passed on that exact final PR head. Codex review is not a formal security certification.

## Pre-merge validation

Private exact-head validation recorded the following sanitized outcomes:

- the exact PR head was deployed;
- source and deployed worktrees were clean after permission normalization;
- the focused PHP AJAX error-disclosure regression passed;
- the focused Node transport-disclosure regression passed;
- the privileged AJAX guard regression passed;
- the full non-mutating Playwright suite passed with **18 passed, 2 skipped**;
- the two skipped tests depended on the separately prepared restricted-role private contract and belong to the separately executed M3-14 boundary rather than invalidating M3-13;
- WP-CLI smoke passed;
- read-only database and synchronization-state checks passed;
- WordPress/plugin health passed;
- synchronization remained idle;
- no real NMKR synchronization ran; and
- no Playwright screenshots, traces, videos, or reports were retained.

## Merge and post-merge validation

PR #76 merged using the regular merge method. Merged-main ancestry and tree equivalence with the validated PR head were verified. The exact merged main was deployed, after which:

- the focused disclosure regressions passed again;
- the full non-mutating Playwright suite passed again;
- WP-CLI smoke passed again;
- read-only database and synchronization-state checks passed again;
- WordPress, plugin, web server, and database service readiness passed;
- API-key presence remained configured without publishing its value;
- synchronization remained idle;
- the deployed worktree was clean;
- plugin permissions and ownership passed;
- no real synchronization ran; and
- no Playwright artifacts were retained.

## Deployment-mode observation

Deployment validation detected mode-only executable-bit differences caused by deployment permission handling. File contents were verified as identical to the exact Git commit before normalization, executable modes were restored from the Git index, and the final deployed worktree was clean. This was deployment tooling behavior, not a plugin-source content difference.

## Result and requirement assessment

**EVD-009 result: PASS.** **M3-13 is Validated within the disclosed privileged AJAX, browser-error, failed-terminal, and affected logging-response boundary.**

[EVD-003](executed-evidence-2026-08-17.md#evd-003--terminal-failure-and-recovery) remains useful supporting failure/recovery evidence. [EVD-006](proof-of-achievement.md) accurately identified a residual error-disclosure hardening action at its earlier assessment baseline; the later EVD-009 implementation and validation closes that specific previously open residual.

## Evidence retention

The private evidence-manifest SHA-256 is `af3f94876bc9bd9f4942eb27880649999b3940ab0c565da19757f5e388d7b04e`.

The raw private record remains outside the public repository. No private paths, URLs, identities, credentials, configuration values, authentication state, database output, logs, or artifacts are published here.

## Limitations

- This is not penetration testing or security certification.
- It is not complete OWASP coverage.
- It does not prove that arbitrary third-party code or arbitrary raw logs redact every possible secret.
- It does not guarantee absence of future disclosure vulnerabilities.
- It validates only the exact source/deployed commits and recorded methods.
- M3-14 restricted-role/capability/nonce live evidence is outside EVD-009 and is registered separately as EVD-010 below.
- No real NMKR synchronization or destructive runtime action was part of this evidence execution.


## EVD-010 — Restricted-role and privileged-AJAX security validation

### Evidence identity and requirement scope

This sanitized executed-evidence record registers **EVD-010** primarily for **M3-14 — WordPress security controls, capabilities and nonces**. It also supports the **M3-19** evidence index and **M3-20** bounded security implementation evidence. EVD-010 is separate from EVD-009 and does not expand EVD-009 beyond M3-13.

The exact clean source commit and exact clean active deployed commit were both `d84a193877ea84f79be94d435b9b7191d1c9a5df`. The public documentation-only `main` observed during execution was `9f0298368c7dd56b970272cb5ed542aa92941c15`. Execution occurred on **2026-08-22**.

### Targeted method and safety boundary

The targeted private execution used `npm run test:ajax-security` through the repository's fail-closed restricted-account wrapper. It verified the exact clean source and active deployment at the same commit, then used prepared Administrator and synthetic `nmkr-marketing` accounts. The restricted role had exactly the intended analytics access and lacked dashboard and synchronization-management capabilities. Synchronization was ownerless and idle before execution.

The execution kept `RUN_REAL_SYNC=false` and `PW_SAVE_ARTIFACTS=false`. It performed no real NMKR synchronization or destructive runtime action. Raw evidence was retained privately outside the repository.

### Sanitized negative-case results

The following checks passed:

- anonymous privileged AJAX requests were denied;
- requests with missing nonces were denied;
- requests with invalid nonces were denied;
- insufficient-role privileged requests were denied;
- a malformed Stop request was denied; and
- the negative cases caused no mutation of the bounded protected plugin state covered by the repository helper.

### Sanitized authorized-case results

The following checks passed:

- the prepared Administrator and restricted `nmkr-marketing` capability split was verified;
- an authorized NMKR Marketing aggregate analytics request succeeded; and
- an authorized Administrator synchronization-health request succeeded.

### Protected-state and final-state results

The following checks passed:

- exact clean source and deployed SHA verification;
- synchronization owner, lifecycle, cron, history, and metrics state remained unchanged;
- final WP-CLI smoke;
- final database integrity checks;
- final ownerless idle-state verification;
- confirmation that no real NMKR synchronization ran; and
- confirmation that no Playwright screenshots, traces, videos, or HTML reports were retained.

These state-equality checks cover only the bounded protected plugin state implemented by the repository helper; they do not claim equality of arbitrary WordPress state.

### Result and M3-14 assessment

**EVD-010 result: PASS.** **M3-14 is Validated only within EVD-010's disclosed live boundary for the tested privileged AJAX actions, role/capability separation, nonce enforcement, malformed Stop rejection, bounded authorized read-oriented requests, and bounded protected plugin state.**

### Evidence retention and public/private boundary

Private evidence retained: **yes**. The public-safe private manifest SHA-256 is `a410eff0c17f32e309961c2a345bdff79b068feccc90d5513421c34cd5f684b1`.

The execution and raw evidence remain private outside the repository. This public record publishes no private record identifier or location, environment file, account identifier, URL, nonce, cookie, password, raw log, database output, authentication state, infrastructure identifier, or private record-bundle hash.

### Limitations

- EVD-010 is representative live-boundary evidence for the tested privileged AJAX actions, role/capability separation, nonce enforcement, malformed Stop rejection, and bounded authorized read-oriented requests.
- It is not penetration testing or security certification.
- It is not complete OWASP coverage.
- It does not establish the absence of every authorization, CSRF, or other vulnerability.
- It does not cover arbitrary third-party code or every WordPress endpoint.
- It validates only the exact commits, environment boundary, accounts, and methods described.
- It treats WordPress nonces as CSRF mitigation, not authentication.
- It involved no real synchronization or destructive runtime action.
