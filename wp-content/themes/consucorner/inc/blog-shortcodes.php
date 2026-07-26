<?php
/**
 * Blog shortcodes — reusable CTAs for post content.
 *
 * @package ConsuCorner
 */

defined('ABSPATH') || exit;

/**
 * Default shop URL for the Shop Now CTA.
 *
 * @return string
 */
function cc_shop_now_default_url()
{
	if (function_exists('wc_get_page_permalink')) {
		$shop = wc_get_page_permalink('shop');
		if ($shop) {
			return $shop;
		}
	}

	return home_url('/shop/');
}

/**
 * Enqueue blog CTA styles when the shortcode is present on a singular page.
 */
function cc_shop_now_maybe_enqueue_styles()
{
	if (is_admin()) {
		return;
	}

	$post = get_post();
	if (! $post instanceof WP_Post) {
		return;
	}

	if (! has_shortcode($post->post_content, 'cc_shop_now')) {
		return;
	}

	if (wp_style_is('consucorner-blog', 'enqueued') || wp_style_is('consucorner-blog', 'done')) {
		return;
	}

	$uri = get_template_directory_uri();
	$dir = get_template_directory();
	$rel = '/assets/css/blog.css';
	$ver = file_exists($dir . $rel) ? (string) filemtime($dir . $rel) : (defined('_S_VERSION') ? _S_VERSION : '1.0.0');

	wp_enqueue_style('consucorner-blog', $uri . $rel, array(), $ver);
}
add_action('wp_enqueue_scripts', 'cc_shop_now_maybe_enqueue_styles', 25);

/**
 * Shop Now banner shortcode for blog posts.
 *
 * Usage:
 *   [cc_shop_now]
 *   [cc_shop_now url="https://example.com/shop/"]
 *   [cc_shop_now text="Browse Shop" url="/shop/"]
 *
 * @param array|string $atts Shortcode attributes.
 * @return string
 */
function cc_shop_now_shortcode($atts)
{
	$atts = shortcode_atts(
		array(
			'url'   => cc_shop_now_default_url(),
			'text'  => __('Shop Now', 'consucorner'),
			'label' => '',
		),
		$atts,
		'cc_shop_now'
	);

	$url = esc_url($atts['url']);
	if (! $url) {
		return '';
	}

	$text = sanitize_text_field($atts['text']);
	$aria = $atts['label'] ? sanitize_text_field($atts['label']) : $text;

	$logo_url = get_template_directory_uri() . '/assets/images/main - logo.svg';

	ob_start();
	?>
	<figure class="cc-shop-now-cta" role="region" aria-label="<?php echo esc_attr__('Shop call to action', 'consucorner'); ?>">
		<a href="<?php echo $url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_url above. ?>" class="cc-shop-now-cta__btn" aria-label="<?php echo esc_attr($aria); ?>"><?php echo esc_html($text); ?></a>
		<span class="cc-shop-now-cta__logo-wrap" aria-hidden="true">
			<img
				class="cc-shop-now-cta__logo"
				src="<?php echo esc_url($logo_url); ?>"
				alt=""
				width="140"
				height="22"
				loading="lazy"
				decoding="async"
			/>
		</span>
	</figure>
	<?php
	return (string) ob_get_clean();
}
add_shortcode('cc_shop_now', 'cc_shop_now_shortcode');
