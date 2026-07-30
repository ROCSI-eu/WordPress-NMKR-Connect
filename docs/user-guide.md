# NMKR Connect user guide

This guide is for WordPress site owners and administrators. It describes the current plugin, not a guarantee about every host, NMKR account, collection size, or upstream API condition. Use the [troubleshooting guide](troubleshooting.md) when a workflow does not complete as expected.

## Install, activate, update, and remove

### Packaged ZIP

Obtain the ZIP from a trusted release source and confirm that it is intended for the version you plan to deploy. In **Plugins → Add New → Upload Plugin**, select the ZIP, choose **Install Now**, and then activate **NMKR Connect**. A usable package must contain `vendor/autoload.php` and the bundled Freemius SDK below `vendor/`; an archive made directly from source without Composer dependencies is incomplete.

### Build from source

Composer is required to assemble runtime dependencies:

```bash
git clone https://github.com/ROCSI-eu/WordPress-NMKR-Connect.git
cd WordPress-NMKR-Connect
composer install --no-dev --prefer-dist
```

Install the resulting directory under `wp-content/plugins/`, or create a ZIP containing that directory, including `vendor/`. Do not commit or distribute populated environment files.

Activation creates or upgrades the plugin tables, installs NMKR roles/capabilities, supplies defaults, attempts safe stale-synchronization recovery, and schedules analytics retention maintenance. Test activation on staging first and make a verified backup before an update.

Deactivation clears volatile synchronization state but is not uninstall. Deleting the plugin through WordPress invokes the registered uninstall routine: project, token, token-detail, synchronization-history, and synchronization-metrics tables and plugin options/transients are removed. The analytics table is removed only when **Remove Data on Uninstall** was selected before deletion. Treat deletion as destructive and verify backups and retention requirements first.

## Administration pages and access

The exact top-level menu is **NMKR Connect**. Its plugin submenus are **Dashboard**, **NFT Projects**, **Shortcodes**, and **Analytics** (unless the analytics UI is disabled by the build). Configuration is separately located at **Settings → NMKR Connect**; the Plugins screen also provides a **Settings** action link.

Access follows capabilities, not a role-name shortcut:

| Role | Access supplied by the plugin |
| --- | --- |
| Administrator | All NMKR capabilities: plugin access; Dashboard, NFT Projects, Shortcodes, and Analytics views; settings management; synchronization management. |
| NMKR Admin | The same NMKR capabilities, without unrelated site-wide Administrator authority. |
| NMKR Marketing | NFT Projects, Shortcodes, and Analytics only; no Dashboard, settings, start, or Stop authority. |

Use **NMKR Marketing** for users who only need display/engagement work and **NMKR Admin** only for users who must configure or synchronize. Custom roles require the corresponding `nmkr_*` capabilities.

## Configure settings

Only a user with `nmkr_manage_settings` can open or save **Settings → NMKR Connect**. WordPress settings nonces and the capability check protect saving; a rejected save should not be worked around by granting broader permissions than needed.

### API Settings

- **API Key** — NMKR API credential used for project and token requests. The field is rendered as a password field and sanitized as text on save; this is not a claim of application-level or at-rest encryption. Obtain the key through NMKR Studio, paste it only into the settings page over HTTPS, and never put it in posts, source control, screenshots, logs, or public issues.

### Synchronization Settings

- **Synchronization Profile** — Light, Balanced, Aggressive, or Custom. Profiles populate the controls below; select according to staging observations and hosting capacity.
- **Batch Size** — sanitized to 1–10.
- **Delay Between Batches (seconds)** — sanitized to 1–10 seconds.
- **Initial Polling Interval (ms)** — browser status-poll start, sanitized to 100–5,000 ms.
- **Maximum Polling Interval (ms)** — polling ceiling, sanitized to 1,000–30,000 ms.
- **Interval Increase Factor** — backoff growth, sanitized to 1.1–3.0.
- **Interval Decrease Factor** — recovery adjustment, sanitized to 0.1–0.9.
- **Maximum Error Count** — recoverable polling-error limit, sanitized to 1–10.

The direct synchronization token-page size is fixed by the current implementation at 50; it is not a user setting and is separate from Batch Size.

### Debug Settings

- **Enable Debug Logging Controls** is the master switch.
- **Enable Logging to debug.log** and **Enable Logging to Dashboard Logs** choose destinations.
- **Enable API Connection Status Logging**, **Enable Data Synchronization Logging**, **Enable User Interface Status Logging**, and **Enable Performance Logging** choose categories and operate only when the master switch and a destination are enabled.
- **Throttle Log Output (Recommended)** reduces repeated output; **Log Retention Limit** is sanitized to 1–1,000 entries (100 by default).

Debug controls default off. Enable the minimum necessary on staging, reproduce briefly, inspect privately, then disable them. Sanitization and supported redaction paths do not make arbitrary raw logs safe to publish.

### Analytics & Privacy

- **Analytics Mode** — Off, Custom (local plugin analytics), GA4, or Both.
- **GA4 Measurement ID** — optional `G-...` identifier, validated to the implemented format.
- **GA4 API Secret** — optional secret for server-side GA4 delivery; handle like the NMKR key.
- **Data Retention (Days)** — local retention, sanitized to 7–365 days.
- **Track Logged In Users**, **Require User Consent**, **Sample Rate** (0–1), **Remove Data on Uninstall**, and **Enable Analytics Debug** control collection and maintenance.

When consent is required, collection waits for the plugin consent signal. The site operator remains responsible for notices, consent integration, lawful configuration, and retention. “Off” is the appropriate choice when collection is not wanted.

