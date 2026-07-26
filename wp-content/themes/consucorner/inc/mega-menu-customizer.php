<?php

/**
 * Customizer: Shop mega-menu promo banners (2 clickable images).
 *
 * @package ConsuCorner
 */

defined('ABSPATH') || exit;

/**
 * Default values for the two mega-menu banners.
 *
 * @return array<string, string>
 */
function consucorner_mega_menu_banner_defaults()
{
	$base = trailingslashit(get_template_directory_uri()) . 'assets/images/';

	return array(
		'cc_mega_banner_1_image' => $base . 'Rectangle 3157.png',
		'cc_mega_banner_1_url'   => home_url('/shop/'),
		'cc_mega_banner_1_alt'   => __('Featured promo', 'consucorner'),
		'cc_mega_banner_2_image' => $base . 'Rectangle 3158.png',
		'cc_mega_banner_2_url'   => home_url('/shop/'),
		'cc_mega_banner_2_alt'   => __('Featured promo', 'consucorner'),
	);
}

/**
 * Return one mega-menu banner's resolved settings.
 *
 * @param int $index Banner index (1 or 2).
 * @return array{image:string,url:string,alt:string}
 */
function consucorner_mega_menu_get_banner($index)
{
	$defaults = consucorner_mega_menu_banner_defaults();
	$prefix   = 'cc_mega_banner_' . (int) $index . '_';

	return array(
		'image' => (string) get_theme_mod($prefix . 'image', $defaults[$prefix . 'image']),
		'url'   => (string) get_theme_mod($prefix . 'url',   $defaults[$prefix . 'url']),
		'alt'   => (string) get_theme_mod($prefix . 'alt',   $defaults[$prefix . 'alt']),
	);
}

/**
 * Default values for the Explore mega-menu image tiles.
 *
 * @return array<string, string>
 */
function consucorner_explore_mega_menu_image_defaults()
{
	$base = trailingslashit(get_template_directory_uri()) . 'assets/images/';

	return array(
		'cc_explore_mega_image_1_image' => $base . 'Rectangle 3174.png',
		'cc_explore_mega_image_1_url'   => home_url('/blog/'),
		'cc_explore_mega_image_1_alt'   => __('Explore ConsuCorner blog', 'consucorner'),
		'cc_explore_mega_image_2_image' => $base . 'Rectangle 3175.png',
		'cc_explore_mega_image_2_url'   => home_url('/shop/'),
		'cc_explore_mega_image_2_alt'   => __('Explore medical supplies', 'consucorner'),
		'cc_explore_mega_image_3_image' => $base . 'Rectangle 3176.png',
		'cc_explore_mega_image_3_url'   => home_url('/shop/'),
		'cc_explore_mega_image_3_alt'   => __('Explore healing products', 'consucorner'),
	);
}

/**
 * Return one Explore mega-menu image tile's resolved settings.
 *
 * @param int $index Image tile index (1, 2, or 3).
 * @return array{image:string,url:string,alt:string}
 */
function consucorner_explore_mega_menu_get_image($index)
{
	$defaults = consucorner_explore_mega_menu_image_defaults();
	$prefix   = 'cc_explore_mega_image_' . (int) $index . '_';

	return array(
		'image' => (string) get_theme_mod($prefix . 'image', $defaults[$prefix . 'image']),
		'url'   => (string) get_theme_mod($prefix . 'url',   $defaults[$prefix . 'url']),
		'alt'   => (string) get_theme_mod($prefix . 'alt',   $defaults[$prefix . 'alt']),
	);
}

/**
 * Register Customizer settings for the mega-menu banners.
 *
 * @param WP_Customize_Manager $wp_customize Customizer manager.
 */
