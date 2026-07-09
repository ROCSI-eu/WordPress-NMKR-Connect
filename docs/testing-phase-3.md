# Phase 3 Playwright regression coverage

Phase 3 adds safe Playwright regression checks for the NMKR Connect WordPress admin pages. These tests verify page structure and important controls without mutating WordPress options, starting synchronization, clearing logs, or exposing private configuration.

## Settings-page regression

The settings regression is implemented in `tests/e2e/nmkr-settings.regression.spec.ts`. It logs in with the existing WordPress admin test environment variables, opens `NMKR_SETTINGS_PATH` (defaulting to `/wp-admin/options-general.php?page=nmkr-connect-settings`), and verifies that the WordPress admin shell, NMKR settings page, settings form, sections, and key controls are present.

The test confirms broad coverage for:

- API Settings
- Synchronization Settings
- Debug Settings
- Analytics & Privacy
- Save Settings and Reset to Defaults controls

### Settings safety guarantees

The settings regression is intentionally non-mutating:

- It does not click **Save Settings**.
- It does not click **Reset to Defaults**.
- It does not click the API key visibility toggle.
- It does not change the synchronization profile.
- It does not fill, clear, check, uncheck, or otherwise change settings controls.
- It does not run real NMKR sync.
- It does not read, print, snapshot, or assert raw API key values.
- It does not read, print, snapshot, or assert raw GA4 API secret values.

The test uses presence and basic attribute assertions for secret fields, such as checking that API key and GA4 API secret inputs exist and remain `type="password"`.

## Dashboard-page regression

The dashboard regression is implemented in `tests/e2e/nmkr-dashboard.regression.spec.ts`. It logs in with the same shared WordPress admin helpers, opens `NMKR_DASHBOARD_PATH` (defaulting to `/wp-admin/admin.php?page=nmkr-connect-dashboard`), and verifies that the WordPress admin shell, NMKR dashboard shell, dashboard panels, status/progress structure, statistics structure, and key controls are present.

The test confirms broad coverage for:

- NMKR Connect Dashboard shell
- API Connection Status panel
- Data Synchronization panel
- Hidden nonce input presence and type
- Sync progress and active metric containers
- Previous Synchronization Statistics panel
- Optional Debug Logs panel structure when dashboard logging is enabled

### Dashboard safety guarantees

The dashboard regression stubs the dashboard page-load `nmkr_check_api_status` AJAX request before navigation so the inline dashboard script does not touch API connectivity logic, sync/recovery state, or log-writing paths in shared validation environments. It also records and fails the test on unexpected dashboard AJAX actions that would store active metrics, read sync statistics, clear logs, or start/stop sync while fulfilling those requests locally so WordPress is not touched.

The dashboard regression is intentionally non-mutating:

- It does not click **Start Synchronization**.
- It does not click **Stop Synchronization**.
- It does not click **Refresh Status**.
- It does not click **Clear Logs**.
- It does not click, fill, or change debug log search/filter controls.
- It treats the Debug Logs panel as optional because it depends on `nmkr_connect_options['log_to_dashboard']`.
- It does not require optional Debug Logs controls when dashboard logging is disabled.
- It does not read, print, snapshot, or assert nonce values.
- It does not inspect debug log entries, messages, payloads, timestamps, counts, or log text.
- It does not assert exact sync metric or performance statistic values, which may vary by environment.

The test uses attachment, role, attribute, and element type assertions for controls that may be hidden by default or may not include explicit `type="button"` attributes.

## Projects-page regression

The projects regression is implemented in `tests/e2e/nmkr-projects.regression.spec.ts`. It logs in with the shared WordPress admin helpers, opens `NMKR_PROJECTS_PATH` (defaulting to `/wp-admin/admin.php?page=nmkr-connect-projects`), and verifies the default **no project selected** state for the NMKR Projects and Tokens admin page.

The test confirms focused coverage for:

- NMKR Projects and Tokens admin shell
- Your Projects panel heading
- Informational box presence
- Project selector form structure
- Project selector `method="post"` form attribute
- `select#project_uid[name="project_uid"]` presence
- Project selector `onchange="this.form.submit()"` attribute
- Exactly one empty placeholder option with `-- Select a Project --` text
- Empty selected value remaining selected by default

