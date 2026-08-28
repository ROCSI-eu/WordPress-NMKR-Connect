# Testing-environment access guide

## Purpose and evidence status

This public-safe guide governs controlled non-production WordPress access for Catalyst assessment. The evidence-navigation folder and every link placed in the Catalyst Proof of Achievement form are public. The public four-document folder was submitted at `2026-08-28 11:53 UTC`; the support route and access window were operational, and access was available on request with no request received as of submission. [EVD-013](executed-evidence-2026-08-28.md) records the sanitized delivery result, and M3-21 is **Validated** within that disclosed scope.

## Environment boundary

The owner must use:

- a controlled non-production WordPress environment containing only synthetic or non-customer data;
- no customer data, production credentials, production environment access, or private infrastructure-administration access;
- the exact public source baseline declared for assessment, selected source SHA, and deployed SHA, recorded privately;
- a healthy WordPress and plugin state;
- a verified binding from the active WordPress plugin file to the expected plugin file inside the verified deployed location;
- an idle synchronization state before access or any permitted synchronization exercise; and
- a staging-only, replaceable API credential.

## Account and capability boundary

Use a dedicated staging account with current credentials and the narrowest suitable capability profile. An existing dedicated account may be reused only after its current credentials, assigned capability profile, login, and intended plugin-page access have been revalidated. Create an account or reset its password only when no suitable existing account is available, its credentials fail, or its assigned capability profile is unsuitable.

The assessor requests temporary access through the support route and identifies the needed generic capability profile:

1. **Full staging administration** — complete administration of the staging WordPress site.
2. **Full NMKR Connect plugin administration** — complete administration of NMKR Connect without general full-site administration.
3. **Restricted marketing access** — access limited to a selected subset of NMKR Connect features.

Temporary staging access is publicly offered through the project-selected support route during the authorized assessment window. Temporary login details are supplied separately through that route. They must not be published, forwarded, committed, pasted into public discussion, or retained in public evidence. Never reuse personal administrator credentials or permit account sharing. Access is time-limited and may be rotated or revoked at the end of the authorized window.

## Permitted scope

After temporary access is supplied, and within ordinary WordPress/plugin dashboard controls and the assigned capability profile, the assessor may:

- navigate and inspect applicable plugin pages, status, projects, NFT displays, configuration, documentation, and evidence-relevant controls;
- view staging-only configuration or retained staging logs when exposed by an intended page needed for the assessment;
- start one synchronization at a time, observe progress and completion, and use the ordinary **Stop** control; and
- observe ordinary abort behavior when a dashboard-controlled synchronization is stopped or aborts.

The preconfigured staging-only API credential is solely for this bounded assessment activity.

## Prohibited scope

Prohibit:

- account or credential sharing, forwarding temporary login details, privilege escalation, or access outside the supplied environment;
- concurrent, repetitive, load, stress, fault-injection, destructive, or infrastructure-level synchronization activity;
- direct database, filesystem, WP-CLI, server, DNS, CDN, hosting, plugin, or theme manipulation;
- deleting users, posts, media, projects, tokens, options, database content, or other state outside ordinary synchronization behavior;
- access to production systems, credentials, or customer data; and
- copying, reusing, publishing, or sharing any visible staging-only credential or log outside the assessment.

The owner accepts the residual non-production exposure risk that a replaceable staging credential or retained staging log may be visible through an intended page during the authorized window. Credential rotation/reset and staging-log cleanup are recommended final-state actions, not prerequisites for access. Actual secrets, logs, private URLs, account details, infrastructure details, authentication state, or populated access values must never be placed in the public evidence folder or Catalyst form, committed, or pasted into pull requests, CI, issues, or public documentation. The public Testing Environment Access document and Catalyst form may contain the project-selected support route, but this repository describes it only generically and must not contain its actual address or populated value.

## Public evidence-navigation folder

Prepare one public-safe Google Drive folder that may be linked from the public Catalyst Proof of Achievement. It contains exactly four public-safe evidence documents aligned to the contractual evidence groups:

1. **Reports of Testing Results**;
2. **Security Implementation Evidence**;
3. **Documentation Evidence**; and
4. **Testing Environment Access**.

The Testing Environment Access document describes the environment class, the three generic capability profiles, the permitted and prohibited scope, and the instruction to request temporary access through the project-selected support route. That public document may contain the operational support route; this repository contains no actual address or populated support value. The folder and Catalyst form contain no usernames, passwords, temporary login values, account email addresses, account identifiers, private access links, authentication state, private evidence locations, other credentials, or other private access values. Public folder submission and private temporary-access delivery are separate events.

## Execution checklist

Complete the private steps outside the repository, while keeping the public-folder checks public-safe:

