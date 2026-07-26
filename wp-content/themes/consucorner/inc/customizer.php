<?php

/**
 * ConsuCorner Theme Customizer
 *
 * @package ConsuCorner
 */

defined('ABSPATH') || exit;

/**
 * Add postMessage support for site title and description for the Theme Customizer.
 *
 * @param WP_Customize_Manager $wp_customize Theme Customizer object.
 */
function consucorner_customize_register($wp_customize)
{
	$wp_customize->get_setting('blogname')->transport         = 'postMessage';
	$wp_customize->get_setting('blogdescription')->transport  = 'postMessage';
	$wp_customize->get_setting('header_textcolor')->transport = 'postMessage';

	if (isset($wp_customize->selective_refresh)) {
		$wp_customize->selective_refresh->add_partial(
			'blogname',
			array(
				'selector'        => '.site-title a',
				'render_callback' => 'consucorner_customize_partial_blogname',
			)
		);
		$wp_customize->selective_refresh->add_partial(
			'blogdescription',
			array(
				'selector'        => '.site-description',
				'render_callback' => 'consucorner_customize_partial_blogdescription',
			)
		);
	}

	$wp_customize->add_section(
		'consucorner_footer',
		array(
			'title'       => __('Footer Settings', 'consucorner'),
			'description' => __('Footer link columns: Appearance → Menus (Explore, Services, Legal). Social icon URLs (Instagram, Facebook, LinkedIn) are edited below.', 'consucorner'),
			'priority'    => 160,
		)
	);

	$defaults = consucorner_footer_defaults();

	$footer_text_controls = array(
		'footer_tagline'       => array(
			'label'       => __('Footer Tagline', 'consucorner'),
			'type'        => 'textarea',
			'sanitize'    => 'sanitize_textarea_field',
		),
		'footer_description'   => array(
			'label'       => __('Footer Description', 'consucorner'),
			'type'        => 'textarea',
			'sanitize'    => 'sanitize_textarea_field',
		),
		'footer_heading_explore'   => array(
			'label'    => __('Explore Heading', 'consucorner'),
			'type'     => 'text',
			'sanitize' => 'sanitize_text_field',
		),
		'footer_heading_services'  => array(
			'label'    => __('Services Heading', 'consucorner'),
			'type'     => 'text',
			'sanitize' => 'sanitize_text_field',
		),
		'footer_heading_connect'   => array(
			'label'    => __('Connect Heading', 'consucorner'),
			'type'     => 'text',
			'sanitize' => 'sanitize_text_field',
		),
		'footer_heading_resources' => array(
			'label'    => __('Resources Heading', 'consucorner'),
			'type'     => 'text',
			'sanitize' => 'sanitize_text_field',
		),
		'footer_heading_legal'     => array(
			'label'    => __('Legal Heading', 'consucorner'),
			'type'     => 'text',
			'sanitize' => 'sanitize_text_field',
		),
		'footer_copyright'     => array(
			'label'       => __('Copyright Text', 'consucorner'),
			'type'        => 'text',
			'sanitize'    => 'sanitize_text_field',
		),
		'footer_terms_label'   => array(
			'label'    => __('Bottom Terms Label', 'consucorner'),
			'type'     => 'text',
			'sanitize' => 'sanitize_text_field',
		),
		'footer_terms_url'     => array(
			'label'    => __('Bottom Terms URL', 'consucorner'),
			'type'     => 'url',
			'sanitize' => 'esc_url_raw',
		),
		'footer_privacy_label' => array(
			'label'    => __('Bottom Privacy Label', 'consucorner'),
			'type'     => 'text',
			'sanitize' => 'sanitize_text_field',
		),
		'footer_privacy_url'   => array(
			'label'    => __('Bottom Privacy URL', 'consucorner'),
			'type'     => 'url',
			'sanitize' => 'esc_url_raw',
		),
		'footer_cookies_label' => array(
			'label'    => __('Bottom Cookies Label', 'consucorner'),
			'type'     => 'text',
			'sanitize' => 'sanitize_text_field',
		),
		'footer_cookies_url'   => array(
			'label'    => __('Bottom Cookies URL', 'consucorner'),
			'type'     => 'url',
			'sanitize' => 'esc_url_raw',
		),
		'footer_payment_alt'   => array(
			'label'    => __('Payment Image Alt Text', 'consucorner'),
			'type'     => 'text',
			'sanitize' => 'sanitize_text_field',
		),
		'footer_instagram_url' => array(
			'label'    => __('Instagram URL', 'consucorner'),
			'type'     => 'url',
			'sanitize' => 'esc_url_raw',
		),
		'footer_facebook_url'  => array(
			'label'    => __('Facebook URL', 'consucorner'),
			'type'     => 'url',
			'sanitize' => 'esc_url_raw',
		),
		'footer_linkedin_url'  => array(
			'label'    => __('LinkedIn URL', 'consucorner'),
			'type'     => 'url',
			'sanitize' => 'esc_url_raw',
		),
	);

	foreach ($footer_text_controls as $setting_id => $control) {
		$wp_customize->add_setting(
			$setting_id,
			array(
				'default'           => isset($defaults[$setting_id]) ? $defaults[$setting_id] : '',
				'sanitize_callback' => $control['sanitize'],
				'transport'         => 'refresh',
			)
		);

		$wp_customize->add_control(
			$setting_id,
			array(
				'label'   => $control['label'],
				'section' => 'consucorner_footer',
				'type'    => $control['type'],
			)
		);
	}

	$wp_customize->add_setting(
		'footer_logo',
		array(
			'default'           => $defaults['footer_logo'],
			'sanitize_callback' => 'esc_url_raw',
			'transport'         => 'refresh',
		)
	);

	$wp_customize->add_control(
		new WP_Customize_Image_Control(
			$wp_customize,
			'footer_logo',
			array(
				'label'   => __('Footer Logo', 'consucorner'),
				'section' => 'consucorner_footer',
			)
		)
	);

	$wp_customize->add_setting(
		'footer_payment_image',
		array(
			'default'           => $defaults['footer_payment_image'],
			'sanitize_callback' => 'esc_url_raw',
			'transport'         => 'refresh',
		)
	);

	$wp_customize->add_control(
		new WP_Customize_Image_Control(
			$wp_customize,
			'footer_payment_image',
			array(
				'label'   => __('Footer Payment Image', 'consucorner'),
				'section' => 'consucorner_footer',
			)
		)
	);

	$wp_customize->add_section(
		'consucorner_single_product',
		array(
			'title'       => __('Single Product Page', 'consucorner'),
			'description' => __('Controls for WooCommerce single product page template elements.', 'consucorner'),
			'priority'    => 165,
		)
	);

	$single_product_defaults = consucorner_single_product_defaults();

	$wp_customize->add_setting(
		'sp_banner_background',
		array(
			'default'           => $single_product_defaults['sp_banner_background'],
			'sanitize_callback' => 'esc_url_raw',
			'transport'         => 'refresh',
		)
	);

	$wp_customize->add_control(
		new WP_Customize_Image_Control(
			$wp_customize,
			'sp_banner_background',
			array(
				'label'       => __('Bottom banner image', 'consucorner'),
				'description' => __('Applies to the bottom promotional banner on all single product pages.', 'consucorner'),
				'section'     => 'consucorner_single_product',
			)
		)
	);
}
add_action('customize_register', 'consucorner_customize_register');

