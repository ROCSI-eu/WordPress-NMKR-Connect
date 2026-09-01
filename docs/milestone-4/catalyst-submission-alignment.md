# Milestone 4 Catalyst submission alignment

## Purpose

This document provides the reviewer-facing bridge between the Milestone 4 Statement of Milestones, the selected OWASP verification requirements, the canonical repository evidence, and the updated documentation. It does not create new runtime evidence, replace the [evidence register](evidence-register.md), or broaden any recorded security conclusion.

For this document, **supported within scope** means that the cited project evidence supports the selected requirement for the exact boundaries and identities stated in that evidence. It is not application-wide OWASP ASVS conformance, penetration testing, formal certification, production assurance, or a guarantee that no vulnerability remains.

## Statement of Milestones requirement inventory

| Requirement class | Milestone 4 obligation | Repository disposition |
| --- | --- | --- |
| Security audit | Conduct a relevant selection of security audits using industry standards, including an OWASP-aligned selection | M4-01 through M4-08 provide source analysis, remediation, public deterministic/synthetic checks, package-private validation, and cumulative private validation; the selected ASVS mapping is below |
| Unauthorized access | Enhance protection against unauthorized administrative API access | `M4-T-01` through `M4-T-03`; `M4-EVD-001`, `M4-EVD-002`, `M4-EVD-005`, and `M4-EVD-007` |
| SQL injection | Enhance protection against SQL injection | `M4-T-04`; `M4-EVD-003`, `M4-EVD-005`, and `M4-EVD-007` |
| Cross-site scripting | Enhance protection against XSS and unsafe final rendering | `M4-T-08`; `M4-EVD-003`, `M4-EVD-005`, and `M4-EVD-007`; open `A-01` preserves the broader assurance limit |
| Robustness verification | Verify the effectiveness of prior security implementations | Package-specific evidence plus cumulative `M4-EVD-007` private DEV validation |
| User documentation | Provide clear step-by-step user instructions | [User guide](../user-guide.md) and `M4-EVD-006` |
| Developer documentation | Provide clear step-by-step developer guidance | [Developer guide](../developer-guide.md) and `M4-EVD-006` |
| Troubleshooting | Address common user issues with clear solutions | [Troubleshooting guide](../troubleshooting.md) and `M4-EVD-006` |
| FAQ | Provide a comprehensive FAQ | [FAQ](../faq.md) and `M4-EVD-006` |
| Completion evidence | Provide completed security-audit reports with an identified/resolved-vulnerability summary | [Final report](final-report.md), [findings register](findings-register.md), package records, and the summary below |
| Documentation access | Provide access to updated user, developer, and troubleshooting documentation | Public links in the [Milestone 4 hub](README.md) and the documentation table below |

## Selected OWASP verification baseline

The selected verification baseline is **OWASP Application Security Verification Standard 5.0.0**. ASVS is used because it supplies versioned, testable technical-security requirements. The OWASP Top 10 remains useful awareness context, but this mapping does not treat the Top 10 as a complete verification checklist.

Requirement identifiers use OWASP's recommended versioned form: `v5.0.0-<requirement>`.

| Selected ASVS requirement | Selected control | Project trace and evidence | Bounded disposition |
| --- | --- | --- | --- |
| `v5.0.0-8.2.1` | Function-level access is restricted to consumers with explicit permissions | `M4-T-01`–`M4-T-03`; `M4-EVD-001`, `M4-EVD-002`, `M4-EVD-005`, `M4-EVD-007` | Supported for the enumerated administrative and synchronization boundaries; open `S-06` prevents a universal claim |
| `v5.0.0-8.3.1` | Authorization is enforced at a trusted service layer rather than by client-controlled UI | `M4-T-01`–`M4-T-03`, `M4-T-06`; `M4-EVD-001`, `M4-EVD-002`, `M4-EVD-005`, `M4-EVD-007` | Supported for exercised server-side capability/nonce/dispatch boundaries; UI visibility is not treated as authorization |
| `v5.0.0-1.2.4` | Database queries use parameterization or equivalent SQL-injection protection | `M4-T-04`; `M4-EVD-003`, `M4-EVD-005`, `M4-EVD-007` | Supported for exercised analytics query construction and placeholder/value-count contracts; not every present or future query |
| `v5.0.0-1.2.1` | Output encoding is appropriate to the final response or document context | `M4-T-08`; `M4-EVD-003`, `M4-EVD-005`, `M4-EVD-007` | Supported by source tracing and targeted rendering evidence for recorded contexts; not universal XSS resistance |
| `v5.0.0-3.2.2` | Text content is rendered through safe text functions rather than interpreted as HTML or JavaScript | `M4-T-08`; `M4-EVD-003`, `M4-EVD-005`, `M4-EVD-007` | Supported for synchronization current-item and analytics project/token UID fixtures; open `A-01` remains beyond those sinks |
| `v5.0.0-2.2.1` | Input is positively validated against expected values, structures, patterns, ranges, or limits | `M4-T-04`–`M4-T-06`; `M4-EVD-003`, `M4-EVD-004`, `M4-EVD-005`, `M4-EVD-007` | Supported for exercised analytics, settings, UID, metadata, range, and payload contracts; not all application input |
| `v5.0.0-16.5.1` | Unexpected or security-sensitive errors return generic consumer messages without sensitive internals | `M4-T-07`; `M4-EVD-003`, `M4-EVD-005`, `M4-EVD-007` | Supported for recorded AJAX, progress, transport, settings-notice, and public-ingestion paths; not every failure or extension |
| `v5.0.0-16.5.3` | Exceptional conditions fail gracefully and securely rather than failing open | `M4-T-03`, `M4-T-05`, `M4-T-07`; `M4-EVD-002`, `M4-EVD-004`, `M4-EVD-005`, `M4-EVD-007` | Supported for covered recovery, admission, ambiguous-state, and disclosure contracts; not universal resilience proof |

