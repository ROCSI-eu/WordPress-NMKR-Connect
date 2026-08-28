# Testing-environment access guide

## Purpose and evidence status

This public-safe guide prepares controlled non-production WordPress access for anonymous Catalyst assessment. Documentation readiness and the completed private staging preflight are not proof that the access package was submitted. M3-21 remains **Implemented, validation pending** until actual Catalyst submission and later sanitized evidence registration. EVD-013 has not been created.

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

Select one of these generic profiles without publishing account identifiers or populated capability details:

1. **Full staging administration** — complete administration of the staging WordPress site.
2. **Full NMKR Connect plugin administration** — complete administration of NMKR Connect without general full-site administration.
3. **Restricted marketing access** — access limited to a selected subset of NMKR Connect features.

Never reuse personal administrator credentials, share credentials, or permit account sharing. Record the authorized assessment window privately and revoke access when that window ends.

## Permitted scope

Within ordinary WordPress/plugin dashboard controls and the assigned capability profile, the assessor may:

- navigate and inspect applicable plugin pages, status, projects, NFT displays, configuration, documentation, and evidence-relevant controls;
- view staging-only configuration or retained staging logs when exposed by an intended page needed for the assessment;
- start one controlled synchronization, observe its progress and completion, and use the ordinary **Stop** control; and
- observe ordinary abort behavior when a dashboard-controlled synchronization is stopped or aborts.

Only one synchronization may run at a time. The preconfigured staging-only API credential is solely for this bounded assessment activity.

## Prohibited scope

Prohibit:

- account or credential sharing, privilege escalation, or access outside the supplied environment;
- concurrent, repetitive, load, stress, fault-injection, destructive, or infrastructure-level synchronization activity;
- direct database, filesystem, WP-CLI, server, DNS, CDN, hosting, plugin, or theme manipulation;
- deleting users, posts, media, projects, tokens, options, database content, or other state outside ordinary synchronization behavior;
- access to production systems, credentials, or customer data; and
- copying, reusing, publishing, or sharing any visible staging-only credential or log outside the assessment.

The owner accepts the residual non-production exposure risk that a replaceable staging credential or retained staging log may be visible through an intended page during the authorized window. Credential rotation/reset and staging-log cleanup are recommended final-state actions, not prerequisites for delivery. Actual secrets, logs, private URLs, account details, support addresses, infrastructure details, or populated access values must never be committed or pasted into pull requests, CI, issues, public Catalyst fields, or public documentation.

## Private Google Drive package template

> **PRIVATE TEMPLATE — populate only in an unlisted Google Drive folder outside this repository.** Configure the folder and its documents as **Anyone with the link — Viewer**. Supply the unlisted link only through the intended Catalyst milestone-submission channel. Never commit or publish the link or any populated private value. Anonymous link-based Viewer distribution is not individually authenticated recipient delivery.

The package may separate:

1. a public-safe evidence and navigation document; and
2. a credential-bearing staging-access document containing the private values below.

- **Access purpose:** Controlled Catalyst assessment of the disclosed plugin scope.
- **Environment class:** [PRIVATE NON-PRODUCTION ENVIRONMENT CLASS]
- **Login URL:** [PRIVATE ENVIRONMENT LOGIN URL]
- **Account identifier:** [PRIVATE ACCOUNT IDENTIFIER]
- **Current credential:** [PRIVATE ACCOUNT CREDENTIAL]
- **Capability profile:** [GENERIC CAPABILITY PROFILE]
- **Assessment window:** [ACCESS START UTC] to [ACCESS END UTC]
- **Login instructions:** Use the private login location and supplied account only within the permitted scope.
- **Permitted actions:** Ordinary navigation and inspection; one-at-a-time dashboard synchronization start, progress observation, completion, Stop, and abort observation.
- **Prohibited actions:** Credential sharing; concurrent or repetitive synchronization; load, stress, fault-injection, destructive, direct-state, or infrastructure-level activity; reuse or publication of visible staging-only credentials or logs; privilege escalation; or access outside the supplied environment.
- **Support route:** [PRIVATE SUPPORT ROUTE]
- **Issue reporting:** Provide safe reproduction steps and a sanitized symptom through the private support route; do not include credentials, authentication state, or raw private output.
- **Logout guidance:** Log out after each session and do not retain credentials in a shared browser or document.

## Private execution checklist

Complete outside the repository:

