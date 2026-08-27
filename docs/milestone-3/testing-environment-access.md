# Testing-environment access guide

## Purpose and evidence status

This public-safe guide prepares controlled non-production WordPress access for an authorized Catalyst assessment recipient. Documentation readiness is not proof that access was provisioned. M3-21 remains **Implemented, validation pending** until the private execution is completed and a sanitized result is registered. That later executed result is expected to use EVD-013; this preparation does not create or claim EVD-013.

## Environment boundary

The owner must use:

- a controlled non-production WordPress environment containing only synthetic or non-customer data;
- access limited to the specifically authorized Catalyst assessor;
- no customer data, production credentials, production environment access, or private infrastructure-administration access;
- the exact source SHA selected for assessment and the exact deployed SHA, recorded privately;
- a healthy WordPress and plugin state before activation;
- an idle synchronization state unless an explicitly authorized activity requires otherwise; and
- no live NMKR synchronization unless it is separately and explicitly authorized.

## Account boundary

Use a dedicated temporary account and unique temporary password with least privilege sufficient for the disclosed assessment scope. Select the narrowest practical role privately after checking the plugin capability requirements; this guide does not prescribe a WordPress role. If a broad role is operationally necessary, retain an explicit private rationale. Never reuse personal administrator credentials, share credentials, or permit account sharing. Define activation and expiry times and revoke the account after the access window.

## Permitted scope

Read-oriented access may cover applicable:

- plugin administration pages;
- dashboard and status information;
- projects and NFT displays;
- configuration and retained staging logs where their intended WordPress/plugin pages are necessary to the assessment scope;
- documentation and evidence-relevant controls; and
- ordinary navigation and non-destructive inspection.

## Prohibited scope

Without separate explicit authorization, prohibit:

- account or credential sharing;
- deleting users, posts, media, projects, tokens, options, or database content;
- installing, deleting, or editing plugins or themes;
- editing WordPress or server files;
- changing infrastructure, DNS, CDN, web-server, database, or hosting configuration;
- starting real synchronization;
- load, stress, fault, or destructive testing;
- exposing production credentials, private URLs, database output, customer information, or any staging credential or log beyond the accepted boundary below;
- copying, sharing, publishing, or reusing visible staging-only credentials or log content outside the assessment; and
- attempting privilege escalation or access outside the supplied environment.

## Authorized staging visibility boundary

The owner explicitly accepts, for the authorized assessment window, the residual non-production exposure risk that a staging-only, replaceable API credential or retained staging log may be visible to the specifically authorized Catalyst assessor through intended WordPress/plugin pages when that visibility is necessary for the assessment scope. This acceptance does not authorize access by anyone else and does not relax the prohibited scope.

No customer data, production credential, or production environment access may be present. Any visible staging-only credential or log content must remain confined to the assessment: copying, sharing, publishing, or reusing it outside the assessment is prohibited. Rotation or reset of staging credentials and cleanup of retained staging logs are recommended final-state actions after the access window; they are not prerequisites for initial delivery under this accepted boundary.

Actual secret values, logs, private URLs, recipient identities, and populated access details must never be committed or pasted into pull-request discussion, CI, issues, public Catalyst fields, or public documentation.

## Private Google Doc template

> **PRIVATE TEMPLATE — copy to a restricted Google Doc outside this repository and populate only there.** Populated credentials and the restricted document URL must never be pasted into GitHub, commits, pull requests, issues, CI, repository files, or public Catalyst fields. Configure access for specifically authorized recipients only, never public or link-wide access.

- **Access purpose:** Controlled, read-oriented Catalyst assessment of the disclosed plugin scope.
- **Environment class:** [PRIVATE NON-PRODUCTION ENVIRONMENT CLASS]
- **Authorized recipient:** [PRIVATE DELIVERY RECIPIENT]
- **Login URL:** [PRIVATE ENVIRONMENT LOGIN URL]
- **Temporary username:** [PRIVATE TEMPORARY USERNAME]
- **Temporary password:** [PRIVATE TEMPORARY PASSWORD]
- **Role or capability class:** [ACCOUNT ROLE OR CAPABILITY CLASS]
- **Activation:** [ACCESS START UTC]
- **Expiry:** [ACCESS END UTC]
- **Login instructions:** Open the private login URL, enter the temporary credentials, confirm the disclosed environment, and use only the permitted scope.
- **Permitted actions:** Read-oriented navigation and non-destructive inspection of the applicable plugin pages, displays, status, configuration presence, documentation, and evidence-relevant controls.
- **Prohibited actions:** Credential sharing; destructive or mutating actions; real synchronization; load/fault testing; copying, sharing, publishing, or reusing visible staging-only credentials or logs outside the assessment; privilege escalation; or access outside the supplied environment.
- **Support route:** [PRIVATE SUPPORT CONTACT]
- **Issue reporting:** Send the time, affected page category, safe reproduction steps, and a sanitized symptom through the private support route. Do not send credentials, secret values, authentication state, or raw private output.
- **Credential rule:** Do not share the account or credentials. Ask the support contact to authorize any recipient change.
- **Logout guidance:** Log out after each session and close the browser session; do not retain the password in a shared browser or document.

## Private execution checklist

Complete outside the repository:

