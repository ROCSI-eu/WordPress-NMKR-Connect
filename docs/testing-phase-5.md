# Phase 5 PHP 7.4 compatibility guardrails

Phase 5 adds lightweight PHP 7.4 compatibility guardrails for public CI and local validation. The guard keeps the repository aligned with the declared Composer PHP floor of `>=7.4` without introducing broad coding-standard or static-analysis tooling.

## Why this exists

Phase 4 public CI already runs PHP syntax checks with PHP 7.4. During Phase 4 validation, that check surfaced a real PHP 8-only syntax issue: a `match` expression in code that should support the repository's declared PHP `>=7.4` floor. Phase 5 preserves that syntax check and hardens it with a narrow compatibility scan for common PHP 8+ constructs and helper functions.

## Local validation commands

Run the Phase 5 PHP 7.4 guard locally with:

```bash
npm run test:php74
```

Continue to validate Playwright test discovery only with:

```bash
npm run test:e2e -- --list
```

Check Bash scripts with:

```bash
bash -n scripts/*.sh
```

## Guarded PHP 8+ constructs and functions

The guard scans tracked PHP files in the same scope used by public CI, excluding dependency, build, report, coverage, and private artifact directories. It runs `php -l` for each discovered PHP file and then checks for these PHP 8+ patterns:

- `match (` expressions
- nullsafe operator usage with `?->`
- PHP attributes at the start of a line with `#[`
- `readonly`
- enum declarations such as `enum Name`
- PHP 8 string helpers:
  - `str_contains(`
  - `str_starts_with(`
  - `str_ends_with(`
- PHP 8 runtime helpers:
  - `fdiv(`
  - `get_debug_type(`
  - `get_resource_id(`

This is a lightweight guardrail, not a complete PHPCompatibility, PHPCS, PHPStan, or Psalm replacement. It is intentionally narrow so public CI remains fast, dependency-light, and low-noise.

## False-positive handling

If the scan reports a false positive, adjust `scripts/nmkr-php74-compat-scan.sh` carefully and keep the affected pattern as narrow as possible. Do not broaden the scan into a general coding-standard or static-analysis replacement in this phase.

## Public-safety non-goals

Phase 5 public CI intentionally does not add or publish:

- WordPress credentials
- Basic Auth credentials
- API keys
- private URLs
- VM paths
- WP-CLI commands against live sites
- deployment commands
- screenshots, traces, or videos
- artifact uploads

Phase 2 remains private-only and must not run in public CI.