1. [ ] Confirm the controlled non-production environment and the anonymous Catalyst link-delivery model.
2. [ ] Record the exact declared public source baseline, selected source SHA, and deployed SHA.
3. [ ] Verify that the selected source equals the declared baseline, or retain documented exact Git-tree equivalence; then verify selected-source/deployed SHA equality or exact tree equivalence.
4. [ ] Confirm a clean deployed tracked worktree and that deployed tracked files match the selected source tree.
5. [ ] Resolve the active WordPress plugin file and verify that it equals the expected plugin file inside the verified deployed location; retain the result privately.
6. [ ] Confirm WordPress/plugin health, no production or customer data exposure, an idle synchronization state, and presence of the staging-only API configuration.
7. [ ] Select one generic capability profile and identify a suitable existing dedicated staging account.
8. [ ] Revalidate the account's current credentials, assigned profile, login, and intended plugin-page access. Create an account or reset credentials only if no suitable account exists, credentials fail, or the profile is unsuitable.
9. [ ] Verify prohibited capabilities are unavailable where applicable and record the authorized window.
10. [ ] Prepare the public-safe evidence/navigation document separately from the credential-bearing access document if useful.
11. [ ] Configure the unlisted Drive folder and documents as **Anyone with the link — Viewer** and confirm no private value appears in the public-safe document.
12. [ ] Submit the unlisted link only through the intended Catalyst milestone-submission channel.
13. [ ] During the window, maintain the private support record and allow only the controlled dashboard synchronization behavior defined above.
14. [ ] At the end of the window, revoke access and confirm login no longer succeeds, or explicitly record that the authorized window remains active.
15. [ ] Perform recommended credential rotation/reset and staging-log cleanup, then record final state and any anomaly.
16. [ ] Register a sanitized result only after actual submission and evidence retention; create EVD-013 only then.

## Private evidence manifest template

Keep this manifest private and populate it outside the repository:

- requirement ID: [REQUIREMENT ID]
- planned evidence ID: [PLANNED EVIDENCE ID]
- generic environment class: [GENERIC ENVIRONMENT CLASS]
- declared public source baseline: [DECLARED PUBLIC SOURCE BASELINE]
- selected source SHA: [SELECTED SOURCE SHA]
- declared-baseline comparison method and result: [DECLARED BASELINE COMPARISON]
- deployed SHA: [DEPLOYED SHA]
- selected/deployed equality or tree-equivalence method and result: [DEPLOYMENT IDENTITY COMPARISON]
- deployed tracked-worktree cleanliness result: [TRACKED WORKTREE CLEANLINESS RESULT]
- deployed-file identity result: [DEPLOYED FILE IDENTITY RESULT]
- active WordPress plugin-file binding result: [ACTIVE PLUGIN BINDING RESULT]
- account disposition, reused or created/reset: [ACCOUNT DISPOSITION]
- account identifier kept private: [PRIVATE ACCOUNT IDENTIFIER]
- generic capability profile: [GENERIC CAPABILITY PROFILE]
- credential revalidation result: [CREDENTIAL REVALIDATION RESULT]
- login and intended-page verification result: [ACCESS VERIFICATION RESULT]
- Drive sharing class: [LINK VIEWER SHARING CLASS]
- Catalyst submission UTC: [CATALYST SUBMISSION UTC]
- access-start and access-end UTC: [AUTHORIZED WINDOW]
- support availability: [SUPPORT AVAILABILITY]
- controlled synchronization observations, if exercised: [DASHBOARD SYNCHRONIZATION RESULT OR NOT EXERCISED]
- expiry or revocation result: [EXPIRY OR REVOCATION RESULT]
- anomalies and cleanup/final state: [ANOMALIES AND FINAL STATE]
- hashes of retained private evidence files: [APPROVED SHA-256 IDENTIFIERS]

The public repository may later publish only sanitized facts and approved SHA-256 identifiers—never credentials, private values, evidence locations, Drive links, account details, or private infrastructure information.

## Stop conditions

Stop before submission or assessment activity if:

- the target is production or contains customer data or production credentials;
- the baseline, selected source, deployed identity, clean tracked state, or deployed-file identity is uncertain or fails;
- the active WordPress plugin file does not bind unambiguously to the expected file in the verified deployed location;
- WordPress/plugin health fails, synchronization is unexpectedly active, or staging-only API configuration is absent;
- no suitable dedicated account can be revalidated or safely created/reset with the assigned generic profile;
- login or intended page access fails, or access is broader than authorized without an accepted private rationale;
- the package is not unlisted Viewer-only, contains unintended private content, or cannot be supplied solely through the intended Catalyst channel;
- concurrent, repetitive, load, stress, fault, destructive, direct-state, or infrastructure-level activity occurs;
- cleanup, recovery, Stop, abort, expiry, or revocation leaves an unsafe or ambiguous state; or
- any credential, log, private link, or populated private value appears publicly.

If public exposure is suspected, stop and follow the owner's private incident and credential-rotation process without reproducing the value publicly.

## Completion boundary

M3-21 may become **Validated** only after:

- declared-baseline, selected-source/deployed identity, tracked-cleanliness, deployed-file identity, and active WordPress plugin-file binding checks passed and were retained privately;
- a suitable dedicated account and one of the three generic capability profiles were revalidated, or creation/reset was completed because reuse was unsuitable;
- WordPress/plugin health, idle state, staging-only API configuration, login, and intended page access were verified;
- the unlisted Viewer package was actually submitted through the intended Catalyst milestone-submission channel;
- support and the authorized window were recorded;
- any controlled dashboard synchronization remained within the permitted safeguards and its result was retained when exercised;
- access was revoked and verified, or a still-active authorized assessment window was explicitly recorded;
- private evidence was retained; and
- a sanitized executed record, including EVD-013, was created and registered.

The package has not yet been submitted, EVD-013 does not yet exist, M3-21 remains **Implemented, validation pending**, and Milestone 3 remains incomplete.
