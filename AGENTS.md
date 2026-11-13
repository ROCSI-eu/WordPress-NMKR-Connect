# Repository validation

- Use [`docs/validation-policy.md`](docs/validation-policy.md) as the canonical validation policy and classify risk before selecting checks.
- Use [`docs/deployment-policy.md`](docs/deployment-policy.md) as the canonical DEV/STAGING/PRODUCTION deployment policy.
- Use [`docs/wordpress-org-release.md`](docs/wordpress-org-release.md) as the canonical WordPress.org release/update checklist.
- Preserve exact-head trust and keep all committed and console output public-safe.
- Never run a real NMKR synchronization by default, and never expose private inputs, environment details, authentication state, logs, or test artifacts.
- Use the smallest profile required by the policy; do not repeat full private validation when a targeted profile is sufficient.

## AGENTIC-INTERACTION-01 — Checkpoint-uri conversaționale

- Se aplică tuturor discuțiilor și fluxurilor NMKR, existente și viitoare, privind website-ul și pluginul WordPress.
- Când agentul oprește execuția în așteptarea utilizatorului, prezintă un **buton efectiv apăsabil „Continuă”** dacă interfața permite și pasul următor nu cere o decizie nouă. Acțiunea trebuie să transmită reluarea de la ultimul checkpoint verificat, nu să fie decorativă.
- Când este necesară o aprobare sau o alegere, prezintă **butoane cu decizia concretă** (de exemplu „Aprob merge” sau „Aprob deployment”); nu substitui aprobarea explicită cu un „Continuă” generic.
- Înainte de oprire, precizează concis ce s-a finalizat, ce urmează, blocajele și gate-ul necesar. Continuă autonom în mandatul deja aprobat și nu cere repetat același acord.
- Afișarea sau apăsarea unui buton nu conferă aprobări în avans și nu modifică regulile existente de validare, securitate, release, merge, deployment ori operațiuni destructive.
- Dacă interfața nu suportă controale interactive, formulează clar următoarea acțiune în text, fără a pretinde că este un buton funcțional.
