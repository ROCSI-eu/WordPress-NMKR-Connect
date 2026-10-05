# Adoption and metrics

**Status:** IN PROGRESS — T0 SEALED; T1 SYNCHRONIZED PRE-CAMPAIGN CHECKPOINT CAPTURED; CAMPAIGN LAUNCHED\
**Document type:** Measurement contract and eventual results record

Measurement definitions and baselines must be established **before** campaign launch so that later results are defensible and reproducible.

## Contract targets

| Metric | Target | Planned primary source | Current status |
| --- | ---: | --- | --- |
| Plugin downloads | >=25 combined | WordPress.org download data + GitHub installable release-asset counter | CAMPAIGN ACTIVE — T1 remains the pre-campaign checkpoint at WordPress.org `77` cumulative and GitHub installable asset `0`; first campaign-period checkpoint pending |
| Active installations | >=10 | WordPress.org active-install signal; privacy-safe corroboration if required | T1 PRE-CAMPAIGN CHECKPOINT — public WordPress.org bucket remains `Fewer than 10` |
| Unique plugin-page visits | >=250 | Cloudflare Web Analytics `Visits` for the canonical landing page and fixed campaign window | CAMPAIGN ACTIVE — launch boundary `2026-10-05T17:28:48.194Z`; T1 `9 Visits / 9 Page views` remains pre-campaign context; first campaign-period checkpoint pending |
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

As of the formal T0 checkpoint on 27 September 2026, the public WordPress.org listing for `rocsi-connector-for-nmkr` is live at version `0.25.0` and reports **Fewer than 10 active installations**. The WordPress.org Plugins API returned `active_installs=0`, but that numeric field must not be interpreted as a precise zero-install claim because the public directory exposes a bucketed/rounded signal.

### Unique visits / Cloudflare Visits

Launch-gate procedure completed before M5-05:

1. create/confirm the Cloudflare Web Analytics site for `connector-for-nmkr.rocsi.eu`;
2. select manual JS snippet installation rather than zone-wide automatic injection;
3. install the Cloudflare beacon only in the canonical Next.js frontend;
4. verify that the canonical page emits exactly one beacon and that `/wp-dev/` and `/wp-demo/` do not;
5. verify that a controlled pre-campaign page load is received by Cloudflare Web Analytics;
6. record an exact UTC baseline cutoff `T0`;
7. capture the Cloudflare `Visits` value/filter context immediately before `T0`;
8. capture WordPress.org/GitHub download and active-install baselines at the same checkpoint;
9. only then begin M5-05 campaign activity.

The sealed `T0` baseline remains historical measurement context, but the targeted M5-05 campaign interval begins at the first real campaign action: `2026-10-05T17:28:48.194Z`. Campaign traffic must be evaluated against that launch boundary using the fixed canonical host/path and Cloudflare `Visits` definition. Do not subtract rolling 24-hour dashboard snapshots to manufacture a campaign delta, and do not count pre-launch activity toward the targeted campaign result.

If the Cloudflare dashboard cannot reproduce second-level `T0` precision, record the exact dashboard interval that is actually available and its relationship to `T0` rather than inventing finer precision.

### Event attendance

Count distinct human attendees. The same person attending multiple sessions counts once toward the milestone threshold unless the final contract explicitly says otherwise.

### Detailed feedback

A user counts only when feedback is actionable enough to inform a decision: context/task, observed problem or friction, expected/desired outcome, and enough detail to assess impact or reproduction. Generic praise alone is not detailed feedback.

## Privacy boundary

Publish aggregate counts, methodology, windows, source/platform, and redacted summaries. Keep names, emails, site URLs, IPs, raw analytics/client identifiers, private account dashboards, registration exports, account/site tokens, and raw private support/feedback messages out of the public repository.

## Baseline record

Formal pre-campaign baseline captured immediately before M5-05:

| Item | Value |
| --- | --- |
| Canonical landing URL | `https://connector-for-nmkr.rocsi.eu/` |
| Analytics source | Cloudflare Web Analytics |
| Contract traffic metric | Cloudflare `Visits` |
| Metric definition | External/direct entry pageview under Cloudflare's documented definition; not a deduplicated person/user count |
| Measurement host/path | `connector-for-nmkr.rocsi.eu` + `/` |
| Installation mode | Manual JS snippet in canonical Next.js frontend only |
| Baseline UTC (`T0`) | `2026-09-27T05:21:31Z` |
| Campaign launch UTC (`M5-05`) | `2026-10-05T17:28:48.194Z` |
| Europe/Bucharest local time | `2026-09-27 08:21:31` (UTC+03:00) |
| Cloudflare site/property | `Web Analytics for rocsi.eu` |
| Cloudflare filters | Site is in `rocsi.eu`; Exclude bots = `Yes`; Host = `connector-for-nmkr.rocsi.eu`; Path = `/` |
| Pre-campaign Cloudflare Visits snapshot/window | **10 Visits**; supporting **11 Page views**; dashboard window `Last 24 hours (GMT+3)` immediately before T0 |
| Cloudflare baseline interpretation | Rolling dashboard snapshot establishing pre-campaign state; campaign evidence must use an interval beginning at T0 rather than subtracting future rolling 24-hour snapshots blindly |
| Campaign end UTC | TBD — to be fixed by M5-05 execution |
| GitHub release asset | None at T0; repository Releases collection was empty |
| GitHub asset baseline | NOT APPLICABLE at T0 because no installable GitHub Release asset existed |
| WordPress.org plugin/slug | `ROCSI Connector for NMKR` / `rocsi-connector-for-nmkr` |
| WordPress.org version | `0.25.0` |
| WordPress.org download baseline | **42** cumulative downloads at synchronized T0 read |
| WordPress.org active-install baseline | **Fewer than 10** (public bucket; do not infer a precise count) |
| Evidence record | `M5-EVD-001` |

