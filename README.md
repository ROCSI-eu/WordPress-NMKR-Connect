# WordPress NMKR Connect

**ROCSI Connector for NMKR** is an open-source WordPress plugin for synchronizing Cardano and Solana NFT project and token data through the NMKR API and presenting that data with shortcodes.

The repository and Cardano Project Catalyst project retain the historical **NMKR Connect** / `WordPress-NMKR-Connect` identity. The public WordPress distribution identity is **ROCSI Connector for NMKR**, published in the WordPress Plugin Directory at [wordpress.org/plugins/rocsi-connector-for-nmkr](https://wordpress.org/plugins/rocsi-connector-for-nmkr/) with the fixed slug/text domain `rocsi-connector-for-nmkr`.

Current public stable release: **0.25.0**.

Milestones 1–4 have been delivered and approved through Catalyst reviewer sign-off. Milestone 5 is in progress.

## Catalyst delivery status

NMKR Connect is supported by [Cardano Project Catalyst Fund 13](https://milestones.projectcatalyst.io/projects/1300195).

| Delivery stage | Status | Scope summary |
| --- | --- | --- |
| Milestone 1 | Approved | Core plugin and NMKR API integration; local project/token tables; administration dashboard; synchronization progress, errors, and logging; Cardano and Solana compatibility; basic responsive NFT display |
| Milestone 2 | Approved | Grid, list, carousel, single-token, and single-project shortcodes; five free shortcode displays; role-based access; synchronization and engagement analytics; admin and responsive shortcode improvements; Cardano and Solana feature testing |
| Milestone 3 | Approved | Bounded functionality, usability, performance, load, API-response, display-performance, uptime, cross-chain, and security evidence; expanded testing, user, developer, and troubleshooting documentation; Milestone 3 Proof of Achievement |
| Milestone 4 | Approved | Security audit analysis and remediation; expanded user manuals, developer guides, FAQ, and troubleshooting content; final report, Proof of Achievement, and M4-10 provenance seal; see the [Milestone 4 audit and evidence hub](docs/milestone-4/README.md) |
| Milestone 5 | In progress | Community outreach and adoption; reporting and close-out; maintenance and handover; WordPress Plugin Directory publication; early post-launch fixes and support |

“Approved” means the milestone was delivered and subsequently approved through Catalyst reviewer sign-off. “In progress” means milestone delivery work is ongoing.

## Contents

- [Installation and first use](#installation-and-first-use)
- [Current functionality](#current-functionality)
- [Requirements](#requirements)
- [Shortcodes](#shortcodes)
- [Roles and access](#roles-and-access)
- [Analytics and privacy](#analytics-and-privacy)
- [Security and validation](#security-and-validation)
- [Documentation](#documentation)
- [Development and testing](#development-and-testing)
- [Troubleshooting](#troubleshooting)
- [Project layout](#project-layout)
- [Contributing](#contributing)
- [License and acknowledgements](#license-and-acknowledgements)

## Installation and first use

### Install from the WordPress Plugin Directory

For normal WordPress installations, use the official WordPress.org distribution path:

1. Open **WordPress Admin → Plugins → Add New**.
2. Search for **ROCSI Connector for NMKR**.
3. Click **Install Now**, then **Activate**.
4. Open **Settings → ROCSI Connector for NMKR**, enter the NMKR API key, review the synchronization settings, and save.
5. Open the ROCSI Connector for NMKR dashboard and start synchronization when ready.
6. Add one of the documented shortcodes to a WordPress page.

Official listing: [ROCSI Connector for NMKR on WordPress.org](https://wordpress.org/plugins/rocsi-connector-for-nmkr/).

Treat the NMKR API key, GA4 API secret, WordPress credentials, and other environment-specific values as secrets. Do not put them in content, source control, screenshots, or support logs.

For complete installation, configuration, synchronization, shortcode, role, analytics, update, and deletion guidance, see the [user guide](docs/user-guide.md).

### Build from source

Source builds are intended for development, contribution, and release validation rather than the normal end-user installation path.

From a clean exact checkout:

```bash
git clone https://github.com/ROCSI-eu/WordPress-NMKR-Connect.git
cd WordPress-NMKR-Connect
npm ci
npm run build:package
```

The package builder creates a self-contained release ZIP under `dist/` by default. Repository-only development and test tooling is excluded from the release package. Do not treat GitHub's automatically generated source archives as the canonical WordPress install package.

For local development, an exact source checkout may also be placed or symlinked under `wp-content/plugins/`.

## Current functionality

- Retrieves Cardano and Solana project and token data through the NMKR API.
- Stores projects, tokens, token details, synchronization history and metrics, and optional engagement analytics in local WordPress database tables.
- Provides run-scoped synchronization with start and stop controls, progress reporting, terminal finalization, durable run ownership, and stale/interrupted-state recovery behavior.
- Traverses token pages sequentially, deduplicates token UIDs within a run, reconciles unique totals, and reserves 100% progress for canonical completed finalization.
- Shows active-run metrics and historical synchronization statistics, including processed-item, API timing, request, duration, and memory information.
- Provides responsive grid, token-list, carousel, single-token, and single-project displays through five registered shortcodes.
- Provides an administrative dashboard, project browser, shortcode reference, settings, analytics dashboard, and capability-based access for Administrators and NMKR roles.
- Supports local analytics, GA4, both, or analytics disabled according to site configuration.

There are currently no registered Gutenberg blocks, Elementor widgets, or separate front-end template system in this repository.

## Requirements

- WordPress 5.8 or later; the latest stable WordPress release is recommended.
- PHP 7.4 or later.
- An NMKR API key with access to the projects to be synchronized.
- Node.js/npm only for repository package-build and test commands; the plugin has no Composer runtime package dependency.

## Shortcodes

All five shipped shortcode implementations are included without a licensing or entitlement gate.

| Shortcode | Purpose | Principal UID/filter parameter |
| --- | --- | --- |
| `[nmkr-grid]` | Responsive token grid for a project | `project_uid`; `allow_user_select` |
| `[nmkr-token-list]` | Searchable/filterable token table for a project | `project_uid`; `allow_user_select` |
| `[nmkr-carousel]` | Carousel presentation of project tokens | `project_uid`; `allow_user_select` |
| `[nmkr-token]` | One token and its details | `token_uid` |
| `[nmkr-project]` | One project and its token information | `project_uid`; `allow_user_select` |

When an optional UID is omitted, the shortcode resolves a suitable synchronized project or token. For project-based displays, `allow_user_select="1"` is the default; set it to `"0"` to hide the selector.

Example:

```text
[nmkr-grid project_uid="your-project-uid" allow_user_select="0"]
```

## Roles and access

Access is based on plugin-specific WordPress capabilities rather than role-name checks alone.

- **Administrator** receives all NMKR capabilities, including dashboard, projects, shortcodes, analytics, settings, and synchronization management.
- **NMKR Admin** receives full plugin access without implicitly receiving unrelated site-wide Administrator permissions.
- **NMKR Marketing** has read/marketing access to Projects, Shortcodes, and Analytics, but cannot change plugin settings or manage synchronization.

Assign roles according to least privilege. The exact capability/action mapping is maintained in the [user guide](docs/user-guide.md#capability-and-action-matrix).

## Analytics and privacy

The analytics dashboard reports views, clicks, click-through rate, time-series data, top projects, top tokens, and shortcode breakdowns. Filtered results can be exported in supported CSV/JSON paths.

Analytics modes are **Off**, local custom analytics only, GA4 only, or both. Settings cover local-data retention, sampling, logged-in-user tracking, and consent requirements. When consent is required, front-end collection waits for the plugin's consent signal. GA4 uses server-side Measurement Protocol requests only when configured.

Site operators remain responsible for choosing notices, consent handling, retention, and configuration appropriate to their users and applicable law.

Front-end analytics ingestion is intentionally public/unauthenticated because ordinary visitors can interact with rendered shortcodes. The implemented validation, sampling, rate admission, deduplication, retention, and sink controls are bounded controls rather than guarantees against all abuse or exactly-once delivery. See the [FAQ analytics section](docs/faq.md#analytics-privacy-consent-and-ga4).

## Security and validation

The plugin uses WordPress security controls including plugin-specific capability checks, nonces on privileged requests, input sanitization and allow-list validation, escaped output, prepared dynamic SQL, and no-cache handling on sensitive AJAX responses. Logging helpers are designed to avoid or redact secret material in supported paths.

Milestone 3 and Milestone 4 include scoped synchronization, performance, security, compatibility, and audit evidence. Those results are bounded to their documented environments and procedures; they do not establish universal capacity, lifetime security, upstream availability, or an SLA.

For detailed evidence and limitations, use the [Milestone 4 audit and evidence hub](docs/milestone-4/README.md), [validation policy](docs/validation-policy.md), and the focused testing documentation under `docs/`.

Keep WordPress and dependencies maintained, grant minimal access, use HTTPS, protect credentials, and validate material changes in an appropriate non-production environment before production rollout.

## Documentation

| Reader or task | Start here |
| --- | --- |
| Site owners and administrators | [User guide](docs/user-guide.md) |
| Quick questions across roles | [Comprehensive FAQ](docs/faq.md) |
| Symptoms, read-only diagnosis, and safe escalation | [Troubleshooting guide](docs/troubleshooting.md) |
| Developers and contributors | [Developer guide](docs/developer-guide.md) |
| Maintainers choosing proportional checks | [Validation policy](docs/validation-policy.md) |
| Maintainers planning releases and version bumps | [Versioning and release policy](docs/versioning.md) |
| DEV/STAGING/PRODUCTION release responsibilities | [Deployment policy](docs/deployment-policy.md) |
| Security audit, evidence, limitations, and Proof of Achievement | [Milestone 4 audit and evidence hub](docs/milestone-4/README.md) |

The focused guides are authoritative for their procedures. Keep public diagnostics sanitized and link to maintained procedures instead of copying large operational instructions into issues or pull requests.

## Development and testing

See the [developer guide](docs/developer-guide.md) for setup, architecture, data flows, security boundaries, check selection, and contribution safety.

Install Node dependencies with:

```bash
npm ci
```

The main public-safe validation entry point is:

```bash
npm run test:public
```

The repository also contains focused browser, synchronization, security-rendering, dependency, WP-CLI, database-state, package, and controlled private-environment validation tooling. Select checks proportionally to the files and behavior changed; ordinary public validation does not require or authorize real NMKR synchronization.

Key testing guides include:

- [Phase 1 smoke testing](docs/testing-phase-1.md)
- [Phase 3 Playwright regression coverage](docs/testing-phase-3.md)
- [Phase 4 public CI](docs/testing-phase-4.md)
- [Phase 5 PHP 7.4 compatibility](docs/testing-phase-5.md)
- [Phase 8 WP-CLI database-state validation](docs/testing-phase-8.md)
- [Phase 15 controlled-sync preflight](docs/testing-phase-15.md)
- [Phase 16A controlled real-sync harness](docs/testing-phase-16a.md)
- [Phase 16B.2 run-scoped synchronization checks](docs/testing-phase-16b2.md)
- [Phase 17 isolated synthetic synchronization harness](docs/testing-phase-17.md)

See the [validation policy](docs/validation-policy.md) for the maintained check-selection rules.

## Troubleshooting

Start with the [troubleshooting guide](docs/troubleshooting.md) and [FAQ](docs/faq.md).

Use read-only checks first:

1. Review dashboard status, progress, final result, and historical statistics.
2. Confirm the NMKR API key is configured without displaying or copying its value.
3. Check failed `wp-admin/admin-ajax.php` requests and browser console errors.
4. If debug logging is deliberately enabled in an appropriate environment, inspect only relevant entries and redact secrets before sharing.
5. Maintainers can use the documented WP-CLI and database-state checks where appropriate.

Do not casually delete options, transients, rows, or tables to clear a status. Treat state-changing recovery as an advanced operation that requires an idle sync, a verified backup where appropriate, and the relevant recovery/testing procedure.

## Project layout

- `rocsi-connector-for-nmkr.php` — plugin bootstrap plus activation, deactivation, and registered uninstall callbacks.
- `includes/api/` — NMKR API client and request helpers.
- `includes/database/` — schema and persistence functions.
- `includes/synchronization/` — run lifecycle, batching, progress, metrics, errors, and recovery.
- `includes/shortcodes/` — the five front-end shortcode implementations.
- `includes/pages/` — dashboard, projects, settings, shortcode reference, and analytics administration pages.
- `includes/roles/` — NMKR roles and capabilities.
- `includes/analytics/` — analytics endpoint and retention scheduling.
- `js/` and `css/` — front-end and administration assets.
- `tests/e2e/` — Playwright suites and helpers.
- `scripts/` — public-safe, private-environment, WP-CLI, package, and controlled-sync tooling.
- `docs/` — user, developer, release, validation, evidence, and testing documentation.
- `.github/workflows/` — public CI workflow.

## Contributing

Issues and focused pull requests are welcome. Do not commit API keys, credentials, populated environment files, private URLs, logs, screenshots, traces, videos, reports, or VM artifacts.

For code changes, run `npm run test:public` plus the focused checks appropriate to the changed behavior, explain the validation boundary, and keep unrelated refactoring out of the change.

## License and acknowledgements

Released under the **MIT License**. See [`LICENSE.txt`](LICENSE.txt) for details. Copyright ROCSI.eu (Romanian Cyber Space Initiative).

- Built by **Mihai Bărbulescu / ROCSI.eu (Romanian Cyber Space Initiative)** to advance open Web3 tooling.
- Supported by **Cardano Project Catalyst Fund 13**; see the [project milestone page](https://milestones.projectcatalyst.io/projects/1300195).
- Thanks to the **NMKR ecosystem**, community contributors, testers, and early adopters.
- All five shortcode displays are included without a licensing SDK or entitlement gate.
