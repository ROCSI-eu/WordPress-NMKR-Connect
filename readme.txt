=== NMKR Connect ===
Contributors: rocsi-eu
Tags: nft, cardano, solana, nmkr, shortcode
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: MIT
License URI: https://opensource.org/license/mit/

Display synchronized NMKR Studio projects and tokens with five free shortcodes.

== Description ==

NMKR Connect synchronizes configured NMKR Studio project and token information into WordPress and provides grid, list, carousel, single-token, and single-project displays. NMKR is a third-party service; this plugin is not an official NMKR product or an affiliation claim.

External services:

* Administrators provide an NMKR Studio API credential and deliberately start synchronization. Requests go to the NMKR Studio API to retrieve project and token data. Review NMKR's terms and privacy information at https://www.nmkr.io/legal.
* Token media can load from remote NMKR, IPFS, or configured gateway URLs. Those providers may receive a visitor IP address and ordinary HTTP request metadata.
* Analytics is disabled by default. Local mode stores interaction events in the WordPress database. Optional GA4 modes transmit configured event data to Google only after deliberate mode selection and valid credentials. See Google Analytics terms (https://marketingplatform.google.com/about/analytics/terms/us/) and Google Privacy Policy (https://policies.google.com/privacy).

Site operators remain responsible for consent, disclosures, retention, and configuration appropriate to their site. The WordPress Privacy Policy Guide includes suggested text after activation.

== Installation ==

1. Install a purpose-built release ZIP through Plugins > Add New > Upload Plugin; do not use an automatic source archive as an installable artifact.
2. Activate the plugin.
3. Configure an NMKR Studio API credential under NMKR Connect settings.
4. Review analytics/privacy settings. Analytics remains Off until explicitly enabled.
5. Run synchronization only when ready, then add one of the documented shortcodes to a page.

== Frequently Asked Questions ==

= Which shortcodes are included? =

`[nmkr-grid]`, `[nmkr-token-list]`, `[nmkr-carousel]`, `[nmkr-token]`, and `[nmkr-project]` are all included without a licence entitlement gate.

= Does the plugin track visitors by default? =

No. Analytics defaults to Off and consent enforcement defaults to enabled. Local database collection or GA4 transmission requires deliberate administrator configuration; GA4 also requires valid credentials.

= What happens on uninstall? =

Deactivation clears the recurring analytics cleanup event. Uninstall removes plugin-owned roles and capabilities and core plugin data. Analytics data and admission state are removed by default; if an administrator deliberately disables “Remove Data on Uninstall,” the analytics table is retained.

= Is this name or slug approved by WordPress.org? =

No claim is made that the working name or slug is approved, accepted, or reserved. Final directory naming remains a release gate.

== Changelog ==

= 1.0.0 =
* Initial public production candidate: five free shortcodes, no licensing SDK, opt-in analytics, targeted lifecycle cleanup, privacy disclosures, and reproducible package tooling.
