# Milestone 4 executed evidence — M4-04 — 2026-08-30

## Purpose and scope

This public-safe record documents completion of the M4-04 source analysis of output contexts and client-facing diagnostic disclosure. The review was documentation-only: it inspected the exact baseline without changing PHP, JavaScript, tests, database behavior, options, transients, synchronization, capabilities, nonces, APIs, dependencies, workflows, or any other runtime behavior.

The scoped analysis found no confirmed unsafe rendering sink, stored or reflected cross-site-scripting path, or inappropriate client-facing diagnostic disclosure on this baseline. Consequently, no runtime remediation was justified. This is a source-analysis conclusion, not runtime or production security evidence.

## Exact baseline

- Baseline commit: `fd1ac072aeb37b189c9734e0602a58c8c6b8a30c`
- Baseline tree: `af40fd04381a57883e961b71fddcb2777c1cda50`
- Evidence date: `2026-08-30`

The commit and tree were obtained from the clean exact baseline before documentation was written. All conclusions in this record are scoped to that source tree.

## Source-only analysis methodology

The analysis traced representative stored and reflected values from request, API, option, transient, database, and synchronization state through normalization and serialization to their final server or browser output context. It reviewed HTML text, attribute, URL, JSON, and DOM-writing boundaries; distinguished fixed markup from dynamic values; and inspected whether client-visible failures could receive internal diagnostics.

The principal inspected boundaries and flows were:

- dashboard status, statistics, notices, API-connection messages, and escaped UI-log fields;
- synchronization progress, current-item and terminal-error serialization, browser formatting, and status rendering;
- analytics administration filters, prepared queries, JSON responses, KPI/chart/table construction, dynamic identifier rows, and export status;
- public analytics REST and AJAX ingestion, including bounded identifiers and metadata and minimal responses;
- settings page fields, validation notices, attributes, and inline text updates;
- project administration selection, project/token values, links, and image output;
- shortcode list, grid, carousel, project, and token markup, including text, attributes, links, and media helpers;
- logging helpers and the distinction between escaped administrative display and server-side diagnostics; and
- existing public source-contract, transport-disclosure, shortcode-image, and browser structure regressions.

This was manual source tracing supported by targeted repository searches. No penetration testing, browser execution, dynamic scanning, deployment, database inspection, or request replay formed part of M4-04.

## Findings and rejected hypotheses

No hypothesis reached the threshold for a confirmed vulnerability or runtime change. Representative rejected hypotheses include:

- **Synchronization current-item or error data reaches an executable DOM sink.** The inspected progress paths use text-only insertion for dynamic messages or fixed, locally constructed status markup. Terminal failure serialization replaces the private diagnostic with generic copy, clears `current_item`, and removes `technical_details`.
- **Analytics project or token identifiers are interpolated into table HTML.** Dynamic row values are assigned with `textContent`; the inspected `innerHTML` uses clear operations or fixed/localized structural states rather than analytics row data.
- **Stored dashboard logs render as markup.** Log timestamps, types, messages, data, and their attribute forms are escaped for their respective server-generated contexts.
- **Administrative query inputs can alter analytics SQL structure and then surface unsafe results.** The inspected ranges, buckets, shortcode types, identifiers, sort/pagination choices, and spans are allow-listed or bounded, while dynamic SQL values use prepared statements. Dynamic analytics rows remain text-only in the browser.
- **Project, token, or shortcode content is emitted without contextual protection.** Representative text, attribute, and URL outputs use `esc_html`, `esc_attr`, and `esc_url`, with fixed markup assembled by the plugin.
- **Media fallback accepts an unsafe active URL scheme.** Direct and resolved public image candidates are restricted to credential-free HTTPS forms; configured IPFS gateway bases are validated and normalized, and rendered URL/attribute values are escaped.
- **Transport, synchronization, or log-clear failures expose raw internal diagnostics to clients.** Inspected response boundaries use generic public messages and bounded error codes. Existing public regressions reject raw transport detail and confirm removal of `technical_details`; detailed diagnostics remain on server-side logging paths.

These rejections are baseline-specific source conclusions. They do not prove that every possible input, extension, browser behavior, future code path, or third-party interaction is safe.

## Existing mitigations observed

The exact baseline already contains relevant defense-in-depth controls:

- contextual WordPress escaping for HTML text, attributes, and URLs;
- `textContent` or equivalent text-only DOM insertion for inspected dynamic synchronization messages and analytics rows;
- fixed server-generated or locally constructed markup where HTML insertion is used in the inspected paths;
- generic public error copy and bounded public error codes rather than raw transport, exception, or database details;
- explicit removal of `technical_details` from the verified failed-terminal progress response;
- bounded and allow-listed analytics identifiers, types, ranges, buckets, ordering, pagination, and metadata;
- prepared SQL for dynamic analytics, project, synchronization, and related values; and
- credential-free HTTPS validation, IPFS input checks, normalized gateway bases, and contextual URL/attribute escaping in media helpers.

## Assurance gap and M4-06 deferral

Existing browser suites primarily verify page structure, controls, and non-mutating behavior. They do not comprehensively exercise adversarial values through final browser rendering. In particular, current structural coverage does not prove that HTML-shaped progress/current-item values and analytics identifiers always remain literal text and never create executable DOM.

The findings register therefore records non-vulnerability assurance item `A-01`. Focused synthetic final-rendering assertions are deferred to M4-06, where consolidated public-safe security regressions belong. This deferral does not convert the gap into a confirmed XSS defect and does not justify a runtime change in M4-04.

## Validation classification and execution

Under the canonical validation policy, this package is **Docs / metadata** because it changes only Milestone 4 documentation and records source-analysis conclusions. Validation is limited to the focused documentation checks, public-safety review, and PR CI.

No private DEV deployment, authenticated or unauthenticated browser execution, database mutation, option or transient change, real or synthetic NMKR synchronization, or NMKR traffic was required or performed for M4-04. Private exact-head runtime evidence was not required for this documentation-only package.

## Limitations and explicit non-claims

This record does not claim universal XSS resistance, comprehensive adversarial rendering coverage, penetration testing, formal certification, production security assurance, complete security assurance, Catalyst delivery or approval, or completion of Milestone 4. It does not establish the behavior of a deployed site, a particular browser, third-party extensions, future changes, or every data-flow permutation.

The analysis may not detect a behavior that depends on runtime configuration, external code, malformed browser parsing, or an uninspected composition of otherwise reviewed helpers. M4-06 assertions and later milestone packages remain separate work. M4-05 is the next planned technical package, and Milestone 4 remains incomplete.

## Public/private evidence boundary

Public evidence consists only of the exact Git identifiers, defensive source-analysis methodology, reviewable repository paths and controls, sanitized conclusions, limitations, and focused check outcomes. No exploit payloads, abuse instructions, private URLs, hosts, paths, credentials, nonces, authentication state, logs, database output, customer data, screenshots, traces, reports, artifacts, or private evidence locations are included.

No private evidence was generated for M4-04. If a later package requires private exact-head runtime validation, only sanitized conclusions may enter the public record and all private inputs and artifacts must remain in their approved private locations.
