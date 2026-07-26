<?php
/**
 * The template for displaying all single products
 *
 * Overrides woocommerce/single-product.php
 *
 * @package ConsuCorner
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main class="page-single-product-main">
	<?php
	while ( have_posts() ) :
		the_post();
		wc_get_template_part( 'content', 'single-product' );
	endwhile;
	?>
</main>

<?php
get_footer();