## Post-T0 GitHub release checkpoint

A formal GitHub Release for the already-public `0.25.0` version was published after T0. This does **not** rewrite the sealed T0 baseline, where no GitHub Release asset existed.

| Item | Value |
| --- | --- |
| GitHub Release | https://github.com/ROCSI-eu/WordPress-NMKR-Connect/releases/tag/0.25.0 |
| Published UTC | `2026-10-05T13:47:12Z` |
| Tag | `0.25.0` |
| Tag target | `5b12e674e6312170cae132f40c710e97fe29dfdf` |
| Exact source tree | `941b7f21ecd67a22a0ae84f90cc700fb88cbceb3` |
| Installable asset | `rocsi-connector-for-nmkr.0.25.0.zip` |
| GitHub-reported asset SHA-256 | `d0f80324736a4e91b21bb512e22ee03c249c2b730076a35bafa29a70daf36ef6` |
| Initial installable-asset `download_count` | **0** |
| Checksum asset | `rocsi-connector-for-nmkr.0.25.0.zip.sha256` |
| Initial checksum-asset `download_count` | **0** |
| Evidence record | `M5-EVD-002` |

This is a distribution/provenance checkpoint, not the synchronized pre-campaign adoption checkpoint. The required synchronized Cloudflare/WordPress.org/GitHub pre-campaign checkpoint was subsequently captured as `M5-EVD-003` before M5-05 launched.

## Synchronized pre-campaign checkpoint (T1)

A new bounded checkpoint was captured after the formal GitHub Release and before any M5-05 campaign action. This preserves the sealed T0 record while establishing the current distribution/traffic state from which campaign execution can begin.

| Item | Value |
| --- | --- |
| Checkpoint window UTC | `2026-10-05T16:59:31Z` to `2026-10-05T17:00:11Z` |
| Europe/Bucharest local window | approximately `2026-10-05 19:59:31–20:00:11` (UTC+03:00) |
| Canonical landing URL | `https://connector-for-nmkr.rocsi.eu/` |
| Cloudflare source | Web Analytics for `rocsi.eu` |
| Cloudflare filters | Site is in `rocsi.eu`; Exclude bots = `Yes`; Host = `connector-for-nmkr.rocsi.eu`; Path = `/` |
| Cloudflare window | `Last 24 hours (GMT+3)` |
| Cloudflare Visits | **9** |
| Cloudflare Page views | **9** |
| WordPress.org version | `0.25.0` |
| WordPress.org cumulative downloads | **77** |
| WordPress.org active-install signal | **Fewer than 10** public bucket; API field `active_installs=0` is not treated as a precise zero-install claim |
| GitHub Release | `0.25.0` |
| GitHub installable asset `download_count` | **0** |
| GitHub checksum asset `download_count` | **0** |
| Evidence record | `M5-EVD-003` |

The WordPress.org cumulative counter increased from 42 at T0 to 77 at T1, a numerical change of +35 before the targeted M5-05 campaign launched. This is **pre-campaign platform activity**, not evidence that the planned campaign caused those downloads and not proof of 35 distinct human downloaders.

The Cloudflare T1 value is another rolling 24-hour snapshot, so it must not be subtracted from T0 to manufacture a campaign visit delta. M5-05 launched at `2026-10-05T17:28:48.194Z`, shortly after T1; that timestamp is now the fixed targeted-campaign launch boundary.

## Campaign launch boundary

| Item | Value |
| --- | --- |
| First targeted campaign action | X post from `@NMKRConnect` |
| Public reference | https://x.com/NMKRConnect/status/2107161092187365609 |
| Launch UTC | `2026-10-05T17:28:48.194Z` |
| Timestamp basis | Public X status ID `2107161092187365609` encodes the publication timestamp |
| Pre-campaign checkpoint | `M5-EVD-003` |
| Campaign evidence record | `M5-EVD-004` |

The first post establishes the targeted M5-05 campaign start; it does not by itself prove any download, active-install, visit, attendance, or feedback threshold. The WordPress.org increase from 42 at T0 to 77 at T1 remains pre-campaign activity and is not attributed to this campaign.

## Checkpoint record

Add dated checkpoints during the campaign/support period rather than relying on one retrospective screenshot. `M5-EVD-003` is the synchronized pre-campaign checkpoint after GitHub Release publication, and `M5-EVD-004` fixes the first real campaign-action UTC. The next metric checkpoint must preserve the platform source/window definitions and must not infer campaign results by blindly subtracting rolling Cloudflare 24-hour snapshots.

## Final results

Not yet available. Populate only from verified measurement sources after the relevant campaign/adoption window.
