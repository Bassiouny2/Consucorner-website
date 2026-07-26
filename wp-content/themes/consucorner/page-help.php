<?php
/**
 * Generic editable Help page template.
 *
 * Template Name: Help Content Page
 *
 * @package ConsuCorner
 */

defined( 'ABSPATH' ) || exit;

get_header();

$parent = wp_get_post_parent_id( get_the_ID() );
$crumb  = $parent ? get_the_title( $parent ) : __( 'Help', 'consucorner' );
$content_class = $parent ? 'cc-help-content cc-help-detail' : 'cc-help-content cc-help-hub';
?>

<main class="cc-help-main">
	<section class="shop-page-head cart-page-head cc-help-head" aria-label="<?php esc_attr_e( 'Help page heading', 'consucorner' ); ?>">
		<div class="shop-page-head-inner">
			<h1 class="shop-page-title"><?php the_title(); ?></h1>
			<p class="shop-page-breadcrumbs">
				<?php
				consucorner_render_breadcrumbs(
					$parent
						? sprintf( 'Home / %s / %s', $crumb, get_the_title() )
						: sprintf( 'Home / %s', get_the_title() ),
					get_permalink()
				);
				?>
			</p>
		</div>
	</section>

	<section class="cc-help-layout">
		<div class="cc-help-wrap">
			<article id="post-<?php the_ID(); ?>" <?php post_class( $content_class ); ?>>
				<?php
				while ( have_posts() ) :
					the_post();
					the_content();
				endwhile;
				?>
			</article>
		</div>
	</section>
</main>

<?php
get_footer();
