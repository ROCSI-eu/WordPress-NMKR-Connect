# WordPress NMKR Connect

NMKR Connect is an open-source WordPress plugin for synchronizing Cardano and Solana NFT project and token data through the NMKR API and presenting that data with shortcodes. The project remains under active development: Milestones 1, 2, and 3 have been delivered and approved through Catalyst reviewer sign-off; Milestone 4 has been delivered and is awaiting Catalyst reviewer sign-off; Milestone 5 is in progress.

## Catalyst delivery status

NMKR Connect is supported by [Cardano Project Catalyst Fund 13](https://milestones.projectcatalyst.io/projects/1300195).

| Delivery stage | Status | Scope summary |
| --- | --- | --- |
| Milestone 1 | Approved | Core plugin and NMKR API integration; local project/token tables; administration dashboard; synchronization progress, errors, and logging; Cardano and Solana compatibility; basic responsive NFT display |
| Milestone 2 | Approved | Grid, list, carousel, single-token, and single-project shortcodes; Free/Premium differentiation; role-based access; synchronization and engagement analytics; admin and responsive shortcode improvements; Cardano and Solana feature testing |
| Milestone 3 | Approved | Completed bounded functionality, usability, performance, load, API-response, display-performance, uptime, and cross-chain evidence; security hardening and bounded WordPress security validation; expanded testing, user, developer, and troubleshooting documentation; submitted Milestone 3 Proof of Achievement |
| Milestone 4 | Delivered | Security audit analysis and remediation sequence; expanded user manuals, developer guides, FAQ, and troubleshooting content; final report, Proof of Achievement, and M4-10 provenance seal; see the [Milestone 4 audit and evidence hub](docs/milestone-4/README.md) |
| Milestone 5 | In progress | Community outreach and adoption; reporting and close-out; maintenance and handover; WordPress Plugin Directory submission; early post-launch fixes and support |

“Approved” means the milestone was delivered and subsequently approved through Catalyst reviewer sign-off. “Delivered” means the milestone has been delivered but formal Catalyst reviewer sign-off remains pending. “In progress” means milestone delivery work is ongoing.

## Contents

- [Documentation](#documentation)
- [Current functionality](#current-functionality)
- [Requirements](#requirements)
- [Installation and first use](#installation-and-first-use)
- [Shortcodes and plans](#shortcodes-and-plans)
- [Roles and access](#roles-and-access)
- [Analytics and privacy](#analytics-and-privacy)
- [Security approach](#security-approach)
- [Development and testing](#development-and-testing)
- [Troubleshooting](#troubleshooting)
- [Project layout](#project-layout)
- [Contributing](#contributing)
- [License and acknowledgements](#license-and-acknowledgements)

## Documentation

| Reader or task | Start here |
| --- | --- |
| Site owners and administrators | [User guide](docs/user-guide.md) |
| Content and marketing users | [Roles, Projects, Shortcodes, and Analytics guidance](docs/user-guide.md#administration-pages-and-access) |
| Quick questions across roles | [Comprehensive FAQ](docs/faq.md) |
| Symptoms, read-only diagnosis, and safe escalation | [Troubleshooting guide](docs/troubleshooting.md) |
| Developers and contributors | [Developer guide](docs/developer-guide.md) |
| Maintainers choosing proportional checks | [Validation policy](docs/validation-policy.md) |
| Security audit, findings, bounded evidence, and limitations | [Milestone 4 audit and evidence hub](docs/milestone-4/README.md), [evidence register](docs/milestone-4/evidence-register.md), [final report](docs/milestone-4/final-report.md), and [Proof of Achievement](docs/milestone-4/proof-of-achievement.md) |

The focused guides are authoritative for their procedures. Use links instead of copying operational instructions into issues or pull requests, and keep all public diagnostics sanitized.

## Current functionality

- Retrieves Cardano and Solana project and token data through the NMKR API.
- Stores projects, tokens, token details, synchronization history and metrics, and optional engagement analytics in local WordPress database tables.
- Provides run-scoped synchronization with start and stop controls, progress reporting, terminal finalization, durable run ownership, and stale/interrupted-state recovery behaviour.
- Streams sequential token pages of 50, deduplicates token UIDs within each run, reconciles unique totals near completion, and reserves 100% for canonical completed finalization.
- Shows live metrics for the active run and historical synchronization statistics, including processed-item, API timing, request, duration, and memory information.
- Provides responsive grid, token-list, carousel, single-token, and single-project displays through the five registered shortcodes documented below.
- Separates Free displays (grid and token list) from Premium displays (carousel, single token, and single project) through Freemius plan checks.
- Provides an administrative dashboard, project browser, shortcode reference, settings, analytics dashboard, and capability-based access for Administrators and the NMKR roles.
- Records shortcode view and click engagement in the plugin database, sends it to GA4, does both, or disables collection, according to site configuration.

There are currently no registered Gutenberg blocks, Elementor widgets, or separate front-end template system in this repository.

## Requirements

- WordPress 5.8 or later (the latest stable release is recommended)
- PHP 7.4 or later
- An NMKR API key with access to the projects to be synchronized
- Composer when building the plugin from source

## Installation and first use

### Build from source

```bash
git clone https://github.com/ROCSI-eu/WordPress-NMKR-Connect.git
cd WordPress-NMKR-Connect
composer install --no-dev --prefer-dist
```

Place or symlink the resulting directory under `wp-content/plugins/`, then activate **NMKR Connect** in **WordPress Admin → Plugins**. Composer installs the runtime dependencies in `vendor/`; those dependencies must be present in any packaged plugin directory or ZIP.

### Install a packaged ZIP

If you have obtained a packaged ZIP that includes `vendor/`, use **WordPress Admin → Plugins → Add New → Upload Plugin**, select the file, and activate it. This repository does not promise that a prebuilt release artifact is available for every revision.

### First-use flow

1. Install and activate the plugin.
2. Open **Settings → NMKR Connect**, enter the NMKR API key, review the synchronization settings, and save.
3. Open the NMKR Connect dashboard and start synchronization. Follow its progress and final result; do not close or restart a run merely because a large collection takes time.
4. Add a Free shortcode such as `[nmkr-grid]` or `[nmkr-token-list]` to a WordPress page. Use a project UID when a specific synchronized project should be displayed.

Treat the NMKR API key, GA4 API secret, license details, WordPress credentials, and other environment values as secrets. Do not put them in content, source control, screenshots, or support logs.

For complete installation, configuration, synchronization, shortcode, role, analytics, update, and deletion guidance, see the **[NMKR Connect user guide](docs/user-guide.md)**.

## Shortcodes and plans

Only the following shortcode attributes are registered by the current implementations. When an optional UID is omitted, the shortcode resolves a suitable synchronized project or token. For project-based displays, `allow_user_select="1"` (the default) enables the project selector; set it to `"0"` to hide the selector.

| Shortcode | Purpose | Plan availability | Principal UID/filter parameter |
| --- | --- | --- | --- |
| `[nmkr-grid]` | Responsive token grid for a project | Free | `project_uid`; `allow_user_select` |
| `[nmkr-token-list]` | Searchable/filterable token table for a project | Free | `project_uid`; `allow_user_select` |
| `[nmkr-carousel]` | Carousel presentation of project tokens | Premium | `project_uid`; `allow_user_select` |
| `[nmkr-token]` | One token and its details | Premium | `token_uid` |
| `[nmkr-project]` | One project and its token information | Premium | `project_uid`; `allow_user_select` |

Example:

```text
[nmkr-grid project_uid="your-project-uid" allow_user_select="0"]
```

Free/Premium availability describes the current shortcode gates. Freemius supplies plan/licensing integration; it does not change the MIT licence that applies to this repository.

## Roles and access

Access is based on plugin-specific WordPress capabilities rather than role-name checks alone.

- **Administrator** receives all NMKR capabilities, including dashboard, projects, shortcodes, analytics, settings, and synchronization management.
- **NMKR Admin** receives full plugin access and can view the dashboard and other plugin pages, change settings, and start or stop synchronization. It does not implicitly receive unrelated site-wide Administrator permissions.
- **NMKR Marketing** has read/marketing access to Projects, Shortcodes, and Analytics. It cannot access the synchronization dashboard, change plugin settings, or manage synchronization.

Assign roles according to least privilege and restrict access to users who need the corresponding data and controls. The exact capability/action mapping is maintained in the [user guide](docs/user-guide.md#capability-and-action-matrix).

## Analytics and privacy

The analytics dashboard reports views, clicks, click-through rate, time-series data, top projects, top tokens, and a shortcode breakdown. Dashboard requests support preset or custom date ranges and shortcode, project UID, and token UID filters; tables also support the implemented search, sorting, and pagination controls. Filtered time-series, top-project, top-token, and shortcode-breakdown results can be exported as CSV or JSON.

Analytics modes are **Off**, local custom analytics only, GA4 only, or both. Settings also cover local-data retention, sampling, logged-in-user tracking, and whether explicit consent is required. When consent is required, front-end collection waits for the plugin's consent signal. GA4 uses a server-side Measurement Protocol request when a Measurement ID and API secret are configured. Site operators remain responsible for choosing settings, notices, consent handling, and retention appropriate to their users and applicable law.

Front-end analytics ingestion is intentionally public/unauthenticated because ordinary visitors can interact with rendered shortcodes. This is not an administrative mutation boundary. Mode, logged-in-user policy, consent, DNT, sampling, host/origin validation, bounded input handling, rate admission, deduplication, retention, and sink configuration are limited controls rather than universal abuse-prevention or exactly-once-delivery guarantees. See the [FAQ analytics section](docs/faq.md#analytics-privacy-consent-and-ga4).

## Security approach

The implementation uses concrete WordPress controls, including plugin-specific capability checks, nonces on privileged requests, input sanitization and allow-list validation, escaped output, prepared dynamic SQL, and no-cache headers on sensitive AJAX responses. Analytics applies UID/event validation, sampling, deduplication and rate limiting; logging helpers redact or avoid secret material in supported paths.

These controls are not an absolute security guarantee. Milestone 3 now includes bounded plugin-level high-volume sequential synthetic synchronization and heavy sequential mixed-chain validation through EVD-008 and EVD-011. EVD-012 separately validates M3-08 only for a disclosed 16.14-day observation of an external public non-production HTTPS service through a CDN/proxy; it is environment availability evidence, not plugin-code, origin-host, production, global, lifetime, SLA, or universal availability evidence. These scoped outcomes do not establish concurrent visitor or arbitrary requests-per-second capacity, saturation or a breaking point, maximum throughput or server capacity, broad hosting/runtime/database/cache/network/WordPress coverage, production infrastructure scalability, live NMKR/upstream capacity, unlimited datasets, or universal behavior. All M3-01 through M3-21 requirements are Validated within their disclosed scopes, and the Milestone 3 Proof of Achievement was submitted at 2026-08-28 11:53 UTC. Milestone 3 has since received Catalyst reviewer sign-off. The controlled testing-access window remains operational until expiry or reassessment on 2026-10-01 00:00 UTC. Keep WordPress and dependencies maintained, grant minimal access, use HTTPS, protect credentials, and validate the plugin in a staging environment before production use.

## Development and testing

See the **[developer guide](docs/developer-guide.md)** for setup, architecture, data flows, security boundaries, check selection, and contribution safety.

Install Node dependencies with `npm ci`. The public-safe validation entry point is:

```bash
npm run test:public
```

It performs Bash syntax checks, Playwright test discovery, synthetic preflight/runner and synchronization regressions, database-write and HTTP/metric regressions, and the PHP 7.4 syntax/compatibility guard. The repository also contains:

- Playwright suites for read-only admin smoke coverage and locally stubbed UI/regression scenarios.
- WP-CLI smoke checks and read-only database-state/schema/integrity checks for a deployed test WordPress installation.
- Private-environment Phase 2 orchestration for browser, WP-CLI, deployment/readiness, and database validation.
- Guarded preflight and controlled real-sync tooling for an explicitly authorized private development environment. Do not run a real synchronization as part of ordinary public validation.

Phase 4 CI uses three independent public jobs without WordPress or deployment credentials:

1. **Public-safe checks:** verify the exact checkout, set up Node and PHP 7.4, run `npm ci`, and run `npm run test:public`.
2. **Synthetic security rendering:** verify the exact checkout, install the locked Chromium version, and run `npm run test:security-rendering` with synthetic/local fixtures only. This is not private WordPress/runtime coverage or universal XSS proof.
3. **Dependency audits:** verify the exact checkout, set up Node and PHP/Composer, run `npm ci`, and run `npm run test:dependencies`. Results are historical, lock-bound, and dependent on advisory data available at execution time; they do not establish perpetual dependency safety.

The jobs do not deploy or upload private Playwright reports, screenshots, traces, videos, VM logs, or other private artifacts. `test:public` does not contain the other two jobs, and Phase 4 CI makes no production-runtime assurance claim.

### Synchronization pagination and progress safety

Token traversal requests sequential numbered pages, beginning with page 1 and using a page size of 50. A valid empty page ends traversal; a partial page does not. Within a synchronization run, each unique token UID is processed once, and final token totals count unique UIDs rather than duplicate records.

Progress during token discovery is provisional and remains below 100%; only canonical completed finalization reports 100%. Malformed pages or records, repeated non-empty pages, pages that make no UID progress, conflicting project ownership for a UID, and exhaustion of the safety ceiling all fail explicitly rather than implying successful completion. The configurable default ceiling of 2,000 pages per project is a plugin safety policy, not a documented NMKR service limit.

Detailed procedures and safety boundaries are maintained in:

- [Phase 1 smoke testing](docs/testing-phase-1.md)
- [Phase 2 private VM runner](docs/testing-phase-2.md)
- [Phase 3 Playwright regression coverage](docs/testing-phase-3.md)
- [Phase 4 public CI](docs/testing-phase-4.md)
- [Phase 5 PHP 7.4 compatibility](docs/testing-phase-5.md)
- [Phase 8 WP-CLI database-state validation](docs/testing-phase-8.md)
- [Phase 15 controlled-sync preflight](docs/testing-phase-15.md)
- [Phase 16A controlled real-sync harness](docs/testing-phase-16a.md)
- [Phase 16B.2 run-scoped synchronization checks](docs/testing-phase-16b2.md)
- [Phase 17 isolated synthetic synchronization harness](docs/testing-phase-17.md)

## Troubleshooting

For symptom-based, read-only-first checks and a public-safe escalation template, see the **[NMKR Connect troubleshooting guide](docs/troubleshooting.md)**. The [FAQ](docs/faq.md) provides shorter cross-role answers and routes each topic to its detailed procedure.

Begin with read-only checks:

1. Review the dashboard status, progress, active metrics, final result, and historical statistics.
2. Confirm the NMKR API key is configured without displaying or copying its value.
3. In browser developer tools, check for failed `wp-admin/admin-ajax.php` requests and JavaScript console errors. Ensure a proxy or CDN does not cache WordPress admin/AJAX responses.
4. If WordPress debug logging is deliberately enabled on a non-production site, inspect relevant entries and redact secrets before sharing anything.
5. Maintainers can run the documented [WP-CLI smoke checks](docs/testing-phase-1.md) and [read-only database-state validation](docs/testing-phase-8.md) in an appropriate private environment.

Do not casually delete options, transients, rows, or tables to clear a status. Any state-changing WP-CLI/database command is an **advanced recovery action**: first make and verify a backup, identify the exact key or row, confirm that no synchronization is active, and follow the relevant testing/recovery documentation. If the issue persists, report the WordPress/PHP versions, reproducible steps, public-safe error text, and redacted diagnostics.

## Project layout

- `nmkr-connect.php` — plugin bootstrap plus activation, deactivation, and registered uninstall callbacks
- `includes/api/` — NMKR API client and request helpers
- `includes/database/` — schema and persistence functions
- `includes/synchronization/` — run lifecycle, batching, progress, metrics, errors, and recovery
- `includes/shortcodes/` — the five front-end shortcode implementations
- `includes/pages/` — dashboard, projects, settings, shortcode reference, and analytics administration pages
- `includes/roles/` — NMKR roles and capabilities
- `includes/analytics/` — analytics endpoint and retention scheduling
- `js/` and `css/` — front-end and administration assets
- `tests/e2e/` — Playwright suites and helpers
- `scripts/` — public-safe, private-environment, WP-CLI, and controlled-sync validation tooling
- `docs/` — focused testing and validation guides
- `.github/workflows/` — public CI workflow

## Contributing

Issues and focused pull requests are welcome. Do not commit API keys, credentials, populated environment files, private URLs, logs, screenshots, traces, videos, reports, or VM artifacts. For code changes, run `npm run test:public` and the relevant private-environment suites where applicable, explain the test boundary, and keep unrelated refactoring out of the change.

## License and acknowledgements

Released under the **MIT License**. See [`LICENSE.txt`](LICENSE.txt) for details. Copyright ROCSI.eu (Romanian Cyber Space Initiative).

- Built by **Mihai Bărbulescu / ROCSI.eu (Romanian Cyber Space Initiative)** to advance open Web3 tooling.
- Supported by **Cardano Project Catalyst Fund 13**; see the [project milestone page](https://milestones.projectcatalyst.io/projects/1300195).
- Thanks to the **NMKR ecosystem**, community contributors, testers, and early adopters.
- Freemius provides the current Free/Premium plan and licensing integration; grid and token-list displays are Free, while carousel, single-token, and single-project displays require Premium access.
