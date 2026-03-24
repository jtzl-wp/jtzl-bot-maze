=== Labyrinth ===
Contributors: labyrinth
Tags: security, bot-protection, anti-scraping, honeypot, ai-bot
Requires at least: 6.9
Tested up to: 6.9
Requires PHP: 8.2
Stable tag: 1.0.0-rc.3
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI bot protection through invisible trap link labyrinths — hidden links lure bots into a maze of fake content for detection and blocking.

== Description ==

Labyrinth protects your WordPress site from unwanted AI crawlers and scrapers by planting invisible trap links that only bots will follow. When a bot enters the labyrinth, it gets lost in an ever-expanding maze of realistic-looking fake pages while Labyrinth quietly builds a suspicion score based on its behavior.

**How it works:**

1. **Trap link injection** — Invisible links are added to your real pages. Legitimate visitors never see them, but bots following every link on the page will enter the labyrinth.
2. **Lazy labyrinth generation** — Trap pages link to more trap pages, generated on demand. The deeper a bot goes, the more time it wastes.
3. **Bot scoring** — Each trap page visit adds suspicion points. Deeper traversal earns bonus points. Once a threshold is reached, the visitor is flagged as a bot.
4. **Blocking and tarpitting** — Flagged bots can be blocked outright (403), served decoy pages (light tarpit), or slowed down with a deliberate delay (full tarpit).
5. **Crawler verification** — Known search engine crawlers (Googlebot, Bingbot, etc.) are verified via reverse DNS and exempted from scoring.

**Features:**

* Zero impact on legitimate visitors — trap links are hidden from humans and search engines
* Configurable injection method (content, footer, or both)
* Adjustable scoring thresholds and blocking behavior
* robots.txt integration to signal trap paths as disallowed
* Analytics dashboard showing bot activity, top IPs, and score distribution
* Automatic log retention and maintenance via WP-Cron
* Lightweight — no external API calls, everything runs locally

== Installation ==

1. Upload the `labyrinth` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **Settings > Labyrinth** to configure trap link injection, scoring thresholds, and blocking behavior.

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

Check the **Labyrinth** analytics dashboard in the WordPress admin. It shows total trap visits, unique bot IPs, score distribution, and top offenders.

== Changelog ==

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
* Lazy labyrinth generation with configurable depth and branching
* Bot scoring based on trap page visits and traversal depth
* IP tracking with proxy-aware detection
* Admin settings page
* Analytics dashboard
