<?php
/**
 * Theme icon helpers — Font Awesome (same library stack as Dokan / WoodMart-style icon fonts).
 *
 * @package ConsuCorner
 */

defined( 'ABSPATH' ) || exit;

/**
 * Legacy SVG sprite IDs mapped to Font Awesome 6 classes.
 *
 * @return array<string, string>
 */
function consucorner_icon_sprite_map() {
	return array(
		'icon-tag-cpu'     => 'fa-solid fa-microchip',
		'icon-tag-clock'   => 'fa-solid fa-truck-fast',
		'icon-tag-shield'  => 'fa-solid fa-shield-halved',
		'icon-cart'        => 'fa-solid fa-cart-shopping',
		'icon-chevron-left'  => 'fa-solid fa-chevron-left',
		'icon-chevron-right' => 'fa-solid fa-chevron-right',
		'icon-search'      => 'fa-solid fa-magnifying-glass',
		'icon-login'       => 'fa-solid fa-right-to-bracket',
	);
}

/**
 * Default hero banner tag icons per slide (Font Awesome — editable in admin).
 *
 * @return array<int, string>
 */
function consucorner_hero_banner_default_tag_icons() {
	return array(
		1 => 'fa-solid fa-wand-magic-sparkles',
		2 => 'fa-solid fa-truck-fast',
		3 => 'fa-solid fa-shield-halved',
	);
}

/**
 * Resolve a banner tag icon value to a Font Awesome class list.
 *
 * @param string $icon        Sprite id, FA class, or slug from meta.
 * @param int    $slide_index 1-based slide fallback.
 * @return string
 */
function consucorner_hero_banner_resolve_tag_fa_class( $icon, $slide_index = 1 ) {
	$icon = trim( (string) $icon );

	if ( '' === $icon ) {
		$defaults = consucorner_hero_banner_default_tag_icons();
		$icon     = $defaults[ (int) $slide_index ] ?? 'fa-solid fa-circle-info';
	}

	return consucorner_normalize_fa_icon_class( $icon );
}

/**
 * Render hero banner tag icon (always white, same slot as legacy .banner-tag svg).
 *
 * @param string $icon        Icon value from meta.
 * @param int    $slide_index 1-based slide number.
 * @return string
 */
function consucorner_hero_banner_render_tag_icon( $icon, $slide_index ) {
	$fa_class = consucorner_hero_banner_resolve_tag_fa_class( $icon, $slide_index );
	if ( '' === $fa_class ) {
		return '';
	}

	return sprintf(
		'<span class="banner-tag-icon" aria-hidden="true"><i class="%s"></i></span>',
		esc_attr( $fa_class )
	);
}

/**
 * Render hero banner button icon markup (fixed cart SVG — not editable).
 *
 * @return string
 */
function consucorner_hero_banner_render_btn_icon() {
	return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><use href="#icon-cart"></use></svg>';
}

/**
 * Normalize a Font Awesome class string.
 *
 * @param string $icon Icon slug, legacy sprite id, or full FA class list.
 * @return string
 */
function consucorner_normalize_fa_icon_class( $icon ) {
	$icon = trim( (string) $icon );
	if ( '' === $icon ) {
		return '';
	}

	$map = consucorner_icon_sprite_map();
	if ( isset( $map[ $icon ] ) ) {
		$icon = $map[ $icon ];
	}

	$icon = preg_replace( '/\s+/', ' ', $icon );

	if ( preg_match( '/\bfa-(solid|regular|brands|light|thin|duotone)\b/', $icon ) ) {
		return $icon;
	}

	if ( preg_match( '/\bfa-[a-z0-9-]+\b/', $icon ) ) {
		return 'fa-solid ' . $icon;
	}

	$icon = ltrim( $icon, 'fa-' );
	return 'fa-solid fa-' . sanitize_html_class( $icon );
}

/**
 * Enqueue Font Awesome from Dokan (already on the site) or theme fallback.
 */
function consucorner_enqueue_theme_icons() {
	if ( wp_style_is( 'dokan-fontawesome', 'registered' ) ) {
		wp_enqueue_style( 'dokan-fontawesome' );
		return;
	}

	$dokan_css = WP_PLUGIN_DIR . '/dokan-lite/assets/vendors/font-awesome/css/font-awesome.min.css';
	if ( file_exists( $dokan_css ) ) {
		wp_enqueue_style(
			'consucorner-fontawesome',
			plugins_url( 'dokan-lite/assets/vendors/font-awesome/css/font-awesome.min.css' ),
			array(),
			'6.5.1'
		);
	}
}

/**
 * Whether the current request should load the icon font.
 *
 * @return bool
 */
function consucorner_should_enqueue_theme_icons() {
	if ( is_front_page() ) {
		return true;
	}

	if ( function_exists( 'is_shop' ) && is_shop() ) {
		return true;
	}

	if ( function_exists( 'is_product_category' ) && is_product_category() ) {
		return true;
	}

	if ( function_exists( 'is_product_tag' ) && is_product_tag() ) {
		return true;
	}

	if ( function_exists( 'is_tax' ) && is_tax( 'specialty' ) ) {
		return true;
	}

	return (bool) apply_filters( 'consucorner_enqueue_theme_icons', false );
}

