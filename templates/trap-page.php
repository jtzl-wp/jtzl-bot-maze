<?php
/**
 * Trap Page Template
 *
 * Renders a trap page that looks like a real themed page.
 * Uses get_header()/get_footer() to inherit the site's active theme.
 *
 * @package Labyrinth
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get trap page data from global scope (set by TrapRouter::template_loader).
$trap_page = $GLOBALS['lby_trap_page'] ?? null;
$children  = $GLOBALS['lby_trap_children'] ?? array();
$base_path = $GLOBALS['lby_trap_base_path'] ?? 'lby';

if ( null === $trap_page ) {
	return;
}

get_header();
?>

<div class="lby-trap-page" style="max-width: 800px; margin: 0 auto; padding: 20px;">

	<article>
		<h1><?php echo esc_html( $trap_page->title ); ?></h1>

		<div class="lby-trap-content entry-content">
			<?php echo wp_kses_post( $trap_page->content ); ?>
		</div>

		<?php if ( ! empty( $children ) ) : ?>
		<nav class="lby-trap-nav" style="margin-top: 2em; padding-top: 1em; border-top: 1px solid #ddd;">
			<h3><?php echo esc_html__( 'Related Articles', 'labyrinth' ); ?></h3>
			<ul>
				<?php foreach ( $children as $child ) : ?>
				<li>
					<a href="<?php echo esc_url( home_url( '/' . $base_path . '/' . $child->slug . '/' ) ); ?>" rel="nofollow">
						<?php echo esc_html( $child->title ); ?>
					</a>
				</li>
				<?php endforeach; ?>
			</ul>
		</nav>
		<?php endif; ?>
	</article>

</div>

<?php
get_footer();
