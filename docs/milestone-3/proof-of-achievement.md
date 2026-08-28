# Milestone 3 proof of achievement

This is the public, documentation-only synthesis of [EVD-001 through EVD-003](executed-evidence-2026-08-17.md), [EVD-004](executed-evidence-2026-08-18.md), [EVD-005](executed-evidence-2026-08-20.md), [EVD-007 and EVD-008](executed-evidence-2026-08-21.md), [EVD-009 and EVD-010](executed-evidence-2026-08-22.md), [EVD-011](executed-evidence-2026-08-26.md), and [EVD-012](executed-evidence-2026-08-27.md), current implementation controls, public regressions and CI foundations, and the existing user, developer, troubleshooting, Playwright, and numbered testing guides. The exact source baseline assessed was `2b97486a023366669d7744993ad15e86f47070ca`; EVD-007 through EVD-011 are later, separately registered executions against the identities stated in their reports. EVD-012 is later external availability evidence for which source and deployed plugin commits are not applicable because no single plugin deployment is claimed across the monitored period.

This assessment produced no new runtime observation, synchronization, benchmark, browser execution, private validation, security attack, participant study, or reviewer account. It links the detailed records instead of reproducing their reports, measurements, guides, or implementation descriptions. “Validated” below means that reviewer evidence supports the stated, bounded requirement; it is not a formal certification or a claim beyond the disclosed scope.

## 1. Reports of Testing Results

### Functionality

