<?php
/**
 * Template part for displaying page content in page.php
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package ConsuCorner
 */

defined( 'ABSPATH' ) || exit;
?>

<?php
$cc_hide_chrome =
	( function_exists( 'is_account_page' )      && call_user_func( 'is_account_page' ) )      ||
	( function_exists( 'is_cart' )              && call_user_func( 'is_cart' ) )              ||
	( function_exists( 'is_checkout' )          && call_user_func( 'is_checkout' ) )          ||
	( function_exists( 'is_wc_endpoint_url' )   && call_user_func( 'is_wc_endpoint_url', 'order-received' ) );
?>
<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<?php if ( ! $cc_hide_chrome ) : ?>
		<header class="entry-header">
			<?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
		</header><!-- .entry-header -->
	<?php endif; ?>

	<?php if ( ! $cc_hide_chrome ) { consucorner_post_thumbnail(); } ?>

	<div class="entry-content">
		<?php
		the_content();

		wp_link_pages(
			array(
				'before' => '<div class="page-links">' . esc_html__( 'Pages:', 'consucorner' ),
				'after'  => '</div>',
			)
		);
		?>
	</div><!-- .entry-content -->

	<?php if ( get_edit_post_link() && ! $cc_hide_chrome ) : ?>
		<footer class="entry-footer">
			<?php
			edit_post_link(
				sprintf(
					wp_kses(
						/* translators: %s: Name of current post. Only visible to screen readers */
						__( 'Edit <span class="screen-reader-text">%s</span>', 'consucorner' ),
						array(
							'span' => array(
								'class' => array(),
							),
						)
					),
					wp_kses_post( get_the_title() )
				),
				'<span class="edit-link">',
				'</span>'
			);
			?>
		</footer><!-- .entry-footer -->
	<?php endif; ?>
</article><!-- #post-<?php the_ID(); ?> -->