/**
 * Footer Customizer defaults.
 *
 * @return array
 */
function consucorner_footer_defaults()
{
	return array(
		'footer_logo'              => get_template_directory_uri() . '/assets/images/main - logo.svg',
		'footer_tagline'           => "Your Trusted Medical Supply\nHub in Egypt",
		'footer_description'       => "ConsuCorner is Egypt's leading medical e-commerce platform dedicated to providing high-quality medical supplies, surgical instruments, and diagnostic tools to healthcare professionals, clinics, hospitals, and medical businesses. We bring reliability, variety, and simplicity to every purchase - all in one organized online shop.",
		'footer_heading_explore'   => 'EXPLORE',
		'footer_heading_services'  => 'SERVICES',
		'footer_heading_connect'   => 'CONNECT',
		'footer_heading_resources' => 'RESOURCES',
		'footer_heading_legal'     => 'LEGAL',
		'footer_copyright'         => 'Copyright 2026 By ConsuCorner. All Rights Reserved.',
		'footer_terms_label'       => 'Terms',
		'footer_terms_url'         => home_url('/help/terms/'),
		'footer_privacy_label'     => 'Privacy',
		'footer_privacy_url'       => home_url('/privacy-policy/'),
		'footer_cookies_label'     => 'Cookies',
		'footer_cookies_url'       => home_url('/help/cookies/'),
		'footer_payment_image'     => get_template_directory_uri() . '/assets/images/footer-payment.png',
		'footer_payment_alt'       => 'Accepted payment methods: Paymob, Mastercard, Visa, Fawry',
		'footer_instagram_url'     => 'https://www.instagram.com/consu.corner',
		'footer_facebook_url'      => 'https://www.facebook.com/Consucorner/',
		'footer_linkedin_url'      => 'https://www.linkedin.com/company/consucorner',
	);
}

/**
 * Single product Customizer defaults.
 *
 * @return array
 */
function consucorner_single_product_defaults()
{
	return array(
		'sp_banner_background' => get_template_directory_uri() . '/assets/images/Banner Section.webp',
	);
}

/**
 * Return the single product banner background URL.
 *
 * @return string
 */
function consucorner_get_sp_banner_background_url()
{
	$defaults = consucorner_single_product_defaults();

	return get_theme_mod('sp_banner_background', $defaults['sp_banner_background']);
}

/**
 * Return inline style attribute for the single product banner background.
 *
 * @return string
 */
function consucorner_sp_banner_bg_style_attr()
{
	$url = trim((string) consucorner_get_sp_banner_background_url());

	return $url ? ' style="background-image:url(' . esc_url($url) . ');"' : '';
}

/**
 * Return footer setting with default fallback.
 *
 * @param string $setting Setting ID.
 * @return string
 */
function consucorner_get_footer_setting($setting)
{
	$defaults = consucorner_footer_defaults();
	$default  = isset($defaults[$setting]) ? $defaults[$setting] : '';

	return get_theme_mod($setting, $default);
}

/**
 * Render the site title for the selective refresh partial.
 *
 * @return void
 */
function consucorner_customize_partial_blogname()
{
	bloginfo('name');
}

/**
 * Render the site tagline for the selective refresh partial.
 *
 * @return void
 */
function consucorner_customize_partial_blogdescription()
{
	bloginfo('description');
}

/**
 * Binds JS handlers to make Theme Customizer preview reload changes asynchronously.
 */
function consucorner_customize_preview_js()
{
	wp_enqueue_script('consucorner-customizer', get_template_directory_uri() . '/js/customizer.js', array('customize-preview'), _S_VERSION, true);
}
add_action('customize_preview_init', 'consucorner_customize_preview_js');
