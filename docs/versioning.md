# Versioning and release policy

**Status:** Maintainer policy

This document defines how Connector for NMKR versions are selected and published. A version identifies a coherent, tested release; it is not a completion percentage and is not bumped for every pull request.

## Canonical stable version

The plugin header `Version` in `connector-for-nmkr.php` is the canonical stable release version.

For a stable release:

- `readme.txt` `Stable tag` must exactly match the plugin header version;
- the release package filename must use the same version, for example `connector-for-nmkr-0.25.0.zip`;
- stable release versions use a numeric three-component form: `X.Y.Z`;
- release tooling and regression checks must fail on version drift rather than silently producing mismatched artifacts.

The package builder derives the ZIP version from the plugin header and verifies the WordPress.org stable tag before packaging.

## Pre-1.0 policy

The first intended public stable release is `0.25.0`.

While the plugin remains pre-1.0:

- use a **patch** bump such as `0.25.0 → 0.25.1` for compatible bug, reliability, security, or internal fixes that do not intentionally add a user-facing capability or meaningful public contract change;
- use a **minor** bump such as `0.25.x → 0.26.0` for a new feature or meaningful public behavior/contract change;
- do not bump the plugin version for every pull request;
- normally batch roughly 3–5 compatible low-risk fixes into one patch release when that produces a coherent tested release;
- a significant security, data-integrity, or reliability fix may ship alone after the required review and validation.

These batching numbers are operating guidance, not a requirement to delay an important fix.

## Prereleases

GitHub/testing builds may use prerelease identifiers such as `0.26.0-beta.1` or `0.26.0-rc.1` when a separate prerelease workflow is explicitly being used.

The ordinary WordPress.org stable release contract remains the numeric `X.Y.Z` version recorded in the plugin header and `Stable tag`. Do not publish a prerelease suffix as the repository's WordPress.org stable tag.

## 1.0.0 maturity gate

`1.0.0` is a compatibility and maturity commitment, not a calendar milestone or a required number of preceding releases.

Before moving to `1.0.0`, maintainers should have evidence that:

- no known P0/P1 release blockers remain;
- important public and persistent contracts are considered stable;
- installation and update behavior is proven;
- synchronization ownership, Stop, failure/recovery, finalization, and history behavior is proven at the required risk level;
- public release identity is settled;
- no near-term disruptive redesign of important persistence or public behavior is expected.

The gate does not require a specific user count or elapsed time.

## Release discipline

Version changes belong to an explicit release-preparation change, not unrelated feature/fix PRs unless that PR is intentionally becoming the release candidate.

Before an immutable tag or release:

1. use the exact reviewed candidate SHA;
2. run the focused release/package checks and any risk-based DEV validation required by the changes included in that release;
3. verify plugin header, `Stable tag`, changelog, and package filename consistency;
4. build the purpose-built package from a clean exact source tree;
5. record provenance/checksum as required by the release process;
6. create the immutable tag/release only through an explicit release task.

If a released artifact needs correction, publish a new version. Do not rewrite an already published immutable release tag.