## Synchronize projects and tokens

1. Save a valid API key and open **NMKR Connect → Dashboard**.
2. Start synchronization only when no run is active. The plugin uses run-scoped ownership so another start cannot simply take over an active run.
3. Keep the Dashboard available to observe status. Temporary polling/network errors cause browser polling to back off and recover up to the configured error limit; they do not necessarily mean the worker failed.
4. If necessary, choose **Stop** once. Stop is cooperative and applies only to that run: the worker observes the request at safe checkpoints, records a stopped terminal result, and finalizes state. Do not repeatedly start another run while Stop is settling.
5. Read the final result and history. A failed or stopped run is not completed; address the reported cause before retrying. Stale/interrupted state has guarded recovery paths, but state-changing recovery belongs in the [troubleshooting guide](troubleshooting.md).

For a direct run, projects are fetched once. Each project's tokens are then requested as sequential numbered pages of 50. First-seen token UIDs are processed while traversal continues, duplicates in that run are ignored, and full page payloads are not deliberately retained until the end. This limits deliberate accumulation but does not promise unlimited scale.

Progress is an estimate while pages are still being discovered. The plugin reconciles authoritative unique totals near completion, and only canonical successful finalization reports 100%. Malformed pages, repeated/no-progress pages, or traversal beyond the safety ceiling fail safely instead of being shown as complete. Upstream failures, account permissions, data shape, or resource limits can still prevent completion.

## NFT Projects and chain data

**NMKR Connect → NFT Projects** browses locally synchronized NMKR project records and their token counts/details. Synchronization stores the project and token identifiers and descriptive/status/media/payment data returned through the NMKR integration. Displays can represent Cardano and Solana pricing/data (including ADA and SOL price badges where present); Cardano-specific policy links appear only when applicable data exists. Absence in WordPress can mean the project was not returned for the configured account, a run did not complete, or the relevant token details were unavailable.

## Shortcodes

UID examples below are synthetic. These are the only registered attributes; there are no shortcode `limit`, ordering, pagination, template, styling, chain, or query attributes.

### `[nmkr-grid]` — Free

- **Purpose:** token cards for one synchronized project.
- **Attributes:** `project_uid` (default empty), `allow_user_select` (default `"1"`).
- **Example:** `[nmkr-grid project_uid="demo-project-001" allow_user_select="0"]`
- **Omission/selector:** without `project_uid`, the helper selects the latest available project. A `nmkr_project` URL selection takes precedence over the attribute. The selector is shown by default; exactly `allow_user_select="0"` hides it.
- **Empty output:** no synchronized projects, selected project not found/no tokens, incomplete synchronization, or inaccessible media/data.

### `[nmkr-token-list]` — Free

- **Purpose:** table/list of tokens for one synchronized project.
- **Attributes:** `project_uid` (default empty), `allow_user_select` (default `"1"`).
- **Example:** `[nmkr-token-list project_uid="demo-project-002" allow_user_select="1"]`
- **Omission/selector:** the latest available project is selected when the UID is omitted, while a `nmkr_project` URL selection takes precedence over the attribute; the selector is enabled unless the value is exactly `"0"`.
- **Empty output:** no project/token rows, a UID that is not synchronized, a failed/incomplete run, or front-end script/theme interference.

### `[nmkr-carousel]` — Premium

- **Purpose:** carousel of tokens for one synchronized project.
- **Attributes:** `project_uid` (default empty), `allow_user_select` (default `"1"`).
- **Example:** `[nmkr-carousel project_uid="demo-project-003" allow_user_select="0"]`
- **Omission/selector:** the latest available project is selected when omitted, while a `nmkr_project` URL selection takes precedence over the attribute; exactly `"0"` hides the selector.
- **Empty/unavailable output:** no suitable project/tokens, unknown UID, incomplete synchronization, front-end JavaScript conflict, or no Premium entitlement. Free access returns the Premium-required message rather than the carousel.

### `[nmkr-token]` — Premium

- **Purpose:** details for one token.
- **Attributes:** `token_uid` only (default empty).
- **Example:** `[nmkr-token token_uid="demo-token-001"]`
- **Omission/selector:** when omitted, the shortcode selects the latest project and prefers its first buyable token, falling back to the first token in the implemented 50-row lookup. It has no user selector attribute.
- **Empty/unavailable output:** no project/token fallback, token not found, missing synchronized details, or no Premium entitlement.

### `[nmkr-project]` — Premium

- **Purpose:** one project's details, counters, links, and a featured token when available.
- **Attributes:** `project_uid` (default empty), `allow_user_select` (default `"1"`).
- **Example:** `[nmkr-project project_uid="demo-project-004" allow_user_select="1"]`
- **Omission/selector:** the latest project is selected when omitted, while a `nmkr_project` URL selection takes precedence over the attribute; exactly `"0"` hides the selector.
- **Empty/unavailable output:** no synchronized projects, selected project not found, absent featured token data, or no Premium entitlement.

Plan checks are performed at render time. Freemius plan/licensing integration controls Premium access; do not post licence information in content or support requests.

## Maintenance and support

Back up, update WordPress and dependencies, and rehearse plugin updates and representative pages on staging before production. After an update, verify activation, settings presence, role access, an idle/terminal synchronization state, Projects, each shortcode in use, and analytics choice. Do not perform an uncontrolled real synchronization solely as an update check.

For help, follow the [read-only-first troubleshooting guide](troubleshooting.md). Share only synthetic identifiers and sanitized error text. Never share keys, secrets, credentials, licence data, cookies/nonces, private addresses, raw logs, database dumps, customer data, or populated environment files.
