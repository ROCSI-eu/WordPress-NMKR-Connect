# NMKR Connect troubleshooting guide

Start with observation, not deletion. Record the exact plugin commit/release when known, WordPress/PHP versions, affected page or shortcode, synchronization status, and sanitized error text. Never casually delete options, transients, history/metrics rows, owner state, tables, or plugin files.

For **any advanced recovery**, first confirm the exact environment, make and verify a backup, confirm synchronization is idle or terminal, write an explicit change and rollback plan, and define final-state verification. If any prerequisite is missing, escalate instead of changing state.

## 1. Plugin activation failure

- **Symptom:** activation reports a PHP/fatal error, or the plugin page fails immediately.
- **Likely causes:** unsupported PHP/WordPress version, incomplete package, dependency conflict, or database/schema permission failure.
- **Safe checks:** compare requirements in the [user guide](user-guide.md), confirm the package check below, and review sanitized WordPress error text.
- **Corrective action:** test on staging and retry activation only after correcting the identified prerequisite.
- **Escalation information:** exact source commit, versions, package origin/build method, missing relative path, and sanitized error.
- **Actions to avoid:** do not download individual vendor files, edit bootstrap requires, suppress fatal errors, or expose filesystem paths/configuration publicly.

## 2. Missing `vendor/autoload.php` or incomplete package

- **Symptom:** activation names a missing Composer/Freemius file, or the source archive activates with a fatal include error.
- **Likely causes:** a Git source archive was installed without Composer, `vendor/` was excluded from a custom ZIP, or the upload was damaged.
- **Safe checks:** inspect the package locally and confirm `vendor/autoload.php` and `vendor/freemius/wordpress-sdk/start.php` exist without publishing a full filesystem listing.
- **Corrective action:** obtain a complete trusted release, or rebuild from the exact source with `composer install --no-dev --prefer-dist`, package the resulting `vendor/`, and test on staging.
- **Escalation information:** exact source commit, build command, package source, and the missing relative path only.
- **Actions to avoid:** do not assemble vendor piecemeal, copy dependencies from an unrelated release, or publish private paths/environment files.

## 2. Settings access or save failure

- **Symptom:** **Settings → NMKR Connect** is absent/denied or values do not save.
- **Likely causes:** missing `nmkr_manage_settings`, expired session/settings nonce, security middleware, invalid values being clamped/rejected, or a persistence failure.
- **Safe checks:** confirm the assigned role/capabilities; reload and sign in again; inspect the browser network response; compare allowed ranges in the [user guide](user-guide.md).
- **Corrective action:** have an Administrator assign the least-privilege **NMKR Admin** role/capability, retry with a fresh page, and correct invalid input. Investigate proxy/security rules on staging if the request is blocked.
- **Escalation information:** affected field (never its secret value), role, response status, and sanitized message.
- **Actions to avoid:** do not share a nonce/API secret, bypass capability checks, grant full Administrator merely as a workaround, or edit options directly.

## 3. NMKR API configuration missing, rejected, or unavailable

- **Symptom:** Dashboard says no API key/disconnected, requests are rejected, or synchronization cannot retrieve projects.
- **Likely causes:** empty/revoked/wrong key, account access limits, temporary NMKR/network failure, DNS/TLS/firewall issue, or rate limiting.
- **Safe checks:** confirm only that the password field is populated; use the Dashboard connection status; check sanitized HTTP status/error class and whether other outbound WordPress requests work.
- **Corrective action:** enter a current authorized key through HTTPS; correct outbound connectivity with the host; wait/back off for temporary upstream/rate failures, then retry only when no run is active.
- **Escalation information:** time window, sanitized status/error, whether all or one operation fails, and terminal state.
- **Actions to avoid:** never paste the key into a URL, CLI history, issue, screenshot, log, or connectivity tester.

## 4. Synchronization cannot start

