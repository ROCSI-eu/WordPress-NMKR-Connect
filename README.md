# WordPress NMKR Connect 🔌

**Display and manage Cardano & Solana NFTs in WordPress — powered by the NMKR API.**

> NMKR Connect lets WordPress sites sync, store, and present NFTs with a simple admin UI and a resilient background synchronization flow.

---

## Table of Contents
- [WordPress NMKR Connect 🔌](#wordpress-nmkr-connect-)
  - [Table of Contents](#table-of-contents)
  - [What is NMKR Connect?](#what-is-nmkr-connect)
  - [Features](#features)
  - [How it works](#how-it-works)
  - [Requirements](#requirements)
  - [Installation](#installation)
    - [From source (with Composer)](#from-source-with-composer)
    - [From a ZIP](#from-a-zip)
  - [Configuration](#configuration)
  - [Usage](#usage)
  - [Synchronization UX](#synchronization-ux)
  - [Troubleshooting](#troubleshooting)
    - [“The dashboard shows a sync in progress after reinstall”](#the-dashboard-shows-a-sync-in-progress-after-reinstall)
    - [“Progress stays at 0% then jumps to 100%”](#progress-stays-at-0-then-jumps-to-100)
    - [“Active metrics don’t update”](#active-metrics-dont-update)
    - [“Stop doesn’t stop” (or UI doesn’t refresh after stop)](#stop-doesnt-stop-or-ui-doesnt-refresh-after-stop)
    - [Clean-state checklist](#clean-state-checklist)
  - [Security \& Privacy](#security--privacy)
  - [Development](#development)
    - [Project layout (high level)](#project-layout-high-level)
    - [Local setup](#local-setup)
  - [License](#license)
  - [Acknowledgments](#acknowledgments)
  - [Licensing \& Updates (open-source + commercial support)](#licensing--updates-open-source--commercial-support)

---

## What is NMKR Connect?
**NMKR Connect** integrates WordPress with the **NMKR** platform so site owners can bring **Cardano** and **Solana** NFTs into their websites. It handles synchronization, local storage, and rendering, so non-technical users can publish NFT content with minimal friction.

---

## Features
- **Multi-chain support**: Cardano and Solana out of the box.  
- **Sync & display**: Pull NFTs via NMKR and render them in WordPress with a responsive UI.  
- **Admin dashboard**: Start/stop syncs, view progress, and inspect live metrics during long runs.  
- **Resilient polling**: Front-end polling with backoff to keep the UI responsive through temporary errors.  
- **Cache-safe AJAX**: Admin-AJAX calls include cache-busting and no-cache headers to avoid stale responses.  
- **Extensible**: Clean structure to add new chains, layouts, and filters over time.

---

## How it works
1. **Configure** NMKR credentials in the plugin settings.  
2. **Synchronize**: A background job iterates through collections/items from NMKR and persists normalized data to WordPress.  
3. **Live updates**: The dashboard polls a progress endpoint; the progress bar and **Active Sync Metrics** update during the run.  
4. **Present**: Use the plugin’s templates/shortcodes/blocks (depending on your theme setup) to display NFTs.

---

## Requirements
- **WordPress 5.8+** (latest stable recommended)  
- **PHP 7.4+** (8.1+ recommended)  
- **NMKR API key** with access to the projects you want to sync

---

## Installation

### From source (with Composer)
If you’re cloning the repository to build the plugin yourself, install PHP dependencies:

```bash
git clone https://github.com/ROCSI-eu/WordPress-NMKR-Connect.git
cd WordPress-NMKR-Connect
composer install --no-dev --prefer-dist
```

This fetches the plugin’s PHP dependencies into `vendor/`.  
> `vendor/` is not committed to the repo. Release ZIPs already include `vendor/`, so Composer is only required when building from source.

Now place the plugin folder under `wp-content/plugins/` (or symlink it), then activate it in **WP Admin → Plugins**.

> Optional: If you have a license key (to enable in-dashboard updates/support), you can enter it later in **NMKR Connect → Settings → License**. The plugin also works without a key—you’ll just update manually.

### From a ZIP
1. In WordPress: **Plugins → Add New → Upload Plugin**  
2. Select the release ZIP (already bundled with `vendor/`) and **Activate**.

## Configuration
1. In **WP Admin**, open **NMKR Connect → Settings**.
2. Paste your **NMKR API key** and configure any sync/scoping options you need (e.g., projects/collections, limits).
3. *(Optional)* Enter your **license key** under **NMKR Connect → Settings → License** to enable in-dashboard updates and support.
4. Click **Save changes**.

> **Tip:** If you use multiple environments (local/staging/production), keep distinct API keys and scopes per site. Treat keys as secrets and restrict admin access.

## Usage
- **Start a sync** from the NMKR Connect dashboard. The UI shows:
  - Current status (initializing, running, finalizing)
  - A progress bar
  - **Active Sync Metrics** (items processed, timing, memory, etc.)
- **Display NFTs** using the plugin’s provided UI components (shortcodes/blocks/templates depending on your theme). Refer to in-plugin hints/tooltips for usage examples.

## Synchronization UX
- **Resilience**: The UI continues polling even if a request hiccups; temporary network/server errors are retried with backoff.
- **Stop safely**: You can request a stop; the job will finalize gracefully and the UI will refresh its “Past” section at completion.
- **Fresh stats**: The dashboard uses cache-busting/no-cache headers to avoid stale progress/metrics.
- **Auto-resume**: Reloading the page during an active sync will re-attach to the current run and continue live updates.

## Troubleshooting

### “The dashboard shows a sync in progress after reinstall”
A stale option/transient may exist. If you’re comfortable with WP-CLI:

```bash
# Inspect likely options/transients (read-only)
wp option list --search=nmkr_sync --field=option_name
wp transient list | grep -E 'nmkr|wp_nmkr'

# (Advanced) Clear specific markers once you’ve reviewed them
# Only delete keys you recognize; back up first.
wp option delete nmkr_sync_last_result
wp option delete nmkr_sync_last_recovery_at
```

### “Progress stays at 0% then jumps to 100%”
- Reload the dashboard to re-attach to the active sync (auto-resume should kick in).
- Ensure **/wp-admin/admin-ajax.php** is **not cached** by your CDN/host (bypass or add a page rule).
- Open DevTools → **Network** and confirm periodic 200 responses for polling endpoints with JSON containing `progress`.
- If behind a proxy/CDN, clear browser and edge caches for admin paths.

### “Active metrics don’t update”
- Verify polling calls aren’t blocked or cached (look for proper `Cache-Control: no-store` and a cache-buster query param).
- Check hosting for output buffering on long-running requests.
- Confirm no JavaScript errors in **Console**; fix any that halt updates.

### “Stop doesn’t stop” (or UI doesn’t refresh after stop)
- Click **Stop** once and wait a few seconds; the job finalizes gracefully before the UI refreshes.
- Check **Network** for the stop request response and subsequent polls.
- Review `wp-content/debug.log` for finalization messages if debugging is enabled.

### Clean-state checklist
1. Deactivate and delete the plugin from **WP Admin → Plugins**.  
2. (Optional) Review and delete known NMKR options/transients with WP-CLI (see commands above).  
3. Reinstall from a release ZIP (includes `vendor/`) or rebuild from source (`composer install --no-dev --prefer-dist`).  
4. Re-enter configuration and run a fresh sync.

## Security & Privacy
- All privileged actions are protected with **capability checks** and **nonces**.
- All input is **sanitized** and all output **escaped** following WordPress best practices.
- API and license keys are stored in WordPress options—treat them as **secrets** and restrict admin access.
- Admin-AJAX endpoints send **no-cache** headers to minimize leakage of sensitive, ephemeral data.
- Optional telemetry (if enabled) is **opt-in** and designed to be minimal and privacy-respecting.

## Development

### Project layout (high level)
- `includes/` — PHP modules (synchronization engine, AJAX handlers, admin pages)
- `js/` — Admin dashboard scripts (polling, progress bar, live metrics)
- `assets/` — Images/styles used by the admin UI
- `templates/` — Optional render templates for front-end display
- `languages/` — Translations (.po/.mo)
- `uninstall.php` — Cleanup routine
- `README.md`, `LICENSE.txt` — Docs and license

### Local setup
1. Clone the repo into your WordPress install:
   ```bash
   git clone https://github.com/ROCSI-eu/WordPress-NMKR-Connect.git
   cd WordPress-NMKR-Connect
   composer install --prefer-dist

2. Place/symlink the folder to `wp-content/plugins/`.
3. Activate in **WP Admin → Plugins**.
4. Configure API credentials in **NMKR Connect → Settings**.

> Release ZIPs already include `vendor/`. Composer is only required when building from source.

### Coding standards & guardrails
- Follow **WordPress PHP coding standards**; keep code **i18n-ready**.
- **Never** commit secrets; strip keys/tokens from logs and examples.
- **Sanitize all input**, **escape all output**; use `$wpdb->prepare()` for SQL.
- All privileged AJAX/actions must have **capability checks** and **nonces**.
- Respect plugin invariants/constants (e.g., transient TTLs, helper wrappers); avoid raw `getmypid()` in live paths.
- Admin JS should be enqueued with `ver=<filemtime>`; all admin-AJAX calls include a cache-buster (e.g., `_=Date.now()`).
- Do **not** edit `vendor/` or third-party code; patch via wrappers/filters.

### Testing
- **Manual**
  - Start a sync and watch **DevTools → Network** for steady polling and backoff (200s with JSON `progress`).
  - Confirm **no-cache** headers and cache-buster query params on admin-AJAX.
  - Stop a sync and verify graceful finalization and UI refresh of the Past panel.
- **Logs**
  - Enable `WP_DEBUG_LOG` and review `wp-content/debug.log` for progress/finalization messages.
- **Clean state (optional)**
  - Deactivate/delete the plugin, then review and (carefully) clear known NMKR options/transients with WP-CLI as needed.
- **Packaging a release**
  ```bash
  composer install --no-dev --prefer-dist
  # zip the plugin folder excluding .git, node_modules (if any), and other dev files

## Contributing

We welcome issues and pull requests — thanks for helping improve NMKR Connect!

### Before you file an issue
- **Search existing issues** to avoid duplicates.
- Include environment details: WordPress + PHP versions, hosting/CDN, browser.
- Add **repro steps**, expected vs. actual behavior, and any **logs/screenshots/Network traces**.

### Branch & commit conventions
- Fork and create a topic branch:
  - `feat/<short-topic>`, `fix/<short-topic>`, `docs/<short-topic>`
- Use clear, focused commits (Conventional Commits encouraged):
  - `feat(sync): add near-completion hint to progress payload`
  - `fix(ui): resume polling after transient 5xx`
  - `docs(readme): clarify Composer vs release ZIP`
- Avoid unrelated refactors in the same PR.

### Pull request checklist
- **Summary & rationale** of the change.
- **Acceptance criteria** and **test plan** (manual steps + expected outcomes).
- **Screenshots/logs** for UI or sync behavior changes.
- Note **risk/rollout** and any **migration/cleanup** steps.
- ✅ Follows WordPress coding standards; code is i18n-ready.  
- ✅ No secrets in diffs; keys/tokens removed from examples/logs.  
- ✅ All privileged AJAX/actions have **cap checks** + **nonces**.  
- ✅ **Sanitize input**, **escape output**; use `$wpdb->prepare()` for SQL.  
- ✅ Admin JS enqueued with `ver=<filemtime>`; admin-AJAX calls include cache-buster (`_=`).  
- ✅ **Do not** edit `vendor/` or third-party code — patch via wrappers/hooks/filters.

### Local dev quickstart
```bash
git clone https://github.com/ROCSI-eu/WordPress-NMKR-Connect.git
cd WordPress-NMKR-Connect
composer install --prefer-dist
# symlink or copy to wp-content/plugins/
Activate in WP Admin → Plugins, configure NMKR Connect → Settings, and enable WP_DEBUG_LOG during testing.
```
Tip: For larger or risky changes, open a draft PR early to discuss approach and reduce rework.

## License
Released under the **MIT License**. See [`LICENSE.txt`](LICENSE.txt) for details.  
© ROCSI.eu (Romanian Cyber Space Initiative).

## Acknowledgments
- Built by **Mihai Bărbulescu / ROCSI.eu (Romanian Cyber Space Initiative)** to advance open Web3 tooling.
- Supported by **Cardano Project Catalyst (Fund 13)** — see the project page on the Catalyst Milestones site:  
  https://milestones.projectcatalyst.io/projects/1300195
- Thanks to the **NMKR** ecosystem and community contributors for feedback and testing.
- Appreciation to early adopters for invaluable bug reports and UX suggestions that shaped the sync flow.

## Licensing & Updates (open-source + commercial support)
This project is released under **MIT**. You can use and modify the code freely.

To help fund ongoing development, we provide optional **license keys** that enable:
- In-dashboard **update delivery** for new releases
- Access to **support** and (if offered) **pro/early** features
- Access to a wide range of **NFT collection display shortcodes/blocks** for front-end use in the **Gutenberg** block editor and as **Elementor** widgets/elements
- Optional, privacy-respecting **telemetry** to improve stability (opt-in only)

**Using the plugin without a key**  
The plugin works without a license key; you’ll just manage updates manually (e.g., by installing release ZIPs).

**Where to enter a license key**  
Go to **WP Admin → NMKR Connect → Settings → License** and follow the activation prompts. You can deactivate/reactivate as needed.

**Building from source**  
When building from source, run `composer install --no-dev --prefer-dist` to install the licensing client into `vendor/` so your packaged ZIP includes all dependencies.

**Advanced/CI**  
For automated deployments, you can predefine environment variables or `wp-config.php` constants to streamline activation per environment (e.g., staging vs. production).