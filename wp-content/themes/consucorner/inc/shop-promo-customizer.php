<?php
/**
 * Theme Customizer: main shop promo slider + shared slide renderer.
 *
 * @package ConsuCorner
 */

defined( 'ABSPATH' ) || exit;

/**
 * True on the main WooCommerce shop archive only (not product_cat, specialty, or tags).
 *
 * @return bool
 */
function consucorner_is_main_shop_archive() {
	if ( ! function_exists( 'is_shop' ) || ! is_shop() ) {
		return false;
	}

	if ( function_exists( 'is_product_category' ) && is_product_category() ) {
		return false;
	}

	if ( function_exists( 'is_product_tag' ) && is_product_tag() ) {
		return false;
	}

	if ( is_tax( 'specialty' ) ) {
		return false;
	}

	return true;
}

/**
 * Shop page URL used for Customizer preview and CTA fallbacks.
 *
 * @return string
 */
function consucorner_shop_promo_shop_page_url() {
	if ( function_exists( 'wc_get_page_permalink' ) ) {
		$url = wc_get_page_permalink( 'shop' );
		if ( $url && ! is_wp_error( $url ) ) {
			return (string) $url;
		}
	}

	return home_url( '/shop/' );
}

/**
 * Maximum slide slots in the Customizer (1 … this number).
 *
 * @return int
 */
function consucorner_shop_promo_max_slots() {
	return (int) apply_filters( 'consucorner_shop_promo_max_slots', 15 );
}

/**
 * Sanitize slide count (1 … max slots).
 *
 * @param mixed $value Raw value.
 * @return int
 */
function consucorner_shop_promo_sanitize_slide_count( $value ) {
	$max = consucorner_shop_promo_max_slots();
	$v   = (int) $value;
	if ( $v < 1 ) {
		$v = 1;
	}
	if ( $v > $max ) {
		$v = $max;
	}
	return $v;
}

/**
 * Whether all Customizer fields for a slide are empty (never saved / cleared).
 *
 * @param string $prefix Setting prefix e.g. cc_shop_promo_2_.
 * @return bool
 */
function consucorner_shop_promo_all_theme_mods_empty_for_slide( $prefix ) {
	return '' === (string) get_theme_mod( $prefix . 'background', '' )
		&& '' === (string) get_theme_mod( $prefix . 'title', '' )
		&& '' === (string) get_theme_mod( $prefix . 'text', '' )
		&& '' === (string) get_theme_mod( $prefix . 'button_text', '' )
		&& '' === (string) get_theme_mod( $prefix . 'button_url', '' )
		&& '' === (string) get_theme_mod( $prefix . 'flag_image', '' )
		&& '' === (string) get_theme_mod( $prefix . 'flag_url', '' );
}

/**
 * Register Customizer settings for the shop promo slider.
 *
 * @param WP_Customize_Manager $wp_customize Customizer object.
 */