- **Symptom:** Start is denied, says a run is active, or returns an AJAX error.
- **Likely causes:** no `nmkr_manage_sync`, missing/invalid nonce, missing API setup, an existing run owner, stale state under guarded recovery, blocked `admin-ajax.php`, or persistence failure.
- **Safe checks:** inspect Dashboard status/history and active run indicators; confirm role; reload for a fresh nonce; inspect the AJAX status and sanitized body; confirm no maintenance/outage is underway.
- **Corrective action:** let an active run finish; correct API/access/AJAX issues; use only the UI's guarded recovery when offered and prerequisites above are met.
- **Escalation information:** start time, active/idle indication, response status, run terminal state, and sanitized error code.
- **Actions to avoid:** do not clear owner/status options, trigger parallel requests, or directly invoke worker endpoints.

## 5. Synchronization appears stalled or polling/network errors occur

- **Symptom:** progress changes slowly/stops, “recoverable” polling errors appear, or the browser increases time between checks.
- **Likely causes:** ongoing paged traversal/detail work, provisional totals, browser/network interruption, adaptive polling backoff, upstream throttling, or a worker/persistence failure.
- **Safe checks:** note status/current item/last visible change; check `admin-ajax.php` requests and browser console; keep in mind progress is provisional and below 100% until finalization; check whether history reaches a terminal state.
- **Corrective action:** keep one Dashboard session, restore connectivity, allow configured backoff/recovery, and wait for a terminal result. If the error limit is reached, preserve the failure text and resolve its cause before a new run.
- **Escalation information:** approximate duration, polling HTTP status pattern, last progress/status, whether Stop was requested, and terminal result.
- **Actions to avoid:** do not refresh/start repeatedly, assume a provisional percentage is a total, terminate PHP/database processes, or clear state.

## 6. Cooperative Stop and stopped final state

- **Symptom:** Stop does not look instantaneous, or the result is **stopped** rather than **completed**.
- **Likely causes:** Stop is run-scoped and cooperative; the worker must reach a checkpoint and finalize counters/state.
- **Safe checks:** submit Stop once, observe `stop_requested`/stopping behavior, and wait for the stopped terminal result and inactive controls.
- **Corrective action:** allow finalization. Retry later only after the stopped state and cleanup/final-state checks are complete and the original cause is understood.
- **Escalation information:** whether Stop was acknowledged, time to terminal state, final counters/status, and cleanup verification.
- **Actions to avoid:** do not start a successor run while stopping, repeatedly submit Stop, or manually rewrite history/owner state.

## Recoverable polling/network errors

- **Symptom:** one or more progress polls fail and the delay increases, but the run has not reported a terminal failure.
- **Likely causes:** short browser/network interruption, temporary `admin-ajax.php` or upstream unavailability, or adaptive polling backoff.
- **Safe checks:** record sanitized HTTP status classes and confirm whether a later poll succeeds; distinguish browser polling from the server worker's terminal state.
- **Corrective action:** restore connectivity and allow automatic retry within **Maximum Error Count**; if it exhausts the limit, preserve the message and resolve the network cause before reloading status.
- **Escalation information:** browser/version, status sequence, configured interval/error-limit values, and eventual terminal state.
- **Actions to avoid:** do not interpret one failed poll as permission to clear the run or start another, and do not publish request cookies/nonces.

## 7. Pagination safety failure

- **Symptom:** synchronization fails with malformed page/record, repeated page, no UID progress, conflicting ownership, or page-limit/safety wording.
- **Likely causes:** unexpected upstream payload, identical non-empty responses, duplicate-only continuation, inconsistent token/project association, or excessive traversal.
- **Safe checks:** record only the sanitized error class, project position (not a real UID), page number if safely displayed, and terminal state; determine whether the failure repeats without publishing payloads.
- **Corrective action:** wait for a transient upstream issue to clear and retry once from an idle state; update to a reviewed fixed release if maintainers identify a compatibility issue.
- **Escalation information:** exact commit, sanitized safety error, synthetic description of response shape, repeatability, and finalization outcome.
- **Actions to avoid:** do not raise/remove the safety ceiling casually, patch payload validation, publish API responses, or claim completion despite failure.

## 8. Persistence/database failure

