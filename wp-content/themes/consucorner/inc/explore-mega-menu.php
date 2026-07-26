<?php
/**
 * Dynamic Explore mega menu.
 *
 * @package ConsuCorner
 */

defined( 'ABSPATH' ) || exit;

/**
 * Return a WordPress menu object by trying multiple names.
 *
 * @param array $names Menu names.
 * @return WP_Term|null
 */
function consucorner_explore_get_menu( array $names ) {
	foreach ( $names as $name ) {
		$menu = wp_get_nav_menu_object( $name );

		if ( $menu instanceof WP_Term ) {
			return $menu;
		}
	}

	return null;
}

/**
 * Resolve the nav menu assigned to a theme location.
 *
 * @param string $location Theme menu location slug.
 * @return WP_Term|null
 */
function consucorner_explore_get_menu_by_location( $location ) {
	if ( '' === (string) $location ) {
		return null;
	}

	$locations = get_nav_menu_locations();
	if ( empty( $locations[ $location ] ) ) {
		return null;
	}

	$menu = wp_get_nav_menu_object( $locations[ $location ] );

	return $menu instanceof WP_Term ? $menu : null;
}

/**
 * Render one Explore menu link group from a WordPress nav menu.
 *
 * Resolution order: assigned theme location → menu matched by name → hardcoded
 * fallback. This lets the operations team manage the column from Appearance →
 * Menus (either by assigning the "Explore - Important" / "Explore - Help"
 * locations, or by naming a menu "Important desktop menu" / "Help Desktop menu").
 *
 * @param string $heading    Group heading.
 * @param array  $menu_names Menu names to try.
 * @param array  $fallback   Fallback links.
 * @param string $location   Optional theme menu location to resolve first.
 */
function consucorner_render_explore_link_group( $heading, array $menu_names, array $fallback, $location = '' ) {
	$menu = consucorner_explore_get_menu_by_location( $location );

	if ( ! $menu instanceof WP_Term ) {
		$menu = consucorner_explore_get_menu( $menu_names );
	}

	$items = $menu instanceof WP_Term ? wp_get_nav_menu_items( $menu->term_id ) : array();

	if ( empty( $items ) || is_wp_error( $items ) ) {
		$items = array_map(
			function ( $item ) {
				return (object) $item;
			},
			$fallback
		);
	}

	$items = array_filter(
		$items,
		function ( $item ) {
			return empty( $item->menu_item_parent );
		}
	);
	?>
	<div class="explore-links-group">
		<?php if ( '' !== (string) $heading ) : ?>
			<h3><?php echo esc_html( $heading ); ?></h3>
		<?php endif; ?>
		<?php foreach ( $items as $item ) : ?>
			<a href="<?php echo esc_url( $item->url ); ?>"><?php echo esc_html( $item->title ); ?></a>
		<?php endforeach; ?>
	</div>
	<?php
}

/**
 * Estimate reading time for a blog post.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function consucorner_explore_reading_time( $post_id ) {
	$content = get_post_field( 'post_content', $post_id );
	$words   = str_word_count( wp_strip_all_tags( $content ) );
	$minutes = max( 1, (int) ceil( $words / 220 ) );

	return sprintf(
		/* translators: %d: number of minutes. */
		_n( '%d Min. To Read', '%d Min. To Read', $minutes, 'consucorner' ),
		$minutes
	);
}

/**
 * Return the primary category label for a blog post.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function consucorner_explore_post_category_label( $post_id ) {
	$categories = get_the_category( $post_id );

	if ( empty( $categories ) || is_wp_error( $categories ) ) {
		return __( 'Blog', 'consucorner' );
	}

	return $categories[0]->name;
}

/**
 * Render the featured Explore post card.
 *
 * @param WP_Post|null $post Featured post.
 */
function consucorner_render_explore_featured_post( $post ) {
	$blog_url = get_permalink( get_option( 'page_for_posts' ) );
	if ( ! $blog_url ) {
		$blog_url = home_url( '/blogs/' );
	}

	if ( ! $post instanceof WP_Post ) {
		?>
		<a class="explore-featured-card" href="<?php echo esc_url( $blog_url ); ?>">
			<span class="explore-tag"><?php esc_html_e( 'Blog', 'consucorner' ); ?></span>
			<h4><?php esc_html_e( 'Visit The ConsuCorner Blog', 'consucorner' ); ?></h4>
			<p><?php esc_html_e( 'Read the latest product guides, marketplace updates, and medical supply insights.', 'consucorner' ); ?></p>
		</a>
		<?php
		return;
	}

	$post_id   = (int) $post->ID;
	$image_url = get_the_post_thumbnail_url( $post_id, 'medium_large' );

	if ( ! $image_url ) {
		$image_url = get_template_directory_uri() . '/assets/images/bloge image.png';
	}
	?>
	<a class="explore-featured-card" href="<?php echo esc_url( get_permalink( $post_id ) ); ?>">
		<span class="explore-tag"><?php echo esc_html( consucorner_explore_post_category_label( $post_id ) ); ?></span>
		<h4><?php echo esc_html( get_the_title( $post_id ) ); ?></h4>
		<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( get_the_title( $post_id ) ); ?>" loading="lazy" />
		<div class="explore-meta-row">
			<span><?php esc_html_e( 'ConsuCorner', 'consucorner' ); ?></span>
			<span><?php echo esc_html( get_the_date( 'd F Y', $post_id ) ); ?></span>
			<span><?php echo esc_html( consucorner_explore_reading_time( $post_id ) ); ?></span>
		</div>
		<p><?php echo esc_html( wp_trim_words( get_the_excerpt( $post_id ), 16 ) ); ?></p>
	</a>
	<?php
}