The canonical requirement text and version information are maintained by the [OWASP ASVS project](https://owasp.org/www-project-application-security-verification-standard/). The selected mapping is intentionally narrower than complete ASVS Level 1 or Level 2 verification.

## Identified, resolved, rejected, and open items

| ID | Type | Milestone 4 disposition | Principal evidence or limitation |
| --- | --- | --- | --- |
| `S-01` | Confirmed authorization weakness | Resolved within M4-02 scope by requiring `nmkr_manage_sync` for active-metrics/UI-log mutation while preserving intended observation | `M4-EVD-001`, complemented by `M4-EVD-007` |
| `S-02` | Confirmed recovery-authority weakness | Resolved within M4-03 scope by keeping polling observational and requiring management authority for recovery mutation | `M4-EVD-002`, complemented by `M4-EVD-007` |
| `S-05` | Confirmed concurrency/input-bound weakness | Resolved within M4-05's bounded public-ingestion contract with durable admission, bounds, recovery, cleanup, and fail-closed ambiguity | `M4-EVD-004`, complemented by `M4-EVD-007` |
| `S-03` | Guard-parity hypothesis | Rejected on the exact M4-01 baseline; re-review is required if registration or alias behavior changes | `M4-FND-001` |
| `S-06` | Broader side-effect-assurance gap | Open and narrowed; representative denial/state checks do not prove every callback, helper, dispatch path, or side effect | `M4-EVD-005`, `M4-EVD-007` |
| `A-01` | Broader adversarial-rendering assurance gap | Open and narrowed beyond the three exercised final-DOM fixtures | `M4-EVD-003`, `M4-EVD-005`, `M4-EVD-007` |

The [findings register](findings-register.md) remains authoritative for all security and documentation findings, including `S-04`, `S-07`, and `D-01` through `D-05`.

## Documentation access

| Audience or task | Public document |
| --- | --- |
| Site owners and administrators | [User guide](../user-guide.md) |
| Developers and maintainers | [Developer guide](../developer-guide.md) |
| Symptom-based diagnosis and safe escalation | [Troubleshooting guide](../troubleshooting.md) |
| Short role-oriented answers | [Comprehensive FAQ](../faq.md) |
| Audit scope, findings, evidence, and limitations | [Milestone 4 hub](README.md) |
| Canonical provenance and evidence classes | [Evidence register](evidence-register.md) |
| Formal close-out and acceptance mapping | [Final report](final-report.md) |
| Concise evidence synthesis | [Proof of Achievement](proof-of-achievement.md) |

## Terminology clarification

The historical M4-05 executed-evidence record uses the phrase **anonymous ingestion**. Current documentation uses **public/unauthenticated ingestion** because front-end requests do not require WordPress authentication, while accepted events may include a WordPress user ID when logged-in-user tracking is enabled. This clarification does not rewrite or broaden the historical execution result.

## Reviewer path

1. Read the [Proof of Achievement](proof-of-achievement.md).
2. Review the selected ASVS mapping above.
3. Review the identified/resolved/open summary above and the canonical [findings register](findings-register.md).
4. Follow the detailed provenance in the [evidence register](evidence-register.md) and linked package records.
5. Open the user, developer, troubleshooting, and FAQ documentation from the table above.

## Limitations and non-claims

This alignment is a documentation-only interpretation of existing evidence. It does not create new runtime controls or observations; claim complete ASVS conformance; claim exhaustive OWASP coverage; establish penetration testing, certification, production security, legal compliance, universal authorization or side-effect absence, universal XSS or SQL-injection resistance, perpetual dependency safety, or external Catalyst assessment or approval; or extend M4-08 private validation beyond its recorded exact runtime SHA and tree.
