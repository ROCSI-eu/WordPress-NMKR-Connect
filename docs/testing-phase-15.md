# Phase 15 guarded private real-sync preflight

## Purpose

Phase 15 is a private, read-only preflight for a future controlled real synchronization. It verifies that a future Phase 16A attempt would be permitted by source, deployment, origin, WordPress, database, runtime-state, profile, backup, and timeout guards. Phase 15 does not run synchronization and does not click or call Start.

## Private-only architecture

Run the preflight only from a private development VM with a private environment file outside the repository. Do not run it against production or any unknown target. Actual origins, credentials, and private paths remain in that private environment file. `NMKR_PHASE2_LOG_DIR` is mandatory and must point outside both the repository and the WordPress web root.

## Exact confirmation variables

The command fails closed unless all exact confirmations are present:

```text
RUN_REAL_SYNC=true
NMKR_REAL_SYNC_CONFIRM=I_UNDERSTAND_THIS_MUTATES_DEV
PW_SAVE_ARTIFACTS=false
NMKR_REAL_SYNC_BACKUP_CONFIRM=I_CONFIRMED_A_RECENT_DEV_BACKUP
```

Generic truthy values are not accepted.

## Exact origin matching

`NMKR_REAL_SYNC_ALLOWED_ORIGIN`, `WP_BASE_URL`, WordPress `home`, and WordPress `siteurl` must normalize to the same HTTPS origin. Userinfo, query strings, fragments, paths other than `/`, unexpected ports, wildcards, suffix matching, substring matching, and regular-expression matching are rejected. The raw origin is not printed and is not stored in the receipt; only a SHA-256 digest is recorded.

## Source and deployed Git alignment

The source checkout and deployed plugin directory must be separate canonical paths. Both must be clean Git worktrees, including staged, unstaged, untracked, and mode-only changes. The deployed plugin must be a Git checkout in Phase 15. Source and deployed full 40-character commits must match exactly. Ignored runtime dependency files are fail-closed by default; the guard permits only the known required ignored dependency locations used by the plugin vendor bootstrap and still rejects unexpected ignored runtime files.

## Phase 13B reuse

Phase 15 invokes the existing WP-CLI DB-state validator with active synchronization explicitly disallowed. That validator checks plugin activation, required tables and schema, API-key presence without printing the value, malformed or active sync rows, stale active rows, orphaned markers, and general database integrity.

## Runtime state and object-cache handling

The WP-CLI runtime helper emits only aggregate booleans and counts. It does not print option values, transient values, user identities, URLs, cron arguments, or serialized state. It is invoked with WP-CLI `--skip-plugins --skip-themes --skip-packages` so normal plugins, themes, and packages are not bootstrapped during the read-only probe; WordPress core remains loaded, and WP-CLI does not skip must-use plugins with `--skip-plugins`. Final `nmkr_sync_data` may remain after a completed or failed sync and is not a failure by itself. Historical metrics snapshots are not active state by themselves.

When persistent object cache is disabled, Phase 15 does not inspect database-backed transients through `get_transient()`, avoiding expiry side effects. When persistent object cache is enabled, runtime transient checks are required and only aggregate active-marker counts are returned. The preflight never flushes or deletes cache entries.

## Cron guard

Queued synchronization cron events block the preflight. Only aggregate counts are used. Timestamps, schedules, arguments, and serialized cron data are not printed or stored.

## Light-profile requirement

The preflight requires `sync_profile=light`, `sync_batch_size` from 1 through 3, and `sync_batch_delay` from 3 through 10. It does not modify settings. The light profile constrains processing pressure; it does not limit dataset size.

## Backup confirmation

`NMKR_REAL_SYNC_BACKUP_CONFIRMED_AT` must be a strict UTC timestamp in the form `YYYY-MM-DDTHH:MM:SSZ`, no older than 24 hours and not more than five minutes in the future. Phase 15 does not inspect backup archives and does not print backup locations, names, or provider details.

## Timeout limits

The command validates and records bounded positive integers:

- `NMKR_REAL_SYNC_MAX_DURATION_SECONDS`: 300-3600
- `NMKR_REAL_SYNC_POLL_TIMEOUT_SECONDS`: 10-60
- `NMKR_REAL_SYNC_RECEIPT_TTL_SECONDS`: 60-300

Phase 15 records these values but does not start a synchronization timer.

## Receipt fields and TTL

After all guards pass, Phase 15 atomically writes a private `real-sync-preflight.receipt.json` in the unique private run directory. The receipt is mode `0600`, short-lived, and secret-free. It records commit identities, the origin digest, aggregate pass booleans, backup confirmation epoch, timeout bounds, creation time, and expiry time. It does not include raw origins, credentials, cookies, nonces, option values, transient values, cron data, synchronization payloads, paths, or backup filenames.

## Public-safety guarantees

Console output is limited to generic PASS/FAIL gate names plus short commit identifiers and private diagnostic-file paths on failure. The preflight does not start sync, stop sync, restart sync, force-stop sync, run cleanup or recovery, schedule or delete cron events, update settings, update options, update transients, modify custom NMKR tables, authenticate through the browser, save Playwright artifacts, or send NMKR API requests.

## Negative validation cases

Public-safe validation should include syntax checks, public tests, e2e listing, and default-failure checks for absent or incorrect confirmations. Do not invent private values to make the preflight pass in a public environment.

## Positive read-only validation procedure

A positive validation must happen only on the private development VM after the private environment file is prepared, the deployed plugin Git checkout matches the source commit, the WordPress target is known, the light profile is already configured, and a recent development backup has been independently confirmed. The positive run remains read-only and should produce only a private receipt.

## Rollback for this code change

Rollback is a normal Git revert of the Phase 15 commit. No WordPress state rollback is expected from Phase 15 because the command is designed to be read-only.

## Phase 16A boundary

Phase 16A must revalidate race-sensitive conditions immediately before Start. The receipt alone must never be treated as sufficient without immediate Phase 16A rechecks. Phase 15 creates authorization evidence for a future step, but it is not a mutating test and does not permit browser Start.

## Review-hardening notes

Phase 15 validates the private state path before creating directories or files. The proposed path is canonicalized through its nearest existing parent, symlinked components are rejected where practical, and paths that equal, contain, or sit inside the source checkout or WordPress root are rejected before filesystem mutation. Existing state directories or `runs` directories with unsafe ownership, permissions, or symlink status fail closed; the preflight does not repair them with `chmod`.

The deployed plugin directory must be a separate canonical checkout that neither contains nor is contained by the source checkout. Its Git top-level must equal the configured deployed plugin directory so `git -C` cannot implicitly select a parent repository. Cleanliness checks explicitly enable `core.fileMode=true` so mode-only drift is detected even when local Git configuration would ignore executable-bit changes.

Cron inspection reads the raw `cron` option and supports only the current versioned cron-array structure. Unsupported, malformed, legacy, or ambiguous cron structures are not repaired and cause the preflight to fail closed through the aggregate inspectability flag.

HTTP readiness keeps normal TLS certificate verification enabled. A private CA bundle can be supplied through `NMKR_PHASE2_CURL_CA_BUNDLE` in the private environment file when needed. Redirects are followed only if the final effective URL remains on the exact approved origin; a cross-origin redirect fails without printing the raw origin or effective URL.