1. [ ] Confirm the intended controlled non-production environment and specifically authorized Catalyst assessor.
2. [ ] Record the exact public source baseline declared for the assessment and later EVD-013 record, the exact source SHA selected for the assessment, and the exact deployed SHA.
3. [ ] Verify that the selected source SHA equals the declared public source baseline, or document the method and successful result for exact Git-tree equivalence to that baseline. Establish exact SHA equality or document the method and successful result for exact Git-tree equivalence.
4. [ ] Confirm the deployed checkout has a clean tracked worktree and that its deployed tracked files match the selected source tree.
5. [ ] Stop before account delivery or login verification for any mismatch, dirty tracked state, ambiguous identity, or unexplained local modification.
6. [ ] Confirm WordPress and the plugin are healthy and no customer data, production credentials, or production access is present.
7. [ ] Create or reset the dedicated temporary account.
8. [ ] Apply the narrowest practical capability profile.
9. [ ] Set a unique temporary password.
10. [ ] Verify successful login privately.
11. [ ] Verify intended plugin pages are accessible.
12. [ ] Verify prohibited capabilities are unavailable where applicable.
13. [ ] Create the restricted private Google Doc.
14. [ ] Deliver it only to the authorized recipient through the intended private channel.
15. [ ] Record activation UTC and intended expiry UTC.
16. [ ] Keep a private support and incident record during the access window.
17. [ ] Revoke or delete the temporary account after the window.
18. [ ] Confirm login no longer succeeds after revocation.
19. [ ] Rotate or reset staging-only credentials and clean up retained staging logs as recommended final-state actions.
20. [ ] Record final state and any residue or anomaly.

## Private evidence manifest template

Keep this manifest private and populate it outside the repository:

- requirement ID: [REQUIREMENT ID]
- planned evidence ID: [PLANNED EVIDENCE ID]
- generic environment class: [GENERIC ENVIRONMENT CLASS]
- declared public source baseline: [DECLARED PUBLIC SOURCE BASELINE]
- selected source SHA: [SELECTED SOURCE SHA]
- declared-baseline comparison method: [DECLARED BASELINE COMPARISON METHOD]
- declared-baseline comparison result: [DECLARED BASELINE COMPARISON RESULT]
- deployed SHA: [DEPLOYED SHA]
- SHA equality or tree-equivalence method: [IDENTITY COMPARISON METHOD]
- SHA equality or tree-equivalence result: [IDENTITY COMPARISON RESULT]
- deployed tracked-worktree cleanliness result: [TRACKED WORKTREE CLEANLINESS RESULT]
- deployed-file identity result: [DEPLOYED FILE IDENTITY RESULT]
- account identifier kept private: [PRIVATE ACCOUNT IDENTIFIER]
- role or capability class: [ACCOUNT ROLE OR CAPABILITY CLASS]
- account-created UTC: [ACCOUNT CREATED UTC]
- login-verified UTC: [LOGIN VERIFIED UTC]
- delivery UTC: [DELIVERY UTC]
- delivery-channel class: [DELIVERY CHANNEL CLASS]
- access-start UTC: [ACCESS START UTC]
- access-end UTC: [ACCESS END UTC]
- support availability: [SUPPORT AVAILABILITY]
- expiry or revocation UTC: [EXPIRY OR REVOCATION UTC]
- post-revocation verification result: [POST-REVOCATION VERIFICATION RESULT]
- anomalies: [ANOMALIES OR NONE]
- cleanup/final state: [CLEANUP AND FINAL STATE]
- hashes of retained private evidence files: [APPROVED SHA-256 IDENTIFIERS]

The public repository may later publish only sanitized facts and approved SHA-256 identifiers—never private values, evidence locations, or restricted-document locations.

## Stop conditions

Stop the process if:

- the target environment is production;
- the selected source SHA or deployed SHA is uncertain;
- the selected source SHA does not equal the declared public source baseline without proven exact Git-tree equivalence;
- the SHAs differ without proven exact tree equivalence;
- the deployed tracked worktree is dirty;
- deployed tracked files do not match the selected source tree;
- identity is ambiguous or a local modification is unexplained;
- sensitive or customer data may be exposed;
- the intended recipient cannot be authenticated;
- the account has broader access than intended without documented justification;
- WordPress or the plugin is unhealthy;
- synchronization or another mutation is unexpectedly active;
- login verification fails;
- revocation cannot be confirmed; or
- any credential appears in public history.

If public exposure is suspected, stop delivery and follow the owner's private incident and credential-rotation process; do not reproduce the value in a public report.

## Completion boundary

M3-21 may become **Validated** only after all of the following are true:

- the account exists;
- the selected source SHA and deployed SHA were retained privately;
- the selected source SHA matched the declared public source baseline, or documented exact Git-tree equivalence passed, and the comparison was retained privately;
- exact SHA equality or documented exact tree equivalence passed;
- deployed tracked-worktree cleanliness and deployed-file identity checks passed and were retained privately;
- private delivery occurred;
- login and intended access were verified;
- the support and expiry model is recorded;
- the account was revoked, or a still-active authorized assessment window is explicitly documented;
- private evidence was retained; and
- a sanitized executed record was added to the evidence register.

Until then, Milestone 3 remains incomplete.
