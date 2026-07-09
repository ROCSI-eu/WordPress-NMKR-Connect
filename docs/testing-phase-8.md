# Phase 8 WP-CLI database-state validation

Phase 8 adds a read-only WP-CLI database-state validation layer for deployed NMKR Connect test environments. It is intended to run after deployment and browser smoke coverage, when no NMKR sync is active.

## Script

```bash
scripts/nmkr-wpcli-db-state.sh
```

The script follows the same WP-CLI environment conventions as the existing smoke script:

- `WP_PATH` optionally points WP-CLI at the WordPress installation.
- `WP_CLI_BIN` defaults to `wp`.
- `NMKR_PLUGIN_SLUG` defaults to `nmkr-connect/nmkr-connect.php`.

## Targeted command

Run only the Phase 8 database-state validation with:

```bash
npm run test:wpcli:db-state
```

## Full validation

Phase 2 runs Phase 8 automatically after the WP-CLI smoke checks pass:

```bash
npm run test:phase2
```

## What Phase 8 validates

The validation checks only public-safe invariants and aggregate counts. Invariant SQL query failures are fatal, include public-safe invariant labels, and are not treated as zero-count passes:

- NMKR Connect plugin activation state.
- Expected NMKR database tables.
- Expected schema columns.
- Expected indexes and unique keys.
- Expected activation/default option keys.
- API key presence without printing the API key.
- No active sync state by default.
- Basic data-integrity counts, including duplicate and relationship checks.
- Sync metrics and sync stats rows for impossible values.
- Latest sync timestamp consistency between sync metrics and the stored option when both values exist.

## Active sync-state handling

By default, Phase 8 fails when it detects active or partial sync state. This keeps normal validation deterministic and avoids treating an in-progress sync as a database invariant failure. Transient sync-state rows are inspected through direct read-only option-table `SELECT` queries rather than `wp transient get`, so expired DB-backed transients are not cleaned up or mutated by validation.

If you intentionally need to inspect a database while sync state may be active, set:

```bash
NMKR_DB_STATE_ALLOW_ACTIVE_SYNC=true npm run test:wpcli:db-state
```

Use the default `false` behavior for normal Phase 2 validation. Do not run the script during an active sync unless the skip is explicitly allowed and understood.

## Read-only and public-safety guarantees

The script is designed to use only read-only WP-CLI and SQL operations, such as `wp core is-installed`, `wp plugin is-active`, `wp db prefix`, `wp db query` with `SHOW`/`SELECT`, `wp option get`, and direct read-only option-row `SELECT` queries for transient state.

It must not activate, deactivate, uninstall, delete, reinstall, or run NMKR sync. It must not write options, transients, database rows, logs, files, or plugin settings.

Console output is intentionally concise and public-safe:

- No secrets.
- No API keys.
- No credential, URL, nonce, or environment printing.
- No row dumps.
- No debug log output.
- No database writes.