- **Symptom:** project/token writes, checkpoints, history, metrics, or finalization report a persistence failure.
- **Likely causes:** database outage, insufficient database privileges/storage, schema mismatch after update, lock contention, or write error.
- **Safe checks:** confirm general WordPress/database health, available storage, recent updates, Dashboard terminal status, and sanitized WordPress/database error category—never raw output.
- **Corrective action:** restore database service/capacity/required privileges with the host; run ordinary plugin schema upgrade by normal activation/update paths on staging; retry only after idle/terminal verification.
- **Escalation information:** operation category, exact commit/version, update history, terminal/cleanup state, and sanitized error.
- **Actions to avoid:** do not run ad-hoc SQL, drop/recreate tables, delete rows/options, or publish a database dump.

## 9. Projects or tokens absent after synchronization

- **Symptom:** **NFT Projects** is empty, a project/token is missing, or counts differ from expectation.
- **Likely causes:** key/account cannot see it, run failed/stopped, upstream omitted data, token was malformed/deduplicated, wrong UID, or stale page/cache.
- **Safe checks:** confirm the latest run is canonically completed (100% only then), inspect sanitized counters/history, reload without shared caching, and test a synthetic/known configured selection privately.
- **Corrective action:** resolve API/run errors and perform one fresh run from idle; verify the expected project belongs to the configured NMKR account.
- **Escalation information:** chain class, synthetic dataset description, expected versus observed counts, final status, and sanitized message.
- **Actions to avoid:** do not insert/edit project or token rows, publish real UIDs, or equate a stopped/failed run with complete data.

## 10. Shortcode empty, unavailable, or Premium-restricted

- **Symptom:** blank/error message, “not found,” no selector/data, or “This feature requires the Premium plan.”
- **Likely causes:** misspelled shortcode/registered attribute, unsynchronized UID/data, omitted UID with no fallback project/buyable token, `allow_user_select="0"`, Premium shortcode on Free plan, or theme/script conflict.
- **Safe checks:** compare the five names and attributes in the [user guide](user-guide.md); test the exact shortcode on a staging page with synthetic IDs; confirm completed sync and plan state without sharing licence data; inspect console/network.
- **Corrective action:** correct only registered attributes, synchronize available data, choose an existing UID privately, use grid/list on Free, or restore reviewed entitlement through the provider. Isolate theme/plugin conflicts on staging.
- **Escalation information:** shortcode with fake identifiers, plan class (Free/Premium only), browser/theme class, console error text sanitized, and expected/actual result.
- **Actions to avoid:** do not invent query/chain/limit attributes, expose real UIDs/licence data, bypass plan checks, or edit shortcode PHP.

## Free/Premium restriction

- **Symptom:** carousel, single-token, or single-project output shows the Premium-required message while grid/list works.
- **Likely causes:** expected plan gating, inactive/unrecognized Premium entitlement, or the wrong installation/account context.
- **Safe checks:** confirm only the Free/Premium plan class and exact shortcode; verify that `[nmkr-grid]` or `[nmkr-token-list]` can use the same synchronized project.
- **Corrective action:** use a Free shortcode or resolve entitlement through the normal Freemius account/support workflow, then retest without exposing licence details.
- **Escalation information:** plugin commit/release, shortcode name, expected plan class, and sanitized message.
- **Actions to avoid:** do not share licence information, alter plan checks, or copy Premium code/files between installations.

## 11. Role or capability denial

- **Symptom:** menu/page/action is missing or Access denied appears.
- **Likely causes:** intended least-privilege boundary, custom role missing a specific NMKR capability, or stale login session.
- **Safe checks:** compare role matrix in the [user guide](user-guide.md); verify the exact `nmkr_*` capability with an authorized Administrator; sign in again.
- **Corrective action:** assign **NMKR Marketing** for Projects/Shortcodes/Analytics or **NMKR Admin** for Dashboard/settings/sync, or add only the specifically required capability to a maintained custom role.
- **Escalation information:** role name, requested page/action, capability present/absent (not user identity), and sanitized denial.
- **Actions to avoid:** do not bypass capability/nonces or grant Administrator solely to hide a denial.

## 12. Analytics not recording

