=== JTZL's Bot Maze ===
Contributors: jtzl, yoren
Tags: security, bot-protection, anti-scraping, honeypot, ai-bot
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 1.6.2
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI bot protection through invisible trap link mazes — hidden links lure bots into a maze of fake content for detection and blocking.

== Description ==

JTZL's Bot Maze protects your WordPress site from unwanted AI crawlers and scrapers by planting invisible trap links that only bots will follow. When a bot enters the trap maze, it gets lost in an ever-expanding maze of realistic-looking fake pages while it quietly builds a suspicion score based on its behavior.

**How it works:**

1. **Trap link injection** — Invisible links are added to your real pages. Legitimate visitors never see them, but bots following every link on the page will enter the trap maze.
2. **Lazy maze generation** — Trap pages link to more trap pages, generated on demand. The deeper a bot goes, the more time it wastes.
3. **Bot scoring** — Each trap page visit adds suspicion points. Deeper traversal earns bonus points. Once a threshold is reached, the visitor is flagged as a bot.
4. **Blocking and tarpitting** — Flagged bots can be blocked outright (403), served decoy pages (light tarpit), or slowed down with a deliberate delay (full tarpit).
5. **Crawler verification** — Known search engine crawlers (Googlebot, Bingbot, etc.) are verified via reverse DNS and exempted from scoring.

**Features:**

* Zero impact on legitimate visitors — trap links are hidden from humans and search engines
* Configurable injection method (content, footer, or both)
* Adjustable scoring thresholds and blocking behavior
* robots.txt integration to signal trap paths as disallowed
* Analytics dashboard showing bot activity, top IPs, and score distribution
* Blocked Bots detail page showing full user agent, score, visit history
* Optional comprehensive tracking mode to monitor blocked bot persistence
* Automatic log retention and maintenance via WP-Cron
* Privacy policy suggestion for GDPR compliance
* Geographic heat map of bot activity by country with two GeoIP provider options
* MaxMind GeoLite2 local database — all lookups on your server, GDPR-friendly (recommended)
* ip-api.com external API — simple setup, no license key required
* Optional AbuseIPDB reporting for eligible blocked public IP addresses, with a one-press catch-up for bots blocked before you enabled it
* Lightweight — minimal footprint, geographic tracking is fully optional

== Installation ==

1. Upload the `jtzl-bot-maze` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **Settings > Bot Maze** to configure trap link injection, scoring thresholds, and blocking behavior.

Optional AbuseIPDB reporting is configured under **Settings > Bot Maze > AbuseIPDB Reporting**.

The plugin works out of the box with sensible defaults. Trap pages are generated automatically on first visit.

== Frequently Asked Questions ==

= Will this affect my SEO? =

No. Trap links are hidden from humans using CSS and include `rel="nofollow"`. Trap pages send `X-Robots-Tag: noindex, nofollow` headers. The plugin also adds `Disallow` rules to robots.txt for the trap path.

= How does the robots.txt rule work in this plugin? =

The plugin adds a `Disallow` rule for the trap base path so well-behaved crawlers (Googlebot, Bingbot, and similar) stay out of the trap maze instead of wasting crawl budget on it.

It does this through the robots.txt that **WordPress generates** — the "virtual" robots.txt — using the standard `robots_txt` filter. It does **not** write a file to your server. What this means in practice:

