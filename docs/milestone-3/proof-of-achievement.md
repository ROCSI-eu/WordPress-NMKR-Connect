# Milestone 3 proof of achievement

This is the public, documentation-only synthesis of [EVD-001 through EVD-003](executed-evidence-2026-08-17.md), [EVD-004](executed-evidence-2026-08-18.md), [EVD-005](executed-evidence-2026-08-20.md), and [EVD-007 and EVD-008](executed-evidence-2026-08-21.md), current implementation controls, public regressions and CI foundations, and the existing user, developer, troubleshooting, Playwright, and numbered testing guides. The exact source baseline assessed was `2b97486a023366669d7744993ad15e86f47070ca`; EVD-007 and EVD-008 are later, separately registered executions against their own exact commit; the later documentation baseline was not deployed or executed for either record.

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

[EVD-001](executed-evidence-2026-08-17.md#evd-001--controlled-mixed-chain-synchronization-and-final-state) is the bounded light-profile foundation. [EVD-008](executed-evidence-2026-08-21.md#evd-008--bounded-repeated-sequential-mixed-chain-traffic) records a completed bounded repeated-traffic campaign: three separately authorized stages of three sequential cycles, 9/9 successful cycles, 1,089 aggregate NMKR API requests, and 900 repeated token-processing operations over the same 10-project, 100-unique-token, 100-detail dataset. Every cycle completed 100/100 items successfully, and the final runtime and database state were clean, terminal, idle, and consistent. EVD-008 remains validated executed evidence for those observations and is supporting/partial evidence for M3-05; it does not validate high traffic because the campaign retained the guarded light profile, ran sequentially, and used cooldowns.

EVD-008 was sequential, never concurrent, and used one stable dataset. It is not 900 unique tokens, production traffic, maximum-capacity, saturation, breaking-point, unlimited-scale, or broad infrastructure-capacity evidence, and it gives no universal guarantee for arbitrary datasets, environments, upstream conditions, or future releases.

### Cardano, Solana, and mixed-chain behavior

EVD-001 directly contained four Cardano-only projects, four Solana-only projects, and two Cardano-and-Solana projects. EVD-008 retained that stable execution-time project profile throughout all nine cycles, alongside 40 Cardano-only, 40 Solana-only, and 20 dual-chain unique tokens per cycle. Its 900 repeated operations comprised 360 Cardano-only, 360 Solana-only, and 180 dual-chain operations. Both chains were directly exercised in every cycle; success for one chain is not substituted for the other.

EVD-008 is supporting/partial evidence for M3-11 because both chains were directly represented throughout the bounded nine-cycle sequential repeated-traffic profile. The disclosed classification-snapshot anomaly does not change the stable execution-time attribution or sealed totals. The campaign does not validate heavier API traffic or stress and is not production-capacity, maximum-load, arbitrary-scale, or universal evidence.

The next required action for M3-05 and M3-11 is a bounded plugin-level higher-pressure test using a defined larger synthetic mixed-chain API workload without loading the live NMKR API. It should evaluate pagination, processing, database writes, deduplication, memory, metrics, terminalization, and cleanup under explicit safety controls. Production, upstream, unlimited-scale, and external infrastructure capacity are not plugin deliverables.

### Performance benchmarks

- [EVD-004](executed-evidence-2026-08-18.md) records a representative guarded NMKR API benchmark in which all relevant endpoint-class maxima were below one second under the disclosed conditions.
- [EVD-005](executed-evidence-2026-08-20.md) records a representative single-NFT display with 10/10 measurements below two seconds and a maximum of 1534.47 ms; the report retains the broader five-page boundary and original conservative composite result.

Each result remains limited by the method and conditions in its source report.

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

### Errors and logging residual

[EVD-003](executed-evidence-2026-08-17.md#evd-003--terminal-failure-and-recovery) proves controlled terminal failure, private remediation, successful recovery, preserved terminal history, and clean final state for the observed path. It does not prove that every error response uses fully generic disclosure. Current source includes internal exception or error text in some privileged synchronization error responses or `technical_details`; generic error-disclosure hardening therefore remains an open residual item under M3-13. No raw response, exploit instruction, private data, or log is published here.

### Capabilities and nonces

Implementation and deterministic regression foundations are strong, but the public register does not contain an unambiguous complete, retained, exact-head live restricted-role/AJAX-security execution record. M3-14 therefore remains **Implemented, validation pending**. The remaining action is to register a complete existing retained record if one can be established without inference, or later perform and retain the separately authorized exact-head private profile; no private suite was run for this assessment.

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

Reviewer access, when provisioned, will use a controlled non-production environment with synthetic or non-customer data and a least-privilege, time-limited account. The public scope permits read-oriented review of the supplied plugin pages, displays, status, and evidence-relevant controls. It prohibits destructive actions, data deletion, uncontrolled mutation, load/stress activity, credential sharing, and real synchronization unless that action receives separate explicit authorization. The reviewer will receive a support/contact route, a limited activation window, and an expiry/revocation model.

The environment URL, username, password, authentication state, activation details, and infrastructure identifiers remain private and are delivered out of band. M3-21 remains **Planned** until the actual account and private access are provisioned; this document is not access provision.

## Requirement disposition

| ID | Status | Evidence and limitation |
| --- | --- | --- |
| M3-01 | Validated | EVD-007 validates the maintained current runtime across separate development and staging installations on one representative self-managed GCP VM stack; no independent-provider, managed-host, broad cross-platform, production-execution, or legacy-runtime claim is made. |
| M3-02 | Validated | EVD-002 and EVD-006 support representative, non-exhaustive functionality coverage. |
| M3-03 | Validated | EVD-001, EVD-004, and EVD-005 retain the scoped performance results. |
| M3-04 | Validated | EVD-006 records the maintainer/operator workflow assessment; no participant study is claimed. |
| M3-05 | Implemented, validation pending | EVD-008 is validated supporting/partial evidence for repeated aggregate volume and reliability, but its guarded sequential light profile does not establish high traffic; a bounded larger synthetic plugin-level workload remains required. |
| M3-06 | Validated | EVD-004 applies only under its defined normal conditions. |
| M3-07 | Validated | EVD-005 applies to the representative single-NFT display under its defined conditions. |
| M3-08 | Planned | The actual monitoring window has not been assessed or registered. |
| M3-09 | Validated | EVD-001 directly includes Cardano-only and dual-chain projects. |
| M3-10 | Validated | EVD-001 directly includes Solana-only and dual-chain projects. |
| M3-11 | Implemented, validation pending | EVD-008 is validated supporting/partial evidence for repeated mixed-chain reliability, but it does not establish heavier API traffic or stress; the bounded larger synthetic plugin-level workload remains required. |
| M3-12 | Validated | EVD-006 records a scoped implementation/control assessment; no universal vulnerability guarantee is made. |
| M3-13 | Implemented, validation pending | EVD-003 is supporting evidence; generic error-disclosure hardening remains open. |
| M3-14 | Implemented, validation pending | Implementation/regression foundations exist; an unambiguous retained exact-head live restricted-role result remains open. |
| M3-15 | Validated | EVD-006 covers lockfiles, reproducible installs, CI, compatibility, and runtime-integrity foundations, not a current advisory audit. |
| M3-16 | Validated | The user documentation is indexed and exact-source reviewed by EVD-006. |
| M3-17 | Validated | The developer documentation is indexed and exact-source reviewed by EVD-006. |
| M3-18 | Validated | The troubleshooting documentation, EVD-003, and EVD-006 provide scoped evidence. |
| M3-19 | Validated | This index and EVD-001 through EVD-008 provide consolidated reviewer-visible reports. |
| M3-20 | Validated | EVD-006 provides bounded security implementation evidence with findings and limitations, not certification. |
| M3-21 | Planned | Generic access controls are documented; actual private provisioning remains outstanding. |

## Open actions

- **M3-05 and M3-11:** run a bounded plugin-level higher-pressure validation using a defined larger synthetic mixed-chain API workload. Exercise plugin-controlled pagination, processing, database writes, deduplication, memory, metrics, terminalization, and cleanup without loading the live NMKR API or certifying production, upstream, unlimited-scale, or external infrastructure capacity.
- **M3-08:** assess and register the actual monitoring start/end, probe, interval/location class, incidents, missing-data handling, calculation, and whether uptime was strictly above 99.9%; no percentage is asserted here.
- **M3-13:** harden the residual privileged synchronization error disclosures in a separate runtime change and validate the affected boundary.
- **M3-14:** register a complete existing retained exact-head restricted-role/private-security record if available, otherwise execute that separately authorized private validation later.
- **M3-21:** create, deliver, support, expire, and revoke reviewer access privately and out of band.

Milestone 3 as a whole remains incomplete while these scoped operational and security actions remain open.
