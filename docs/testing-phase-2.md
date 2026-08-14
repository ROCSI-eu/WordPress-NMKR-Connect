# Phase 2 private VM test runner

The [validation policy](validation-policy.md) determines when to use full or targeted private validation. Phase 2 orchestrates validation of a deployed NMKR Connect installation from a private, user-owned checkout. For Playwright-only guidance and the current coverage map, see [`testing-playwright.md`](testing-playwright.md).

The runner combines optional deployment, dependency/browser preparation, WordPress readiness, the complete Playwright suite, WP-CLI smoke checks, and WP-CLI database-state checks. WP-CLI smoke reports recent third-party `vendor/` PHP warnings/notices generically without blocking; fatal/parse errors, NMKR errors, first-party warnings/notices, and unclassifiable warnings/notices remain blocking. It does not change the implementations of those checks, and real NMKR synchronization remains outside this workflow.

## Private inputs and external state

Provide `WP_BASE_URL`, `WP_ADMIN_USER`, `WP_ADMIN_PASSWORD`, and `WP_PATH` through an owner-private environment file outside both the repository and WordPress root. Use `NMKR_PHASE2_ENV_FILE` to select it explicitly:

```bash
NMKR_PHASE2_ENV_FILE=/path/to/private/phase2.env npm run test:phase2
```

Never commit or print the populated file. The checkout-local `.env.tests` fallback is supported, but an external file is preferred because it establishes a clearer private-input boundary.

Private output defaults to `${XDG_STATE_HOME}/nmkr-connect`, or `${HOME}/.local/state/nmkr-connect` when `XDG_STATE_HOME` is unset. `NMKR_PHASE2_LOG_DIR` may select another owner-private external state directory. The runner rejects a private root located under the repository, the WordPress root, or the repository's Playwright output trees; do not configure private run output anywhere beneath the checkout or deployed web root.

The root and its `runs` directory must be real, owner-controlled directories rather than symlinks, and must not be group- or world-writable. The runner creates them with restrictive permissions where needed. Each invocation creates:

```text
<external-state-root>/
└── runs/
    └── <YYYYmmddTHHMMSSZ>-<pid>/
        ├── preflight.log
        ├── step-specific private logs
        ├── playwright-report/
        └── test-results/
```

The run directory also serves as the private parent for a fresh wrapper-owned authentication-state directory. File presence varies with the stages executed and their outcome.

## General profile

The default profile accepts these preparation controls:

- `NMKR_PHASE2_SKIP_DEPLOY=false` by default. When it is `false`, `NMKR_DEPLOY_COMMAND` is required and executed without printing the command value; set it to `true` to skip deployment.
- `NMKR_PHASE2_INSTALL_DEPS=auto`, which runs `npm ci` only when `node_modules` is absent. `true` always installs and `false` skips installation.
- `NMKR_PHASE2_INSTALL_BROWSER=false`; set it to `true` to run `npx playwright install chromium`.
- `RUN_REAL_SYNC=false` and `PW_SAVE_ARTIFACTS=false`.
- Readiness defaults of 120 seconds overall, 5 seconds between attempts, and 10 seconds per HTTP request; the corresponding `NMKR_PHASE2_WP_READY_*` values must be positive integers.

Run dependency installation from a writable, user-owned checkout, not the deployed webserver-owned plugin directory. The runner requires its command-line prerequisites, and `WP_CLI_BIN` must resolve when specified as a simple command name.

Stages run in this order:

1. Deployment, unless skipped.
2. Dependency installation, when selected or needed.
3. Chromium installation, when selected.
4. WordPress readiness.
5. The complete Playwright suite.
6. WP-CLI smoke checks.
7. WP-CLI database-state checks.

The readiness gate checks `WP_PATH/.maintenance` without removing it and requests the private site's login page until it receives an HTTP 200 response containing the login-form marker and no recognized maintenance response. It does not retry the whole Playwright suite.

## `existing-readonly` profile

