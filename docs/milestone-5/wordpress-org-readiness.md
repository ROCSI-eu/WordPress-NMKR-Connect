# WordPress.org readiness

**Status:** PLANNED\
**Document type:** Living readiness/findings document\
**Initial baseline:** `c375ddf76dfb8965f568253f2e3563eb3c339f6f`

This document does not claim WordPress Plugin Directory compliance, submission, approval, or listing. M5-01 must inspect current code and current WordPress.org requirements before implementation decisions are made.

## M5-01 purpose

Determine the smallest coherent WordPress.org-compatible free distribution while preserving intended project behavior and Premium strategy where possible, without over-building.

## Preliminary areas requiring exact-code verification

These are planning hypotheses, not final findings.

### Free vs Premium architecture

The current repository contains local Premium-gated behavior and Freemius integration. M5-01 must compare the exact implementation against current WordPress.org rules concerning trialware, paid functionality, serviceware, external add-ons, upgrade behavior, and externally delivered executable code.

### Name and slug

The current product/repository identity uses `NMKR Connect` / `nmkr-connect`. M5-01 must determine whether current WordPress.org trademark/slug rules require documented NMKR authorization or a non-trademark-leading alternative before submission. The final slug decision is high impact because a WordPress.org plugin URL is not a casual post-submission rename.

### Licensing and redistribution

Reconcile project/distribution metadata and bundled dependency obligations. Current repository licensing references must be inspected as a whole rather than assuming one file controls every distribution concern.

### Plugin metadata and readme

Audit production header metadata, versioning, changelog/stable-tag strategy, required/expected `readme.txt` content, external-service disclosures, screenshots/assets, internationalization metadata, and current readme validation requirements.

### External services and privacy

Trace every external service or optional service in the distributable plugin, including NMKR API calls, Freemius/licensing/update behavior, GA4/server-side analytics, and any gateway/service endpoint. Verify when calls occur, what data is sent, whether consent is required, what disclosures/terms/privacy links are necessary, and which behavior belongs in the WordPress.org free package.

### Packaging and dependencies

Establish a reproducible installable ZIP from an exact commit. Verify Composer dependencies required at runtime, included/excluded files, third-party license/source obligations, package layout, checksums, and package-install behavior. A raw GitHub source archive must not be treated as an installable release if runtime dependencies are absent.

### Uninstall and data retention

Verify current uninstall/data-retention behavior and any release disclosures required for persistent plugin data, analytics data, options, transients, tables, scheduled events, and optional retention choices.

### Update/licensing behavior

Audit any custom/Freemius update paths, premium-delivery behavior, activation/licensing flow, and potential conflicts with directory policy or WordPress core update expectations.

### Plugin Check and release validation

Define the current Plugin Check/readme/package checks required before submission and distinguish warnings/recommendations from blockers.

## M5-01 required output

The analysis should record:

1. blockers;
2. non-blockers;
3. ambiguities requiring owner/product/legal decisions;
4. options and trade-offs;
5. recommended smallest compliant architecture;
6. exact likely file scope;
7. focused acceptance/tests for later modification work.

Any material finding must also be recorded in [risks-and-decisions.md](risks-and-decisions.md) and any sequencing consequence reflected in [delivery-plan.md](delivery-plan.md).

## Submission-readiness gate

Do not mark this document `READY FOR VALIDATION` until the selected architecture, naming/slug, licensing/disclosure, package composition, metadata/readme, external-service behavior, and current Plugin Check/readme validation path are all understood well enough to define an exact M5-02 implementation scope.
