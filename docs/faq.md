# NMKR Connect FAQ

This FAQ gives short, role-oriented answers for site owners, administrators, content users, developers, maintainers, and support readers. It links to the authoritative procedures instead of duplicating them.

## Start here

- [User guide](user-guide.md) — installation, settings, synchronization, projects, shortcodes, analytics, updates, and uninstall.
- [Troubleshooting guide](troubleshooting.md) — symptom-based, read-only-first diagnosis and safe escalation.
- [Developer guide](developer-guide.md) — architecture, data flows, security boundaries, tests, and contribution workflow.
- [Validation policy](validation-policy.md) — risk classification and proportional public/private checks.
- [Milestone 4 hub](milestone-4/README.md) — audit plan, findings, traceability, bounded evidence, limitations, and non-claims.
- [Milestone 4 Catalyst submission alignment](milestone-4/catalyst-submission-alignment.md) — Statement of Milestones inventory, selected OWASP ASVS mapping, findings summary, and reviewer path.
- [Milestone 4 evidence register](milestone-4/evidence-register.md) — canonical stable evidence IDs and provenance.
- [Milestone 4 final report](milestone-4/final-report.md) and [Proof of Achievement](milestone-4/proof-of-achievement.md) — completed M4-10 reporting synthesis and provenance seal.

### What is NMKR Connect?

NMKR Connect is a WordPress plugin that synchronizes Cardano and Solana NFT project and token data through the NMKR API, stores the synchronized data locally, and renders it through five shortcodes. It also provides role-based administration, synchronization status and history, and optional engagement analytics.

### Which guide should I read first?

