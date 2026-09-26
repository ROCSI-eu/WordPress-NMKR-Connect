# Adoption and metrics

**Status:** IN PROGRESS — MEASUREMENT CONTRACT SELECTED; BASELINE NOT YET CAPTURED\
**Document type:** Measurement contract and eventual results record

Measurement definitions and baselines must be established **before** campaign launch so that later results are defensible and reproducible.

## Contract targets

| Metric | Target | Planned primary source | Current status |
| --- | ---: | --- | --- |
| Plugin downloads | >=25 combined | WordPress.org download data + GitHub installable release-asset counter | BASELINE PENDING |
| Active installations | >=10 | WordPress.org active-install signal; privacy-safe corroboration if required | PUBLIC LISTING LIVE; FORMAL BASELINE PENDING |
| Unique plugin-page visits | >=250 | Cloudflare Web Analytics `Visits` for the canonical landing page and fixed campaign window | CONTRACT SELECTED; BEACON/BASELINE PENDING |
| Community attendance | >=10 distinct attendees | Event attendance record | NOT STARTED |
| Detailed user feedback | >=5 distinct users | Feedback/support records using public-safe opaque IDs | NOT STARTED |

## Canonical landing page and website measurement contract

Canonical Milestone 5 campaign/measurement URL:

`https://connector-for-nmkr.rocsi.eu/`

The selected analytics source is **Cloudflare Web Analytics**, installed manually in the canonical Next.js frontend rather than enabled through zone-wide automatic injection. This keeps the public landing page measurable while avoiding deliberate analytics injection into the supporting `/wp-dev/` and `/wp-demo/` WordPress environments.

For `M5-REQ-004`, the accepted Statement of Milestones phrase **"unique visits"** is operationalized as Cloudflare's source-defined **`Visits`** metric.

Cloudflare currently defines a Visit as a page view that originated from a different website or a direct link, where the HTTP referrer does not match the hostname; one Visit can contain multiple page views:

- https://developers.cloudflare.com/web-analytics/data-metrics/high-level-metrics/

This is a metric-definition decision, not a claim that Cloudflare Visits represent deduplicated people, browsers, devices, or globally unique users. Final evidence must therefore report **Cloudflare Visits** and must not silently relabel the result as "unique people", "unique users", or another stronger concept.

The primary M5-REQ-004 evidence view is:

- analytics source: Cloudflare Web Analytics;
- host: `connector-for-nmkr.rocsi.eu`;
- path: `/`;
- metric: `Visits`;
- campaign interval: fixed before M5-05 launch and recorded with UTC boundaries;
- target: at least 250 Visits during that fixed campaign/adoption interval.

Page views may be recorded as supporting context but are not interchangeable with the contractual Visits metric.

## Cloudflare privacy and measurement boundary

Cloudflare describes Web Analytics as privacy-first, says that Web Analytics does not collect or use visitors' personal data, and states that it does not track individual end users across customers' Internet properties:

- https://developers.cloudflare.com/web-analytics/about/
- https://developers.cloudflare.com/web-analytics/data-metrics/data-origin-and-collection/

That product description supports the project's privacy-minimal measurement choice, but this repository does **not** make a legal conclusion that use of the service is exempt from every disclosure or consent obligation in every jurisdiction.

Known measurement limitations to preserve in evidence:

- the Web Analytics beacon is client-side JavaScript/RUM, so blocked scripts, unsupported clients, network failures, or beacon delivery failures may undercount traffic;
- a Cloudflare Visit is not a deduplicated human identity and repeat visits by the same person may contribute more than one Visit;
- the metric is not claimed to be bot-free; browser-capable automation can potentially affect client-side analytics;
- Cloudflare Web Analytics currently does not log query strings/UTM parameters and does not support custom events, so channel attribution and funnel events require separate evidence if needed;
- dashboard data may use adaptive sampling/resolution depending on the requested view; preserve the displayed source/window and do not invent precision.

## Measurement rules

### Downloads