* **No physical robots.txt file:** the rule is added automatically, alongside whatever WordPress and other plugins (e.g. your SEO plugin's sitemap line) already output.
* **A static `robots.txt` file exists at your site root:** your web server serves that file directly and WordPress never runs, so neither this plugin nor any other can modify it. The rule will not appear until you add it to that file.
* **An SEO plugin manages robots.txt:** the rule is added at a late priority so it survives plugins that *replace* the generated file (such as Rank Math's editor). Yoast appends without removing other rules, so it is unaffected. If the rule still doesn't appear, add it through that plugin's robots.txt editor.

The Settings page verifies whether the rule is actually being served and, if it isn't, shows you the exact lines to add:

`User-agent: *`
`Disallow: /your-trap-base-path/`

(replace `your-trap-base-path` with your configured Trap Base Path). For safety, the plugin never creates or overwrites a physical robots.txt on its own — creating one would shadow WordPress's virtual file and drop other plugins' directives. Where a writable robots.txt already exists, it offers an optional one-click button to append the rule for you; otherwise it shows the lines to paste.

= Does it work with caching plugins? =

Yes. Trap link injection happens during content rendering, so cached pages will include the trap links. The trap pages themselves are served dynamically and should be excluded from page caching (they use custom query vars that most caching plugins ignore by default).

= What happens to legitimate crawlers like Googlebot? =

Known search engine crawlers are verified via reverse DNS lookup. Verified crawlers are exempted from bot scoring even if they follow trap links.

= Can I customize the trap page content? =

Trap pages are generated from a built-in content template engine that produces realistic-looking text. The content varies based on a seed value to ensure each page looks different.

= What is the difference between the blocking behaviors? =

* **Block (403)** — Returns a 403 Forbidden response. Lowest server cost.
* **Light tarpit** — Serves a decoy trap page with more trap links. No delay.
* **Full tarpit** — Same as light tarpit but adds a 1-second delay, holding the PHP worker longer.

= How do I know it's working? =

Check the **Bot Maze** analytics dashboard in the WordPress admin. It shows total trap visits, unique bot IPs, score distribution, and top offenders.

= Why does a blocked bot show no AbuseIPDB report? =

A dash in the Reported column on the Blocked Bots page means no report has been accepted for that address yet. The usual reasons, in the order worth checking:

1. **Reporting is off, or has no API key.** Nothing is ever queued until both are set.
2. **The bot was blocked before you switched reporting on.** Reporting fires when a score crosses the blocking threshold, and a bot that crossed it earlier will not cross it again. Use the catch-up button on the Blocked Bots page.
3. **It was last seen longer ago than your data retention period** (Settings > Bot Maze > Maintenance, 30 days by default). The catch-up only offers addresses inside that window, because past it the plugin deletes the bot's record anyway. An address that falls outside will not be offered again, since a blocked bot stops accumulating visits.
4. **It was reported in the last 24 hours already**, under a different user agent for the same address.
5. **The address is not publicly routable** — a private, loopback, reserved, documentation or multicast address. AbuseIPDB only accepts reports about addresses reachable on the public internet. This usually means the site is behind a proxy and the trusted proxy settings need attention, since the recorded address is the proxy's rather than the visitor's.
6. **Delivery has not caught up.** Reports leave in small batches through WP-Cron, so a large catch-up takes hours. Settings > Bot Maze > AbuseIPDB Reporting shows how many are still pending.

Verified search engine crawlers are never scored, so they never reach the Blocked Bots page at all.

= Is the Cloudflare trusted client IP setting safe to enable? =

Cloudflare mode trusts the `CF-Connecting-IP` header only when the request comes from Cloudflare's published edge IP ranges. Because those edge IPs are **shared by every Cloudflare customer**, this proves a request came from some Cloudflare edge, not specifically from your zone.

In plain terms: your server must not be reachable directly by its own IP address — only through Cloudflare. Putting your site behind Cloudflare's proxy (the orange cloud) hides your server's IP but does not block direct connections to it; if someone discovers the IP, they can still reach the server and bypass Cloudflare. A quick check: if your site still loads when you request it by its raw server IP, the origin is exposed.

To lock the origin down, use any one of: Cloudflare Tunnel (your server has no public inbound port at all), Authenticated Origin Pulls (the origin requires Cloudflare's client certificate), or a firewall that allows inbound web traffic only from Cloudflare's IP ranges. If your origin stays reachable directly, visitor IP attribution can be influenced by requests outside your zone, and bots can bypass Cloudflare's own protections by hitting the origin directly.

== Third-Party Services ==

Every external service listed below is optional and off by default. No data is sent to any of them unless a site administrator explicitly enables the corresponding feature.

= MaxMind GeoLite2 (Recommended) =

When **MaxMind GeoLite2** is selected as the GeoIP provider (Settings > Bot Maze > Geographic Tracking), the plugin downloads the GeoLite2-Country database from MaxMind and performs all IP-to-country lookups locally. **No visitor data leaves your server.**

* **What is downloaded:** The GeoLite2-Country database (~60 MB), updated weekly via WP-Cron.
* **What is sent to MaxMind:** Only your license key during database downloads. No visitor IP addresses are shared.
* **Requires:** A free MaxMind license key from [maxmind.com/en/geolite2/signup](https://www.maxmind.com/en/geolite2/signup).
* **Service website:** [https://www.maxmind.com](https://www.maxmind.com)
* **License:** GeoLite2 databases are licensed under [CC BY-SA 4.0](https://creativecommons.org/licenses/by-sa/4.0/).
* **Terms of service:** [https://www.maxmind.com/en/geolite2/eula](https://www.maxmind.com/en/geolite2/eula)

= ip-api.com =

When **ip-api.com** is selected as the GeoIP provider, the plugin sends visitor IP addresses to [ip-api.com](http://ip-api.com) to resolve their country of origin. This data is used to display a geographic heat map of bot activity in the admin dashboard.

* **What is sent:** The visitor's IP address only, over unencrypted HTTP.
* **When it is sent:** At the time a trap page visit is recorded, only while this provider is selected.
* **Service website:** [http://ip-api.com](http://ip-api.com)
* **Terms of service:** [https://ip-api.com/docs/legal](https://ip-api.com/docs/legal)
* **Privacy policy:** ip-api.com does not log queries from the free API endpoint.
* **Note:** The free tier only supports HTTP (not HTTPS). If your site must comply with GDPR, use the MaxMind local database option instead.

Geographic tracking is **off by default** and requires explicit opt-in by a site administrator.

= AbuseIPDB Reporting =

AbuseIPDB reporting is **off by default**. It requires explicit enablement plus an administrator-owned [AbuseIPDB account](https://www.abuseipdb.com/register) and API key from the [API dashboard](https://www.abuseipdb.com/account/api). Protect the API key like a password.

An address is queued for reporting in exactly three situations:

1. A non-verified crawler's persisted score crosses the configured blocking threshold.
2. An address that is already blocked requests a trap page while reporting is on, which is fresh evidence it is still crawling the maze.
3. An administrator presses the catch-up button on the Blocked Bots page, which covers addresses blocked before reporting was switched on.

Every queued address must also be publicly routable. Private, loopback, reserved, documentation and multicast addresses are never reported, because AbuseIPDB only accepts reports about addresses reachable on the public internet. Verified search engine crawlers are never scored in the first place, so they are never candidates.

The catch-up in (3) reports each address against the time it was actually last seen, rather than the time the button was pressed, and covers addresses seen within your configured data retention period (Settings > Bot Maze > Maintenance, 30 days by default). That is the period you have declared this evidence meaningful for, and the same period after which the plugin deletes the record, so the catch-up offers exactly the blocked addresses your site still holds evidence for. It reports up to 500 addresses per press.

In all three cases the report sends only the public IP address, category 19 ([Bad Web Bot](https://www.abuseipdb.com/categories)), the time of the observation being reported, and a generic explanation for security reporting. It sends these four fields over HTTPS to the [AbuseIPDB service](https://www.abuseipdb.com/). It never sends the user agent, referrer, trap URL, session, score, or traversal depth.

The plugin limits reports to once per IP address in any 24-hour period. A queued report that cannot be delivered for seven days is abandoned. Delivery runs in the background through WP-Cron, so reports may wait on low-traffic sites and are not real-time. Disabling reporting or clearing the key stops delivery and deletes queued reports.

The current free Individual plan includes 1,000 IP checks and reports per day. See [AbuseIPDB pricing](https://www.abuseipdb.com/pricing); your account limit may differ. Review AbuseIPDB's [terms](https://www.abuseipdb.com/terms) and [privacy policy](https://www.abuseipdb.com/privacy) before enabling reporting.

== Changelog ==

= 1.6.2 =
* Fixed the AbuseIPDB catch-up silently discarding everything it queued. Each queued report recorded the time of the observation as the time it entered the queue, so a catch-up of older evidence arrived already past the point the plugin abandons undelivered work, and the next background run deleted the whole batch before sending any of it. The button then reappeared with the same count, as if nothing had happened. A queued report now records when it was queued, separately from the observation it reports, and the confirmation counts only what the queue actually accepted.

= 1.6.1 =
* The AbuseIPDB catch-up now covers everything within your data retention period (30 days by default) instead of a fixed seven days. The seven-day limit was never a judgement about how old an observation may be — it was the queue's own "give up on undeliverable work" timeout, which the catch-up had inherited by accident. Those are now separate: the queue still abandons a report it has failed to send for a week, measured from when it was queued, while how far back the catch-up reaches is your retention setting. On a site left at the default, a press now reaches four times further back.
* Documented exactly which addresses get reported and why one might not, including the requirement that an address be publicly routable — a rule that previously had no mention at all. See the new "Why does a blocked bot show no AbuseIPDB report?" entry under Frequently Asked Questions.

= 1.6.0 =
* The Blocked Bots page now offers a one-press catch-up that reports bots blocked before you switched AbuseIPDB reporting on. Version 1.5.1 only covered bots that came back to a trap page after being blocked, which in Block mode almost never happens: a blocked bot is refused every page, so it never receives a trap link again and has nothing left to come back to. The button names exactly how many addresses it will report, covers those seen within the last seven days (widened to your retention period in 1.6.1) and reports each one against the time it was actually last seen, never the time you pressed the button. It disappears as soon as you press it, and addresses that could never be reported are never offered.

= 1.5.1 =
* AbuseIPDB reporting now also covers bots that were already blocked before you enabled it. Previously only a bot crossing the blocking threshold for the first time was ever reported, so an established bot could keep crawling your trap pages every day without a single report being sent. Now any blocked address that requests a trap page is reported too, at most once per address in any 24-hour period.

= 1.5.0 =
* Added optional AbuseIPDB reporting for eligible public IP addresses after a bot crosses the blocking threshold, with durable WP-Cron delivery, per-IP limits, bounded retention, and privacy-focused setup guidance.

= 1.4.1 =
* The copy button on user-agent cells now shows a tooltip on hover, matching the filter button next to it.
* Bumped "Tested up to" to WordPress 7.1.

= 1.4.0 =
* Every column on the Blocked Bots page is now sortable, including Country and User Agent — full parity with Recent Activity. Country sorts by its two-letter code, matching the other table.
* The Top Bots by Score table on the analytics dashboard now always shows the real user agent and country. Previously, bots not seen recently could show a long string of letters and digits (an internal hash) and a blank country.
* Blocked-bot searches match user agents directly and are much lighter on the database; results no longer depend on how much visit history is retained.
* Under the hood, the readable user agent and latest country are now stored with each bot's score record, kept fresh on every visit, and existing records are upgraded automatically — no action needed.
* The one-time database upgrade now reports its status under Tools → Site Health and finishes on its own in the background if it can't complete right away, with a one-click retry — so the readable user agent and country always fill in without any manual step.

= 1.3.0 =
* Recent Activity and Blocked Bots are now fully sortable and searchable. Click any column heading to sort your entire history — not just the page you're looking at — search by IP address or user agent, and page through the results, all handled server-side over the complete dataset.
* User agents are no longer shortened. Both tables show the complete user-agent string: click it to expand the full value in place, or use the one-click copy button to grab it. Click an IP address or user agent to filter the table down to just that value.
* On the Blocked Bots page, the Score, Visits, Max Depth, First Seen, and Last Seen columns are each individually sortable, so you can rank blocked bots by suspicion score, visit count, or when they were last seen.
* Refined the table column layout so the user-agent column gets the room it needs while the numeric and date columns stay compact.

= 1.2.0 =
* robots.txt: the settings page now verifies whether the trap Disallow rule is actually live in the robots.txt your site serves, and reports the result right under the checkbox. Previously the rule could silently have no effect when a static robots.txt file at your site root overrode the one WordPress generates.
* Added an admin notice when the Disallow rule is enabled but missing from the served robots.txt, with the exact lines to add and a one-click option to append them to an existing writable robots.txt file.
* SEO-plugin compatibility: the Disallow rule is now added at a late filter priority so it survives plugins that replace the generated robots.txt (such as Rank Math's editor). When a static file or another plugin manages your robots.txt, the settings page and admin notice point you to add the rule there.
* The verification re-checks daily in the background and never runs a blocking request on every admin page; a failed check retries within minutes instead of staying stale, and the rule is recognized regardless of spacing or inline comments.
* Clarified that the robots.txt modification applies to WordPress's generated robots.txt and cannot take effect alongside a static robots.txt file or when search-engine visibility is switched off.

= 1.1.0 =
* Cloudflare support: selecting the Cloudflare trusted client IP header now loads Cloudflare's official IP ranges automatically — no manual entry required. Sites behind Cloudflare record the real visitor IP (and country) instead of Cloudflare's edge servers.
* Cloudflare's IP ranges refresh automatically each week via WP-Cron, falling back to a bundled, validated list if a refresh fails.
* Added an admin notice that detects when your site is served through Cloudflare and offers one-click setup of the correct trusted client IP header.
* Clarified the Trusted Proxy IPs field as an optional override and made clear that only the Cloudflare preset ships built-in ranges (other headers still require manual entry).
* Improved IPv6 handling when matching manually entered trusted-proxy addresses.

= 1.0.1 =
* WordPress.org review compliance: removed non-distributable files from the plugin package and removed an unused class.
* Bumped "Tested up to" to WordPress 7.0.

= 1.0.0-rc.6 =
* Added MaxMind GeoLite2 as a GDPR-friendly local GeoIP provider option.
* Added 3-way GeoIP provider selector: Disabled, MaxMind GeoLite2 (local), or ip-api.com (external).
* MaxMind database auto-updates weekly via WP-Cron.
* Added clear privacy warnings for each provider option in the settings UI.
* Updated privacy policy text to describe both provider options.
* Geographic map section now hidden entirely when geo tracking is disabled.
* Migrates existing geo tracking setting to new provider architecture.
* Added Vitest job to CI with coverage reporting.

= 1.0.0-rc.5 =
* Added geographic heat map of bot activity by country using Leaflet.js choropleth.
* Added GeoIP service for resolving visitor IP addresses to country codes.
* Added AJAX endpoint for dynamic country data loading.
* Added the "Full Tarpit Delay (ms)" setting.
* UI improvements on the Analytics page.

= 1.0.0-rc.4 =
* Added Blocked Bots detail page showing full user agent, score, visit count, and timestamps.
* Added Recent Activity page with 7-day filter, pagination, and request type badges (Trap Visit, Blocked, Exempted).
* All dashboard summary cards are now clickable links to their own detail pages.
* Added comprehensive bot tracking mode (opt-in setting) to continue recording blocked bot visits on trap pages.
* Added request type badges to distinguish trap visits, blocked bot visits, and exempted crawler visits.
* Removed Recent Activity table from analytics dashboard (moved to dedicated page).

= 1.0.0-rc.3 =
* Added privacy policy suggestion for GDPR compliance.

= 1.0.0-rc.2 =
* Added the About Page.
* Added the Trap Pages.
* Improved the Analytics Page.

= 1.0.0-rc.1 =
* Hardened settings with server-side upper bounds on all numeric options
* Centralized option sanitization — single source of truth for bounds
* Added length truncation for stored user-agent, referrer, and request URI values
* Per-request slug cache in trap router eliminates duplicate DB queries
* Extracted shared rightmost-IP logic into a reusable helper
* Validated injection method and blocking behavior enums at read time
* Added bot scoring options (points_per_visit, depth_bonus, block_threshold) to bounded sanitization
* Routed maintenance cron through container config for consistent bounds enforcement
* Full test coverage across all source files (100% lines, 100% methods)

= 1.0.0-alpha.4 =
* Light tarpit as default blocking behavior
* Clearer blocking settings descriptions

= 1.0.0-alpha.3 =
* Bot blocking and tarpitting with configurable behavior
* Robots.txt integration
* Trusted proxy header support
* Race condition guards for concurrent trap page generation

= 1.0.0-alpha.2 =
* Full test suite and CI workflow
* Frontend build tooling for admin assets
* Bug fixes for bot score threshold and URL normalization

= 1.0.0-alpha.1 =
* Initial release
* Trap link injection into post content and footer
* Lazy maze generation with configurable depth and branching
* Bot scoring based on trap page visits and traversal depth
* IP tracking with proxy-aware detection
* Admin settings page
* Analytics dashboard
