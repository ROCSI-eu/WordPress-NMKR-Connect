# Repository validation

- Use [`docs/validation-policy.md`](docs/validation-policy.md) as the canonical validation policy and classify risk before selecting checks.
- Use [`docs/deployment-policy.md`](docs/deployment-policy.md) as the canonical DEV/STAGING/PRODUCTION deployment policy.
- Use [`docs/wordpress-org-release.md`](docs/wordpress-org-release.md) as the canonical WordPress.org release/update checklist.
- Preserve exact-head trust and keep all committed and console output public-safe.
- Never run a real NMKR synchronization by default, and never expose private inputs, environment details, authentication state, logs, or test artifacts.
- Use the smallest profile required by the policy; do not repeat full private validation when a targeted profile is sufficient.

## Authority and AGENTIC-NO-LOOP-01

- At the start of every mandate or successor conversation, resolve live `main`, read this `AGENTS.md` and relevant canonical policies linked above, and record the observed baseline, rule paths and complete objective in the existing checkpoint. Use the current [README](README.md) and applicable release or evidence documents. Project Settings and handoffs prompt rule intake; they do not replace current GitHub rules or grant permission. If material rules cannot be read or reconciled, report the gap and stop affected writes or final claims.
- This repository owns plugin code, tests, synchronization semantics, packaging, WordPress.org release and plugin evidence. The separate website operator owns website content, hosting, routing, and WordPress environment infrastructure. Cross-repository work requires the relevant owner's current rules and authorization; never publish private repository locations, endpoints, configuration or evidence here.
- Deliver one coherent objective through applicable stages: (1) reconcile live state, rules and scope; (2) implement the smallest useful isolated change; (3) verify proportionately and reuse valid scope-matched PASS evidence; (4) integrate via the existing PR, required CI/review and explicitly authorized merge; (5) perform separately authorized environment deployment or WordPress.org release only when the objective requires it; (6) verify actual acceptance, integrity and recovery as applicable; (7) reconcile canonical documentation and evidence in main or the existing authoritative record; (8) compare every initial acceptance condition against evidence before FINAL/PASS. Docs-only changes normally need no private deployment or runtime validation. Merge, private acceptance, public release and distribution verification are distinct outcomes.
- Continue autonomously within approved scope, including bounded fixes and documentation reconciliation. Reconfirm only material changes or facts needed for the next action. Reuse existing tooling, QA and scope-matched evidence; protect shared resources and other agents' branches. Preserve explicit merge, deployment, release, security, real synchronization, cost and destructive-operation gates. Guard consequential writes by exact SHA or equivalent target and verify actual outcomes; stop and reconcile ambiguous or concurrently changed targets before retrying.
- Pending CI/review is not a new approval gate. Follow through when practical in the current session; otherwise provide one precise PENDING checkpoint and next authorized action under AGENTIC-INTERACTION-01. No repetitive polling, redundant approval, background-execution claim or parallel tracking system.

## AGENTIC-CI-MINIMAL-01 — risk-based existing validation

- Classify changes using [validation policy](docs/validation-policy.md), including the highest applicable risk class and its required review/validation. Docs/metadata use public CI, focused diff/link/table/path/command checks and `git diff --check`; do not run private WordPress/Playwright/DB profiles, real synchronization or deploy just for documentation. For runtime changes retain the required exact-head, security and state checks.
- Keep the existing public-safe CI workflow and all applicable required checks, including configured Draft and main runs; Draft is not runner-free. Reuse successful exact-HEAD checks and reviewed-tree equivalence only within the canonical policy; a materially changed HEAD/tree requires affected verification. No redundant rerun, empty commit, duplicate review request or new CI infrastructure merely for ceremony. Never relax safety, protection or release gates to reduce CI.
- Preserve [deployment policy](docs/deployment-policy.md): DEV is routine development validation; STAGING is WordPress.org release preparation; PRODUCTION consumes the official public plugin, not Git PR heads. No blanket LIVE-FIRST override, private diagnostic disclosure or real synchronization without distinct authorization. Use the [WordPress.org checklist](docs/wordpress-org-release.md) for actual releases and do not equate a Git merge with publication.

## AGENTIC-INTERACTION-01 — Checkpoint-uri conversaționale

- Se aplică tuturor discuțiilor și fluxurilor NMKR, existente și viitoare, privind website-ul și pluginul WordPress.
- Când agentul oprește execuția în așteptarea utilizatorului, prezintă un **buton efectiv apăsabil „Continuă”** dacă interfața permite și pasul următor nu cere o decizie nouă. Acțiunea trebuie să transmită reluarea de la ultimul checkpoint verificat, nu să fie decorativă.
- Când este necesară o aprobare sau o alegere, prezintă **butoane cu decizia concretă** (de exemplu „Aprob merge” sau „Aprob deployment”); nu substitui aprobarea explicită cu un „Continuă” generic.
- Înainte de oprire, precizează concis ce s-a finalizat, ce urmează, blocajele și gate-ul necesar. Continuă autonom în mandatul deja aprobat și nu cere repetat același acord.
- Afișarea sau apăsarea unui buton nu conferă aprobări în avans și nu modifică regulile existente de validare, securitate, release, merge, deployment ori operațiuni destructive.
- Dacă interfața nu suportă controale interactive, formulează clar următoarea acțiune în text, fără a pretinde că este un buton funcțional.