Use `existing-readonly` to validate an already deployed exact commit without runner-driven deployment or installation:

```bash
NMKR_PHASE2_ENV_FILE=/path/to/private/phase2.env \
NMKR_PHASE2_EXPECTED_SOURCE_SHA=<40-character-reviewed-sha> \
npm run test:phase2:existing-readonly
```

The profile requires a maintainer-supplied full 40-character SHA. It verifies that checkout `HEAD` equals that exact SHA and that the source worktree is clean. It forces deployment, dependency installation, Chromium installation, real synchronization, and artifact retention off, and discards any deploy command. A caller-selected profile cannot be replaced by a conflicting value from the environment file.

Because installation is prohibited in this profile, the Node dependencies and a launchable Playwright Chromium must already be present; preflight checks both before browser execution. Prepare them before switching to the exact clean commit if necessary.

Source integrity is reported as `PASS` only after the exact-SHA and clean-worktree checks pass. If `NMKR_DEPLOYED_PLUGIN_PATH` is provided, the runner also requires that path to be a Git worktree at the same exact SHA with a clean worktree and reports deployed integrity as `PASS`. If no deployed path is provided, deployed integrity remains `SKIPPED`; the runner does not infer equivalence.

`existing-readonly` prevents runner-driven deployment and package/browser installation. The Playwright suite still authenticates and executes browser behavior, and the orchestration still runs readiness and WP-CLI/database-state checks, so use only a private test environment prepared for those checks.

## `targeted-readonly` profile

Use this profile for an ordinary exact-head runtime change that needs one affected browser suite, readiness, and WP-CLI smoke without full Playwright or DB-state validation:

```bash
NMKR_PHASE2_ENV_FILE=/path/to/private/phase2.env \
NMKR_PHASE2_EXPECTED_SOURCE_SHA=<40-character-reviewed-sha> \
NMKR_DEPLOYED_PLUGIN_PATH=/path/to/deployed/plugin-worktree \
NMKR_PHASE2_TARGET_SUITE=settings \
npm run test:phase2:targeted-readonly
```

The internal allowlist is `settings`, `dashboard`, `projects`, `shortcodes`, `analytics`, `ajax-security`, `sync-state`, `sync-run-authority`, `sync-resilience`, and `sync-final-state`. Unknown values fail closed; values are mapped to existing `test:e2e:*` package scripts and are never executed as caller-provided commands. Caller-selected profile and suite values cannot be replaced by the private environment file.

This profile requires `NMKR_DEPLOYED_PLUGIN_PATH` and enforces the same exact source SHA, clean source, exact/clean deployed worktree, no-install, no-deploy, no-real-sync, and no-artifact rules as `existing-readonly`. It runs readiness, only the selected Playwright suite, and WP-CLI smoke; DB-state is intentionally skipped. Both readonly profiles perform one final source/deployed SHA and cleanliness recheck. Set `NMKR_PHASE2_RUNTIME_INTEGRITY=true` to run the existing Composer/vendor integrity gate once.

## Authentication and output handling

Phase 2 redirects `PLAYWRIGHT_HTML_REPORT` and `PLAYWRIGHT_TEST_OUTPUT_DIR` into the private run directory and supplies that directory as `NMKR_AUTH_STATE_ROOT`. The Playwright wrapper creates a unique owner-only child directory and normally removes it after the run. The authentication cleanup project removes the state file as well. The runner records authentication-state cleanup as `MANAGED_BY_WRAPPER`; `NMKR_RETAIN_AUTH_STATE=true` changes the summary to `RETAINED` and is appropriate only for exceptional private diagnostics.

Treat authentication state, HTML reports, screenshots, traces, videos, logs, response material, nonces, credentials, URLs, and environment files as private. Screenshots, traces, and videos remain off unless explicitly enabled outside the readonly profile. Do not upload run directories or paste their contents into public issues or pull requests.

## Success summary

A successful general run prints these current fields (individual status values depend on the selected options):

