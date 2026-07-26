<?php

/**
 * Dynamic mobile drawer menus — card-based design.
 *
 * Shop tab : top-4 product categories + top-4 specialty terms, each as a
 *            static 2×2 card grid (no horizontal slider).
 * Explore tab: 4 Important Links cards (2×2 grid) + Help & Guidelines list.
 *
 * @package ConsuCorner
 */

defined('ABSPATH') || exit;

/* =====================================================================
   SVG icon helper
   ===================================================================== */

/**
 * Return an inline SVG icon string for the mobile drawer.
 *
 * @param string $name Icon key.
 * @return string SVG markup (pre-sanitised — all values are hard-coded).
 */
function cc_drawer_svg_icon($name)
{
	static $icons = null;
	if (null === $icons) {
		$icons = array(
			'info'     => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
			'phone'    => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.61 3.39 2 2 0 0 1 3.58 1h3a2 2 0 0 1 2 1.72c.12.958.347 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.353 1.852.58 2.81.7A2 2 0 0 1 22 16.92z"/></svg>',
			'blog'     => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>',
			'partners' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
			'doc'      => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>',
			'shield'   => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
			'support'  => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
			'faq'      => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
			'chevron'  => '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>',
			'category' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>',
		);
	}

	return isset($icons[$name]) ? $icons[$name] : $icons['category'];
}

/* =====================================================================
   Cache management
   ===================================================================== */

/**
 * Clear cached mobile drawer HTML fragments.
 *
 * Called whenever product_cat terms, specialty terms, or WP nav menus change.
 */
function consucorner_clear_mobile_drawer_cache()
{
	delete_transient('consucorner_mobile_shop_drawer_html_v1');
	delete_transient('consucorner_mobile_shop_drawer_html_v2');
	delete_transient('consucorner_mobile_shop_drawer_html_v3');
	delete_transient('consucorner_mobile_shop_drawer_html_v4');
	delete_transient('consucorner_mobile_shop_drawer_html_v5');
	delete_transient('consucorner_mobile_shop_drawer_html_v6');
	delete_transient('consucorner_mobile_shop_drawer_html_v7');
	delete_transient('consucorner_mobile_shop_drawer_html_v8');
	delete_transient('consucorner_mobile_explore_drawer_html_v1');
	delete_transient('consucorner_mobile_explore_drawer_html_v2');
	delete_transient('consucorner_mobile_explore_drawer_html_v3');
	delete_transient('consucorner_mobile_explore_drawer_html_v4');
}

add_action('created_product_cat', 'consucorner_clear_mobile_drawer_cache');
add_action('edited_product_cat',  'consucorner_clear_mobile_drawer_cache');
add_action('delete_product_cat',  'consucorner_clear_mobile_drawer_cache');
add_action('wp_update_nav_menu',  'consucorner_clear_mobile_drawer_cache');
add_action('created_specialty',   'consucorner_clear_mobile_drawer_cache');
add_action('edited_specialty',    'consucorner_clear_mobile_drawer_cache');
add_action('delete_specialty',    'consucorner_clear_mobile_drawer_cache');

/**
 * Clear mobile drawer cache when WooCommerce writes the 'order' termmeta for
 * a product_cat, or when a category icon attachment is updated.
 *
 * WooCommerce's drag-and-drop AJAX sort in Products → Categories writes
 * termmeta directly without firing 'edited_product_cat', so we must hook
 * updated_term_meta / added_term_meta.
 *
 * @param int    $meta_id  Termmeta row ID (unused).
 * @param int    $term_id  Term whose meta was written.
 * @param string $meta_key Meta key that was written.
 */
function consucorner_clear_drawer_cache_on_order_meta($meta_id, $term_id, $meta_key)
{
	if ('order' === $meta_key) {
		if (
			term_exists((int) $term_id, 'product_cat')
			|| term_exists((int) $term_id, 'specialty')
		) {
			consucorner_clear_mobile_drawer_cache();
		}
		return;
	}

	if (
		'_cc_product_cat_icon' === $meta_key
		&& term_exists((int) $term_id, 'product_cat')
	) {
		consucorner_clear_mobile_drawer_cache();
	}
}

add_action('updated_term_meta', 'consucorner_clear_drawer_cache_on_order_meta', 10, 3);
add_action('added_term_meta',   'consucorner_clear_drawer_cache_on_order_meta', 10, 3);

/* =====================================================================
   Shop tab
   ===================================================================== */

/**
 * Render the Shop tab content for the mobile drawer.
 *
 * Outputs ALL top-level product categories and ALL specialty terms as
 * horizontal snap-sliders (groups of 4 per slide, 2×2 grid layout).
 */