/**
 * @param string               $icon Icon name or FA class list.
 * @param array<string, mixed> $args Optional args: class, size (sm|lg), aria_hidden.
 * @return string
 */
function consucorner_icon( $icon, $args = array() ) {
	$fa_class = consucorner_normalize_fa_icon_class( $icon );
	if ( '' === $fa_class ) {
		return '';
	}

	$args = wp_parse_args(
		$args,
		array(
			'class'       => '',
			'size'        => '',
			'aria_hidden' => true,
		)
	);

	$classes = array( 'cc-icon', $fa_class );
	if ( ! empty( $args['size'] ) ) {
		$classes[] = 'cc-icon--' . sanitize_html_class( (string) $args['size'] );
	}
	if ( ! empty( $args['class'] ) ) {
		$classes[] = (string) $args['class'];
	}

	$attrs = array( 'class' => implode( ' ', array_filter( $classes ) ) );
	if ( ! empty( $args['aria_hidden'] ) ) {
		$attrs['aria-hidden'] = 'true';
	}

	$html = '<i';
	foreach ( $attrs as $key => $value ) {
		$html .= ' ' . $key . '="' . esc_attr( $value ) . '"';
	}
	$html .= '></i>';

	return $html;
}

/**
 * Echo a Font Awesome icon.
 *
 * @param string               $icon Icon name.
 * @param array<string, mixed> $args Optional args.
 */
function consucorner_the_icon( $icon, $args = array() ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in consucorner_icon().
	echo consucorner_icon( $icon, $args );
}

/**
 * Render hero product-banner slides (homepage + filter sidebar).
 *
 * @param array<int, array<string, string>> $slides Slide definitions.
 * @param array<string, mixed>              $args   title_class, image_class, shop_url.
 */
function consucorner_render_hero_banner_slides( array $slides, $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'title_class' => 'banner-title',
			'image_class' => 'banner-product-image',
			'shop_url'    => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' ),
		)
	);

	$total = count( $slides );
	if ( $total < 1 ) {
		return;
	}

	foreach ( $slides as $index => $slide ) {
		if ( ! is_array( $slide ) ) {
			continue;
		}

		$tag_icon  = isset( $slide['tag_icon'] ) ? (string) $slide['tag_icon'] : '';
		$tag       = isset( $slide['tag'] ) ? (string) $slide['tag'] : '';
		$title     = isset( $slide['title'] ) ? (string) $slide['title'] : '';
		$button    = isset( $slide['button'] ) ? (string) $slide['button'] : '';
		$btn_link = isset( $slide['btn_link'] ) ? trim( (string) $slide['btn_link'] ) : '';
		if ( '' === $btn_link ) {
			$btn_link = (string) $args['shop_url'];
		}
		$image     = isset( $slide['image'] ) ? (string) $slide['image'] : '';
		$image_alt = isset( $slide['image_alt'] ) ? (string) $slide['image_alt'] : wp_strip_all_tags( $title );
		$is_active = ( 0 === (int) $index );
		$slide_num = (int) $index + 1;
		?>
		<div class="hero-slide<?php echo $is_active ? ' active' : ''; ?>"<?php echo $is_active ? '' : ' aria-hidden="true"'; ?>>
			<div class="banner-overlay">
				<span class="banner-tag">
					<?php
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built in consucorner_hero_banner_render_tag_icon().
					echo consucorner_hero_banner_render_tag_icon( $tag_icon, $slide_num );
					?>
					<?php echo esc_html( $tag ); ?>
				</span>
				<h2 class="<?php echo esc_attr( (string) $args['title_class'] ); ?>">
					<?php echo wp_kses_post( $title ); ?>
				</h2>
				<a href="<?php echo esc_url( $btn_link ); ?>" class="banner-btn"<?php echo $is_active ? '' : ' tabindex="-1"'; ?>>
					<?php
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built in consucorner_hero_banner_render_btn_icon().
					echo consucorner_hero_banner_render_btn_icon();
					?>
					<?php echo esc_html( $button ); ?>
				</a>
				<div class="banner-bottom-info">
					<div class="banner-slider-indicator">
						<span class="active-slide"><?php echo esc_html( sprintf( '%02d', (int) $index + 1 ) ); ?></span><span class="total-slide">/<?php echo esc_html( sprintf( '%02d', $total ) ); ?></span>
					</div>
					<div class="slide-line" style="--hero-progress: <?php echo esc_attr( (string) ( ( $slide_num / max( 1, $total ) ) * 100 ) ); ?>%;"></div>
				</div>
			</div>
			<?php if ( '' !== $image ) : ?>
				<img
					src="<?php echo esc_url( $image ); ?>"
					alt="<?php echo esc_attr( $image_alt ); ?>"
					class="<?php echo esc_attr( (string) $args['image_class'] ); ?>"
					loading="lazy"
					decoding="async"
				/>
			<?php endif; ?>
		</div>
		<?php
	}
}