function consucorner_mega_menu_customize_register($wp_customize)
{
	$defaults = consucorner_mega_menu_banner_defaults();
	$explore_defaults = consucorner_explore_mega_menu_image_defaults();

	$wp_customize->add_section(
		'cc_mega_menu_banners',
		array(
			'title'       => __('Shop mega menu banners', 'consucorner'),
			'description' => __('Two clickable banner images shown inside the header Shop mega menu, beneath the specialty cards. Each banner can link to any URL.', 'consucorner'),
			'priority'    => 127,
		)
	);

	foreach (array(1, 2) as $i) {
		$prefix = 'cc_mega_banner_' . $i . '_';

		$wp_customize->add_setting(
			$prefix . 'image',
			array(
				'default'           => $defaults[$prefix . 'image'],
				'sanitize_callback' => 'esc_url_raw',
				'transport'         => 'refresh',
			)
		);
		$wp_customize->add_control(
			new WP_Customize_Image_Control(
				$wp_customize,
				$prefix . 'image',
				array(
					/* translators: %d: banner number */
					'label'       => sprintf(__('Banner %d image', 'consucorner'), $i),
					'description' => __('Recommended ratio is approximately 2:1. PNG or JPG.', 'consucorner'),
					'section'     => 'cc_mega_menu_banners',
				)
			)
		);

		$wp_customize->add_setting(
			$prefix . 'url',
			array(
				'default'           => $defaults[$prefix . 'url'],
				'sanitize_callback' => 'esc_url_raw',
				'transport'         => 'refresh',
			)
		);
		$wp_customize->add_control(
			$prefix . 'url',
			array(
				/* translators: %d: banner number */
				'label'       => sprintf(__('Banner %d link URL', 'consucorner'), $i),
				'description' => __('Destination when the banner is clicked.', 'consucorner'),
				'section'     => 'cc_mega_menu_banners',
				'type'        => 'url',
			)
		);

		$wp_customize->add_setting(
			$prefix . 'alt',
			array(
				'default'           => $defaults[$prefix . 'alt'],
				'sanitize_callback' => 'sanitize_text_field',
				'transport'         => 'refresh',
			)
		);
		$wp_customize->add_control(
			$prefix . 'alt',
			array(
				/* translators: %d: banner number */
				'label'       => sprintf(__('Banner %d alt text', 'consucorner'), $i),
				'description' => __('Used for screen readers and shown if the image fails to load.', 'consucorner'),
				'section'     => 'cc_mega_menu_banners',
				'type'        => 'text',
			)
		);
	}

	$wp_customize->add_section(
		'cc_explore_mega_menu_images',
		array(
			'title'       => __('Explore mega menu images', 'consucorner'),
			'description' => __('Three clickable images shown inside the header Explore mega menu. Image 1 and 2 are the top row; image 3 is the wide bottom row.', 'consucorner'),
			'priority'    => 128,
		)
	);

	foreach (array(1, 2, 3) as $i) {
		$prefix = 'cc_explore_mega_image_' . $i . '_';

		$wp_customize->add_setting(
			$prefix . 'image',
			array(
				'default'           => $explore_defaults[$prefix . 'image'],
				'sanitize_callback' => 'esc_url_raw',
				'transport'         => 'refresh',
			)
		);
		$wp_customize->add_control(
			new WP_Customize_Image_Control(
				$wp_customize,
				$prefix . 'image',
				array(
					/* translators: %d: image number */
					'label'       => sprintf(__('Explore image %d', 'consucorner'), $i),
					'description' => 3 === $i
						? __('Bottom wide image. Recommended size: 810x193.', 'consucorner')
						: __('Top row image. Recommended size: 392x193.', 'consucorner'),
					'section'     => 'cc_explore_mega_menu_images',
				)
			)
		);

		$wp_customize->add_setting(
			$prefix . 'url',
			array(
				'default'           => $explore_defaults[$prefix . 'url'],
				'sanitize_callback' => 'esc_url_raw',
				'transport'         => 'refresh',
			)
		);
		$wp_customize->add_control(
			$prefix . 'url',
			array(
				/* translators: %d: image number */
				'label'       => sprintf(__('Explore image %d link URL', 'consucorner'), $i),
				'description' => __('Destination when visitors click this image.', 'consucorner'),
				'section'     => 'cc_explore_mega_menu_images',
				'type'        => 'url',
			)
		);

		$wp_customize->add_setting(
			$prefix . 'alt',
			array(
				'default'           => $explore_defaults[$prefix . 'alt'],
				'sanitize_callback' => 'sanitize_text_field',
				'transport'         => 'refresh',
			)
		);
		$wp_customize->add_control(
			$prefix . 'alt',
			array(
				/* translators: %d: image number */
				'label'       => sprintf(__('Explore image %d alt text', 'consucorner'), $i),
				'description' => __('Used for screen readers and shown if the image fails to load.', 'consucorner'),
				'section'     => 'cc_explore_mega_menu_images',
				'type'        => 'text',
			)
		);
	}
}
add_action('customize_register', 'consucorner_mega_menu_customize_register', 25);

/**
 * Bust the cached mega-menu markup when Customizer settings change.
 */
function consucorner_mega_menu_customize_save_after()
{
	if (function_exists('consucorner_clear_product_mega_menu_cache')) {
		consucorner_clear_product_mega_menu_cache();
	}
}
add_action('customize_save_after', 'consucorner_mega_menu_customize_save_after');
