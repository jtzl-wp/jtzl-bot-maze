<?php
/**
 * Admin About Page Template
 *
 * @package JTZL\Bot_Labyrinth
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php echo esc_html__( 'About JTZL\'s Bot Labyrinth — Bot-Be-Gone', 'jtzl-bot-labyrinth' ); ?></h1>

	<div class="card" style="max-width: 800px;">
		<h2><?php echo esc_html__( 'What is JTZL\'s Bot Labyrinth — Bot-Be-Gone?', 'jtzl-bot-labyrinth' ); ?></h2>
		<p>
			<?php echo esc_html__( 'JTZL\'s Bot Labyrinth — Bot-Be-Gone protects your WordPress site from unwanted AI crawlers and scrapers by planting invisible trap links that only bots will follow. When a bot enters the trap maze, it gets lost in an ever-expanding maze of realistic-looking fake pages while it quietly builds a suspicion score based on its behavior.', 'jtzl-bot-labyrinth' ); ?>
		</p>
	</div>

	<div class="card" style="max-width: 800px;">
		<h2><?php echo esc_html__( 'How It Works', 'jtzl-bot-labyrinth' ); ?></h2>
		<ol>
			<li>
				<strong><?php echo esc_html__( 'Trap link injection', 'jtzl-bot-labyrinth' ); ?></strong> &mdash;
				<?php echo esc_html__( 'Invisible links are added to your real pages. Legitimate visitors never see them, but bots following every link on the page will enter the trap maze.', 'jtzl-bot-labyrinth' ); ?>
			</li>
			<li>
				<strong><?php echo esc_html__( 'Lazy maze generation', 'jtzl-bot-labyrinth' ); ?></strong> &mdash;
				<?php echo esc_html__( 'Trap pages link to more trap pages, generated on demand. The deeper a bot goes, the more time it wastes.', 'jtzl-bot-labyrinth' ); ?>
			</li>
			<li>
				<strong><?php echo esc_html__( 'Bot scoring', 'jtzl-bot-labyrinth' ); ?></strong> &mdash;
				<?php echo esc_html__( 'Each trap page visit adds suspicion points. Deeper traversal earns bonus points. Once a threshold is reached, the visitor is flagged as a bot.', 'jtzl-bot-labyrinth' ); ?>
			</li>
			<li>
				<strong><?php echo esc_html__( 'Blocking and tarpitting', 'jtzl-bot-labyrinth' ); ?></strong> &mdash;
				<?php echo esc_html__( 'Flagged bots can be blocked outright (403), served decoy pages (light tarpit), or slowed down with a deliberate delay (full tarpit).', 'jtzl-bot-labyrinth' ); ?>
			</li>
			<li>
				<strong><?php echo esc_html__( 'Crawler verification', 'jtzl-bot-labyrinth' ); ?></strong> &mdash;
				<?php echo esc_html__( 'Known search engine crawlers (Googlebot, Bingbot, etc.) are verified via reverse DNS and exempted from scoring.', 'jtzl-bot-labyrinth' ); ?>
			</li>
		</ol>
	</div>

	<div class="card" style="max-width: 800px;">
		<h2><?php echo esc_html__( 'Getting Started', 'jtzl-bot-labyrinth' ); ?></h2>
		<ol>
			<li>
				<?php
				printf(
					/* translators: %s: link to the settings page */
					esc_html__( 'Go to %s to configure trap link injection, scoring thresholds, and blocking behavior.', 'jtzl-bot-labyrinth' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=jtzl-bot-labyrinth-settings' ) ) . '">' . esc_html__( 'JTZL\'s Bot Labyrinth — Bot-Be-Gone Settings', 'jtzl-bot-labyrinth' ) . '</a>'
				);
				?>
			</li>
			<li>
				<?php echo esc_html__( 'The plugin works out of the box with sensible defaults. Trap pages are generated automatically on first visit.', 'jtzl-bot-labyrinth' ); ?>
			</li>
			<li>
				<?php
				printf(
					/* translators: %s: link to the analytics page */
					esc_html__( 'Check the %s to monitor bot activity, top IPs, and score distribution.', 'jtzl-bot-labyrinth' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=jtzl-bot-labyrinth-analytics' ) ) . '">' . esc_html__( 'Analytics Dashboard', 'jtzl-bot-labyrinth' ) . '</a>'
				);
				?>
			</li>
		</ol>
	</div>

	<div class="card" style="max-width: 800px;">
		<h2><?php echo esc_html__( 'Features', 'jtzl-bot-labyrinth' ); ?></h2>
		<ul style="list-style: disc; padding-left: 20px;">
			<li><?php echo esc_html__( 'Zero impact on legitimate visitors — trap links are hidden from humans and search engines.', 'jtzl-bot-labyrinth' ); ?></li>
			<li><?php echo esc_html__( 'Configurable injection method (content, footer, or both).', 'jtzl-bot-labyrinth' ); ?></li>
			<li><?php echo esc_html__( 'Adjustable scoring thresholds and blocking behavior.', 'jtzl-bot-labyrinth' ); ?></li>
			<li><?php echo esc_html__( 'robots.txt integration to signal trap paths as disallowed.', 'jtzl-bot-labyrinth' ); ?></li>
			<li><?php echo esc_html__( 'Analytics dashboard showing bot activity, top IPs, and score distribution.', 'jtzl-bot-labyrinth' ); ?></li>
			<li><?php echo esc_html__( 'Blocked Bots detail page showing full user agent, score, and visit history.', 'jtzl-bot-labyrinth' ); ?></li>
			<li><?php echo esc_html__( 'Optional comprehensive tracking mode to monitor blocked bot persistence on trap pages.', 'jtzl-bot-labyrinth' ); ?></li>
			<li><?php echo esc_html__( 'Automatic log retention and maintenance via WP-Cron.', 'jtzl-bot-labyrinth' ); ?></li>
			<li><?php echo esc_html__( 'Geographic heat map of bot activity by country with interactive choropleth visualization.', 'jtzl-bot-labyrinth' ); ?></li>
			<li><?php echo esc_html__( 'Two GeoIP provider options: MaxMind GeoLite2 local database (GDPR-friendly, recommended) or ip-api.com external API.', 'jtzl-bot-labyrinth' ); ?></li>
			<li><?php echo esc_html__( 'Privacy policy suggestion for GDPR compliance.', 'jtzl-bot-labyrinth' ); ?></li>
			<li><?php echo esc_html__( 'Lightweight — geographic tracking is fully optional, and the MaxMind option keeps all data on your server.', 'jtzl-bot-labyrinth' ); ?></li>
		</ul>
	</div>

	<div class="card" style="max-width: 800px;">
		<h2><?php echo esc_html__( 'Geographic Tracking', 'jtzl-bot-labyrinth' ); ?></h2>
		<p><?php echo esc_html__( 'JTZL\'s Bot Labyrinth — Bot-Be-Gone can resolve bot IP addresses to country codes and display a geographic heat map of bot activity. This feature is optional and disabled by default.', 'jtzl-bot-labyrinth' ); ?></p>

		<h3><?php echo esc_html__( 'Provider Options', 'jtzl-bot-labyrinth' ); ?></h3>
		<ul style="list-style: disc; padding-left: 20px;">
			<li>
				<strong><?php echo esc_html__( 'MaxMind GeoLite2 (Recommended)', 'jtzl-bot-labyrinth' ); ?></strong> &mdash;
				<?php echo esc_html__( 'Downloads a local database and performs all lookups on your server. No visitor data leaves your site. Requires a free license key from maxmind.com. The database updates automatically once per week.', 'jtzl-bot-labyrinth' ); ?>
			</li>
			<li>
				<strong><?php echo esc_html__( 'ip-api.com', 'jtzl-bot-labyrinth' ); ?></strong> &mdash;
				<?php echo esc_html__( 'Sends visitor IP addresses to a third-party API over unencrypted HTTP. Simpler to set up (no license key required), but not recommended for sites that must comply with GDPR or similar privacy regulations.', 'jtzl-bot-labyrinth' ); ?>
			</li>
		</ul>

		<h3><?php echo esc_html__( 'Setup', 'jtzl-bot-labyrinth' ); ?></h3>
		<ol>
			<li>
				<?php
				printf(
					/* translators: %s: link to the settings page */
					esc_html__( 'Go to %s and choose a GeoIP provider under Geographic Tracking.', 'jtzl-bot-labyrinth' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=jtzl-bot-labyrinth-settings' ) ) . '">' . esc_html__( 'Settings', 'jtzl-bot-labyrinth' ) . '</a>'
				);
				?>
			</li>
			<li><?php echo esc_html__( 'For MaxMind: enter your license key and click "Download Database". The heat map will appear on dashboard pages once country data is collected.', 'jtzl-bot-labyrinth' ); ?></li>
			<li><?php echo esc_html__( 'For ip-api.com: simply select the provider. No additional setup required.', 'jtzl-bot-labyrinth' ); ?></li>
		</ol>
	</div>

	<div class="card" style="max-width: 800px;">
		<h2><?php echo esc_html__( 'Frequently Asked Questions', 'jtzl-bot-labyrinth' ); ?></h2>

		<h3><?php echo esc_html__( 'Will this affect my SEO?', 'jtzl-bot-labyrinth' ); ?></h3>
		<p><?php echo esc_html__( 'No. Trap links are hidden from humans using CSS and include rel="nofollow". Trap pages send X-Robots-Tag: noindex, nofollow headers. The plugin also adds Disallow rules to robots.txt for the trap path.', 'jtzl-bot-labyrinth' ); ?></p>

		<h3><?php echo esc_html__( 'Does it work with caching plugins?', 'jtzl-bot-labyrinth' ); ?></h3>
		<p><?php echo esc_html__( 'Yes. Trap link injection happens during content rendering, so cached pages will include the trap links. The trap pages themselves are served dynamically and should be excluded from page caching (they use custom query vars that most caching plugins ignore by default).', 'jtzl-bot-labyrinth' ); ?></p>

		<h3><?php echo esc_html__( 'What happens to legitimate crawlers like Googlebot?', 'jtzl-bot-labyrinth' ); ?></h3>
		<p><?php echo esc_html__( 'Known search engine crawlers are verified via reverse DNS lookup. Verified crawlers are exempted from bot scoring even if they follow trap links.', 'jtzl-bot-labyrinth' ); ?></p>

		<h3><?php echo esc_html__( 'What is the difference between the blocking behaviors?', 'jtzl-bot-labyrinth' ); ?></h3>
		<ul style="list-style: disc; padding-left: 20px;">
			<li><strong><?php echo esc_html__( 'Block (403)', 'jtzl-bot-labyrinth' ); ?></strong> &mdash; <?php echo esc_html__( 'Returns a 403 Forbidden response. Lowest server cost.', 'jtzl-bot-labyrinth' ); ?></li>
			<li><strong><?php echo esc_html__( 'Light tarpit', 'jtzl-bot-labyrinth' ); ?></strong> &mdash; <?php echo esc_html__( 'Serves a decoy trap page with more trap links. No delay.', 'jtzl-bot-labyrinth' ); ?></li>
			<li><strong><?php echo esc_html__( 'Full tarpit', 'jtzl-bot-labyrinth' ); ?></strong> &mdash; <?php echo esc_html__( 'Same as light tarpit but adds a 1-second delay, holding the PHP worker longer.', 'jtzl-bot-labyrinth' ); ?></li>
		</ul>

		<h3><?php echo esc_html__( 'Why don\'t my visits show in the analytics?', 'jtzl-bot-labyrinth' ); ?></h3>
		<p><?php echo esc_html__( 'The analytics dashboard only counts visits to trap pages, not your real site pages. Normal browsing of your site (including the WordPress admin) will not increment any counters. Only bots that follow the hidden trap links will generate visits.', 'jtzl-bot-labyrinth' ); ?></p>
	</div>

	<div class="card" style="max-width: 800px;">
		<h2><?php echo esc_html__( 'Understanding the Dashboard', 'jtzl-bot-labyrinth' ); ?></h2>

		<h3><?php echo esc_html__( 'Summary Cards', 'jtzl-bot-labyrinth' ); ?></h3>
		<p><?php echo esc_html__( 'All summary cards are clickable and link to their respective detail pages.', 'jtzl-bot-labyrinth' ); ?></p>
		<ul style="list-style: disc; padding-left: 20px;">
			<li><strong><?php echo esc_html__( 'Active Trap Pages', 'jtzl-bot-labyrinth' ); ?></strong> &mdash; <?php echo esc_html__( 'The number of trap pages currently active in the trap maze. Click to view the full list with preview links.', 'jtzl-bot-labyrinth' ); ?></li>
			<li><strong><?php echo esc_html__( 'Recent Activity (7 Days)', 'jtzl-bot-labyrinth' ); ?></strong> &mdash; <?php echo esc_html__( 'Total activity on trap pages in the last 7 days, including trap visits, blocked bot visits, and exempted crawler visits. Click to view the full list with pagination.', 'jtzl-bot-labyrinth' ); ?></li>
			<li><strong><?php echo esc_html__( 'Bots Blocked / Detected', 'jtzl-bot-labyrinth' ); ?></strong> &mdash; <?php echo esc_html__( 'Shown as a ratio. Click to view the Blocked Bots detail page with full user agent, score, visit count, and timestamps for each blocked bot.', 'jtzl-bot-labyrinth' ); ?></li>
		</ul>

		<h3><?php echo esc_html__( 'Top Bots Table', 'jtzl-bot-labyrinth' ); ?></h3>
		<p><?php echo esc_html__( 'Shows the top 10 bots ranked by suspicion score. Each row shows the bot\'s IP address, user agent, accumulated score, number of trap page visits, the deepest trap maze level reached, when it was last seen, and whether it is currently blocked or still being tracked.', 'jtzl-bot-labyrinth' ); ?></p>

		<h3><?php echo esc_html__( 'Recent Activity', 'jtzl-bot-labyrinth' ); ?></h3>
		<p><?php echo esc_html__( 'Shows the 20 most recent trap page visits in reverse chronological order, so you can see bot activity as it happens.', 'jtzl-bot-labyrinth' ); ?></p>

		<h3><?php echo esc_html__( 'Comprehensive Tracking Mode', 'jtzl-bot-labyrinth' ); ?></h3>
		<p><?php echo esc_html__( 'Enable "Track Blocked Bot Activity" in Settings > Bot Scoring to continue recording visits from blocked bots on trap pages. This lets you see whether blocked bots are still trying to access your trap maze. Disabled by default to minimise database writes.', 'jtzl-bot-labyrinth' ); ?></p>
	</div>
</div>