/**
 * Build homepage hero banner slide data from post meta.
 *
 * @param int    $post_id   Front page ID.
 * @param string $img_base  Theme images base URI.
 * @return array<int, array<string, string>>
 */
function consucorner_get_home_hero_banner_slides( $post_id, $img_base ) {
	$post_id  = absint( $post_id );
	$defaults = consucorner_hero_banner_default_tag_icons();
	$slides   = array();

	for ( $n = 1; $n <= 3; $n++ ) {
		$default_titles = array(
			1 => 'Shop Now With<br />Premium<br />Quality',
			2 => 'Explore Global<br />Brands at<br />Your Doorstep',
			3 => 'Reliable Checkouts<br />For Every<br />Order',
		);
		$default_tags = array(
			1 => 'Future is here',
			2 => 'Fast Delivery',
			3 => 'Secure Payments',
		);
		$default_btns = array(
			1 => 'Shop Now',
			2 => 'Shop Brands',
			3 => 'Order Now',
		);

		$title = function_exists( 'cc_front_meta' )
			? cc_front_meta( $post_id, "_cc_home_banner_{$n}_title", $default_titles[ $n ] )
			: $default_titles[ $n ];

		$slides[] = array(
			'tag_icon'  => function_exists( 'cc_front_meta' )
				? cc_front_meta( $post_id, "_cc_home_banner_{$n}_tag_icon", $defaults[ $n ] )
				: $defaults[ $n ],
			'tag'       => function_exists( 'cc_front_meta' )
				? cc_front_meta( $post_id, "_cc_home_banner_{$n}_tag", $default_tags[ $n ] )
				: $default_tags[ $n ],
			'title'     => $title,
			'button'    => function_exists( 'cc_front_meta' )
				? cc_front_meta( $post_id, "_cc_home_banner_{$n}_btn_text", $default_btns[ $n ] )
				: $default_btns[ $n ],
			'btn_link'  => function_exists( 'cc_front_meta' )
				? cc_front_meta( $post_id, "_cc_home_banner_{$n}_btn_link", home_url( '/shop/' ) )
				: home_url( '/shop/' ),
			'image'     => function_exists( 'cc_front_meta' )
				? cc_front_meta( $post_id, "_cc_home_banner_{$n}_image", $img_base . 'product banner.png' )
				: $img_base . 'product banner.png',
			'image_alt' => wp_strip_all_tags( $title ),
		);
	}

	return $slides;
}

/**
 * Default filter-sidebar hero banner slides.
 *
 * @param string $theme_uri Theme URI.
 * @return array<int, array<string, string>>
 */
function consucorner_get_sidebar_hero_banner_slides( $theme_uri ) {
	$fa_defaults = consucorner_hero_banner_default_tag_icons();
	$home_id     = absint( get_option( 'page_on_front' ) );
	$image       = trailingslashit( (string) $theme_uri ) . 'assets/images/Bundle.svg';

	$slide_defs = array(
		array(
			'tag_key' => 1,
			'tag'     => __( 'Future is here', 'consucorner' ),
			'title'   => __( 'Shop Now With', 'consucorner' ) . '<br>' . __( 'Premium', 'consucorner' ) . '<br>' . __( 'Quality', 'consucorner' ),
			'button'  => __( 'Shop Now', 'consucorner' ),
		),
		array(
			'tag_key' => 2,
			'tag'     => __( 'Fast Delivery', 'consucorner' ),
			'title'   => __( 'Explore Global', 'consucorner' ) . '<br>' . __( 'Brands at', 'consucorner' ) . '<br>' . __( 'Your Doorstep', 'consucorner' ),
			'button'  => __( 'Shop Brands', 'consucorner' ),
		),
		array(
			'tag_key' => 3,
			'tag'     => __( 'Secure Payments', 'consucorner' ),
			'title'   => __( 'Reliable Checkouts', 'consucorner' ) . '<br>' . __( 'For Every', 'consucorner' ) . '<br>' . __( 'Order', 'consucorner' ),
			'button'  => __( 'Order Now', 'consucorner' ),
		),
	);

	$slides = array();
	foreach ( $slide_defs as $def ) {
		$n = (int) $def['tag_key'];
		$slides[] = array(
			'tag_icon' => ( $home_id && function_exists( 'cc_front_meta' ) )
				? cc_front_meta( $home_id, "_cc_home_banner_{$n}_tag_icon", $fa_defaults[ $n ] )
				: $fa_defaults[ $n ],
			'tag'      => $def['tag'],
			'title'    => $def['title'],
			'button'   => $def['button'],
			'image'    => $image,
		);
	}

	return $slides;
}

add_action(
	'wp_enqueue_scripts',
	static function () {
		if ( consucorner_should_enqueue_theme_icons() ) {
			consucorner_enqueue_theme_icons();
		}
	},
	25
);