function consucorner_render_mobile_shop_drawer()
{
	$cached = get_transient('consucorner_mobile_shop_drawer_html_v8');
	if (false !== $cached) {
		echo $cached; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		return;
	}

	ob_start();

	/* ── Shop by Category — horizontal slider ─────────────
	   All top-level product categories, 4 per slide (2×2 grid).
	   ─────────────────────────────────────────────────────── */
	$top_categories = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'parent'     => 0,
			'hide_empty' => false,
			'orderby'    => 'menu_order',
			'order'      => 'ASC',
		)
	);

	if (! is_wp_error($top_categories) && ! empty($top_categories) && function_exists('consucorner_sort_terms_by_order')) {
		$top_categories = consucorner_sort_terms_by_order($top_categories);
	}

	if (! is_wp_error($top_categories) && ! empty($top_categories)) :
		$cat_slides = array_chunk($top_categories, 4);
?>
		<section class="cc-section">
			<div class="cc-section-head">
				<h3><?php esc_html_e('Shop by Category', 'consucorner'); ?></h3>
				<?php if (count($cat_slides) > 1) : ?>
					<div class="cc-slider-nav" aria-label="<?php esc_attr_e('Category slider navigation', 'consucorner'); ?>">
						<button class="cc-slider-arrow cc-slider-arrow--prev" type="button" aria-label="<?php esc_attr_e('Previous categories', 'consucorner'); ?>"><?php echo cc_drawer_svg_icon('chevron'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
																																																																												?></button>
						<button class="cc-slider-arrow cc-slider-arrow--next" type="button" aria-label="<?php esc_attr_e('Next categories', 'consucorner'); ?>"><?php echo cc_drawer_svg_icon('chevron'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
																																																																										?></button>
					</div>
				<?php endif; ?>
			</div>
			<div class="cc-slider-wrap">
				<div class="cc-slider">
					<?php foreach ($cat_slides as $slide_cats) : ?>
						<div class="cc-slide">
							<?php foreach ($slide_cats as $cat) :
								$cat_url  = get_term_link($cat);
								if (is_wp_error($cat_url)) {
									continue;
								}
								$icon_url = function_exists('cc_get_product_cat_icon_url')
									? cc_get_product_cat_icon_url($cat, 'thumbnail')
									: '';
							?>
								<a href="<?php echo esc_url($cat_url); ?>" class="cc-card">
									<span class="cc-icon">
										<?php if ($icon_url) : ?>
											<img src="<?php echo esc_url($icon_url); ?>" alt="" width="22" height="22" loading="lazy">
										<?php else : ?>
											<?php echo cc_drawer_svg_icon('category'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
											?>
										<?php endif; ?>
									</span>
									<strong><?php echo esc_html($cat->name); ?></strong>
								</a>
							<?php endforeach; ?>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</section>
	<?php
	endif;

	/* ── Shop by Specialty — horizontal slider ─────────────
	   All specialty terms, 4 per slide (2×2 grid).
	   Gradient colour cycles: teal / blue / purple / teal …
	   ─────────────────────────────────────────────────────── */
	$specialty_terms = taxonomy_exists('specialty')
		? get_terms(
			array(
				'taxonomy'   => 'specialty',
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		)
		: array();

	if (! is_wp_error($specialty_terms) && ! empty($specialty_terms) && function_exists('consucorner_sort_terms_by_order')) {
		$specialty_terms = consucorner_sort_terms_by_order($specialty_terms);
	}

	$gradient_classes = array('', 'blue', 'purple', 'teal');

	if (! is_wp_error($specialty_terms) && ! empty($specialty_terms)) :
		$spec_slides = array_chunk($specialty_terms, 4);
	?>
		<section class="cc-section">
			<div class="cc-section-head">
				<h3><?php esc_html_e('Shop by Specialty', 'consucorner'); ?></h3>
				<?php if (count($spec_slides) > 1) : ?>
					<div class="cc-slider-nav" aria-label="<?php esc_attr_e('Specialty slider navigation', 'consucorner'); ?>">
						<button class="cc-slider-arrow cc-slider-arrow--prev" type="button" aria-label="<?php esc_attr_e('Previous specialties', 'consucorner'); ?>"><?php echo cc_drawer_svg_icon('chevron'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
																																																																													?></button>
						<button class="cc-slider-arrow cc-slider-arrow--next" type="button" aria-label="<?php esc_attr_e('Next specialties', 'consucorner'); ?>"><?php echo cc_drawer_svg_icon('chevron'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
																																																																											?></button>
					</div>
				<?php endif; ?>
			</div>
			<div class="cc-slider-wrap">
				<div class="cc-slider">
					<?php
					$global_index = 0;
					foreach ($spec_slides as $slide_terms) :
					?>
						<div class="cc-slide cc-specialty-slide">
							<?php foreach ($slide_terms as $term) :
								$term_url = get_term_link($term);
								if (is_wp_error($term_url)) {
									$global_index++;
									continue;
								}
								$cls = $gradient_classes[$global_index % 4];
								$global_index++;
							?>
								<a href="<?php echo esc_url($term_url); ?>" class="cc-specialty-card<?php echo $cls ? ' ' . esc_attr($cls) : ''; ?>">
									<?php echo esc_html($term->name); ?>
								</a>
							<?php endforeach; ?>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</section>
	<?php
	endif;

	$html = ob_get_clean();
	set_transient('consucorner_mobile_shop_drawer_html_v8', $html, 12 * HOUR_IN_SECONDS);
	echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/* =====================================================================
   Explore tab
   ===================================================================== */

/**
 * Render the Explore tab content for the mobile drawer.
 *
 * Outputs 4 Important Links as a static 2×2 card grid (no slider/horizontal
 * scroll) and a Help & Guidelines row list below.
 */
function consucorner_render_mobile_explore_drawer()
{
	$cached = get_transient('consucorner_mobile_explore_drawer_html_v4');
	if (false !== $cached) {
		echo $cached; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		return;
	}

	ob_start();

	/* ── Important Links — 4 cards, static 2×2 grid ─────── */
	$important_links = array(
		array(
			'icon'     => 'info',
			'title'    => __('About Us', 'consucorner'),
			'subtitle' => __('Our Story', 'consucorner'),
			'url'      => home_url('/about/'),
		),
		array(
			'icon'     => 'phone',
			'title'    => __('Contact Us', 'consucorner'),
			'subtitle' => __('Get in Touch', 'consucorner'),
			'url'      => home_url('/contact/'),
		),
		array(
			'icon'     => 'blog',
			'title'    => __('Medical Blog', 'consucorner'),
			'subtitle' => __('Insights', 'consucorner'),
			'url'      => home_url('/blogs/'),
		),
		array(
			'icon'     => 'partners',
			'title'    => __('ConsuCorner Partners', 'consucorner'),
			'subtitle' => '',
			'url'      => home_url('/vendor/'),
			'active'   => true,
		),
	);
	?>
	<section class="cc-section">
		<h3 class="with-dot"><?php esc_html_e('Important Links', 'consucorner'); ?></h3>
		<div class="cc-grid">
			<?php foreach ($important_links as $link) : ?>
				<a href="<?php echo esc_url($link['url']); ?>" class="cc-explore-card<?php echo ! empty($link['active']) ? ' is-active' : ''; ?>">
					<span class="cc-icon">
						<?php echo cc_drawer_svg_icon($link['icon']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
						?>
					</span>
					<strong><?php echo esc_html($link['title']); ?></strong>
					<?php if (! empty($link['subtitle'])) : ?>
						<small><?php echo esc_html($link['subtitle']); ?></small>
					<?php endif; ?>
				</a>
			<?php endforeach; ?>
		</div>
	</section>

	<?php
	/* ── Help & Guidelines — row list ───────────────────── */
	$help_links = array(
		array(
			'icon'     => 'doc',
			'title'    => __('Terms and Conditions', 'consucorner'),
			'subtitle' => __('Legal Framework', 'consucorner'),
			'url'      => home_url('/terms-and-conditions/'),
		),
		array(
			'icon'     => 'shield',
			'title'    => __('Privacy Policy', 'consucorner'),
			'subtitle' => __('Your Data Security', 'consucorner'),
			'url'      => home_url('/privacy-policy/'),
		),
		array(
			'icon'     => 'support',
			'title'    => __('Contact & Support', 'consucorner'),
			'subtitle' => __('24/7 Assistance', 'consucorner'),
			'url'      => home_url('/contact/'),
		),
		array(
			'icon'     => 'faq',
			'title'    => __('FAQ', 'consucorner'),
			'subtitle' => __('Common Questions', 'consucorner'),
			'url'      => home_url('/faq/'),
		),
	);
	?>
	<section class="cc-section">
		<h3><?php esc_html_e('Help &amp; Guidelines', 'consucorner'); ?></h3>
		<div class="cc-help-list">
			<?php foreach ($help_links as $link) : ?>
				<a href="<?php echo esc_url($link['url']); ?>" class="cc-help-item">
					<span class="cc-help-item-icon">
						<?php echo cc_drawer_svg_icon($link['icon']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
						?>
					</span>
					<div class="cc-help-item-text">
						<strong><?php echo esc_html($link['title']); ?></strong>
						<small><?php echo esc_html($link['subtitle']); ?></small>
					</div>
					<em class="cc-help-item-arrow">
						<?php echo cc_drawer_svg_icon('chevron'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
						?>
					</em>
				</a>
			<?php endforeach; ?>
		</div>
	</section>
<?php

	$html = ob_get_clean();
	set_transient('consucorner_mobile_explore_drawer_html_v4', $html, 12 * HOUR_IN_SECONDS);
	echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
