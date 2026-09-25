# WordPress.org release/update checklist

**Status:** Maintainer procedure

Use this checklist for every WordPress.org release after `0.25.0`. GitHub is the source of truth; WordPress.org SVN is a release repository, not a development branch.

The release version below means one exact numeric `X.Y.Z` value. Do not continue when any version, source, package, SVN, or changelog check disagrees.

## 1. Prepare the Git release candidate

- Select the intended release version under the [versioning policy](versioning.md).
- Update the main plugin `Version` and `readme.txt` `Stable tag` to that same version.
- Add a concise, user-facing `readme.txt` changelog entry headed `= X.Y.Z =`. Summarize meaningful user-visible fixes, behavior, security/reliability changes, or features; do not paste or mechanically transform the Git commit log.
- Keep the current release changelog in `readme.txt`. When older history makes the readme unnecessarily long, move older entries to `changelog.txt` while retaining the current release entry in `readme.txt`.
- Review any `Tested up to`, compatibility, installation, FAQ, or external-service text that the release actually changes.
- Merge the release-preparation change and record the exact reviewed Git SHA/tree.

Run from an exact clean candidate checkout:

```bash
npm run test:release
npm run build:package
```

Stop if the worktree is dirty, release checks fail, package generation changes the source tree, or the package checksum/provenance is not reproducible.

## 2. Validate the release package

Verify that the purpose-built package:

- is built from the exact intended Git SHA/tree;
- is named `rocsi-connector-for-nmkr-X.Y.Z.zip`;
- contains `rocsi-connector-for-nmkr.php` with `Version: X.Y.Z`;
- contains `readme.txt` with `Stable tag: X.Y.Z` and the current `= X.Y.Z =` changelog entry;
- verifies `PACKAGE-MANIFEST.sha256`;
- contains no repository-only tests, documentation, secrets, logs, environment files, source ZIPs, or obsolete bootstrap files;
- reproduces the same ZIP SHA-256 when rebuilt from the same exact source.

Record the exact source SHA/tree and package SHA-256 before SVN publication.

## 3. Stage WordPress.org SVN locally

Start from an up-to-date checkout of the official `rocsi-connector-for-nmkr` SVN repository. Verify repository identity before mutation.

Copy the verified package **contents** directly into `trunk/`; do not nest the package directory inside `trunk`.

Before any server commit:

- confirm `trunk/rocsi-connector-for-nmkr.php` reports `Version: X.Y.Z`;
- confirm `trunk/readme.txt` reports `Stable tag: X.Y.Z`;
- confirm the current `= X.Y.Z =` changelog entry is present;
- confirm remote `tags/X.Y.Z` does not already exist;
- create the local release tag with SVN copy semantics: `svn copy trunk tags/X.Y.Z`;
- confirm `tags/X.Y.Z/rocsi-connector-for-nmkr.php` reports `Version: X.Y.Z`;
- confirm `tags/X.Y.Z/readme.txt` reports `Stable tag: X.Y.Z` and contains the same current changelog entry;
- compare `trunk/` and `tags/X.Y.Z/` release payloads and inspect `svn status` plus `svn diff --summarize`;
- leave `assets/` unchanged unless separately intended and reviewed.

The release is **not ready** if the plugin version, trunk Stable tag, numeric SVN tag, tagged Stable tag, or current changelog entry disagree.

## 4. Publish deliberately

- Re-run the exact-source/package/SVN checks immediately before the write.
- Confirm the SVN-specific credential is available privately; never place it in GitHub, chat, shell history, scripts, or logs.
- Commit only the reviewed release changes. Record the SVN revision and publication UTC.
- If WordPress.org Release Confirmation is enabled, complete that confirmation before treating the release as published.
- Do not rewrite an already published numeric tag. Correct a released defect with a new version.

## 5. Verify WordPress.org after propagation

Verify independently that:

- the public directory page serves the expected plugin identity and version;
- the Plugins API/download path resolves the intended version;
- the rendered **Changelog** tab shows the new `X.Y.Z` entry as intended;
- the public package has the expected top-level plugin directory and release identity;
- any search/profile propagation lag is recorded separately rather than treated as release failure.

Record the public verification result with the Git SHA/tree, package checksum, SVN tag/revision, and publication/verification UTCs.

## References

- [WordPress Plugin Handbook: Using Subversion](https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/)
- [WordPress Plugin Handbook: Plugin Readmes](https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/)
- [WordPress Plugin Handbook: Release Confirmation Emails](https://developer.wordpress.org/plugins/wordpress-org/release-confirmation-emails/)