### Projects safety guarantees

The projects regression installs an `admin-ajax.php` route guard before navigating to the Projects page. The guard records and locally fulfills unexpected sync or mutation-oriented actions so they do not reach WordPress, then fails the test if any of those actions were attempted during page load. Guarded actions include sync start/stop, active metric storage, sync statistics reads, log clearing, and sync progress polling.

The projects regression is intentionally non-mutating:

- It checks only the default no-project-selected state.
- It does not select a project.
- It does not submit the project selector form.
- It does not fill, change, or submit token search or filter controls.
- It does not run real NMKR sync.
- It does not inspect project descriptions, synchronized project option names, token names, token metadata, image URLs, payment gateway links, or project URLs.
- It does not count synchronized project options.
- It does not click token images, Buy Now links, project website links, external links, or lightbox interactions.
- It does not require selected-project or token-grid controls in this first default-state regression.

## Shortcodes-page regression

The shortcodes regression is implemented in `tests/e2e/nmkr-shortcodes.regression.spec.ts`. It logs in with the shared WordPress admin helpers, opens `NMKR_SHORTCODES_PATH` (defaulting to `/wp-admin/admin.php?page=nmkr-connect-shortcodes`), and verifies the NMKR Connect Shortcodes admin page structure and shortcode reference headings.

The test confirms focused structural coverage for:

- NMKR Connect Shortcodes admin shell
- Main `NMKR Connect - Shortcodes` page heading
- `Available Shortcodes` panel heading
- Informational box presence
- Stable shortcode reference headings for `[nmkr-grid]`, `[nmkr-token-list]`, `[nmkr-carousel]`, `[nmkr-token]`, and `[nmkr-project]`

### Shortcodes safety guarantees

The shortcodes regression installs an `admin-ajax.php` route guard before navigating to the Shortcodes page. The guard records and locally fulfills unexpected sync or mutation-oriented actions so they do not reach WordPress, then fails the test if any guarded action was attempted during page load. Guarded actions include sync start/stop, active metric storage, sync statistics reads, log clearing, and sync progress polling.

The shortcodes regression is intentionally non-mutating:

- It performs structural-only shortcode reference checks.
- It does not click Freemius upgrade links, internal links, or external links.
- It does not inspect or assert upgrade URLs.
- It does not navigate to external URLs.
- It does not render shortcodes on the frontend.
- It does not create posts or pages.
- It does not save options or submit forms.
- It does not run real NMKR sync.
- It does not inspect customer project or token data.
- It avoids plan-specific premium/free upsell copy assertions because rendered copy may differ by license state.

## Running Phase 3 locally

The Phase 3 regressions are included in the default Playwright suite:

```bash
npm run test:e2e
```

They can also be listed or run directly with:

```bash
npm run test:e2e:settings -- --list
npm run test:e2e:settings
npm run test:e2e:dashboard -- --list
npm run test:e2e:dashboard
npm run test:e2e:projects -- --list
npm run test:e2e:projects
npm run test:e2e:shortcodes -- --list
npm run test:e2e:shortcodes
```

Direct targeted Playwright scripts do not automatically load `NMKR_PHASE2_ENV_FILE`. For direct targeted runs on the VM, source the private environment first, then run the targeted script:

```bash
set -a
source "$HOME/.config/nmkr-connect/phase2.env"
set +a

npm run test:e2e:shortcodes
```

Because the Phase 2 runner executes `npm run test:e2e`, the Phase 3 regressions are automatically included in Phase 2 VM validation. Full Phase 2 validation should continue to use:

```bash
NMKR_PHASE2_ENV_FILE="$HOME/.config/nmkr-connect/phase2.env" \
NMKR_PHASE2_LOG_DIR="$HOME/.local/state/nmkr-connect-phase2" \
npm run test:phase2
```

## Artifacts and public safety

Playwright screenshots, traces, and videos remain disabled by default. They are only enabled when `PW_SAVE_ARTIFACTS=true`, and Phase 2 stores reports and test output in the private run directory.

Do not commit or publish secrets, API keys, WordPress admin passwords, Basic Auth credentials, private token URLs, private screenshots, traces, videos, `.env.tests`, private environment files, VM output, Playwright reports, or sensitive logs.
