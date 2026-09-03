# NMKR Connect developer guide

## Purpose and scope

NMKR Connect is a WordPress plugin that retrieves Cardano and Solana NFT project and token data through NMKR, persists synchronized data in WordPress, exposes five shortcode displays, and provides administration, synchronization, role/capability, and optional engagement-analytics features. The code is organized primarily as procedural, `nmkr_`-prefixed PHP modules with administration and front-end JavaScript assets.

This guide describes the current repository for maintainers and prospective contributors. It is an onboarding map, not an exhaustive API reference or a guarantee that internal functions, hooks, constants, tables, options, or lifecycle behavior form a stable public API. Those implementation contracts—especially synchronization and persistence state—must not be changed casually.

Related guidance:

- [Repository documentation hub](../README.md#documentation)
- [User guide](user-guide.md)
- [Comprehensive FAQ](faq.md)
- [Troubleshooting guide](troubleshooting.md)
- [Validation policy](validation-policy.md)
- [Playwright guide](testing-playwright.md)
- [Milestone 4 audit and evidence hub](milestone-4/README.md)
- [M4-02 dashboard mutation-authority evidence](milestone-4/executed-evidence-m4-02-2026-08-29.md)
- [M4-03 progress-recovery authority evidence](milestone-4/executed-evidence-m4-03-2026-08-30.md)
- [M4-05 public-ingestion evidence](milestone-4/executed-evidence-m4-05-2026-08-31.md)
- [Milestone 3 evidence workspace](milestone-3/README.md)
- [Phase 17 isolated synthetic harness](testing-phase-17.md)

## Development prerequisites

Verified requirements and tools are:

- WordPress 5.8 or later and PHP 7.4 or later;
- Composer for manifest/dependency validation only; the current plugin has no Composer runtime package dependency;
- Node.js and npm for repository validation, package scripts, and Playwright tooling;
- Chromium only for private Playwright browser execution; and
- an independently prepared WordPress test installation for runtime tests.

The public CI workflow currently uses Node.js 20 and PHP 7.4. Run `composer validate --strict` for metadata validation. The plugin has no Composer runtime package dependency and its purpose-built ZIP is produced with `npm run build:package` from a clean exact source tree. `npm ci` installs the locked Node test dependencies.

The repository does not provide a self-contained Docker, Local, or `wp-env` WordPress environment. Browser, WP-CLI, and runtime tests require a separately prepared installation. Never put its addresses, paths, credentials, or populated environment values in repository files or public output.

## Repository architecture

| Path | Current responsibility |
| --- | --- |
| [`nmkr-connect.php`](../nmkr-connect.php) | Bootstrap; plugin constants; activation defaults, schema and roles; explicit module includes; deactivation cleanup; registered uninstall callback; asset and shortcode initialization. |
| [`includes/api/`](../includes/api/) | NMKR HTTP request construction, response handling, throttling, and project/token/detail retrieval helpers. |
| [`includes/database/`](../includes/database/) | Custom-table schema creation and verified upgrades, plus project, token, detail, history, and metric persistence helpers. |
| [`includes/synchronization/`](../includes/synchronization/) | Run admission/ownership, worker lifecycle, sequential pagination, progress, checkpoints, metrics, failures, recovery, and terminalization; it also contains synchronization AJAX handlers. |
| [`includes/ajax/`](../includes/ajax/) | Other AJAX boundaries, including the analytics ingestion fallback. |
| [`includes/pages/`](../includes/pages/) and [`includes/menus/`](../includes/menus/) | Administration pages, menus, access-controlled rendering, settings, dashboard, projects, shortcode reference, and analytics UI. |
| [`includes/roles/`](../includes/roles/) | Administrator grants, NMKR Admin/Marketing roles, and plugin-specific capabilities. |
| [`includes/shortcodes/`](../includes/shortcodes/) | The five current displays: grid, token list, carousel, single token, and single project. |
| [`includes/analytics/`](../includes/analytics/) | Public event endpoints and scheduled retention maintenance; shared validation/storage/GA4 helpers live under `includes/helpers/`. |
| [`js/`](../js/) and [`css/`](../css/) | Administration and front-end behavior and styling, including synchronization polling and analytics collection. |
| [`tests/e2e/`](../tests/e2e/) | Playwright authentication, read-only smoke, locally stubbed regressions, and synchronization UI-state coverage. |
| [`scripts/`](../scripts/) | Public-safe regressions, private-environment orchestration, WP-CLI checks, and guarded controlled-sync validation. |
| [`docs/`](./) and [`.github/workflows/`](../.github/workflows/) | Operational/testing documentation and public CI. |

`composer.json` retains project metadata plus the PHP and license requirements used by validation; it has no autoload section and no runtime package dependency. `nmkr-connect.php` explicitly `require_once`s the procedural modules in load order, and the release package does not require `vendor/`.

## Core data flows

### Settings

```mermaid
flowchart LR
    A[Settings → NMKR Connect] --> B[WordPress Settings API]
    B --> C[Capability and settings nonce]
    C --> D[nmkr_connect_sanitize_options]
    D --> E[nmkr_connect_options]
```

The page is registered with `add_options_page()` under **Settings → NMKR Connect**. WordPress's Settings API supplies the save request and settings nonce, the option-page capability is mapped to `nmkr_manage_settings`, the page checks that capability, and the registered sanitization callback validates the submitted option values.

### Synchronization

```mermaid
flowchart TD
    A[NMKR dashboard] --> B[js/nmkr-sync-progress.js]
    B --> C[admin-ajax.php]
    C --> D[Action nonce and capability validation]
    D --> E[Run admission and durable owner]
    E --> F[Exact run-scoped WP-Cron event]
    F --> G[NMKR API requests]
    G --> H[Project, token and detail persistence]
    H --> I[Options, transients, checkpoints and live metrics]
    I --> J[Browser polling]
    J --> K[Canonical completed, stopped or failed finalization]
    K --> L[Metrics and synchronization history]
```

A direct run has one active durable owner and a UUID `run_id`. The start handler admits the run and schedules the exact run-scoped event; the cooperative Stop request must name that run and is observed at worker checkpoints. Token pages are traversed sequentially and token UIDs are deduplicated within the run. Discovery progress remains provisional and below 100%; only canonical completed finalization reaches 100%. Completion, Stop, and failure pass through ownership, recovery, cleanup, history, metric, and terminal-state verification paths.

Ordinary progress polling is observational under `nmkr_view_dashboard`. Start, Stop, cleanup, and state-changing recovery require `nmkr_manage_sync`. Use the dashboard/AJAX lifecycle. Do not bypass admission or checkpoints by calling worker functions directly or editing database state. The bounded M4-02 and M4-03 evidence linked above records the current authority split; it is not universal lifecycle authorization proof.

### Front-end displays

```mermaid
flowchart LR
    A[Registered shortcode] --> B[Validated attributes and project/token resolution]
    B --> C[Local WordPress tables]
    C --> D[Escaped HTML and front-end assets]
    D --> E[Optional configured analytics event]
```

Ordinary shortcode rendering resolves synchronized local project/token data; it does not initiate a fresh full NMKR synchronization. Each implementation validates its supported attributes and selection, queries local plugin tables, and escapes at the output boundary.

### Analytics

```mermaid
flowchart LR
    A[Front-end interaction] --> B[Consent, DNT, sampling and configuration checks]
    B --> C[Public REST endpoint or AJAX fallback]
    C --> D[Validation, deduplication and rate admission]
    D --> E[Local table, GA4, both, or neither by mode]
    E --> F[Scheduled retention maintenance]
```

Analytics ingestion is intentionally public/unauthenticated because ordinary front-end visitors may interact with rendered shortcodes. It is not an administrative mutation boundary. The common ingestion path enforces the configured mode, logged-in-user policy, consent, DNT, server-authoritative sampling, host/origin checks, allowed event/shortcode shapes, recursive metadata and UID bounds, durable rate counters, and deduplication admission. When logged-in tracking is enabled, accepted events may include a WordPress user ID; do not describe every event as anonymous.

`custom` stores locally, `ga4` sends eligible events through the server-side GA4 Measurement Protocol helper, `both` does both, and `off` does neither. Missing GA4 configuration can prevent that destination from being active. The daily retention task deletes old local events in bounded chunks according to the configured retention window; it does not delete events already sent to GA4.

The M4-05 controls are bounded mitigations, not universal denial-of-service resistance, exactly-once delivery, or coverage for every deployment/cache topology. Durable pre-sink admission supports at-most-once sink invocation within the deduplication window, so a later sink failure may lose the event. Plugin-level request-body bounds do not prevent upstream servers, proxies, or PHP from buffering data before plugin code runs. See the [M4-05 evidence](milestone-4/executed-evidence-m4-05-2026-08-31.md) and [FAQ](faq.md#analytics-privacy-consent-and-ga4).

## Persistence model

All names below use the active WordPress `$wpdb->prefix` before the `nmkr_` portion.

| Table | Purpose |
| --- | --- |
| `nmkr_projects` | Synchronized NMKR project identity, chain, supply/state, presentation, and project metadata. |
| `nmkr_tokens` | Synchronized token identity, project relationship, state, pricing, media, and summarized detail data. |
| `nmkr_token_details` | One-to-one extended token sale, receiver, metadata, payment, and source details keyed by token UID. |
| `nmkr_sync_stats` | Run-scoped synchronization history, outcome, processed/success/failure counts, and failure information. |
| `nmkr_sync_metrics` | Historical synchronization performance totals such as duration, API timing/request count, and memory. |
| `nmkr_analytics` | Optional validated view/click engagement events and associated bounded context for reporting and retention. |

`nmkr_connect_schema_version` versions the database schema separately from the plugin version. Normal plugin loading runs a site-scoped, token-owned upgrade lock; the current upgrade creates or verifies the nullable `char(36)` unique `run_id` contract on synchronization history before recording success.

Synchronization also coordinates durable ownership, checkpoints, progress, recovery, and finalization through options and transients. Tables, options, and transients are concurrency and lifecycle contracts. Any change needs explicit migration, compatibility, cleanup, rollback, cache, and concurrent-worker analysis. The uninstall callback's current whitelist is cleanup behavior, not a complete inventory of every state key the plugin might ever own.

## WordPress security boundaries

For every change:

- keep `ABSPATH` guards on directly loadable PHP modules;
- authorize with the narrow plugin-specific capability appropriate to the action;
- verify the action's correct nonce as well as capability—never reuse a convenient unrelated nonce;
- unslash, sanitize, type-check, allowlist, and validate request data before use;
- escape for the final HTML, attribute, URL, JavaScript, or JSON output context;
- use `$wpdb->prepare()` for dynamic SQL values and retain strict table/column allowlists where identifiers cannot be placeholders;
- send no-cache headers for sensitive polling/AJAX responses;
- pass AJAX URLs, nonces, and bounded configuration with WordPress localization rather than hard-coding deployment details;
- make user-facing strings internationalization-ready;
- design and test for least privilege; and
- use HTTPS and keep API keys, analytics secrets, authentication material, and environment details out of source and diagnostics.

A nonce mitigates CSRF and demonstrates request intent; it is **not authentication or capability authorization**. A hidden or removed UI action still requires server-side authorization. Existing controls do not constitute a security certification or a guarantee that the plugin has no vulnerabilities.

## Extension points and compatibility

Only the following deliberate hooks and ordinary WordPress registrations should be treated as current integration points. Their presence is not a promise that every payload or internal side effect will remain unchanged forever.

| Hook/registration | Kind | Purpose | Caveat |
| --- | --- | --- | --- |

| `nmkr_ipfs_gateway_base` | Filter | Supplies the optional base used for a one-time IPFS token-image fallback. | The default is empty. Return a trusted, credential-free HTTPS base; shared public gateways can be slow, rate-limited, blocked, or unavailable. The helper validates and normalizes the base to end in `/ipfs/`, but consumers remain responsible for availability, privacy, and compatibility. |
| `nmkr_marketing_allowed_submenus` | Filter | Adjusts the submenu slug allowlist visible to restricted users. | Visibility is not authorization. Never use this to grant access; page and AJAX capability checks must remain authoritative. |
| WordPress plugin lifecycle hooks | Core registrations | Activation establishes schema/defaults/capabilities; deactivation clears volatile state; uninstall performs configured cleanup. | Do not call callbacks directly or assume uninstall removes every possible plugin-owned state key. |
| `init` shortcode registrations | Core registrations | Registers `[nmkr-grid]`, `[nmkr-token-list]`, `[nmkr-carousel]`, `[nmkr-token]`, and `[nmkr-project]`. | Preserve availability, registered attributes, escaping, local-data behavior, and compatibility when altering callbacks. |

Synthetic filter examples:

```php
add_filter( 'nmkr_ipfs_gateway_base', function () {
    return 'https://dedicated-gateway.example/ipfs/';
} );

add_filter( 'nmkr_marketing_allowed_submenus', function ( $slugs ) {
    return array_values( array_intersect( $slugs, array( 'nmkr-connect-projects' ) ) );
} );
```

Internal synchronization constants and limits are safety policies, not recommended override points. Changes require focused regression tests and authorized private runtime validation.

## Development conventions

- Prefix plugin functions, options, and custom tables with `nmkr_` (with existing `nmkr_connect_` names retained where established).
- Follow the current procedural PHP organization and WordPress APIs/coding conventions; preserve PHP 7.4 compatibility.
- Validate input strictly and escape at the final output boundary.
- Continue `filemtime()` cache-busting where the current asset loaders use it.
- Use synthetic, public-safe identifiers and payloads in committed tests and examples.
- Make the smallest coherent change and avoid unrelated refactoring.
- Never edit third-party files in `vendor/` or `node_modules/`; update dependencies through their manifests and lockfiles in a dedicated dependency change.

Synchronization lifecycle, database/schema state, authorization/security boundaries, and deployment integrity are high-risk areas. They require deeper review, affected regressions, and exact-head validation in a prepared private environment.

## Test entry points

The numbered guides are authoritative for full procedures. This table is a selector, not a replacement.

| Boundary | Command | What it does / prerequisite |
| --- | --- | --- |
| Dependency setup | `npm ci` | Installs locked Node test dependencies; public-safe by itself. |
| Public/local | `npm run test:public` | Runs the public-safe umbrella: syntax, Playwright discovery, compatibility, and synthetic regressions. |
| Public/local | `npm run test:php74` | Runs tracked-PHP syntax and focused PHP 7.4 compatibility checks. |
| Public/local discovery | `npm run test:e2e -- --list --reporter=list` | Lists Playwright tests only; it does **not** execute browser behavior. |
| Prepared private WordPress | `npm run test:e2e` | Executes the complete Playwright suite with private environment/authentication inputs. |
| Prepared private WordPress | `npm run test:e2e:settings` (or another current `test:e2e:*` package script) | Executes a targeted Playwright suite; append `-- --list` for discovery only. |
| Prepared private WordPress | `npm run test:phase2` | Orchestrates prepared-environment readiness, browser, WP-CLI, and database-state checks. |
| Prepared private WordPress | `npm run test:phase2:existing-readonly` | Validates an explicitly identified existing deployment without runner-driven deployment/install steps; browser authentication still occurs. |
| Prepared private WordPress | `bash scripts/nmkr-wpcli-smoke.sh` / `npm run test:wpcli:db-state` | Runs WP-CLI smoke or read-only database/schema/state checks against the prepared installation. See [Phase 1](testing-phase-1.md) and [Phase 8](testing-phase-8.md). |
| Controlled real synchronization | `npm run test:real-sync:preflight` | Runs the guarded authorization/readiness preflight; it is not ordinary public validation. See [Phase 15](testing-phase-15.md). |
| Controlled real synchronization | `npm run test:real-sync:phase16a:regression` | Runs the public-safe synthetic Phase 16A harness regression; an actual controlled run follows the separately guarded [Phase 16A procedure](testing-phase-16a.md). |

Public CI does not authenticate to WordPress or execute Playwright browser behavior. Browser execution needs a privately prepared WordPress environment and Chromium. `.env.tests`, any external populated environment file, and authentication state are private. Defaults remain `RUN_REAL_SYNC=false` and `PW_SAVE_ARTIFACTS=false`. Real synchronization requires explicit authorization and controlled data; it is not part of routine validation. Never upload reports, traces, screenshots, videos, logs, authentication state, or other private run output to public CI, issues, or pull requests.

## Selecting checks by change type

Use the canonical [pull-request validation policy](validation-policy.md) to classify changes as docs/metadata, test/tooling-only, ordinary runtime, or high-risk and to select proportional pre-merge, review, and post-merge checks. The numbered testing guides remain authoritative for how their individual runners operate; they do not replace the policy.

## Contribution workflow

1. Fetch current `main` and record its exact baseline SHA.
2. Create a focused branch from that baseline.
3. Trace affected hooks, capabilities, nonces, data stores, concurrency, and cleanup paths.
4. Apply the smallest coherent change.
5. Run public-safe checks and affected targeted checks.
6. Open a focused pull request with exact base/head SHAs, scope, tests, risks, and limitations.
7. Review every changed hunk and address blocking findings.
8. Perform private exact-head validation when runtime or environment risk requires it.
9. Merge only the reviewed and validated exact head.
10. Revalidate merged `main` when runtime behavior changed.

## Public-repository safety

| Safe to commit | Never commit or paste |
| --- | --- |
| Source code; synthetic tests; generic unpopulated configuration examples; public-safe scripts; sanitized summaries; documentation without environment identifiers. | NMKR API keys; GA4 API secrets; credentials; cookies or nonces; private URLs, domains, IP addresses, deployment paths, or commands; populated environment files; raw logs or database output; customer/project/token data; private screenshots, traces, videos, reports, or authentication state. |

Treat every commit, CI log, issue, review, and pull-request comment as public. If a result cannot be made safe without losing important meaning, retain it only under the project's approved private controls and publish a bounded sanitized summary.

## Documentation maintenance

When a focused change alters shortcodes, settings, roles/capabilities, menu labels, synchronization behavior, analytics, requirements, test commands, or extension points, update the relevant README, user, developer, troubleshooting, and testing documentation in the same change where practical. Keep authoritative procedures in their focused documents and link to them instead of creating divergent copies.

## Next steps by task

- **Change user-facing behavior:** update the [user guide](user-guide.md), [FAQ](faq.md), and relevant troubleshooting entry.
- **Change authorization or synchronization state:** trace capabilities, nonces, ownership, polling, recovery, cleanup, tests, and exact-head private validation requirements.
- **Change analytics ingestion:** trace the public request boundary, admission state, local/GA4 sinks, retention, privacy controls, and M4-05 limitations.
- **Choose checks:** use the [validation policy](validation-policy.md) before running or requesting private validation.
- **Assess assurance:** use the [Milestone 4 hub](milestone-4/README.md), findings, traceability, and package evidence without converting bounded results into universal claims.
