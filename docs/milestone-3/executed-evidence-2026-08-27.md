# Milestone 3 executed evidence — 2026-08-27

## EVD-012 — external public staging-service availability

### Evidence identity and scope

This sanitized report registers **EVD-012** for **M3-08 — Uptime above 99.9% during a defined test period** and **M3-19 — Testing reports and performance results**. It assesses externally observable availability of a public non-production HTTPS service endpoint through a CDN/proxy. The public hostname, monitor identifier, account details, and private evidence locations are intentionally withheld.

**M3-08 is Validated only for the disclosed 16.14-day public staging-service observation window and monitoring profile below.** This is environmental public-service availability evidence. It is not plugin runtime-code evidence and is not proof of plugin correctness, functionality, usefulness, usability, synchronization behavior, origin-VM uptime, CDN uptime, or production infrastructure capacity.

No single plugin source or deployed commit is claimed for the monitored period because this is environment availability evidence spanning time, not code execution evidence. The exact source commit is therefore **Not applicable — external availability measurement**, and the exact deployed commit is **Not applicable — no single plugin deployment is claimed**.

### Observation window

| Field | Value |
| --- | --- |
| Start | `2026-08-11T05:16:22Z` |
| End | `2026-08-27T08:42:33Z` |
| Duration | `1,394,771` seconds (16.14 days) |
| Monitor creation | The monitor was created at the window start |

### Monitoring profile

| Field | Value |
| --- | --- |
| Target class | External public non-production HTTPS service endpoint through a CDN/proxy |
| Method | `HEAD` |
| Interval | `300` seconds |
| Timeout | `30` seconds |
| Redirect following | Enabled |
| Accepted responses | `2xx` and `3xx` |
| Authentication | None |
| Custom request body | None |
| Custom request headers | None |
| Checker selection | Provider default auto-selected location; North America was displayed at evidence capture time |
| Configured maintenance windows | `0` |
| Pause events | No pause event was returned for the evidence window |

The measurement covers the externally observable service through a CDN/proxy. It does not isolate the plugin, origin VM, web server, DNS, CDN, network path, or upstream cause.

### Result and independent calculation

| Measure | Result |
| --- | --- |
| Provider-reported uptime | `99.912%` |
| Independently calculated uptime | `99.911956873%` |
| Contractual threshold | Strictly above `99.9%` |
| Total downtime | `1,228` seconds (`20m 28s`) |
| Incident count | `4` |
| Current state at evidence capture | Up |
| Outcome | **PASS** |

The independent calculation uses the complete disclosed duration and aggregate downtime:

```text
((1,394,771 - 1,228) / 1,394,771) * 100 = 99.911956873%
```

Because `99.911956873%` is strictly above `99.9%`, the M3-08 threshold passes within this window and profile.

### Sanitized incident record

| # | Start (UTC) | Duration | Observation | Recovery |
| --- | --- | --- | --- | --- |
| 1 | `2026-08-19T03:29:06Z` | `308` seconds | Connection timeout | Successful up event |
| 2 | `2026-08-20T19:19:43Z` | `305` seconds | Connection timeout | Successful up event |
| 3 | `2026-08-25T20:41:13Z` | `306` seconds | Connection timeout | Successful up event |
| 4 | `2026-08-26T19:32:18Z` | `309` seconds | Connection timeout | Successful up event |

The four connection timeouts are availability observations, not attributed plugin defects. Their durations total `1,228` seconds. The monitor record reports no configured maintenance window and returned no pause event for the evidence window, so neither category was removed from the disclosed calculation.

### Private evidence integrity identifiers

Private evidence is retained under project controls. These approved identifiers allow integrity comparison without publishing raw evidence or its location:

- Sanitized API-derived JSON record SHA-256: `ae632f22c69ce4b5ae6950629d4a571c49247d3c1cd91187e0f34d0e105bafb0`
- Incident CSV export SHA-256: `2a6bd576dcdbc6b2064ca44a7f2e60dcd0d791ed1af222590a3c2d0f5dcbdcf9`
- Private evidence manifest SHA-256: `c711a178411554d4a67bcdfe50ff81f05b45c8ab0a8d729f744479a376d2cb53`

Raw screenshots, CSV, JSON, logs, monitor/account identifiers, the monitored hostname, and private evidence locations are not published.

### Excluded non-comparable context

A separate cloud-platform uptime check covered a different target with a different interval and multi-region profile. It was excluded from the M3-08 acceptance calculation: its result was not combined, averaged, or substituted for EVD-012.

### Limitations and non-claims

- A 300-second single-default-location profile may miss shorter interruptions and is not global availability certification.
- The displayed North America location describes the provider-selected checker at capture time; it does not establish worldwide coverage.
- The monitor observes the combined public delivery path. It cannot attribute an incident to the plugin, origin VM, web server, DNS, CDN, network path, or an upstream service.
- No production, lifetime, future-period, SLA, origin-host, infrastructure, or universal availability guarantee is claimed.
- The result does not prove plugin correctness, functionality, usefulness, usability, synchronization behavior, CDN uptime, or production infrastructure capacity.
- No single plugin source or deployed commit is claimed for this time-spanning environmental observation.

Only M3-21 private reviewer-access provisioning remains open. Milestone 3 remains incomplete until that access is privately created, delivered, supported, expired, and revoked as planned.
