<?php
/**
 * Trap page template
 *
 * Standalone minimal HTML (no get_header/get_footer / active theme). Used for
 * all trap maze URLs (TrapRouter) and blocked-visitor tarpit responses
 * (Bootstrap). Trade-off: lower PHP cost vs pixel-perfect mimicry of real pages.
 *
 * @package JTZL\Bot_Maze
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$trap_page = $GLOBALS['jtzl_bm_trap_page'] ?? null;
$children  = $GLOBALS['jtzl_bm_trap_children'] ?? array();
$base_path = $GLOBALS['jtzl_bm_trap_base_path'] ?? 'jbm';

if ( null === $trap_page ) {
	return;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php echo esc_attr( get_bloginfo( 'charset' ) ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo esc_html( $trap_page->title ); ?></title>
</head>
<body class="jtzl-bm-trap">
	<main class="jtzl-bm-trap-page" style="max-width: 800px; margin: 0 auto; padding: 20px;">
		<article>
			<h1><?php echo esc_html( $trap_page->title ); ?></h1>
			<div class="jtzl-bm-trap-content">
				<?php echo wp_kses_post( $trap_page->content ); ?>
			</div>
			<?php if ( ! empty( $children ) ) : ?>
			<nav class="jtzl-bm-trap-nav" style="margin-top: 2em; padding-top: 1em; border-top: 1px solid #ddd;" aria-label="<?php echo esc_attr__( 'Related', 'jtzl-bot-maze' ); ?>">
				<h2><?php echo esc_html__( 'Related articles', 'jtzl-bot-maze' ); ?></h2>
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
	</main>
</body>
</html>
