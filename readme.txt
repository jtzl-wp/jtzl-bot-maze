=== JTZL's Bot Maze ===
Contributors: jtzl, yoren
Tags: security, bot-protection, anti-scraping, honeypot, ai-bot
Requires at least: 6.9
Tested up to: 7.0
Requires PHP: 8.2
Stable tag: 1.0.1
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
* Lightweight — minimal footprint, geographic tracking is fully optional

== Installation ==

1. Upload the `jtzl-bot-maze` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **Settings > Bot Maze** to configure trap link injection, scoring thresholds, and blocking behavior.

The plugin works out of the box with sensible defaults. Trap pages are generated automatically on first visit.

== Frequently Asked Questions ==

= Will this affect my SEO? =

No. Trap links are hidden from humans using CSS and include `rel="nofollow"`. Trap pages send `X-Robots-Tag: noindex, nofollow` headers. The plugin also adds `Disallow` rules to robots.txt for the trap path.

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

== Third-Party Services ==

This plugin offers optional geographic tracking with two provider options. No data is sent to any external service unless a site administrator explicitly enables one of these providers.

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

== Changelog ==

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