[EVD-002](executed-evidence-2026-08-17.md#evd-002--exact-commit-existing-state-functionality) records an exact-commit full existing-readonly Phase 2 pass across Playwright, WP-CLI, database-state, runtime/final/source/deployed integrity, and read-only enforcement. Together with the [Playwright coverage index](../testing-playwright.md#implemented-coverage), [WP-CLI smoke guidance](../testing-phase-1.md), [database-state checks](../testing-phase-8.md), and public regressions described by the [CI guide](../testing-phase-4.md), it covers representative critical functionality: administration and readiness; settings and access controls; dashboards and project views; synchronization status and lifecycle; shortcode and NFT display paths; analytics; error/final-state behavior; and integrity and read-only enforcement.

This is representative rather than exhaustive coverage. A new manual matrix was not created merely to duplicate the automated and already exercised operational paths.

### Compatibility

[EVD-007](executed-evidence-2026-08-21.md) records a passing read-oriented compatibility smoke on separate development and staging WordPress installations, with separate databases/configuration states and disclosed collations, on one representative self-managed Google Cloud VM stack. Both profiles used the same exact clean plugin commit and the project's maintained current WordPress/PHP runtime; required tables, API-key presence, idle/database health, WP-CLI smoke, shortcode runtime registration, homepage readiness, and before/after state fingerprints passed without a real synchronization or intentional mutation.

This validates M3-01 only within that current-runtime, multi-installation boundary. It is not evidence from independent hosting providers, and it does not certify managed hosts, every platform combination, production, or older WordPress/PHP runtimes. Obsolete or older runtimes were not deployed solely to create an artificial legacy matrix; declared minimum-version metadata remains separate from executed runtime evidence.

### Maintainer/operator usability

The exact-source assessment found coherent maintainer/operator workflows for installation and activation, configuration, synchronization start/progress/completion/Stop/recovery, shortcode setup and NFT display, analytics, role boundaries, troubleshooting, and safe escalation. The assessment is grounded in the repeatedly exercised functionality above and the [user](../user-guide.md), [developer](../developer-guide.md), and [troubleshooting](../troubleshooting.md) guides.

This is specifically a **maintainer/operator workflow usability assessment**. It is not an independent participant study and is not statistically representative user research.

### Bounded operational synchronization

[EVD-001](executed-evidence-2026-08-17.md#evd-001--controlled-mixed-chain-synchronization-and-final-state) is the bounded light-profile foundation, and [EVD-008](executed-evidence-2026-08-21.md#evd-008--bounded-repeated-sequential-mixed-chain-traffic) remains historically accurate supporting evidence. [EVD-011](executed-evidence-2026-08-26.md) records one cold and one warm `private-2400-v1` campaign. Each sequential campaign processed 24 projects, 2,400 unique tokens, and 2,400 details through 2,497 isolated synthetic requests. Cold state moved from 0/0/0 to 24/2,400/2,400; warm state remained unchanged with no duplicate business rows. History and metrics advanced once per run, provider counters matched the model, and terminalization, diagnostics, and cleanup passed.

Across both campaigns, 4,994 requests and 4,800 token-processing operations were executed over the same 2,400 unique tokens. EVD-011 validates M3-05 only within this bounded plugin-level high-volume sequential synthetic profile, covering reliable plugin-controlled pagination, project/token/detail processing, persistence, deduplication, progress, metrics, history, terminalization, and cleanup. The outcome-based requirement does not prescribe concurrency, requests per second, saturation or breaking-point testing, CPU/memory thresholds, throughput targets, latency percentiles, or a particular load-testing tool; exact numeric API-response, NFT-page-load, and uptime targets are stated separately in M3-06, M3-07, and M3-08.

### Cardano, Solana, and mixed-chain behavior

EVD-011 directly exercised and preserved 8 Cardano-only, 8 Solana-only, and 8 dual-chain projects, with 800 Cardano-only, 800 Solana-only, and 800 dual-chain unique tokens in both cold and warm runs. It supports the existing M3-09 and M3-10 conclusions and validates M3-11 only within this bounded sequential heavy mixed-chain request profile.

The execution was sequential, not concurrent; synthetic, not production; and limited to one controlled environment. It establishes no saturation or breaking point, maximum capacity, live NMKR/upstream capacity, external infrastructure certification, unlimited scale, universal guarantee, or future-release behavior. Broader testing is deferred strengthening, not a claim or blocker for these bounded conclusions.

### Performance benchmarks

- [EVD-004](executed-evidence-2026-08-18.md) records a representative guarded NMKR API benchmark in which all relevant endpoint-class maxima were below one second under the disclosed conditions.
- [EVD-005](executed-evidence-2026-08-20.md) records a representative single-NFT display with 10/10 measurements below two seconds and a maximum of 1534.47 ms; the report retains the broader five-page boundary and original conservative composite result.

Each result remains limited by the method and conditions in its source report.

### Public staging-service availability

[EVD-012](executed-evidence-2026-08-27.md) records `99.911956873%` independently calculated uptime from `2026-08-11T05:16:22Z` through `2026-08-27T08:42:33Z` (`1,394,771` seconds), with `1,228` seconds of downtime across four recovered connection-timeout observations. The result is strictly above the `99.9%` contractual threshold, so M3-08 is **Validated only for this disclosed 16.14-day public staging-service observation window and external `HEAD` monitoring profile**.

The measurement covers the externally observable HTTPS service through a CDN/proxy, not an isolated component. It is environment availability evidence rather than plugin-code or synchronization evidence, and it makes no plugin-correctness, origin-VM, CDN, production, global, lifetime, future-period, SLA, infrastructure-capacity, or universal availability claim. A separate cloud-platform check used a different target and profile and was excluded from the acceptance calculation.

## 2. Security Implementation Evidence

This section is a bounded implementation-evidence index, not a security certification. The [developer security boundaries](../developer-guide.md#wordpress-security-boundaries) and current source support:

- WordPress authenticated administrative sessions and server-side [plugin-specific capability checks](../../includes/roles/nmkr-roles.php), including least-privilege Administrator, NMKR Admin, and NMKR Marketing grants;
- action-specific nonces on privileged requests as CSRF mitigation, not authentication, with deterministic guard and prepared live-boundary foundations documented in the [Playwright guide](../testing-playwright.md#implemented-coverage);
- sanitization plus type, range, and allow-list validation in [settings](../../includes/pages/settings/nmkr-settings-validation.php), synchronization, analytics, and request boundaries;
- contextual output escaping in PHP and safe text insertion where implemented in browser code;
- prepared dynamic SQL values and constrained identifier/query boundaries in the database and reporting helpers;
- no-cache behavior on freshness-sensitive or privileged AJAX responses;
- run-scoped synchronization ownership, canonical terminalization, and safe failure/final-state behavior, supported by EVD-003 and public synchronization regressions;
- opt-in logging controls, bounded retention and entry sizes, and supported redaction or secret-avoidance paths, without treating arbitrary raw logs as public-safe; and
- locked Composer/npm dependencies, reproducible installation, [PHP compatibility checks](../testing-phase-5.md), [public CI](../testing-phase-4.md), and the deployed runtime dependency-integrity foundations described by the [validation policy](../validation-policy.md#exact-ref-deployment-contract).

### Encryption boundary

HTTPS/TLS provides encryption in transit when the WordPress environment and upstream requests are served over HTTPS. This assessment does not claim application-level encryption, encryption at rest, encrypted WordPress options, formal security certification, penetration testing, complete OWASP coverage, or absence of all vulnerabilities.

### Errors and logging

[EVD-003](executed-evidence-2026-08-17.md#evd-003--terminal-failure-and-recovery) remains useful supporting evidence for controlled terminal failure, private remediation, successful recovery, preserved terminal history, and clean final state. EVD-006 accurately identified a residual privileged error-disclosure hardening action at its earlier assessment baseline. The later [EVD-009](executed-evidence-2026-08-22.md) records its implementation and exact-head pre-merge and post-merge validation, so **M3-13 is Validated within the disclosed privileged AJAX, browser-error, failed-terminal, and affected logging-response boundary**.

This scoped EVD-009 result is not penetration testing, complete OWASP coverage, a universal log-redaction guarantee, or a substitute for the separate EVD-010 M3-14 restricted-role/capability/nonce live-evidence boundary. No raw response, private data, or log is published here.

### Capabilities and nonces

[EVD-010](executed-evidence-2026-08-22.md#evd-010--restricted-role-and-privileged-ajax-security-validation) records a passing targeted private exact-clean-source and active-deployment execution at `d84a193877ea84f79be94d435b9b7191d1c9a5df`. It verified the prepared Administrator and synthetic restricted-role capability split; denial of anonymous, missing-nonce, invalid-nonce, insufficient-role, and malformed Stop requests without mutation of the helper-bounded protected plugin state; successful bounded authorized analytics and synchronization-health reads; and unchanged synchronization/final state. **M3-14 is Validated only within that disclosed representative live boundary.** WordPress nonces are CSRF mitigation, not authentication. EVD-010 is not penetration testing, certification, complete OWASP coverage, every endpoint or third-party code, or proof that every authorization, CSRF, or other vulnerability is absent.

### Dependency management

`composer.lock`, `package-lock.json`, `composer install --no-dev --prefer-dist`, `npm ci`, public CI, PHP compatibility checks, and deployed runtime dependency-integrity foundations provide proportionate dependency-management evidence. No new dependency audit was run, and this assessment does not claim that a current online advisory audit passed or provide a permanent advisory-free guarantee.

## 3. Documentation Evidence

- The [user guide](../user-guide.md) covers site-owner installation/lifecycle, configuration, access, synchronization, NFT display, analytics/privacy, maintenance, and support boundaries.
- The [developer guide](../developer-guide.md) covers prerequisites, architecture and data flows, persistence, security boundaries, extension points, validation entry points, contribution workflow, and public safety.
- The [troubleshooting guide](../troubleshooting.md) supplies symptom-based, read-only-first diagnosis and safe escalation across setup, synchronization, display, permissions, analytics, and diagnostics.
- The [root README](../../README.md) is the concise product, requirements, feature, security, testing, and navigation entry point.
- The [Playwright guide](../testing-playwright.md) indexes browser coverage and its private-environment and artifact boundaries.
- The numbered [Phase 1](../testing-phase-1.md), [Phase 2](../testing-phase-2.md), [Phase 4](../testing-phase-4.md), [Phase 5](../testing-phase-5.md), [Phase 8](../testing-phase-8.md), [Phase 15](../testing-phase-15.md), [Phase 16A](../testing-phase-16a.md), and [Phase 16B.2](../testing-phase-16b2.md) guides retain focused validation procedures, while the [validation policy](../validation-policy.md) selects proportionate checks.

These links are the evidence index; the underlying instructions are not copied here.

## 4. Testing Environment Access

The [testing-environment access guide](testing-environment-access.md) implements the public procedure for controlled non-production access. One public-safe Google Drive folder may be linked from the public Catalyst Proof of Achievement and contains four evidence documents aligned to Reports of Testing Results, Security Implementation Evidence, Documentation Evidence, and Testing Environment Access. Temporary login details are excluded from that folder and the public form and are supplied separately through the support route after the assessor identifies one of three generic capability profiles. Existing dedicated accounts may be reused after credential, capability, login, and intended-page revalidation. Once access is supplied, ordinary one-at-a-time dashboard Start, progress, completion, Stop, and abort observation remain permitted; concurrent, repetitive, load, stress, fault-injection, destructive, direct-state, and infrastructure-level activity remain prohibited.

The private staging preflight revalidated three existing dedicated accounts and the required environment-integrity and health conditions, without publishing private values. The public evidence folder has not been submitted through the Catalyst form, so no temporary-access delivery, expiry/revocation result, or EVD-013 is asserted. Actual folder submission, separate support-route delivery when requested, and later sanitized evidence registration remain open. M3-21 is **Implemented, validation pending**, and Milestone 3 remains incomplete.

## Requirement disposition

| ID | Status | Evidence and limitation |
| --- | --- | --- |
| M3-01 | Validated | EVD-007 validates the maintained current runtime across separate development and staging installations on one representative self-managed GCP VM stack; no independent-provider, managed-host, broad cross-platform, production-execution, or legacy-runtime claim is made. |
| M3-02 | Validated | EVD-002 and EVD-006 support representative, non-exhaustive functionality coverage. |
| M3-03 | Validated | EVD-001, EVD-004, and EVD-005 retain the scoped performance results. |
| M3-04 | Validated | EVD-006 records the maintainer/operator workflow assessment; no participant study is claimed. |
| M3-05 | Validated | EVD-011 validates only the bounded plugin-level high-volume sequential synthetic profile; no concurrent, production/upstream, saturation, maximum-capacity, unlimited-scale, or external infrastructure claim is made. |
| M3-06 | Validated | EVD-004 applies only under its defined normal conditions. |
| M3-07 | Validated | EVD-005 applies to the representative single-NFT display under its defined conditions. |
| M3-08 | Validated | EVD-012 passes strictly above `99.9%` only for its disclosed 16.14-day public staging-service window and external monitor profile; no plugin-code, origin-host, CDN, production, global, lifetime, SLA, infrastructure-capacity, or universal availability claim is made. |
| M3-09 | Validated | EVD-001 is primary evidence; EVD-011 supports it with 8 Cardano-only and 8 dual-chain projects and 800 Cardano-only and 800 dual-chain tokens. |
| M3-10 | Validated | EVD-001 is primary evidence; EVD-011 supports it with 8 Solana-only and 8 dual-chain projects and 800 Solana-only and 800 dual-chain tokens. |
| M3-11 | Validated | EVD-011 validates only the bounded sequential heavy mixed-chain request profile directly exercising both chains; no concurrent stress, production/upstream, breaking-point, maximum-capacity, or universal claim is made. |
| M3-12 | Validated | EVD-006 records a scoped implementation/control assessment; no universal vulnerability guarantee is made. |
| M3-13 | Validated | EVD-003 remains supporting failure/recovery evidence; EVD-006 identified the earlier residual, and EVD-009 records its later implemented and executed closure within the disclosed scope. |
| M3-14 | Validated | EVD-010 records the retained exact-clean-source/deployment live restricted-role and privileged-AJAX result, limited to its disclosed tested actions, accounts, nonce/CSRF, malformed-Stop, bounded authorized-read, and protected-plugin-state boundary. |
| M3-15 | Validated | EVD-006 covers lockfiles, reproducible installs, CI, compatibility, and runtime-integrity foundations, not a current advisory audit. |
| M3-16 | Validated | The user documentation is indexed and exact-source reviewed by EVD-006. |
| M3-17 | Validated | The developer documentation is indexed and exact-source reviewed by EVD-006. |
| M3-18 | Validated | The troubleshooting documentation, EVD-003, and EVD-006 provide scoped evidence. |
| M3-19 | Validated | This index and EVD-001 through EVD-012 provide consolidated reviewer-visible reports. |
| M3-20 | Validated | EVD-006, EVD-009, and EVD-010 provide bounded security implementation evidence with findings and limitations, not certification. |
| M3-21 | Implemented, validation pending | The public four-document folder procedure and private preflight are ready; actual folder submission, separate temporary-access delivery through support, assessment-window finalization, and sanitized EVD-013 registration remain outstanding. |

## Open actions

- **M3-21:** submit the public-safe four-document folder through the Catalyst Proof of Achievement, supply temporary login details separately through support when requested, finalize the authorized window, then create and register sanitized EVD-013.

Only M3-21 remains open. Milestone 3 as a whole remains incomplete until private reviewer-access execution is completed and registered.