/**
 * Render a small recent Explore post card.
 *
 * @param WP_Post $post Post object.
 */
function consucorner_render_explore_recent_post( WP_Post $post ) {
	$post_id   = (int) $post->ID;
	$image_url = get_the_post_thumbnail_url( $post_id, 'thumbnail' );

	if ( ! $image_url ) {
		$image_url = function_exists( 'consucorner_get_product_placeholder_image_url' )
			? consucorner_get_product_placeholder_image_url()
			: get_template_directory_uri() . '/assets/images/' . rawurlencode( 'consucorner icon-logo.jpg' );
	}
	?>
	<a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>" class="explore-small-card">
		<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( get_the_title( $post_id ) ); ?>" loading="lazy" />
		<div class="explore-small-content">
			<span class="explore-small-tag"><?php echo esc_html( consucorner_explore_post_category_label( $post_id ) ); ?></span>
			<h6 class="explore-small-title"><?php echo esc_html( get_the_title( $post_id ) ); ?></h6>
			<div class="explore-small-meta">
				<span><?php esc_html_e( 'ConsuCorner', 'consucorner' ); ?></span>
				<span><?php echo esc_html( get_the_date( 'd F Y', $post_id ) ); ?></span>
				<span><?php echo esc_html( consucorner_explore_reading_time( $post_id ) ); ?></span>
			</div>
			<p class="explore-small-desc"><?php echo esc_html( wp_trim_words( get_the_excerpt( $post_id ), 10 ) ); ?></p>
		</div>
	</a>
	<?php
}

/**
 * Render the desktop Explore mega menu.
 */
function consucorner_render_explore_mega_menu() {
	$explore_images = array();
	if ( function_exists( 'consucorner_explore_mega_menu_get_image' ) ) {
		$explore_images = array(
			consucorner_explore_mega_menu_get_image( 1 ),
			consucorner_explore_mega_menu_get_image( 2 ),
			consucorner_explore_mega_menu_get_image( 3 ),
		);
	}
	?>
	<div class="explore-mega-menu" id="explore-mega-menu" aria-hidden="true">
		<div class="explore-mega-inner">
			<div class="explore-links-col">
				<?php
				consucorner_render_explore_link_group(
					'',
					array( 'Important desktop menu', 'Important Desktop Menu', 'Important Desktop', 'Explore - Important' ),
					array(
						array( 'title' => __( 'About Us', 'consucorner' ), 'url' => home_url( '/about/' ) ),
						array( 'title' => __( 'Contact Us', 'consucorner' ), 'url' => home_url( '/contact/' ) ),
						array( 'title' => __( 'Blog', 'consucorner' ), 'url' => home_url( '/blogs/' ) ),
						array( 'title' => __( 'ConsuCorner Partners', 'consucorner' ), 'url' => home_url( '/vendor/' ) ),
					),
					'explore-important'
				);

				consucorner_render_explore_link_group(
					__( 'Help', 'consucorner' ),
					array( 'Help Desktop menu', 'Help Desktop Menu', 'Help Desktop', 'Explore - Help' ),
					array(
						array( 'title' => __( 'Terms and Conditions', 'consucorner' ), 'url' => home_url( '/help/terms/' ) ),
						array( 'title' => __( 'Privacy Policy', 'consucorner' ), 'url' => home_url( '/privacy-policy/' ) ),
						array( 'title' => __( 'Contact & Support', 'consucorner' ), 'url' => home_url( '/contact/' ) ),
						array( 'title' => __( 'FAQ', 'consucorner' ), 'url' => home_url( '/help/faq/' ) ),
					),
					'explore-help'
				);
				?>
			</div>

			<div class="explore-image-grid" aria-label="<?php esc_attr_e( 'Explore featured links', 'consucorner' ); ?>">
				<?php foreach ( $explore_images as $index => $image ) : ?>
					<?php
					if ( empty( $image['image'] ) ) {
						continue;
					}

					$image_url   = ! empty( $image['url'] ) ? $image['url'] : '#';
					$image_class = 2 === (int) $index ? 'explore-image-card-wide' : 'explore-image-card-top';
					?>
					<a class="explore-image-card <?php echo esc_attr( $image_class ); ?>" href="<?php echo esc_url( $image_url ); ?>">
						<img src="<?php echo esc_url( $image['image'] ); ?>" alt="<?php echo esc_attr( $image['alt'] ); ?>" loading="lazy" decoding="async" />
					</a>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
	<?php
}
