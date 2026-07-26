<?php
/**
 * Template Name: Marketing Blank Page
 * Template Post Type: page
 *
 * Minimal full-canvas page template for standalone marketing/landing pages.
 *
 * @package ConsuCorner
 */

defined( 'ABSPATH' ) || exit;
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<?php wp_head(); ?>
	<style>
		body.cc-marketing-blank-template {
			margin: 0;
			background: #fff;
		}

		body.cc-marketing-blank-template .cc-marketing-blank-main {
			min-height: 100vh;
			width: 100%;
		}

		body.cc-marketing-blank-template .cc-marketing-blank-content > *:first-child {
			margin-top: 0;
		}

		body.cc-marketing-blank-template .cc-marketing-blank-content > *:last-child {
			margin-bottom: 0;
		}
	</style>
</head>
<body <?php body_class( 'cc-marketing-blank-template' ); ?>>
<?php wp_body_open(); ?>

<main id="primary" class="cc-marketing-blank-main" role="main">
	<?php
	while ( have_posts() ) :
		the_post();
		?>
		<div id="post-<?php the_ID(); ?>" <?php post_class( 'cc-marketing-blank-content' ); ?>>
			<?php
			the_content();

			wp_link_pages(
				array(
					'before' => '<div class="page-links">' . esc_html__( 'Pages:', 'consucorner' ),
					'after'  => '</div>',
				)
			);
			?>
		</div>
	<?php endwhile; ?>
</main>

<?php wp_footer(); ?>
</body>
</html>