1. [ ] Confirm the controlled non-production environment and the public four-document evidence-folder model.
2. [ ] Record privately the exact declared public source baseline, selected source SHA, and deployed SHA.
3. [ ] Verify that the selected source equals the declared baseline, or retain documented exact Git-tree equivalence; then verify selected-source/deployed SHA equality or exact tree equivalence.
4. [ ] Confirm a clean deployed tracked worktree and that deployed tracked files match the selected source tree.
5. [ ] Resolve the active WordPress plugin file, verify that it equals the expected plugin file inside the verified deployed location, and retain the result privately.
6. [ ] Confirm WordPress/plugin health, no production or customer data exposure, an idle synchronization state, and presence of the staging-only API configuration.
7. [ ] Prepare and review the four public-safe evidence documents; confirm that the folder and Catalyst form contain no temporary login details or other private values.
8. [ ] Submit the public folder through the Catalyst Proof of Achievement only when authorized, recording that public submission separately from private access delivery.
9. [ ] Confirm the project-selected public support route is available and operational, record the authorized window and support availability, and revalidate suitable dedicated staging accounts for all three generic capability profiles.
10. [ ] When an assessor requests access through the support route, record the requested generic capability profile and identify a suitable revalidated dedicated staging account.
11. [ ] Revalidate privately the selected account's current credentials, assigned profile, login, intended plugin-page access, and prohibited-capability boundary. Create an account or reset credentials only if no suitable account exists, credentials fail, or the profile is unsuitable.
12. [ ] If access was requested, supply temporary login details separately through the support route and record the handoff privately; otherwise, explicitly record that access was available on request and no request had been received as of evidence registration. Never place login details in the public folder or form, forward them, or retain them in public evidence.
13. [ ] If synchronization is exercised, allow only one ordinary dashboard synchronization at a time and retain private observations of Start, progress, completion, ordinary Stop, and abort behavior as applicable.
14. [ ] At the end of the window, revoke or rotate access and confirm the result, or explicitly record privately that the authorized window remains active.
15. [ ] Perform recommended staging-credential rotation/reset and staging-log cleanup, then record final state and any anomaly.
16. [ ] Register a sanitized result only after actual public-folder submission, recording either a requested private handoff or the absence of a request, and retaining the required private evidence; create EVD-013 only then.

## Private evidence manifest template

Retain privately, without publishing account identifiers or populated access values:

- requirement and planned evidence IDs;
- generic environment class;
- declared public source baseline, selected source SHA, and their comparison method and result;
- deployed SHA, selected/deployed comparison method and result, tracked-worktree cleanliness, deployed-file identity, and active WordPress plugin-file binding results;
- public folder submission state and time, recorded separately from temporary-access delivery;
- account disposition (reused or created/reset) and generic capability profile;
- credential revalidation, login, and intended-page verification results;
- operational public-support-route result, authorized window, and support availability;
- temporary-access request and private handoff time when a request occurred, or an explicit record that access was available on request and no request had been received as of evidence registration;
- synchronization observations, including Start, progress, completion, ordinary Stop, and abort behavior, only if exercised;
- expiry, revocation, rotation, or still-active-window state;
- anomalies and cleanup/final state; and
- approved hashes of retained private evidence files.

Keep actual account identifiers, temporary login details, support address, private links, authentication state, evidence locations, and all populated access values private. The public repository may later publish only sanitized facts and approved hashes.

## Stop conditions

Stop before public-folder submission or private access delivery if:

- the target is production or contains customer data or production credentials;
- the baseline, selected source, deployed identity, clean tracked state, or deployed-file identity is uncertain or fails;
- the active WordPress plugin file does not bind unambiguously to the expected file in the verified deployed location;
- WordPress/plugin health fails, synchronization is unexpectedly active, or staging-only API configuration is absent;
- the public folder does not contain exactly the four public-safe evidence documents, or any folder document or Catalyst field contains temporary login details or another private value;
- no suitable dedicated account can be revalidated or safely created/reset with the requested generic profile;
- login or intended-page access fails, or access is broader than authorized without an accepted private rationale;
- the public support route is unavailable, the authorized window is not documented, or requested temporary login details cannot be delivered separately through the support route;
- concurrent, repetitive, load, stress, fault-injection, destructive, direct-state, or infrastructure-level activity occurs;
- cleanup, recovery, Stop, abort, expiry, rotation, or revocation leaves an unsafe or ambiguous state; or
- any credential, log, private link, account identifier, authentication state, or populated private value appears publicly.

If public exposure is suspected, stop and follow the owner's private incident and credential-rotation process without reproducing the value publicly.

## Completion boundary

M3-21 may become **Validated** only after:

- declared-baseline, selected-source/deployed identity, tracked-cleanliness, deployed-file identity, and active WordPress plugin-file binding checks passed and were retained privately;
- the four-document public-safe evidence folder was reviewed and actually submitted through the Catalyst Proof of Achievement;
- suitable dedicated staging accounts and all three generic capability profiles were revalidated and ready for use, with creation/reset completed where reuse was unsuitable;
- WordPress/plugin health, idle state, staging-only API configuration, login, and intended-page access were verified;
- the project-selected public support route was available and operational, and the authorized access window and support availability were recorded;
- actual credential handoff was recorded privately when a request occurred, or the absence of any request as of evidence registration was recorded explicitly;
- any synchronization remained within the permitted safeguards and its observations were retained privately when exercised;
- access was rotated or revoked and verified, or a still-active authorized assessment window was explicitly recorded;
- private evidence was retained; and
- a sanitized executed record, including EVD-013, was created and registered.

The public folder and EVD-013 have been submitted and registered. No temporary credential handoff or synchronization exercise is claimed. The authorized access window remains active, the support route must remain operational, and expiry or reassessment remains `2026-10-01 00:00 UTC`.