function consucorner_shop_promo_customize_register( $wp_customize ) {
	if ( ! class_exists( 'WP_Customize_Image_Control' ) ) {
		require_once ABSPATH . WPINC . '/class-wp-customize-image-control.php';
	}

	$max = consucorner_shop_promo_max_slots();

	$wp_customize->add_panel(
		'cc_shop_promo_panel',
		array(
			'title'       => __( 'Shop promo slider', 'consucorner' ),
			'description' => __( 'Promo slider shown only on the main Shop page (/shop/). Category and specialty archives use per-term banners in Products → Categories / Specialties.', 'consucorner' ),
			'priority'    => 50,
		)
	);

	$wp_customize->add_section(
		'cc_shop_promo_general',
		array(
			'title' => __( 'Slider setup', 'consucorner' ),
			'panel' => 'cc_shop_promo_panel',
		)
	);

	$wp_customize->add_setting(
		'cc_shop_promo_slide_count',
		array(
			'default'           => 3,
			'sanitize_callback' => 'consucorner_shop_promo_sanitize_slide_count',
			'transport'         => 'refresh',
		)
	);
	$wp_customize->add_control(
		'cc_shop_promo_slide_count',
		array(
			'label'       => __( 'Number of slides', 'consucorner' ),
			'description' => sprintf(
				/* translators: %d: maximum slides */
				__( 'Only this many slides are shown (1–%d). Lower the number to hide trailing slides. Raise it to configure more sections below.', 'consucorner' ),
				$max
			),
			'section'     => 'cc_shop_promo_general',
			'type'        => 'number',
			'input_attrs' => array(
				'min'  => 1,
				'max'  => $max,
				'step' => 1,
			),
		)
	);

	for ( $n = 1; $n <= $max; $n++ ) {
		$section_id = 'cc_shop_promo_slide_' . $n;
		$wp_customize->add_section(
			$section_id,
			array(
				'title'       => sprintf(
					/* translators: 1: slide number, 2: max slots */
					__( 'Promo slide %1$d (of up to %2$d)', 'consucorner' ),
					$n,
					$max
				),
				'description' => __( 'Ignored on the storefront if this index is greater than “Number of slides”.', 'consucorner' ),
				'panel'       => 'cc_shop_promo_panel',
			)
		);

		$prefix = 'cc_shop_promo_' . $n . '_';

		$wp_customize->add_setting(
			$prefix . 'background',
			array(
				'default'           => '',
				'sanitize_callback' => 'esc_url_raw',
				'transport'         => 'refresh',
			)
		);
		$wp_customize->add_control(
			new WP_Customize_Image_Control(
				$wp_customize,
				$prefix . 'background',
				array(
					'label'       => __( 'Background image', 'consucorner' ),
					'description' => __( 'Recommended upload: 1208 × 390 px JPG/WebP. The storefront uses this landscape crop when available.', 'consucorner' ),
					'section'     => $section_id,
				)
			)
		);

		$wp_customize->add_setting(
			$prefix . 'title',
			array(
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
				'transport'         => 'refresh',
			)
		);
		$wp_customize->add_control(
			$prefix . 'title',
			array(
				'label'   => __( 'Heading (H2)', 'consucorner' ),
				'section' => $section_id,
				'type'    => 'text',
			)
		);

		$wp_customize->add_setting(
			$prefix . 'text',
			array(
				'default'           => '',
				'sanitize_callback' => 'sanitize_textarea_field',
				'transport'         => 'refresh',
			)
		);
		$wp_customize->add_control(
			$prefix . 'text',
			array(
				'label'   => __( 'Paragraph text', 'consucorner' ),
				'section' => $section_id,
				'type'    => 'textarea',
			)
		);

		$wp_customize->add_setting(
			$prefix . 'button_text',
			array(
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
				'transport'         => 'refresh',
			)
		);
		$wp_customize->add_control(
			$prefix . 'button_text',
			array(
				'label'   => __( 'Button label', 'consucorner' ),
				'section' => $section_id,
				'type'    => 'text',
			)
		);

		$wp_customize->add_setting(
			$prefix . 'button_url',
			array(
				'default'           => '',
				'sanitize_callback' => 'esc_url_raw',
				'transport'         => 'refresh',
			)
		);
		$wp_customize->add_control(
			$prefix . 'button_url',
			array(
				'label'       => __( 'Button link', 'consucorner' ),
				'description' => __( 'Leave empty to use the main shop page URL.', 'consucorner' ),
				'section'     => $section_id,
				'type'        => 'url',
			)
		);

		$wp_customize->add_setting(
			$prefix . 'flag_image',
			array(
				'default'           => '',
				'sanitize_callback' => 'esc_url_raw',
				'transport'         => 'refresh',
			)
		);
		$wp_customize->add_control(
			new WP_Customize_Image_Control(
				$wp_customize,
				$prefix . 'flag_image',
				array(
					'label'       => __( 'Flag / badge image', 'consucorner' ),
					'description' => __( 'Shown as a circle next to the button. Leave empty to use the theme default.', 'consucorner' ),
					'section'     => $section_id,
				)
			)
		);

		$wp_customize->add_setting(
			$prefix . 'flag_url',
			array(
				'default'           => '',
				'sanitize_callback' => 'esc_url_raw',
				'transport'         => 'refresh',
			)
		);
		$wp_customize->add_control(
			$prefix . 'flag_url',
			array(
				'label'       => __( 'Flag link', 'consucorner' ),
				'description' => __( 'Optional URL when visitors click the flag. Leave empty for a non-clickable flag.', 'consucorner' ),
				'section'     => $section_id,
				'type'        => 'url',
			)
		);
	}
}
add_action( 'customize_register', 'consucorner_shop_promo_customize_register', 20 );

/**
 * When the Shop promo panel opens, switch the Customizer preview to the shop page.
 */