- **Symptom:** Analytics remains empty or GA4 events do not arrive.
- **Likely causes:** mode Off/wrong destination, logged-in tracking off, consent required but not signalled, sampling, invalid/missing GA4 fields, endpoint blocked, deduplication/rate limiting, or retention purge.
- **Safe checks:** review mode, consent and logged-in settings, sample rate, retention, and only the presence/format (not value) of GA4 configuration; inspect sanitized front-end request status.
- **Corrective action:** select the intended lawful mode, implement the consent signal where required, use valid GA4 configuration, and allow the analytics endpoint through reviewed security/cache rules.
- **Escalation information:** mode, user login/consent state class, affected shortcode/event class, response status, and sanitized error.
- **Actions to avoid:** never share GA4 secret, disable consent to force a test, publish visitor/customer data, or delete analytics rows.

## 13. `admin-ajax.php`, JavaScript, CDN, proxy, or security interference

- **Symptom:** buttons/status/selectors/analytics fail, requests are 403/404/5xx, stale, or cached; console errors appear.
- **Likely causes:** expired nonce, cached admin/AJAX response, WAF/security rule, JS aggregation/order conflict, blocked cookies, or network outage.
- **Safe checks:** reload and authenticate; inspect browser Network/Console; confirm `wp-admin/admin-ajax.php` is not cached; compare behavior on staging with one controlled integration change at a time.
- **Corrective action:** exclude authenticated admin/AJAX traffic from CDN cache, update reviewed allow-rules, and correct the identified script optimization conflict; revert if ineffective.
- **Escalation information:** browser class/version, response status/action class, relevant sanitized console text, integrations involved, and reproducible steps.
- **Actions to avoid:** do not publish nonces/cookies/private URLs/IPs, broadly disable protection in production, or make multiple untracked changes.

## 14. Debug logging and sanitized diagnostics

- **Symptom:** too little information, excessive logs, or concern that diagnostics contain sensitive data.
- **Likely causes:** debug master/destination/category disabled, throttling, retention, WordPress debug configuration, or overly broad debugging.
- **Safe checks:** review toggles without exposing contents; reproduce on staging; inspect privately for keys, secrets, identifiers, paths, URLs, customer data, cookies, and nonces.
- **Corrective action:** enable only the narrow category/destination briefly, retain only the minimum sanitized excerpt, then disable debugging and verify retention behavior.
- **Escalation information:** category, time window, sanitized error text, and whether debugging was disabled afterward.
- **Actions to avoid:** do not post raw logs, assume universal redaction, leave verbose logging enabled, or upload traces/screenshots/videos without manual sanitization.

## Safe escalation checklist

Before escalating, confirm the environment and commit, reproduce with the least privilege, record whether synchronization was active and its terminal state, finish cleanup/final-state verification, replace every real project/token identifier with a fake one, and manually sanitize the report.

Copy this public-safe template:

```text
NMKR Connect source/release commit (when known):
WordPress version:
PHP version:
Browser class/version (if relevant):
Affected feature or shortcode (fake identifiers only):
Reproducible steps:
Expected result:
Actual result:
Sanitized error text:
Was synchronization active? yes/no/unknown
Resulting terminal state:
Did cleanup/final-state verification complete? yes/no/not applicable
```

Do **not** attach or paste API keys, GA4 secrets, licence information, credentials, cookies or nonces, private URLs or IP addresses, populated environment files, raw logs, database dumps/output, customer data, real project or token identifiers, or sensitive screenshots, traces, videos, or reports.

- **Symptom:** the issue remains after the safe checks above.
- **Likely causes:** a reproducible product defect, unsupported environment interaction, upstream incompatibility, or a condition needing private maintainer review.
- **Safe checks:** review the completed template for public safety and confirm final state.
- **Corrective action:** submit the sanitized template through the project's support/issue route; transfer any genuinely necessary sensitive material only through an explicitly approved private channel, never by default.
- **Escalation information:** only the fields in the template.
- **Actions to avoid:** do not deploy fixes or perform advanced recovery without the exact-environment confirmation, verified backup, idle/terminal state, explicit change/rollback plan, and final-state verification required at the top of this guide.
