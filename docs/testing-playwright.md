# Playwright testing guide

This is the authoritative operational overview of the repository's Playwright suite. The numbered phase documents remain implementation records and contain the detailed safety rationale for the coverage introduced at each stage.

## Purpose and architecture

The suite checks the NMKR Connect WordPress admin UI in Chromium. It is a focused regression suite, not comprehensive product coverage: it does not exercise a real NMKR synchronization, frontend shortcode rendering, every project/token state, or every WordPress and license configuration.

There are three distinct ways maintainers encounter these checks:

1. **Public-safe discovery**: public CI installs dependencies and runs `npm run test:public`. Its Playwright portion invokes the suite with `--list`; Playwright discovers configuration, projects, specifications, and tests, but does not launch a browser, authenticate, or contact WordPress.
2. **Playwright-only execution**: `npm run test:e2e` (or a targeted script below) launches Chromium against a prepared **private WordPress test environment**. This authenticates and executes browser assertions, but it does not run the Phase 2 readiness or WP-CLI checks.
3. **Phase 2 orchestration**: `npm run test:phase2` runs deployment when configured, dependency/browser preparation, the WordPress readiness gate, the complete Playwright suite, WP-CLI smoke checks, and database-state checks. This is private-environment orchestration, not merely a Playwright command.

See [Phase 1](testing-phase-1.md), [Phase 2](testing-phase-2.md), and [Phase 3](testing-phase-3.md) for historical and detailed implementation notes. [Phase 4](testing-phase-4.md) records the public CI boundary.

## Setup

From a user-owned checkout, install the locked Node dependencies and Chromium:

```bash
npm ci
npx playwright install chromium
```

For private Playwright-only work, copy `.env.tests.example` to the ignored `.env.tests` file and supply environment-specific values there, or export the variables without writing them to the checkout:

```bash
cp .env.tests.example .env.tests
$EDITOR .env.tests
```

The required browser-execution values are `WP_BASE_URL`, `WP_ADMIN_USER`, and `WP_ADMIN_PASSWORD`. Phase 2 additionally requires `WP_PATH`. Keep `RUN_REAL_SYNC=false` and `PW_SAVE_ARTIFACTS=false` for ordinary runs. Optional page-path variables in the example file allow a private environment to override repository defaults.

An explicit `NMKR_PHASE2_ENV_FILE` is loaded by the Playwright configuration when present. Use it for Phase 2 as documented in [the Phase 2 guide](testing-phase-2.md); do not place a populated environment file in the repository or WordPress root.

## Current commands

The following are the exact Playwright scripts exposed by `package.json`:

| Command | Scope |
| --- | --- |
| `npm run test:e2e` | Current default suite: every implemented Playwright spec, plus authentication setup and cleanup projects. |
| `npm run test:e2e:settings` | `nmkr-settings.regression.spec.ts` |
| `npm run test:e2e:dashboard` | `nmkr-dashboard.regression.spec.ts` |
| `npm run test:e2e:projects` | `nmkr-projects.regression.spec.ts` |
| `npm run test:e2e:shortcodes` | `nmkr-shortcodes.regression.spec.ts` |
| `npm run test:e2e:analytics` | `nmkr-analytics.regression.spec.ts` |
| `npm run test:e2e:sync-state` | `nmkr-sync-state.regression.spec.ts` |
| `npm run test:e2e:sync-resilience` | `nmkr-sync-resilience.regression.spec.ts` |
| `npm run test:e2e:sync-final-state` | `nmkr-sync-final-state.regression.spec.ts` |
| `npm run test:e2e:sync-run-authority` | `nmkr-sync-run-authority.regression.spec.ts` |
| `npm run test:e2e:report` | Open an already-generated local HTML report; it does not run tests. |

Append Playwright CLI options after `--`. For example, public-safe discovery of the default or a targeted spec is:

```bash
npm run test:e2e -- --list --reporter=list
npm run test:e2e:settings -- --list --reporter=list
```

These commands only discover tests. Removing `--list` performs browser execution and therefore requires the private WordPress test environment.

## Implemented coverage

