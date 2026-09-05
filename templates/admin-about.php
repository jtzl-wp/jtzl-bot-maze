<?php
/**
 * Admin About Page Template
 *
 * @package JTZL\Bot_Maze
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php echo esc_html__( 'About JTZL\'s Bot Maze', 'jtzl-bot-maze' ); ?></h1>

	<div class="card" style="max-width: 800px;">
		<h2><?php echo esc_html__( 'What is JTZL\'s Bot Maze?', 'jtzl-bot-maze' ); ?></h2>
		<p>
			<?php echo esc_html__( 'JTZL\'s Bot Maze protects your WordPress site from unwanted AI crawlers and scrapers by planting invisible trap links that only bots will follow. When a bot enters the trap maze, it gets lost in an ever-expanding maze of realistic-looking fake pages while it quietly builds a suspicion score based on its behavior.', 'jtzl-bot-maze' ); ?>
		</p>
	</div>

	<div class="card" style="max-width: 800px;">
		<h2><?php echo esc_html__( 'How It Works', 'jtzl-bot-maze' ); ?></h2>
		<ol>
			<li>
				<strong><?php echo esc_html__( 'Trap link injection', 'jtzl-bot-maze' ); ?></strong> &mdash;
				<?php echo esc_html__( 'Invisible links are added to your real pages. Legitimate visitors never see them, but bots following every link on the page will enter the trap maze.', 'jtzl-bot-maze' ); ?>
			</li>
			<li>
				<strong><?php echo esc_html__( 'Lazy maze generation', 'jtzl-bot-maze' ); ?></strong> &mdash;
				<?php echo esc_html__( 'Trap pages link to more trap pages, generated on demand. The deeper a bot goes, the more time it wastes.', 'jtzl-bot-maze' ); ?>
			</li>
			<li>
				<strong><?php echo esc_html__( 'Bot scoring', 'jtzl-bot-maze' ); ?></strong> &mdash;
				<?php echo esc_html__( 'Each trap page visit adds suspicion points. Deeper traversal earns bonus points. Once a threshold is reached, the visitor is flagged as a bot.', 'jtzl-bot-maze' ); ?>
			</li>
			<li>
				<strong><?php echo esc_html__( 'Blocking and tarpitting', 'jtzl-bot-maze' ); ?></strong> &mdash;
				<?php echo esc_html__( 'Flagged bots can be blocked outright (403), served decoy pages (light tarpit), or slowed down with a deliberate delay (full tarpit).', 'jtzl-bot-maze' ); ?>
			</li>
			<li>
				<strong><?php echo esc_html__( 'Crawler verification', 'jtzl-bot-maze' ); ?></strong> &mdash;
				<?php echo esc_html__( 'Known search engine crawlers (Googlebot, Bingbot, etc.) are verified via reverse DNS and exempted from scoring.', 'jtzl-bot-maze' ); ?>
			</li>
		</ol>
	</div>

	<div class="card" style="max-width: 800px;">
		<h2><?php echo esc_html__( 'Getting Started', 'jtzl-bot-maze' ); ?></h2>
		<ol>
			<li>
				<?php
				printf(
					/* translators: %s: link to the settings page */
					esc_html__( 'Go to %s to configure trap link injection, scoring thresholds, and blocking behavior.', 'jtzl-bot-maze' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=jtzl-bot-maze-settings' ) ) . '">' . esc_html__( 'JTZL\'s Bot Maze Settings', 'jtzl-bot-maze' ) . '</a>'
				);
				?>
			</li>
			<li>
				<?php echo esc_html__( 'The plugin works out of the box with sensible defaults. Trap pages are generated automatically on first visit.', 'jtzl-bot-maze' ); ?>
			</li>
			<li>
				<?php
				printf(
					/* translators: %s: link to the analytics page */
					esc_html__( 'Check the %s to monitor bot activity, top IPs, and score distribution.', 'jtzl-bot-maze' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=jtzl-bot-maze-analytics' ) ) . '">' . esc_html__( 'Analytics Dashboard', 'jtzl-bot-maze' ) . '</a>'
				);
				?>
			</li>
		</ol>
	</div>

	<div class="card" style="max-width: 800px;">
		<h2><?php echo esc_html__( 'Features', 'jtzl-bot-maze' ); ?></h2>
		<ul style="list-style: disc; padding-left: 20px;">
			<li><?php echo esc_html__( 'Zero impact on legitimate visitors — trap links are hidden from humans and search engines.', 'jtzl-bot-maze' ); ?></li>
			<li><?php echo esc_html__( 'Configurable injection method (content, footer, or both).', 'jtzl-bot-maze' ); ?></li>
			<li><?php echo esc_html__( 'Adjustable scoring thresholds and blocking behavior.', 'jtzl-bot-maze' ); ?></li>
			<li><?php echo esc_html__( 'robots.txt integration to signal trap paths as disallowed.', 'jtzl-bot-maze' ); ?></li>
			<li><?php echo esc_html__( 'Analytics dashboard showing bot activity, top IPs, and score distribution.', 'jtzl-bot-maze' ); ?></li>
			<li><?php echo esc_html__( 'Blocked Bots detail page showing full user agent, score, and visit history.', 'jtzl-bot-maze' ); ?></li>
			<li><?php echo esc_html__( 'Optional comprehensive tracking mode to monitor blocked bot persistence on trap pages.', 'jtzl-bot-maze' ); ?></li>
			<li><?php echo esc_html__( 'Automatic log retention and maintenance via WP-Cron.', 'jtzl-bot-maze' ); ?></li>
			<li><?php echo esc_html__( 'Geographic heat map of bot activity by country with interactive choropleth visualization.', 'jtzl-bot-maze' ); ?></li>
			<li><?php echo esc_html__( 'Two GeoIP provider options: MaxMind GeoLite2 local database (GDPR-friendly, recommended) or ip-api.com external API.', 'jtzl-bot-maze' ); ?></li>
			<li><?php echo esc_html__( 'Optional AbuseIPDB reporting for blocked public IP addresses, with a one-press catch-up for bots blocked before you enabled it.', 'jtzl-bot-maze' ); ?></li>
			<li><?php echo esc_html__( 'Privacy policy suggestion for GDPR compliance.', 'jtzl-bot-maze' ); ?></li>
			<li><?php echo esc_html__( 'Lightweight — geographic tracking is fully optional, and the MaxMind option keeps all data on your server.', 'jtzl-bot-maze' ); ?></li>
		</ul>
	</div>

	<div class="card" style="max-width: 800px;">
		<h2><?php echo esc_html__( 'Geographic Tracking', 'jtzl-bot-maze' ); ?></h2>
		<p><?php echo esc_html__( 'JTZL\'s Bot Maze can resolve bot IP addresses to country codes and display a geographic heat map of bot activity. This feature is optional and disabled by default.', 'jtzl-bot-maze' ); ?></p>

		<h3><?php echo esc_html__( 'Provider Options', 'jtzl-bot-maze' ); ?></h3>
		<ul style="list-style: disc; padding-left: 20px;">
			<li>
				<strong><?php echo esc_html__( 'MaxMind GeoLite2 (Recommended)', 'jtzl-bot-maze' ); ?></strong> &mdash;
				<?php echo esc_html__( 'Downloads a local database and performs all lookups on your server. No visitor data leaves your site. Requires a free license key from maxmind.com. The database updates automatically once per week.', 'jtzl-bot-maze' ); ?>
			</li>
			<li>
				<strong><?php echo esc_html__( 'ip-api.com', 'jtzl-bot-maze' ); ?></strong> &mdash;
				<?php echo esc_html__( 'Sends visitor IP addresses to a third-party API over unencrypted HTTP. Simpler to set up (no license key required), but not recommended for sites that must comply with GDPR or similar privacy regulations.', 'jtzl-bot-maze' ); ?>
			</li>
		</ul>

		<h3><?php echo esc_html__( 'Setup', 'jtzl-bot-maze' ); ?></h3>
		<ol>
			<li>
				<?php
				printf(
					/* translators: %s: link to the settings page */
					esc_html__( 'Go to %s and choose a GeoIP provider under Geographic Tracking.', 'jtzl-bot-maze' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=jtzl-bot-maze-settings' ) ) . '">' . esc_html__( 'Settings', 'jtzl-bot-maze' ) . '</a>'
				);
				?>
			</li>
			<li><?php echo esc_html__( 'For MaxMind: enter your license key and click "Download Database". The heat map will appear on dashboard pages once country data is collected.', 'jtzl-bot-maze' ); ?></li>
			<li><?php echo esc_html__( 'For ip-api.com: simply select the provider. No additional setup required.', 'jtzl-bot-maze' ); ?></li>
		</ol>
	</div>

	<?php
	$jtzl_bm_per_run   = \JTZL\Bot_Maze\Service\AbuseIpDbReportProcessor::MAX_BATCH;
	$jtzl_bm_run_every = \JTZL\Bot_Maze\Service\AbuseIpDbScheduler::INTERVAL_SECONDS;
	$jtzl_bm_per_hour  = (int) ( $jtzl_bm_per_run * HOUR_IN_SECONDS / $jtzl_bm_run_every );
	?>
	<div class="card" style="max-width: 800px;">
		<h2><?php echo esc_html__( 'AbuseIPDB Reporting', 'jtzl-bot-maze' ); ?></h2>
		<p><?php echo esc_html__( 'JTZL\'s Bot Maze can report the addresses it blocks to AbuseIPDB, a shared database of abusive IP addresses. This feature is optional and disabled by default. It uses your own AbuseIPDB account and API key; the plugin does not provide a shared key.', 'jtzl-bot-maze' ); ?></p>

		<h3><?php echo esc_html__( 'What Gets Reported', 'jtzl-bot-maze' ); ?></h3>
		<p><?php echo esc_html__( 'A report sends four things over HTTPS: the public IP address, category 19 (Bad Web Bot), the time of the observation being reported, and a generic explanation. It never sends the user agent, referrer, trap URL, session, score, or traversal depth.', 'jtzl-bot-maze' ); ?></p>
		<p><?php echo esc_html__( 'An address is queued for reporting in exactly three situations:', 'jtzl-bot-maze' ); ?></p>
		<ul style="list-style: disc; padding-left: 20px;">
			<li><?php echo esc_html__( 'A non-verified crawler\'s score crosses the blocking threshold.', 'jtzl-bot-maze' ); ?></li>
			<li><?php echo esc_html__( 'An address that is already blocked requests a trap page, which is fresh evidence it is still crawling the maze.', 'jtzl-bot-maze' ); ?></li>
			<li>
				<?php
				printf(
					/* translators: %d: maximum number of addresses one catch-up press reports */
					esc_html__( 'You press the catch-up button on the Blocked Bots page, which covers addresses blocked before you switched reporting on. One press reports up to %d addresses, each against the time it was actually last seen rather than the time you pressed.', 'jtzl-bot-maze' ),
					absint( \JTZL\Bot_Maze\Service\AbuseIpDbBackfill::MAX_PER_RUN )
				);
				?>
			</li>
		</ul>
		<p><?php echo esc_html__( 'The address must also be publicly routable. Private, loopback, reserved, documentation and multicast addresses are never reported, because AbuseIPDB only accepts reports about addresses reachable on the public internet. Verified search engine crawlers are never scored in the first place, so they are never candidates.', 'jtzl-bot-maze' ); ?></p>

		<h3><?php echo esc_html__( 'How Quickly Reports Are Sent', 'jtzl-bot-maze' ); ?></h3>
		<p>
			<?php
			printf(
				/* translators: 1: reports sent per background run, 2: minutes between runs, 3: resulting reports per hour */
				esc_html__( 'Queued addresses are never sent all at once. A background run sends at most %1$d reports, and a run is scheduled every %2$d minutes — a ceiling of roughly %3$d reports per hour. A full catch-up therefore takes several hours to clear, and the queue drains in small batches rather than in one burst.', 'jtzl-bot-maze' ),
				absint( $jtzl_bm_per_run ),
				absint( $jtzl_bm_run_every / MINUTE_IN_SECONDS ),
				absint( $jtzl_bm_per_hour )
			);
			?>
		</p>
		<p><?php echo esc_html__( 'Individual batches are often smaller than that ceiling, and the gaps between them are rarely even:', 'jtzl-bot-maze' ); ?></p>
		<ul style="list-style: disc; padding-left: 20px;">
			<li><?php echo esc_html__( 'A run stops at the first delivery failure, so a batch can be a single report when AbuseIPDB or the network is unhappy.', 'jtzl-bot-maze' ); ?></li>
			<li><?php echo esc_html__( 'When your daily AbuseIPDB quota runs out, delivery pauses entirely until the time AbuseIPDB says to retry. Nothing is lost; it resumes on its own.', 'jtzl-bot-maze' ); ?></li>
			<li>
				<?php
				printf(
					/* translators: %d: minutes between background runs */
					esc_html__( 'WP-Cron only fires on a page request after a run is due, so on a low-traffic site runs land at irregular intervals. The first run comes no sooner than %d minutes after you enable reporting.', 'jtzl-bot-maze' ),
					absint( $jtzl_bm_run_every / MINUTE_IN_SECONDS )
				);
				?>
			</li>
			<li><?php echo esc_html__( 'Each address is reported at most once in any 24-hour period, so a bot that keeps crawling does not generate repeat reports.', 'jtzl-bot-maze' ); ?></li>
			<li><?php echo esc_html__( 'A queued report that could not be delivered for seven days is abandoned rather than held forever.', 'jtzl-bot-maze' ); ?></li>
		</ul>
		<p>
			<?php
			printf(
				/* translators: %s: link to the settings page */
				esc_html__( 'The Delivery Status panel in %s shows how many reports are still pending, when the last one was delivered, and the most recent error.', 'jtzl-bot-maze' ),
				'<a href="' . esc_url( admin_url( 'admin.php?page=jtzl-bot-maze-settings' ) ) . '">' . esc_html__( 'Settings > AbuseIPDB Reporting', 'jtzl-bot-maze' ) . '</a>'
			);
			?>
		</p>

		<h3><?php echo esc_html__( 'Setup', 'jtzl-bot-maze' ); ?></h3>
		<ol>
			<li>
				<?php
				printf(
					/* translators: %s: link to the AbuseIPDB registration page */
					esc_html__( 'Create your own free account at %s and generate an API key in the API dashboard.', 'jtzl-bot-maze' ),
					'<a href="' . esc_url( 'https://www.abuseipdb.com/register' ) . '">abuseipdb.com</a>'
				);
				?>
			</li>
			<li>
				<?php
				printf(
					/* translators: %s: link to the settings page */
					esc_html__( 'Paste the key into %s and save changes. Treat it like a password.', 'jtzl-bot-maze' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=jtzl-bot-maze-settings' ) ) . '">' . esc_html__( 'Settings > AbuseIPDB Reporting', 'jtzl-bot-maze' ) . '</a>'
				);
				?>
			</li>
			<li><?php echo esc_html__( 'Review the data-sharing consent, then tick "Enable Automatic Reporting". Clearing the key or switching reporting off stops delivery and discards anything still queued.', 'jtzl-bot-maze' ); ?></li>
		</ol>
	</div>

	<div class="card" style="max-width: 800px;">
		<h2><?php echo esc_html__( 'Frequently Asked Questions', 'jtzl-bot-maze' ); ?></h2>

		<h3><?php echo esc_html__( 'Will this affect my SEO?', 'jtzl-bot-maze' ); ?></h3>
		<p><?php echo esc_html__( 'No. Trap links are hidden from humans using CSS and include rel="nofollow". Trap pages send X-Robots-Tag: noindex, nofollow headers. The plugin also adds Disallow rules to robots.txt for the trap path.', 'jtzl-bot-maze' ); ?></p>

		<h3><?php echo esc_html__( 'Does it work with caching plugins?', 'jtzl-bot-maze' ); ?></h3>
		<p><?php echo esc_html__( 'Yes. Trap link injection happens during content rendering, so cached pages will include the trap links. The trap pages themselves are served dynamically and should be excluded from page caching (they use custom query vars that most caching plugins ignore by default).', 'jtzl-bot-maze' ); ?></p>

		<h3><?php echo esc_html__( 'What happens to legitimate crawlers like Googlebot?', 'jtzl-bot-maze' ); ?></h3>
		<p><?php echo esc_html__( 'Known search engine crawlers are verified via reverse DNS lookup. Verified crawlers are exempted from bot scoring even if they follow trap links.', 'jtzl-bot-maze' ); ?></p>

		<h3><?php echo esc_html__( 'What is the difference between the blocking behaviors?', 'jtzl-bot-maze' ); ?></h3>
		<ul style="list-style: disc; padding-left: 20px;">
			<li><strong><?php echo esc_html__( 'Block (403)', 'jtzl-bot-maze' ); ?></strong> &mdash; <?php echo esc_html__( 'Returns a 403 Forbidden response. Lowest server cost.', 'jtzl-bot-maze' ); ?></li>
			<li><strong><?php echo esc_html__( 'Light tarpit', 'jtzl-bot-maze' ); ?></strong> &mdash; <?php echo esc_html__( 'Serves a decoy trap page with more trap links. No delay.', 'jtzl-bot-maze' ); ?></li>
			<li><strong><?php echo esc_html__( 'Full tarpit', 'jtzl-bot-maze' ); ?></strong> &mdash; <?php echo esc_html__( 'Same as light tarpit but adds a 1-second delay, holding the PHP worker longer.', 'jtzl-bot-maze' ); ?></li>
		</ul>

		<h3><?php echo esc_html__( 'Why don\'t my visits show in the analytics?', 'jtzl-bot-maze' ); ?></h3>
		<p><?php echo esc_html__( 'The analytics dashboard only counts visits to trap pages, not your real site pages. Normal browsing of your site (including the WordPress admin) will not increment any counters. Only bots that follow the hidden trap links will generate visits.', 'jtzl-bot-maze' ); ?></p>

		<h3><?php echo esc_html__( 'Why does a blocked bot show no AbuseIPDB report?', 'jtzl-bot-maze' ); ?></h3>
		<p><?php echo esc_html__( 'A dash in the Reported column on the Blocked Bots page means no report has been accepted for that address yet. The usual reasons, in the order worth checking:', 'jtzl-bot-maze' ); ?></p>
		<ol>
			<li><?php echo esc_html__( 'Reporting is off, or has no API key. Nothing is ever queued until both are set.', 'jtzl-bot-maze' ); ?></li>
			<li><?php echo esc_html__( 'The bot was blocked before you switched reporting on. Reporting fires when a score crosses the blocking threshold, and a bot that crossed it earlier will not cross it again. Use the catch-up button on the Blocked Bots page.', 'jtzl-bot-maze' ); ?></li>
			<li><?php echo esc_html__( 'It was last seen longer ago than your data retention period (Settings > Maintenance, 30 days by default). The catch-up only offers addresses inside that window, because past it the plugin deletes the record anyway.', 'jtzl-bot-maze' ); ?></li>
			<li><?php echo esc_html__( 'It was reported in the last 24 hours already, under a different user agent for the same address. Reports are limited per address, while this table lists each address and user agent separately.', 'jtzl-bot-maze' ); ?></li>
			<li><?php echo esc_html__( 'The address is not publicly routable — a private, loopback, reserved, documentation or multicast address. This usually means the site is behind a proxy and the trusted proxy settings need attention, since the recorded address is the proxy\'s rather than the visitor\'s.', 'jtzl-bot-maze' ); ?></li>
			<li><?php echo esc_html__( 'Delivery has not caught up. Reports leave in small batches, so a large catch-up takes hours — see "How Quickly Reports Are Sent" above, and the pending count under Settings > AbuseIPDB Reporting.', 'jtzl-bot-maze' ); ?></li>
		</ol>
	</div>

	<div class="card" style="max-width: 800px;">
		<h2><?php echo esc_html__( 'Understanding the Dashboard', 'jtzl-bot-maze' ); ?></h2>

		<h3><?php echo esc_html__( 'Summary Cards', 'jtzl-bot-maze' ); ?></h3>
		<p><?php echo esc_html__( 'All summary cards are clickable and link to their respective detail pages.', 'jtzl-bot-maze' ); ?></p>
		<ul style="list-style: disc; padding-left: 20px;">
			<li><strong><?php echo esc_html__( 'Active Trap Pages', 'jtzl-bot-maze' ); ?></strong> &mdash; <?php echo esc_html__( 'The number of trap pages currently active in the trap maze. Click to view the full list with preview links.', 'jtzl-bot-maze' ); ?></li>
			<li><strong><?php echo esc_html__( 'Recent Activity (7 Days)', 'jtzl-bot-maze' ); ?></strong> &mdash; <?php echo esc_html__( 'Total activity on trap pages in the last 7 days, including trap visits, blocked bot visits, and exempted crawler visits. Click to view the full list with pagination.', 'jtzl-bot-maze' ); ?></li>
			<li><strong><?php echo esc_html__( 'Bots Blocked / Detected', 'jtzl-bot-maze' ); ?></strong> &mdash; <?php echo esc_html__( 'Shown as a ratio. Click to view the Blocked Bots detail page with full user agent, score, visit count, and timestamps for each blocked bot.', 'jtzl-bot-maze' ); ?></li>
		</ul>

		<h3><?php echo esc_html__( 'Top Bots Table', 'jtzl-bot-maze' ); ?></h3>
		<p><?php echo esc_html__( 'Shows the top 10 bots ranked by suspicion score. Each row shows the bot\'s IP address, user agent, accumulated score, number of trap page visits, the deepest trap maze level reached, when it was last seen, and whether it is currently blocked or still being tracked.', 'jtzl-bot-maze' ); ?></p>

		<h3><?php echo esc_html__( 'Recent Activity', 'jtzl-bot-maze' ); ?></h3>
		<p><?php echo esc_html__( 'Shows the 20 most recent trap page visits in reverse chronological order, so you can see bot activity as it happens.', 'jtzl-bot-maze' ); ?></p>

		<h3><?php echo esc_html__( 'Comprehensive Tracking Mode', 'jtzl-bot-maze' ); ?></h3>
		<p><?php echo esc_html__( 'Enable "Track Blocked Bot Activity" in Settings > Bot Scoring to continue recording visits from blocked bots on trap pages. This lets you see whether blocked bots are still trying to access your trap maze. Disabled by default to minimise database writes.', 'jtzl-bot-maze' ); ?></p>
	</div>
</div>