```text
Phase 2 summary
  deploy: PASS
  dependencies: SKIPPED
  browser: SKIPPED
  wordpress-ready: PASS
  playwright: PASS
  wpcli: PASS
  db-state: PASS
  targeted-suite: full
  runtime-integrity: SKIPPED
  final-integrity: SKIPPED
  profile: general
  source-integrity: SKIPPED
  deployed-integrity: SKIPPED
  readonly-policy: SKIPPED
  authentication-state-cleanup: MANAGED_BY_WRAPPER
  result: PASS
  commit: abc1234
```

The console does **not** print the private run-directory path. On failure it adds `failed step` and `private diagnostics: available locally`; inspect the appropriate step log directly inside the external state directory. Begin with non-mutating checks and do not copy private diagnostics into a public channel.

## Safety boundary

Keep `RUN_REAL_SYNC=false`. Neither the Playwright route simulations nor Phase 2 orchestration executes a real NMKR synchronization. Do not commit `.env.tests`, populated environment files, private state directories, reports, test output, authentication state, screenshots, traces, videos, or logs.

## Unified pre/post-merge operator workflow

Private operators can select the complete exact-head workflow without rebuilding an environment-variable prefix:

```bash
npm run test:phase2 -- \
  --stage pre-merge \
  --class ordinary-runtime \
  --profile targeted-readonly \
  --reviewed-sha <reviewed-40-character-sha> \
  --deployed-sha <deployed-40-character-sha> \
  --target-suite <allowlisted-suite> \
  --deploy-mode skip \
  --runtime-integrity false
```

All eight options are required in operator mode. Unknown, duplicate, missing, malformed, or conflicting selections fail closed. The arguments are captured before the owner-private environment file is sourced; that file cannot replace or clear them. Operator mode always forces `RUN_REAL_SYNC=false`, `PW_SAVE_ARTIFACTS=false`, `NMKR_PHASE2_INSTALL_DEPS=false`, and `NMKR_PHASE2_INSTALL_BROWSER=false`.

Use `targeted-readonly` with exactly one existing allowlisted package suite, or `existing-readonly` for full Playwright plus WP-CLI smoke and DB-state. `docs-metadata` is rejected before private inputs are used and belongs in public CI. `test-tooling` is appropriate only for an explicit private run when tooling changes private orchestration or runtime semantics. `ordinary-runtime` normally uses targeted readonly; selecting the full profile is an explicit escalation. `high-risk` requires the full profile. Privileged AJAX authorization validation remains separate under `npm run test:ajax-security`.

For pre-merge validation, the reviewed and deployed SHAs must be equal, and both source and deployed worktrees must be clean at that exact commit. For post-merge validation, source and deployed HEAD must equal the merged/deployed SHA, while Git tree objects for the reviewed and merged commits must be identical. The runner resolves already-local commits only; it does not fetch, switch, or alter branches. Failure to resolve either commit or prove tree equality requires deeper review and validation.

`--deploy-mode run` checks the clean source first, executes the private `NMKR_DEPLOY_COMMAND` exactly once with output captured privately, and then requires exact deployed identity, cleanliness, and active-plugin binding before runtime checks. `--deploy-mode skip` never executes that command and applies the same checks to the existing deployment. The runner does not clone, fetch, check out, copy files, install Composer dependencies, or roll back. If deployment succeeds and a later check fails, the summary reports deployment status and `rollback: NOT_ATTEMPTED`; the validated private rollback procedure remains the operator's responsibility.

A post-merge invocation changes the stage and identities, for example:

```bash
npm run test:phase2 -- \
  --stage post-merge \
  --class high-risk \
  --profile existing-readonly \
  --reviewed-sha <reviewed-40-character-sha> \
  --deployed-sha <merged-main-40-character-sha> \
  --target-suite <allowlisted-suite> \
  --deploy-mode run \
  --runtime-integrity true
```

The existing environment-only `test:phase2`, `test:phase2:targeted-readonly`, and `test:phase2:existing-readonly` entry points remain supported with their existing semantics.
