# Adoption and metrics

**Status:** NOT STARTED\
**Document type:** Measurement contract and eventual results record

Measurement definitions and baselines must be established **before** campaign launch so that later results are defensible and reproducible.

## Contract targets

| Metric | Target | Planned primary source | Initial status |
| --- | ---: | --- | --- |
| Plugin downloads | >=25 combined | WordPress.org download data + GitHub installable release-asset counter | NOT STARTED |
| Active installations | >=10 | WordPress.org active-install signal when listing is live; privacy-safe corroboration if required | NOT STARTED |
| Unique plugin-page visits | >=250 | Canonical landing-page analytics | NOT STARTED |
| Community attendance | >=10 distinct attendees | Event attendance record | NOT STARTED |
| Detailed user feedback | >=5 distinct users | Feedback/support records using public-safe opaque IDs | NOT STARTED |

## Measurement rules

### Downloads

Native platform counters may not prove literal unique human downloaders. Record exactly what each platform measures. The planned primary evidence is the delta in WordPress.org download counts plus the GitHub **installable release asset** `download_count`, with any uniqueness limitation disclosed.

If stronger uniqueness corroboration is required, prefer voluntary, privacy-safe adopter/download confirmations using opaque public IDs while retaining any identity mapping privately. Do not introduce invasive telemetry solely to manufacture a Catalyst metric.

### Active installations

Prefer the public WordPress.org active-install signal once available. If WordPress.org reports a bucket such as `10+`, report that bucket rather than inventing a precise value. Additional aggregate installation/licensing data may be supporting evidence only after its privacy/consent and measurement meaning are verified.

### Unique visits

Before launch, define:

- one canonical plugin landing URL;
- analytics source;
- definition of `unique visitor/user` used by that source;
- campaign start/end UTC;
- timezone/reporting settings;
- known consent/bot-filter limitations;
- baseline count captured immediately before campaign start.

Sessions and pageviews are not interchangeable with unique visitors.

### Event attendance

Count distinct human attendees. The same person attending multiple sessions counts once toward the milestone threshold unless the final contract explicitly says otherwise.

### Detailed feedback

A user counts only when feedback is actionable enough to inform a decision: context/task, observed problem or friction, expected/desired outcome, and enough detail to assess impact or reproduction. Generic praise alone is not detailed feedback.

## Privacy boundary

Publish aggregate counts, methodology, windows, source/platform, and redacted summaries. Keep names, emails, site URLs, IPs, raw analytics/client identifiers, private account dashboards, registration exports, and raw private support/feedback messages out of the public repository.

## Baseline record

Populate before M5-05:

| Item | Value |
| --- | --- |
| Canonical landing URL | TBD |
| Analytics source | TBD |
| Baseline UTC | TBD |
| Unique-visitor baseline | TBD |
| GitHub release asset | TBD |
| GitHub asset baseline | TBD |
| WordPress.org plugin/slug | TBD |
| WordPress.org download baseline | TBD |
| WordPress.org active-install baseline | TBD |

## Checkpoint record

Add dated checkpoints during the campaign/support period rather than relying on one retrospective screenshot. Each checkpoint should reference an `M5-EVD-*` entry and preserve measurement limitations.

## Final results

Not yet available. Populate only from verified measurement sources after the relevant campaign/adoption window.