| Area | Specification | What it currently checks | Interaction model |
| --- | --- | --- | --- |
| Admin smoke | `nmkr-admin.smoke.spec.ts` | Login, active plugin row, dashboard/settings navigation, and basic safe controls. | Read-only navigation and assertions. |
| Settings | `nmkr-settings.regression.spec.ts` | Settings form sections, control types, profile options, and save/reset control presence. | Structural/read-only; it does not submit settings. |
| Dashboard structure | `nmkr-dashboard.regression.spec.ts` | Dashboard panels, controls, nonces by presence/type, progress UI structure, statistics structure, and optional log-panel structure. | Structural/read-only with locally fulfilled page-load API-status AJAX and guards for unexpected actions. |
| Projects | `nmkr-projects.regression.spec.ts` | Default no-project-selected selector state and surrounding page structure. | Structural/read-only with locally fulfilled guards for unexpected actions. |
| Shortcodes | `nmkr-shortcodes.regression.spec.ts` | Shortcode reference page structure and implemented shortcode headings. | Structural/read-only with locally fulfilled guards for unexpected actions. |
| Analytics | `nmkr-analytics.regression.spec.ts` | Analytics shell and runtime control/container presence when that UI is enabled. | Structural/read-only; expected page-load analytics AJAX is locally fulfilled with empty data and guarded actions are intercepted. |
| Synchronization state | `nmkr-sync-state.regression.spec.ts` | Dashboard transitions for controlled active and completed synchronization responses. | Locally stubbed AJAX lifecycle simulation. |
| Synchronization resilience | `nmkr-sync-resilience.regression.spec.ts` | Dashboard polling UI reactions to controlled retriable and security-error responses. | Locally stubbed AJAX lifecycle simulation. |
| Synchronization final states | `nmkr-sync-final-state.regression.spec.ts` | Dashboard UI reactions to controlled stopped, incomplete-marker, and payload-error final-state sequences. | Locally stubbed AJAX lifecycle simulation. |
| Run authority | `nmkr-sync-run-authority.regression.spec.ts` | Run-scoped dashboard control authority across controlled response sequences. | Locally stubbed AJAX lifecycle simulation. |

Locally fulfilled AJAX responses are browser-route fixtures. They exercise client-side behavior using controlled responses and prevent the intercepted request from reaching WordPress. They **do not execute a real NMKR synchronization**, prove the server-side synchronization path, or validate live NMKR API results. Some unrelated requests may be allowed to continue; the private test site remains a required boundary.

## Authentication and private-environment boundary

The default project order is `auth-setup`, `chromium`, then `auth-cleanup`. Setup signs in through the WordPress login form, validates the admin shell, and writes Playwright storage state to a wrapper-approved owner-only location. `scripts/nmkr-playwright.js` creates a fresh randomly named authentication directory for each invocation and normally removes it at process exit; the cleanup project also removes the state file. `NMKR_RETAIN_AUTH_STATE=true` is for exceptional private diagnostics only.

Authentication state is sensitive session material. Browser execution must stay on a private WordPress test environment with test credentials and an approved target URL. Never use public CI for login or add credentials, private URLs, environment contents, cookies, nonces, or authentication state to command output, issues, pull requests, or committed files.

## Output and data-safety rules

- Screenshots, traces, and videos are off by default. `PW_SAVE_ARTIFACTS=true` retains failure artifacts; enable it only for necessary private diagnostics and review every file locally.
- The HTML reporter is configured even when rich artifacts are off. Treat `playwright-report/` and any custom report directory as private run output, not as an ordinary shareable artifact.
- Keep `test-results/`, HTML reports, screenshots, traces, videos, logs, and authentication state out of Git and public uploads. Phase 2 redirects these paths beneath its external private run directory.
- Keep `.env.tests` and every populated environment file private and uncommitted. Do not print or copy their contents into diagnostics.
- Use generic placeholders in documentation and shared commands. Do not publish credentials, test-site or service URLs, nonces, cookies, API keys, deployment commands, or filesystem details that identify a private environment.
- Assertions and diagnostics must avoid exposing customer project/token data, analytics data, secrets, or raw log content. A passing structural assertion does not make its surrounding report safe to publish.

## Troubleshooting: start with non-mutating checks

1. Confirm the checkout and dependencies without contacting WordPress: `git status --short`, `npm ci`, then `npm run test:e2e -- --list --reporter=list`.
2. Confirm Chromium is installed with a discovery command first; install it with `npx playwright install chromium` if the browser executable is absent.
3. Check that required variable names are populated without printing their values. Confirm the target is the intended private WordPress test environment before browser execution.
4. Verify the configured WordPress and NMKR admin paths by read-only navigation. Do not begin by saving settings, clearing logs, selecting customer data, or starting synchronization.
5. Run one targeted Playwright spec to isolate a UI failure. Keep `PW_SAVE_ARTIFACTS=false` initially.
6. For Phase 2 failures, use its summary to identify the failed stage and inspect the referenced private log locally. Do not paste the log or report into a public channel.
7. Enable private failure artifacts only when the preceding non-mutating checks are insufficient; remove them after diagnosis.