function consucorner_shop_promo_customize_controls_scripts() {
	$shop_url = consucorner_shop_promo_shop_page_url();
	?>
	<script>
	( function ( $ ) {
		'use strict';
		wp.customize.panel( 'cc_shop_promo_panel', function ( panel ) {
			panel.expanded.bind( function ( isExpanded ) {
				if ( isExpanded && wp.customize.previewer ) {
					wp.customize.previewer.previewUrl.set( <?php echo wp_json_encode( $shop_url ); ?> );
				}
			} );
		} );
	} )( jQuery );
	</script>
	<?php
}
add_action( 'customize_controls_print_footer_scripts', 'consucorner_shop_promo_customize_controls_scripts' );

/**
 * True if the PHP fallback row has any non-empty content.
 *
 * @param array $fb Fallback slide row.
 * @return bool
 */
function consucorner_shop_promo_fallback_slide_is_meaningful( $fb ) {
	if ( ! is_array( $fb ) ) {
		return false;
	}
	foreach ( array( 'title', 'subtitle', 'button', 'image' ) as $key ) {
		if ( ! empty( $fb[ $key ] ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Resolve a promo background URL to the registered 1208×390 crop when possible.
 *
 * @param string $image_url Image URL from Customizer/fallback.
 * @return string
 */
function consucorner_shop_promo_resolve_background_url( $image_url ) {
	$image_url = (string) $image_url;
	if ( '' === $image_url || ! function_exists( 'attachment_url_to_postid' ) ) {
		return $image_url;
	}

	$attachment_id = attachment_url_to_postid( $image_url );
	if ( ! $attachment_id ) {
		return $image_url;
	}

	$cropped = wp_get_attachment_image_url( $attachment_id, 'cc_shop_promo_banner' );
	return $cropped ? (string) $cropped : $image_url;
}

/**
 * Build slide data from Customizer + PHP fallbacks (main shop only).
 *
 * @param array  $fallback_slides Slides from archive-product (title, subtitle, button, optional image).
 * @param string $shop_url        Default button URL when Customizer button URL is empty.
 * @param string $banner_url      Default background when Customizer background is empty.
 * @param string $theme_uri       Theme URI for default flag asset.
 * @return array<int, array<string, string>>
 */
function consucorner_shop_promo_get_slides( $fallback_slides, $shop_url, $banner_url, $theme_uri ) {
	if ( ! is_array( $fallback_slides ) ) {
		$fallback_slides = array();
	}

	$default_flag = $theme_uri . '/assets/images/germany-flag.png';
	$slides       = array();
	$max          = consucorner_shop_promo_max_slots();
	$count        = consucorner_shop_promo_sanitize_slide_count( get_theme_mod( 'cc_shop_promo_slide_count', 3 ) );

	for ( $n = 1; $n <= $count; $n++ ) {
		$fb = isset( $fallback_slides[ $n - 1 ] ) && is_array( $fallback_slides[ $n - 1 ] )
			? $fallback_slides[ $n - 1 ]
			: array();

		$prefix = 'cc_shop_promo_' . $n . '_';

		$bg = get_theme_mod( $prefix . 'background', '' );
		if ( '' === $bg ) {
			$bg = ! empty( $fb['image'] ) ? (string) $fb['image'] : (string) $banner_url;
		}
		$bg = consucorner_shop_promo_resolve_background_url( $bg );

		$title = get_theme_mod( $prefix . 'title', '' );
		if ( '' === $title && isset( $fb['title'] ) ) {
			$title = (string) $fb['title'];
		}

		$subtitle = get_theme_mod( $prefix . 'text', '' );
		if ( '' === $subtitle && isset( $fb['subtitle'] ) ) {
			$subtitle = (string) $fb['subtitle'];
		}

		$button = get_theme_mod( $prefix . 'button_text', '' );
		if ( '' === $button && isset( $fb['button'] ) ) {
			$button = (string) $fb['button'];
		}

		$button_url = get_theme_mod( $prefix . 'button_url', '' );
		if ( '' === $button_url ) {
			$button_url = (string) $shop_url;
		}

		$flag_image = get_theme_mod( $prefix . 'flag_image', '' );
		if ( '' === $flag_image ) {
			$flag_image = $default_flag;
		}

		$flag_url = get_theme_mod( $prefix . 'flag_url', '' );

		$text_blob = trim( $title . $subtitle . $button );
		if (
			'' === $text_blob
			&& consucorner_shop_promo_all_theme_mods_empty_for_slide( $prefix )
			&& ! consucorner_shop_promo_fallback_slide_is_meaningful( $fb )
		) {
			continue;
		}

		$slides[] = array(
			'title'      => $title,
			'subtitle'   => $subtitle,
			'button'     => $button,
			'button_url' => $button_url,
			'image'      => $bg,
			'flag_image' => $flag_image,
			'flag_url'   => $flag_url,
		);
	}

	if ( empty( $slides ) && ! empty( $fallback_slides[0] ) && is_array( $fallback_slides[0] ) ) {
		$fb0      = $fallback_slides[0];
		$slides[] = array(
			'title'      => isset( $fb0['title'] ) ? (string) $fb0['title'] : '',
			'subtitle'   => isset( $fb0['subtitle'] ) ? (string) $fb0['subtitle'] : '',
			'button'     => isset( $fb0['button'] ) ? (string) $fb0['button'] : '',
			'button_url' => (string) $shop_url,
			'image'      => consucorner_shop_promo_resolve_background_url( ! empty( $fb0['image'] ) ? (string) $fb0['image'] : (string) $banner_url ),
			'flag_image' => $default_flag,
			'flag_url'   => '',
		);
	}

	return $slides;
}

/**
 * Render adaptive Country of Origin badges for term archive promo banners.
 *
 * @param array<string, mixed> $origins Origin payload from cc_get_archive_country_origins().
 */
function consucorner_shop_promo_render_origins( $origins ) {
	if ( ! is_array( $origins ) || empty( $origins['visible'] ) || empty( $origins['mode'] ) || 'none' === $origins['mode'] ) {
		return;
	}

	$visible      = is_array( $origins['visible'] ) ? $origins['visible'] : array();
	$all          = is_array( $origins['all'] ?? null ) ? $origins['all'] : array();
	$hidden_count = isset( $origins['hidden_count'] ) ? max( 0, (int) $origins['hidden_count'] ) : 0;
	$mode         = sanitize_html_class( (string) $origins['mode'] );
	$popover_id   = $hidden_count > 0 && ! empty( $all ) ? wp_unique_id( 'ab-promo-origin-popover-' ) : '';

	/**
	 * Build a circular flag badge for cluster or popover rows.
	 *
	 * @param array<string, mixed> $origin Origin item.
	 * @param int                  $size   Image size in pixels.
	 */
	$render_origin_badge = static function ( $origin, $size = 44 ) {
		if ( ! is_array( $origin ) ) {
			return '';
		}

		$name      = isset( $origin['name'] ) ? (string) $origin['name'] : '';
		$image_url = isset( $origin['image_url'] ) ? (string) $origin['image_url'] : '';
		$initial   = function_exists( 'mb_substr' ) ? mb_substr( $name, 0, 1 ) : substr( $name, 0, 1 );
		$badge     = '<span class="ab-promo-flag" role="img" aria-label="' . esc_attr( $name ) . '">';

		if ( '' !== $image_url ) {
			$badge .= '<img src="' . esc_url( $image_url ) . '" alt="" width="' . (int) $size . '" height="' . (int) $size . '" loading="lazy" decoding="async" />';
		} else {
			$badge .= '<span class="ab-promo-origin-initial" aria-hidden="true">' . esc_html( strtoupper( $initial ) ) . '</span>';
		}

		$badge .= '</span>';

		return $badge;
	};
	?>
	<div class="ab-promo-origin-block ab-promo-origin-block--<?php echo esc_attr( $mode ); ?>">
		<div class="ab-promo-origin-cluster" aria-label="<?php esc_attr_e( 'Countries of origin', 'consucorner' ); ?>">
			<?php foreach ( $visible as $origin ) : ?>
				<?php
				if ( ! is_array( $origin ) ) {
					continue;
				}
				$name       = isset( $origin['name'] ) ? (string) $origin['name'] : '';
				$filter_url = isset( $origin['filter_url'] ) ? (string) $origin['filter_url'] : '';
				$badge      = $render_origin_badge( $origin );
				?>
				<?php if ( '' !== $filter_url ) : ?>
					<a class="ab-promo-flag-link ab-promo-origin-link" href="<?php echo esc_url( $filter_url ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: country name */ __( 'Filter by %s', 'consucorner' ), $name ) ); ?>">
						<?php echo $badge; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>
					</a>
				<?php else : ?>
					<span class="ab-promo-origin-link">
						<?php echo $badge; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>
					</span>
				<?php endif; ?>
			<?php endforeach; ?>
			<?php if ( $hidden_count > 0 && '' !== $popover_id ) : ?>
				<button
					class="ab-promo-origin-more"
					type="button"
					data-origin-more
					data-origin-popover="<?php echo esc_attr( $popover_id ); ?>"
					aria-expanded="false"
					aria-controls="<?php echo esc_attr( $popover_id ); ?>"
					aria-label="<?php echo esc_attr( sprintf( /* translators: %d: hidden countries */ __( 'Show %d more countries of origin', 'consucorner' ), $hidden_count ) ); ?>"
				>
					<?php echo esc_html( sprintf( '+%d', $hidden_count ) ); ?>
				</button>
			<?php endif; ?>
		</div>
		<?php if ( $hidden_count > 0 && '' !== $popover_id ) : ?>
			<?php
			$modal_id    = $popover_id . '-modal';
			$heading_id  = $popover_id . '-title';
			$total_count = isset( $origins['total'] ) ? max( 0, (int) $origins['total'] ) : count( $all );
			?>
			<div
				id="<?php echo esc_attr( $modal_id ); ?>"
				class="ab-promo-origin-modal"
				data-origin-modal
				hidden
			>
				<button
					type="button"
					class="ab-promo-origin-backdrop"
					data-origin-close
					tabindex="-1"
					aria-hidden="true"
				></button>
				<div
					id="<?php echo esc_attr( $popover_id ); ?>"
					class="ab-promo-origin-popover"
					role="dialog"
					aria-modal="true"
					aria-labelledby="<?php echo esc_attr( $heading_id ); ?>"
					hidden
				>
					<div class="ab-promo-origin-popover-header">
						<div class="ab-promo-origin-popover-heading">
							<h3 id="<?php echo esc_attr( $heading_id ); ?>" class="ab-promo-origin-popover-title">
								<?php esc_html_e( 'Countries of origin', 'consucorner' ); ?>
							</h3>
							<?php if ( $total_count > 0 ) : ?>
								<p class="ab-promo-origin-popover-count">
									<?php
									echo esc_html(
										sprintf(
											/* translators: %d: number of countries */
											_n( '%d country', '%d countries', $total_count, 'consucorner' ),
											$total_count
										)
									);
									?>
								</p>
							<?php endif; ?>
						</div>
						<button
							type="button"
							class="ab-promo-origin-popover-close"
							data-origin-close
							aria-label="<?php esc_attr_e( 'Close countries list', 'consucorner' ); ?>"
						>
							<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false">
								<path d="M6 6l12 12M18 6L6 18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" />
							</svg>
						</button>
					</div>
					<div class="ab-promo-origin-popover-body">
						<?php foreach ( $all as $origin ) : ?>
							<?php
							if ( ! is_array( $origin ) ) {
								continue;
							}
							$name       = isset( $origin['name'] ) ? (string) $origin['name'] : '';
							$filter_url = isset( $origin['filter_url'] ) ? (string) $origin['filter_url'] : '';
							$badge      = $render_origin_badge( $origin, 36 );
							?>
							<?php if ( '' !== $filter_url ) : ?>
								<a class="ab-promo-origin-popover-item" href="<?php echo esc_url( $filter_url ); ?>">
									<?php echo $badge; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>
									<span class="ab-promo-origin-popover-name"><?php echo esc_html( $name ); ?></span>
								</a>
							<?php else : ?>
								<div class="ab-promo-origin-popover-item ab-promo-origin-popover-item--static">
									<?php echo $badge; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>
									<span class="ab-promo-origin-popover-name"><?php echo esc_html( $name ); ?></span>
								</div>
							<?php endif; ?>
						<?php endforeach; ?>
					</div>
				</div>
			</div>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Render one promo slide card (shared by slider + static banner templates).
 *
 * @param array<string, string> $promo_slide      Slide data.
 * @param int                     $index            Slide index.
 * @param string                  $theme_uri        Theme URI.
 * @param string                  $shop_url         Shop URL fallback.
 * @param string                  $banner_url       Background fallback.
 * @param bool                    $is_active        Whether slide is active/visible.
 * @param bool                    $use_slider_attrs Whether to output slider data attributes.
 */
function consucorner_shop_promo_render_slide( $promo_slide, $index, $theme_uri, $shop_url, $banner_url, $is_active, $use_slider_attrs = true ) {
	$slide_bg = '';
	if ( ! empty( $promo_slide['image'] ) ) {
		$slide_bg = (string) $promo_slide['image'];
	} elseif ( '' !== $banner_url ) {
		$slide_bg = $banner_url;
	}
	$slide_style = '' !== $slide_bg
		? 'background-image:url(' . esc_url( $slide_bg ) . ');'
		: '';
	$title          = isset( $promo_slide['title'] ) ? (string) $promo_slide['title'] : '';
	$subtitle       = isset( $promo_slide['subtitle'] ) ? (string) $promo_slide['subtitle'] : '';
	$button         = isset( $promo_slide['button'] ) ? (string) $promo_slide['button'] : '';
	$button_url     = isset( $promo_slide['button_url'] ) ? (string) $promo_slide['button_url'] : '';
	$flag_image     = isset( $promo_slide['flag_image'] ) ? (string) $promo_slide['flag_image'] : '';
	$flag_url       = isset( $promo_slide['flag_url'] ) ? (string) $promo_slide['flag_url'] : '';
	$origins        = isset( $promo_slide['origins'] ) && is_array( $promo_slide['origins'] ) ? $promo_slide['origins'] : array();
	$design         = function_exists( 'consucorner_term_promo_normalize_design' )
		? consucorner_term_promo_normalize_design( $promo_slide['design'] ?? 'normal' )
		: 'normal';
	$is_offer       = ( 'offer' === $design );
	$btn_href       = '' !== $button_url ? $button_url : ( '' !== $shop_url ? $shop_url : home_url( '/' ) );
	$slide_label    = '' !== $title ? $title : __( 'Featured offer', 'consucorner' );
	$has_slide_link = '' !== $btn_href && '#' !== $btn_href;
	$slide_classes  = 'ab-promo-slide';
	if ( $is_active ) {
		$slide_classes .= ' is-active';
	}
	if ( $has_slide_link ) {
		$slide_classes .= ' has-slide-link';
	}
	if ( $is_offer ) {
		$slide_classes .= ' ab-promo-slide--offer';
	}
	?>
	<article
		class="<?php echo esc_attr( $slide_classes ); ?>"
		<?php if ( $use_slider_attrs ) : ?>
			data-promo-slide
			data-promo-index="<?php echo esc_attr( (string) $index ); ?>"
		<?php endif; ?>
		<?php if ( $has_slide_link ) : ?>
			data-promo-url="<?php echo esc_url( $btn_href ); ?>"
		<?php endif; ?>
		aria-hidden="<?php echo $is_active ? 'false' : 'true'; ?>"
		<?php echo '' !== $slide_style ? ' style="' . esc_attr( $slide_style ) . '"' : ''; ?>
	>
		<?php if ( $has_slide_link ) : ?>
			<a
				class="ab-promo-slide-link"
				href="<?php echo esc_url( $btn_href ); ?>"
				aria-label="<?php echo esc_attr( sprintf( /* translators: %s: promo slide title */ __( 'View offer: %s', 'consucorner' ), $slide_label ) ); ?>"
			></a>
		<?php endif; ?>
		<div class="ab-promo-copy">
			<?php if ( $is_offer ) : ?>
				<span class="ab-promo-offer-badge"><?php esc_html_e( 'Offer', 'consucorner' ); ?></span>
			<?php endif; ?>
			<?php if ( '' !== $title ) : ?>
				<h2 class="ab-promo-title"><?php echo esc_html( $title ); ?></h2>
			<?php endif; ?>
			<?php if ( '' !== $subtitle ) : ?>
				<p class="ab-promo-sub"><?php echo esc_html( $subtitle ); ?></p>
			<?php endif; ?>
			<div class="ab-promo-cta-row">
				<?php if ( '' !== $button ) : ?>
					<a href="<?php echo esc_url( $btn_href ); ?>" class="ab-promo-btn"><?php echo esc_html( $button ); ?></a>
				<?php endif; ?>
				<?php if ( ! empty( $origins ) && ! empty( $origins['mode'] ) && 'none' !== $origins['mode'] ) : ?>
					<?php consucorner_shop_promo_render_origins( $origins ); ?>
				<?php elseif ( '' !== $flag_image ) : ?>
					<?php if ( '' !== $flag_url ) : ?>
						<a class="ab-promo-flag-link" href="<?php echo esc_url( $flag_url ); ?>">
							<span class="ab-promo-flag" role="img" aria-label="<?php esc_attr_e( 'Promo badge', 'consucorner' ); ?>">
								<img src="<?php echo esc_url( $flag_image ); ?>" alt="" width="44" height="44" loading="lazy" decoding="async" />
							</span>
						</a>
					<?php else : ?>
						<span class="ab-promo-flag" role="img" aria-label="<?php esc_attr_e( 'Promo badge', 'consucorner' ); ?>">
							<img src="<?php echo esc_url( $flag_image ); ?>" alt="" width="44" height="44" loading="lazy" decoding="async" />
						</span>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		</div>
	</article>
	<?php
}