Site owners and administrators should start with the [user guide](user-guide.md). Content and marketing users should begin with the [roles and access guidance](user-guide.md#administration-pages-and-access) and then use the Projects, Shortcodes, and Analytics sections. Developers should start with the [developer guide](developer-guide.md). When something fails, use the [troubleshooting guide](troubleshooting.md).

### Does this FAQ replace the detailed guides?

No. The FAQ explains boundaries and directs readers to the maintained procedure. When a short answer and a detailed guide differ, verify the current source and follow the detailed guide.

### What does current Milestone 4 evidence prove?

Only the bounded behavior recorded in the linked package evidence. It supports the project's completed Milestone 4 delivery within those disclosed scopes; it does not prove universal authorization safety, universal XSS or SQL-injection resistance, dependency safety, production security, unlimited scale, complete ASVS conformance, formal certification, penetration testing, or external Catalyst assessment or approval.

## Installation, requirements, updates, and removal

### What are the minimum requirements?

WordPress 5.8 or later, PHP 7.4 or later, an authorized NMKR API key, and Composer when building from source. The latest stable WordPress and maintained dependencies are recommended.

### Why can a raw GitHub source ZIP fail to activate?

The runtime requires Composer-managed files, including `vendor/autoload.php` and the bundled Freemius SDK. A source archive without `vendor/` is not a complete installable package. See [Install, activate, update, and remove](user-guide.md#install-activate-update-and-remove).

### Do I need Composer?

Yes when building from source. A trusted packaged ZIP may already include the required runtime dependencies. The repository does not promise a prebuilt ZIP for every revision.

### How should I update the plugin?

Use a verified backup, stage the exact package first, and verify activation, settings presence, role access, an idle or terminal synchronization state, Projects, every shortcode in use, and the chosen analytics mode. Do not run an uncontrolled real synchronization solely as an update check.

### Is deactivation the same as uninstall?

No. Deactivation clears volatile synchronization state but retains the plugin installation and persistent data. Deleting the plugin invokes the registered uninstall callback. The current uninstall behavior removes a fixed table/option/transient whitelist, with additional analytics cleanup only when **Remove Data on Uninstall** was enabled. It does not guarantee removal of every role, capability, scheduled hook, or possible plugin-owned state key.

### What should I back up before deletion?

Back up the database and any deployment files needed for rollback. Confirm retention and legal requirements before removing local analytics or synchronized data, then verify the intended final state after uninstall.

## Roles, capabilities, and administration pages

### Where are the plugin pages?

The top-level **NMKR Connect** menu contains **Dashboard**, **NFT Projects**, **Shortcodes**, and **Analytics** when that UI is available. Configuration is separately under **Settings → NMKR Connect**.

### What can the built-in roles do?

- **Administrator** receives all plugin capabilities plus its ordinary WordPress authority.
- **NMKR Admin** receives all NMKR Connect capabilities but no unrelated site-wide Administrator authority.
- **NMKR Marketing** can use NFT Projects, Shortcodes, and Analytics, but cannot view the synchronization Dashboard, change settings, or manage synchronization.

Use the [capability and action matrix](user-guide.md#capability-and-action-matrix) for the exact seven `nmkr_*` capabilities.

### Which role follows least privilege for content work?

Use **NMKR Marketing** when the user only needs Projects, Shortcodes, and Analytics. Grant **NMKR Admin** only when the user must configure the plugin or manage synchronization.

### Can a custom role receive selected access?

Yes. Assign only the action-specific capabilities it needs. `nmkr_access_plugin` exposes the plugin menu shell and routing behavior; it does not authorize every child page or action by itself.

### Is a visible menu or button the authorization boundary?

No. UI visibility improves usability, but server-side capability checks are authoritative. Hiding a control does not secure an endpoint, and showing a parent menu does not grant every child capability.

### Is a WordPress nonce authorization?

No. A nonce provides request-intent and CSRF protection. The server must still authenticate the user and require the correct capability. Possessing a nonce does not grant permission.

### Can a Dashboard viewer perform recovery?

Ordinary progress observation uses `nmkr_view_dashboard`. State-changing start, Stop, cleanup, or recovery actions require `nmkr_manage_sync`. A view-only user should preserve evidence and escalate rather than editing state.

## API and analytics secret handling

### Where should I enter the NMKR API key?

Only in **Settings → NMKR Connect → API Settings**, over HTTPS, using an account with `nmkr_manage_settings`.

### Is the API key encrypted at rest by the plugin?

The field is rendered as a password field and sanitized on save, but the plugin documentation does not claim application-level or at-rest encryption. Protect WordPress database access, backups, administrator accounts, and transport security accordingly.

### How should I handle the GA4 API secret?

Treat it like the NMKR API key. Do not place it in posts, source control, screenshots, logs, shell history, issue reports, or public test output.

### What can I share when configuration fails?

Share only whether a field is populated, the affected setting name, a sanitized HTTP status or error class, the plugin commit/release, and reproduction steps. Never share the secret value.

## Synchronization lifecycle and recovery

### Who can start synchronization?

Submitting Start requires `nmkr_manage_sync`, a valid action nonce, and no active run owning the lifecycle. The start handler can admit ownership and queue the background worker before validating API configuration. The worker checks the API key when it runs; if the key is missing, it records failure and invokes the run's failure cleanup. Configure a valid API key before starting if the synchronization is expected to proceed.

### What is run ownership?

A direct run receives a UUID `run_id` and durable ownership. Another request cannot simply take over the active run. Ownership protects worker, Stop, recovery, history, cleanup, and terminalization paths from cross-run interference.

### Does closing the Dashboard stop the worker?

No. The Dashboard observes server state. Closing or losing the browser does not by itself prove that the server worker stopped or failed.

### Why does the polling delay increase?

Temporary progress-poll failures retry with exponential backoff capped near 30 seconds. This browser behavior is observational and does not own server completion.

### Does **Maximum Error Count** terminate current polling retries?

No. The setting is retained and sanitized, but the current progress poller does not use it as the transient polling-failure termination limit.

### What does Stop do?

Stop is a cooperative, run-scoped request. The worker observes it at safe checkpoints, records a stopped terminal result, and finalizes the run. It is not an immediate process kill and should be submitted once for the trusted active run.

### When can progress reach 100%?

Only canonical successful completion reaches 100%. Discovery progress is provisional while pages are still being traversed. Failed or stopped terminal states are not completion.

### Who may perform recovery mutation?

Only a user with `nmkr_manage_sync`, through the guarded recovery path. Ordinary `nmkr_view_dashboard` polling remains observational. See the [synchronization procedure](user-guide.md#synchronize-projects-and-tokens) and [stalled-run troubleshooting](troubleshooting.md#6-synchronization-appears-stalled-or-pollingnetwork-errors-occur).

### What should never be edited casually?

Do not manually rewrite owner, status, progress, history, metric, option, transient, cron, or table state to clear a run. Start read-only, confirm the exact environment and terminal state, make a verified backup, define rollback and final-state checks, and escalate when any prerequisite is missing.

## Projects, tokens, and incomplete data

### Where does displayed data come from?

Shortcodes read the locally synchronized plugin tables. Rendering a shortcode does not start a fresh NMKR synchronization.

### Why can a project or token be missing?

The configured account may not return it, the run may have failed or stopped, upstream data may be absent or malformed, the UID may be wrong, or the relevant token details may not have been synchronized.

### How are duplicate token UIDs handled?

Within a run, first-seen token UIDs are processed and duplicate UIDs are ignored. Authoritative unique totals are reconciled near completion.

### What happens on malformed, repeated, or no-progress pages?

The run fails explicitly rather than claiming completion. The configured page ceiling is a plugin safety policy, not a documented NMKR service limit.

### Are Cardano and Solana supported?

Yes within the current implementation and documented data paths. That does not guarantee every account, payload shape, collection size, host, or upstream condition.

## Shortcodes and plans

### What are the exact five shortcodes?

| Shortcode | Plan | Supported attributes |
| --- | --- | --- |
| `[nmkr-grid]` | Free | `project_uid`, `allow_user_select` |
| `[nmkr-token-list]` | Free | `project_uid`, `allow_user_select` |
| `[nmkr-carousel]` | Premium | `project_uid`, `allow_user_select` |
| `[nmkr-token]` | Premium | `token_uid` |
| `[nmkr-project]` | Premium | `project_uid`, `allow_user_select` |

There are no registered shortcode `limit`, ordering, pagination, template, styling, chain, or query attributes. See [Shortcodes](user-guide.md#shortcodes) for the detailed behavior.

### What happens when `project_uid` is omitted?

Project-based shortcodes select the latest suitable synchronized project. A `nmkr_project` URL selection takes precedence over the shortcode attribute where implemented.

### How does `allow_user_select` work?

It defaults to `"1"`. Exactly `allow_user_select="0"` hides the project selector.

### What happens when `[nmkr-token]` omits `token_uid`?

It selects the latest project and prefers its first buyable token, then falls back to the first token in the implemented 50-row lookup. It has no user-selector attribute.

### Why is output empty or unavailable?

Common causes are no synchronized data, an unknown UID, an incomplete run, unavailable media/details, a front-end conflict, or a Premium shortcode without Premium entitlement.

## Analytics, privacy, consent, and GA4

### Is analytics ingestion intentionally public?

Yes. Front-end visitors can view and click rendered shortcodes without authenticating to WordPress, so the REST endpoint and AJAX fallback intentionally accept public/unauthenticated event requests. This is not an administrative configuration or synchronization mutation boundary.

### Does public/unauthenticated mean every event is anonymous?

No. When **Track Logged In Users** is enabled, accepted events may include a WordPress user ID. Use the term **public/unauthenticated ingestion** rather than assuming every event is anonymous.

### Which analytics modes exist?

- **Off** — neither local storage nor GA4 delivery.
- **Custom** — local plugin analytics only.
- **GA4** — eligible server-side GA4 delivery only.
- **Both** — local storage and eligible GA4 delivery.

GA4 delivery also requires a valid Measurement ID and API secret.

### How do consent, DNT, logged-in tracking, and sampling work?

The configured mode must allow collection. Logged-in events are excluded unless logged-in tracking is enabled. When consent is required, the front-end waits for the plugin consent signal. DNT is respected when presented. Sampling is enforced server-side within the configured 0–1 rate.

### What does local retention affect?

It deletes old local analytics rows in bounded scheduled work. It does not delete events already sent to GA4; external retention is controlled separately by the site operator and GA4 configuration.

### What are rate limiting and deduplication for?

They are bounded admission controls for repeated or excessive requests. They do not guarantee universal abuse resistance, denial-of-service resistance, or exactly-once delivery across every deployment topology.

### Can an event be lost after admission?

Yes. Durable pre-sink admission supports at-most-once sink invocation within the deduplication window, but a later local or GA4 sink failure can lose the event rather than retrying it as a duplicate.

### Does the plugin body bound prevent upstream buffering?

No. Plugin-level decode and sink bounds apply after the web server, proxy, or PHP stack may already have buffered request data.

### Who is responsible for privacy and legal configuration?

The site operator remains responsible for notices, lawful basis, consent integration, logged-in-user policy, retention, data-subject handling, GA4 configuration, and any other applicable legal obligations.

## Troubleshooting, diagnostics, and support

### What is the safest first diagnostic step?

Observe current status, history, request status classes, and sanitized error text without deleting state. Use the [troubleshooting symptom index](troubleshooting.md#symptom-index).

### Should I delete options or transients to clear a status?

No. Those keys participate in lifecycle and ownership contracts. Use guarded UI actions only when authorized and when prerequisites are satisfied.

### When is database or WP-CLI mutation appropriate?

Only as an advanced, controlled recovery action after exact-environment confirmation, a verified backup, idle or terminal synchronization, an explicit change and rollback plan, and final-state verification.

### What is safe to share publicly?

The exact public commit/release, WordPress and PHP versions, browser class/version, affected feature, synthetic identifiers, reproducible steps, expected/actual results, sanitized error text, and terminal-state information.

### What must never be shared publicly?

API keys, GA4 secrets, license data, credentials, cookies, nonces, private URLs or IPs, populated environment files, raw logs, database dumps/output, customer data, real project/token identifiers, or sensitive screenshots, traces, videos, reports, and authentication state.

### Where is the support template?

Use the copyable [safe escalation checklist](troubleshooting.md#18-safe-escalation-checklist).

## Developers and maintainers

### Where should a contributor start?

Read the [developer guide](developer-guide.md), then choose checks through the [validation policy](validation-policy.md). Inspect the current source and exact baseline rather than assuming documentation is current.

### Are internal functions and database state stable public APIs?

No. The guide is an onboarding map, not an exhaustive public API contract. Synchronization, ownership, persistence, option, transient, schema, and cleanup behavior require explicit compatibility and concurrency analysis before modification.

### What is the public CI entry point?

`npm run test:public` after `npm ci`. Public CI uses no WordPress credentials and does not deploy or publish private browser artifacts.

### When is private validation required?

According to risk. Runtime synchronization, persistence, schema, authorization, external API, destructive cleanup, or deployment-integrity changes normally require exact-head private validation. Documentation-only changes normally require focused link/path/terminology checks and public CI, not a private deployment.

### Why must exact heads be verified?

Validation is evidence only for the exact source that was inspected and executed. Record the base, PR head, deployed head when applicable, worktree cleanliness, and merge/tree equivalence.

### May public CI receive private inputs or artifacts?

No. Keep credentials, URLs, authentication state, logs, reports, screenshots, traces, videos, database output, and private environment details out of public CI and repository history.

### When may a real synchronization run?

Only after explicit authorization, controlled data selection, readiness checks, rollback and Stop/recovery planning, and final-state verification. It is excluded from routine public validation.

### Where are current findings and evidence?

Use the [Milestone 4 hub](milestone-4/README.md), canonical [evidence register](milestone-4/evidence-register.md), [findings register](milestone-4/findings-register.md), [traceability matrix](milestone-4/traceability.md), and [Catalyst submission alignment](milestone-4/catalyst-submission-alignment.md). The linked M4-02 through M4-07 package records distinguish their public CI, synthetic coverage, and package-specific evidence; the [M4-08 record](milestone-4/executed-evidence-m4-08-2026-09-01.md) adds sanitized cumulative private DEV validation.

## Assurance, evidence, and non-claims

### Is the Milestone 4 audit complete?

Yes, within the project's disclosed bounded scopes. M4-01 through M4-10, including the [final report](milestone-4/final-report.md), [Proof of Achievement](milestone-4/proof-of-achievement.md), and final M4-10 provenance seal, are complete. This project delivery status is not external Catalyst assessment or approval and does not broaden the recorded security, runtime, dependency, compliance, or production claims.

### Which OWASP standard is selected for Milestone 4?

The milestone maps a selected subset of **OWASP Application Security Verification Standard 5.0.0** requirements to exact project trace and evidence IDs. The mapping supports only the recorded boundaries and does not claim complete ASVS conformance, exhaustive OWASP coverage, penetration testing, or certification. See the [Catalyst submission alignment](milestone-4/catalyst-submission-alignment.md#selected-owasp-verification-baseline).

### Is NMKR Connect certified secure?

No. Current controls and evidence are bounded engineering assurance, not formal certification, penetration testing, or a production security guarantee.

### Does the evidence prove universal authorization, XSS, or SQL-injection resistance?

No. It proves only the specific inspected and exercised boundaries identified in each package record.

### Does a dependency audit guarantee dependency safety?

No. Advisory results are time-dependent and limited to the committed lock state and advisory services available when the check ran.

### Has Catalyst approved Milestone 4?

No such claim is made. Delivery status and external Catalyst assessment are separate.

### Where are residual limitations recorded?

In the [findings register](milestone-4/findings-register.md), [traceability matrix](milestone-4/traceability.md), package evidence, and the explicit non-claims in the [Milestone 4 hub](milestone-4/README.md).
