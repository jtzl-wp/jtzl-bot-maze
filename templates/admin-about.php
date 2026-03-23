<?php
/**
 * Admin About Page Template
 *
 * @package Labyrinth
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php echo esc_html__( 'About Labyrinth', 'labyrinth' ); ?></h1>

	<div class="card" style="max-width: 800px;">
		<h2><?php echo esc_html__( 'What is Labyrinth?', 'labyrinth' ); ?></h2>
		<p>
			<?php echo esc_html__( 'Labyrinth protects your WordPress site from unwanted AI crawlers and scrapers by planting invisible trap links that only bots will follow. When a bot enters the labyrinth, it gets lost in an ever-expanding maze of realistic-looking fake pages while Labyrinth quietly builds a suspicion score based on its behavior.', 'labyrinth' ); ?>
		</p>
	</div>

	<div class="card" style="max-width: 800px;">
		<h2><?php echo esc_html__( 'How It Works', 'labyrinth' ); ?></h2>
		<ol>
			<li>
				<strong><?php echo esc_html__( 'Trap link injection', 'labyrinth' ); ?></strong> &mdash;
				<?php echo esc_html__( 'Invisible links are added to your real pages. Legitimate visitors never see them, but bots following every link on the page will enter the labyrinth.', 'labyrinth' ); ?>
			</li>
			<li>
				<strong><?php echo esc_html__( 'Lazy labyrinth generation', 'labyrinth' ); ?></strong> &mdash;
				<?php echo esc_html__( 'Trap pages link to more trap pages, generated on demand. The deeper a bot goes, the more time it wastes.', 'labyrinth' ); ?>
			</li>
			<li>
				<strong><?php echo esc_html__( 'Bot scoring', 'labyrinth' ); ?></strong> &mdash;
				<?php echo esc_html__( 'Each trap page visit adds suspicion points. Deeper traversal earns bonus points. Once a threshold is reached, the visitor is flagged as a bot.', 'labyrinth' ); ?>
			</li>
			<li>
				<strong><?php echo esc_html__( 'Blocking and tarpitting', 'labyrinth' ); ?></strong> &mdash;
				<?php echo esc_html__( 'Flagged bots can be blocked outright (403), served decoy pages (light tarpit), or slowed down with a deliberate delay (full tarpit).', 'labyrinth' ); ?>
			</li>
			<li>
				<strong><?php echo esc_html__( 'Crawler verification', 'labyrinth' ); ?></strong> &mdash;
				<?php echo esc_html__( 'Known search engine crawlers (Googlebot, Bingbot, etc.) are verified via reverse DNS and exempted from scoring.', 'labyrinth' ); ?>
			</li>
		</ol>
	</div>

	<div class="card" style="max-width: 800px;">
		<h2><?php echo esc_html__( 'Getting Started', 'labyrinth' ); ?></h2>
		<ol>
			<li>
				<?php
				printf(
					/* translators: %s: link to the settings page */
					esc_html__( 'Go to %s to configure trap link injection, scoring thresholds, and blocking behavior.', 'labyrinth' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=labyrinth-settings' ) ) . '">' . esc_html__( 'Labyrinth Settings', 'labyrinth' ) . '</a>'
				);
				?>
			</li>
			<li>
				<?php echo esc_html__( 'The plugin works out of the box with sensible defaults. Trap pages are generated automatically on first visit.', 'labyrinth' ); ?>
			</li>
			<li>
				<?php
				printf(
					/* translators: %s: link to the analytics page */
					esc_html__( 'Check the %s to monitor bot activity, top IPs, and score distribution.', 'labyrinth' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=labyrinth-analytics' ) ) . '">' . esc_html__( 'Analytics Dashboard', 'labyrinth' ) . '</a>'
				);
				?>
			</li>
		</ol>
	</div>

	<div class="card" style="max-width: 800px;">
		<h2><?php echo esc_html__( 'Features', 'labyrinth' ); ?></h2>
		<ul style="list-style: disc; padding-left: 20px;">
			<li><?php echo esc_html__( 'Zero impact on legitimate visitors — trap links are hidden from humans and search engines.', 'labyrinth' ); ?></li>
			<li><?php echo esc_html__( 'Configurable injection method (content, footer, or both).', 'labyrinth' ); ?></li>
			<li><?php echo esc_html__( 'Adjustable scoring thresholds and blocking behavior.', 'labyrinth' ); ?></li>
			<li><?php echo esc_html__( 'robots.txt integration to signal trap paths as disallowed.', 'labyrinth' ); ?></li>
			<li><?php echo esc_html__( 'Analytics dashboard showing bot activity, top IPs, and score distribution.', 'labyrinth' ); ?></li>
			<li><?php echo esc_html__( 'Automatic log retention and maintenance via WP-Cron.', 'labyrinth' ); ?></li>
			<li><?php echo esc_html__( 'Lightweight — no external API calls, everything runs locally.', 'labyrinth' ); ?></li>
		</ul>
	</div>

	<div class="card" style="max-width: 800px;">
		<h2><?php echo esc_html__( 'Frequently Asked Questions', 'labyrinth' ); ?></h2>

		<h3><?php echo esc_html__( 'Will this affect my SEO?', 'labyrinth' ); ?></h3>
		<p><?php echo esc_html__( 'No. Trap links are hidden from humans using CSS and include rel="nofollow". Trap pages send X-Robots-Tag: noindex, nofollow headers. The plugin also adds Disallow rules to robots.txt for the trap path.', 'labyrinth' ); ?></p>

		<h3><?php echo esc_html__( 'Does it work with caching plugins?', 'labyrinth' ); ?></h3>
		<p><?php echo esc_html__( 'Yes. Trap link injection happens during content rendering, so cached pages will include the trap links. The trap pages themselves are served dynamically and should be excluded from page caching (they use custom query vars that most caching plugins ignore by default).', 'labyrinth' ); ?></p>

		<h3><?php echo esc_html__( 'What happens to legitimate crawlers like Googlebot?', 'labyrinth' ); ?></h3>
		<p><?php echo esc_html__( 'Known search engine crawlers are verified via reverse DNS lookup. Verified crawlers are exempted from bot scoring even if they follow trap links.', 'labyrinth' ); ?></p>

		<h3><?php echo esc_html__( 'What is the difference between the blocking behaviors?', 'labyrinth' ); ?></h3>
		<ul style="list-style: disc; padding-left: 20px;">
			<li><strong><?php echo esc_html__( 'Block (403)', 'labyrinth' ); ?></strong> &mdash; <?php echo esc_html__( 'Returns a 403 Forbidden response. Lowest server cost.', 'labyrinth' ); ?></li>
			<li><strong><?php echo esc_html__( 'Light tarpit', 'labyrinth' ); ?></strong> &mdash; <?php echo esc_html__( 'Serves a decoy trap page with more trap links. No delay.', 'labyrinth' ); ?></li>
			<li><strong><?php echo esc_html__( 'Full tarpit', 'labyrinth' ); ?></strong> &mdash; <?php echo esc_html__( 'Same as light tarpit but adds a 1-second delay, holding the PHP worker longer.', 'labyrinth' ); ?></li>
		</ul>

		<h3><?php echo esc_html__( 'Why don\'t my visits show in the analytics?', 'labyrinth' ); ?></h3>
		<p><?php echo esc_html__( 'The analytics dashboard only counts visits to trap pages, not your real site pages. Normal browsing of your site (including the WordPress admin) will not increment any counters. Only bots that follow the hidden trap links will generate visits.', 'labyrinth' ); ?></p>
	</div>

	<div class="card" style="max-width: 800px;">
		<h2><?php echo esc_html__( 'Understanding the Dashboard', 'labyrinth' ); ?></h2>

		<h3><?php echo esc_html__( 'Summary Cards', 'labyrinth' ); ?></h3>
		<ul style="list-style: disc; padding-left: 20px;">
			<li><strong><?php echo esc_html__( 'Active Trap Pages', 'labyrinth' ); ?></strong> &mdash; <?php echo esc_html__( 'The number of trap pages currently active in the labyrinth. These are the fake pages that bots will encounter.', 'labyrinth' ); ?></li>
			<li><strong><?php echo esc_html__( 'Trap Visits (7 Days)', 'labyrinth' ); ?></strong> &mdash; <?php echo esc_html__( 'Number of visits to trap pages in the last 7 days. Only bots that follow hidden trap links generate visits — normal site browsing does not.', 'labyrinth' ); ?></li>
			<li><strong><?php echo esc_html__( 'Bots Blocked / Detected', 'labyrinth' ); ?></strong> &mdash; <?php echo esc_html__( 'Shown as a ratio. The first number is how many bots have been blocked (score exceeded threshold). The second number is the total unique bots detected (all visitors that hit trap pages). A higher ratio means more aggressive bots are being caught.', 'labyrinth' ); ?></li>
		</ul>

		<h3><?php echo esc_html__( 'Top Bots Table', 'labyrinth' ); ?></h3>
		<p><?php echo esc_html__( 'Shows the top 10 bots ranked by suspicion score. Each row shows the bot\'s IP address, user agent, accumulated score, number of trap page visits, the deepest labyrinth level reached, when it was last seen, and whether it is currently blocked or still being tracked.', 'labyrinth' ); ?></p>

		<h3><?php echo esc_html__( 'Recent Activity', 'labyrinth' ); ?></h3>
		<p><?php echo esc_html__( 'Shows the 20 most recent trap page visits in reverse chronological order, so you can see bot activity as it happens.', 'labyrinth' ); ?></p>
	</div>
</div>
