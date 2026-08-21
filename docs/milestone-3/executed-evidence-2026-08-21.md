# Executed evidence — 2026-08-21

## Evidence identity and scope

This sanitized report registers **EVD-007** for **M3-01** and records a bounded compatibility execution across two separately configured WordPress installations on the project’s maintained runtime. The exact source and deployed commit was `bbf87e351a118ff67d5b57c2c20dfcf4e936b477`.

The execution covered one development installation and one staging installation. A production installation on the same host was inventoried only and was not modified, authenticated against, or used for intentional runtime mutation.

## Tested environment

The tested installations were hosted on a conventional self-managed Google Cloud Compute Engine WordPress stack with:

- Ubuntu 24.04.4 LTS;
- Apache 2.4.58 using the prefork MPM and Apache PHP module;
- PHP 8.3.6;
- MariaDB 10.11.14;
- WordPress 7.1;
- Hello Elementor 3.4.9;
- NMKR Connect 0.1.

Both installations used the same operating-system, web-server, PHP, database-server, WordPress, theme, and plugin versions. They remained separate WordPress sites with separate databases and environment classifications. The development database used `utf8mb4_unicode_ci`; staging used `utf8mb4_unicode_520_ci`.

## Method

Before execution, the operator confirmed the exact public `main` commit, clean source identity, required tooling, WordPress readiness, plugin activation, API-key presence without printing the value, idle synchronization state, database health, and absence of maintenance mode.

The exact commit was deployed as a clean Git worktree to development and staging with production Composer dependencies. The compatibility smoke then ran the repository’s non-mutating WP-CLI smoke on both installations and verified:

- exact deployed commit and clean worktree;
- site identity and WordPress environment type;
- WordPress, PHP, database, theme, and plugin versions;
- required NMKR database tables;
- non-empty API configuration without exposing it;
- latest synchronization timestamp consistency when both values existed;
- absence of suspicious completed synchronization rows;
- recent first-party PHP or NMKR errors where a debug log existed;
- representative shortcode runtime registration;
- unauthenticated homepage HTTP readiness;
- unchanged fingerprint of selected synchronization options and NMKR table counts before and after the smoke.

No real NMKR synchronization ran. No intentional database, option, transient, history, or content mutation was performed.

## Results

| Profile | WordPress environment type | Database collation | Exact commit | Repository WP-CLI smoke | Runtime registration | Homepage readiness | State preservation |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Development | `development` | `utf8mb4_unicode_ci` | PASS | PASS | PASS | PASS | PASS |
| Staging | `staging` | `utf8mb4_unicode_520_ci` | PASS | PASS | PASS | PASS | PASS |

The cross-environment execution result was **PASS**. Both installations ran the same exact clean plugin build on the project’s current maintained WordPress/PHP stack, and the bounded read-oriented checks completed without detected state mutation.

The private compatibility execution record SHA-256 was `603a7bb9b234bd1fa411628802ff8156d69558bf7bf68221f6fbf819b1473b53`.

## Requirement assessment

**M3-01 is Validated within the disclosed compatibility boundary.** NMKR Connect was exercised on separate development and staging WordPress installations using a representative real-world self-managed Google Cloud VM stack and the current maintained runtime used by the project.

This result demonstrates compatibility with the recorded WordPress environment and with separately configured WordPress installations. It does not claim certification for every hosting provider, managed WordPress platform, operating system, web server, PHP version, database version, theme, plugin combination, or network topology.

Runtime evidence was intentionally limited to the current maintained WordPress and PHP environment used by the project. Older runtime versions were not deployed solely to expand the matrix, and this report does not convert declared minimum-version metadata into executed runtime coverage.

## Final state and evidence retention

Development and staging remained healthy, active, idle, and database-ready after the smoke. The production installation was not modified or authenticated against. Private raw logs and the private evidence manifest remain outside the public repository. Only the sanitized method, aggregate result, exact commit, and evidence hash are published here.

## Limitations

- Both tested WordPress installations shared one Google Cloud VM and the same underlying software stack.
- The result is multi-installation WordPress compatibility evidence, not independent-provider or broad cross-hosting certification.
- Only WordPress 7.1 and PHP 8.3.6 were executed for this evidence item.
- The smoke was read-oriented and representative rather than exhaustive.
- No real synchronization, destructive action, production deployment, authenticated production test, browser matrix, or third-party hosting comparison was part of this execution.
- The result does not guarantee compatibility with every theme, extension, managed host, runtime combination, or future release.

## Public/private publication boundary

This report excludes private URLs and domains, infrastructure identifiers, filesystem paths, credentials, API keys, cookies, nonces, database contents, project/token identifiers, raw logs, screenshots, traces, videos, generated reports, authentication state, and private evidence locations. The published SHA-256 identifies the retained private compatibility execution record without exposing its contents.