Native platform counters may not prove literal unique human downloaders. Record exactly what each platform measures. The planned primary evidence is the delta in WordPress.org download counts plus the GitHub **installable release asset** `download_count`, with any uniqueness limitation disclosed.

If stronger uniqueness corroboration is required, prefer voluntary, privacy-safe adopter/download confirmations using opaque public IDs while retaining any identity mapping privately. Do not introduce invasive telemetry solely to manufacture a Catalyst metric.

### Active installations

Prefer the public WordPress.org active-install signal. If WordPress.org reports a bucket such as `Fewer than 10` or `10+`, report that bucket rather than inventing a precise value. Additional aggregate installation data may be supporting evidence only after its privacy and measurement meaning are verified.

As of the 26 September 2026 M5-04 reconciliation, the public WordPress.org listing for `rocsi-connector-for-nmkr` is live at version `0.25.0` and reports **fewer than 10 active installations**. This is a checkpoint, not yet the formal M5-05 baseline.

### Unique visits / Cloudflare Visits

Before M5-05 launches:

1. create/confirm the Cloudflare Web Analytics site for `connector-for-nmkr.rocsi.eu`;
2. select manual JS snippet installation rather than zone-wide automatic injection;
3. install the Cloudflare beacon only in the canonical Next.js frontend;
4. verify that the canonical page emits exactly one beacon and that `/wp-dev/` and `/wp-demo/` do not;
5. verify that a controlled pre-campaign page load is received by Cloudflare Web Analytics;
6. record an exact UTC baseline cutoff `T0`;
7. capture the Cloudflare `Visits` value/filter context immediately before `T0`;
8. capture WordPress.org/GitHub download and active-install baselines at the same checkpoint;
9. only then begin M5-05 campaign activity.

Campaign traffic is evaluated from `T0` forward using the fixed canonical host/path and Cloudflare `Visits` definition. Pre-baseline verification traffic must not be counted toward the campaign target.

If the Cloudflare dashboard cannot reproduce second-level `T0` precision, record the exact dashboard interval that is actually available and its relationship to `T0` rather than inventing finer precision.

### Event attendance

Count distinct human attendees. The same person attending multiple sessions counts once toward the milestone threshold unless the final contract explicitly says otherwise.

### Detailed feedback

A user counts only when feedback is actionable enough to inform a decision: context/task, observed problem or friction, expected/desired outcome, and enough detail to assess impact or reproduction. Generic praise alone is not detailed feedback.

## Privacy boundary

Publish aggregate counts, methodology, windows, source/platform, and redacted summaries. Keep names, emails, site URLs, IPs, raw analytics/client identifiers, private account dashboards, registration exports, account/site tokens, and raw private support/feedback messages out of the public repository.

## Baseline record

Populate immediately before M5-05:

| Item | Value |
| --- | --- |
| Canonical landing URL | `https://connector-for-nmkr.rocsi.eu/` |
| Analytics source | Cloudflare Web Analytics |
| Contract traffic metric | Cloudflare `Visits` |
| Metric definition | External/direct entry pageview under Cloudflare's documented definition; not a deduplicated person/user count |
| Measurement host/path | `connector-for-nmkr.rocsi.eu` + `/` |
| Installation mode | Manual JS snippet in canonical Next.js frontend only |
| Baseline UTC (`T0`) | TBD |
| Pre-campaign Cloudflare Visits snapshot/window | TBD |
| Campaign end UTC | TBD |
| GitHub release asset | TBD |
| GitHub asset baseline | TBD |
| WordPress.org plugin/slug | `ROCSI Connector for NMKR` / `rocsi-connector-for-nmkr` |
| WordPress.org version | `0.25.0` |
| WordPress.org download baseline | TBD |
| WordPress.org active-install baseline | TBD — current pre-baseline checkpoint is `Fewer than 10` |

## Checkpoint record

Add dated checkpoints during the campaign/support period rather than relying on one retrospective screenshot. Each checkpoint should reference an `M5-EVD-*` entry and preserve the Cloudflare metric definition, filters/window, and measurement limitations.

## Final results

Not yet available. Populate only from verified measurement sources after the relevant campaign/adoption window.
