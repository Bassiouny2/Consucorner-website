<?php

/**
 * Dynamic WooCommerce mega menu.
 *
 * @package ConsuCorner
 */

defined('ABSPATH') || exit;

/**
 * Clear cached mega menu markup.
 */
function consucorner_clear_product_mega_menu_cache()
{
	delete_transient('consucorner_product_mega_menu_html_v1');
	delete_transient('consucorner_product_mega_menu_html_v2');
	delete_transient('consucorner_product_mega_menu_html_v3');
	delete_transient('consucorner_product_mega_menu_html_v4');
	delete_transient('consucorner_product_mega_menu_html_v5');
	delete_transient('consucorner_product_mega_menu_html_v6');
	delete_transient('consucorner_product_mega_menu_html_v7');
	delete_transient('consucorner_product_mega_menu_html_v8');
	delete_transient('consucorner_product_mega_menu_html_v9');
}
add_action('created_product_cat', 'consucorner_clear_product_mega_menu_cache');
add_action('edited_product_cat', 'consucorner_clear_product_mega_menu_cache');
add_action('delete_product_cat', 'consucorner_clear_product_mega_menu_cache');
add_action('created_specialty', 'consucorner_clear_product_mega_menu_cache');
add_action('edited_specialty', 'consucorner_clear_product_mega_menu_cache');
add_action('delete_specialty', 'consucorner_clear_product_mega_menu_cache');
add_action('created_procedure', 'consucorner_clear_product_mega_menu_cache');
add_action('edited_procedure', 'consucorner_clear_product_mega_menu_cache');
add_action('delete_procedure', 'consucorner_clear_product_mega_menu_cache');
add_action('save_post_product', 'consucorner_clear_product_mega_menu_cache');

/**
 * Clear desktop mega menu cache when WooCommerce writes the 'order' termmeta.
 *
 * WooCommerce's drag-and-drop AJAX sort writes termmeta directly without
 * firing 'edited_product_cat', so we hook updated_term_meta / added_term_meta.
 *
 * @param int    $meta_id  Termmeta row ID (unused).
 * @param int    $term_id  Term whose meta was written.
 * @param string $meta_key Meta key that was written.
 */
function consucorner_clear_mega_menu_cache_on_order_meta( $meta_id, $term_id, $meta_key ) {
	if ( 'order' !== $meta_key ) {
		return;
	}

	if ( term_exists( (int) $term_id, 'product_cat' ) || term_exists( (int) $term_id, 'specialty' ) ) {
		consucorner_clear_product_mega_menu_cache();
	}
}
add_action( 'updated_term_meta', 'consucorner_clear_mega_menu_cache_on_order_meta', 10, 3 );
add_action( 'added_term_meta',   'consucorner_clear_mega_menu_cache_on_order_meta', 10, 3 );

/**
 * Return product IDs that belong to a top-level product category.
 *
 * @param int $category_id Product category term ID.
 * @return array
 */
function consucorner_mega_menu_product_ids_for_category($category_id)
{
	$query = new WP_Query(
		array(
			'post_type'              => 'product',
			'post_status'            => 'publish',
			'fields'                 => 'ids',
			'posts_per_page'         => -1,
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'tax_query'              => array(
				array(
					'taxonomy'         => 'product_cat',
					'field'            => 'term_id',
					'terms'            => array((int) $category_id),
					'include_children' => true,
				),
			),
		)
	);

	return array_map('intval', $query->posts);
}

/**
 * Return procedure terms connected to products under a product category.
 *
 * @param int $category_id Product category term ID.
 * @return array
 */
function consucorner_mega_menu_procedures_for_category($category_id)
{
	$product_ids = consucorner_mega_menu_product_ids_for_category($category_id);

	if (empty($product_ids)) {
		return array();
	}

	$terms = wp_get_object_terms(
		$product_ids,
		'procedure',
		array(
			'orderby' => 'count',
			'order'   => 'DESC',
		)
	);

	if (is_wp_error($terms) || empty($terms)) {
		return array();
	}

	return $terms;
}

/**
 * Render a horizontal row of mega-menu cards.
 *
 * @param array  $terms Terms to render.
 * @param string $empty_message Empty state message.
 */
function consucorner_render_mega_menu_cards(array $terms, $empty_message, $data_callback = null)
{
	if (empty($terms)) {
		printf(
			'<p class="mega-empty-state">%s</p>',
			esc_html($empty_message)
		);
		return;
	}

	foreach ($terms as $index => $term) {
		$link = get_term_link($term);
		if (is_wp_error($link)) {
			continue;
		}

		$color_class = 0 === $index % 2 ? 'mega-card-green' : 'mega-card-blue';
		$data_attrs  = is_callable($data_callback) ? (string) call_user_func($data_callback, $term) : '';
		printf(
			'<a href="%1$s" class="mega-card %2$s"%3$s>%4$s</a>',
			esc_url($link),
			esc_attr($color_class),
			$data_attrs,
			esc_html($term->name)
		);
	}
}

/**
 * Map each procedure term to the child product categories it appears under.
 *
 * @param array $child_categories Child product category terms.
 * @return array
 */
function consucorner_mega_menu_procedure_specialty_map(array $child_categories)
{
	$map = array();

	foreach ($child_categories as $child_category) {
		$product_ids = consucorner_mega_menu_product_ids_for_category((int) $child_category->term_id);

		if (empty($product_ids)) {
			continue;
		}

		$procedures = wp_get_object_terms(
			$product_ids,
			'procedure',
			array(
				'fields' => 'ids',
			)
		);

		if (is_wp_error($procedures) || empty($procedures)) {
			continue;
		}

		foreach ($procedures as $procedure_id) {
			$procedure_id = (int) $procedure_id;

			if (! isset($map[$procedure_id])) {
				$map[$procedure_id] = array();
			}

			$map[$procedure_id][] = (int) $child_category->term_id;
		}
	}

	foreach ($map as $procedure_id => $specialty_ids) {
		$map[$procedure_id] = array_values(array_unique(array_map('intval', $specialty_ids)));
	}

	return $map;
}

/**
 * Render the cached WooCommerce mega menu.
 */
function consucorner_render_product_mega_menu()
{
	$cached = get_transient('consucorner_product_mega_menu_html_v9');

	if (false !== $cached) {
		echo $cached; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		return;
	}

	$top_categories = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'parent'     => 0,
			'hide_empty' => true,
			'orderby'    => 'menu_order',
			'order'      => 'ASC',
		)
	);

	if (is_wp_error($top_categories) || empty($top_categories)) {
		return;
	}

	$banners = array();
	if (function_exists('consucorner_mega_menu_get_banner')) {
		$banners = array(
			consucorner_mega_menu_get_banner(1),
			consucorner_mega_menu_get_banner(2),
		);
	}

	$default_category_id = 0;
	foreach ($top_categories as $category) {
		$default_category_id = (int) $category->term_id;
		break;
	}

	$all_specialties = array();
	if (taxonomy_exists('specialty')) {
		$all_specialties_raw = get_terms(
			array(
				'taxonomy'   => 'specialty',
				'hide_empty' => true,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if (! is_wp_error($all_specialties_raw)) {
			$all_specialties = function_exists('consucorner_sort_terms_by_order')
				? consucorner_sort_terms_by_order($all_specialties_raw)
				: $all_specialties_raw;
		}
	}

	ob_start();
?>
	<div class="mega-menu" id="mega-menu" aria-hidden="true">
		<div class="mega-menu-inner">
			<div class="mega-left">
				<h3 class="mega-cat-title"><?php esc_html_e('SHOP BY CATEGORY', 'consucorner'); ?></h3>
				<p class="mega-cat-desc">
					<?php esc_html_e('ConsuCorner brings multiple medical suppliers, multiple specialties, and hundreds of instruments into one organized platform.', 'consucorner'); ?>
				</p>
				<hr class="mega-cat-divider" />
				<ul class="mega-cat-list">
					<?php foreach ($top_categories as $category) : ?>
						<li class="mega-cat-list-item">
							<?php
							$category_link = get_term_link($category);
							if (is_wp_error($category_link)) {
								continue;
							}
							$is_default = ((int) $category->term_id === $default_category_id);
							?>
							<a
								href="<?php echo esc_url($category_link); ?>"
								class="mega-cat-item<?php echo $is_default ? ' active' : ''; ?>"
								data-category-id="<?php echo esc_attr($category->term_id); ?>"
								aria-controls="panel-<?php echo esc_attr($category->term_id); ?>">
								<span><?php echo esc_html($category->name); ?></span>
								<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true" focusable="false">
									<use href="#icon-chevron-right" />
								</svg>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>

			<div class="mega-panels">
				<?php foreach ($top_categories as $category) : ?>
					<?php
					$is_default = ((int) $category->term_id === $default_category_id);
					?>
					<div
						class="mega-right mega-category-panel<?php echo $is_default ? ' active' : ''; ?>"
						id="panel-<?php echo esc_attr($category->term_id); ?>"
						data-category-id="<?php echo esc_attr($category->term_id); ?>"
						data-category-label="<?php echo esc_attr($category->name); ?>"
						aria-hidden="<?php echo $is_default ? 'false' : 'true'; ?>">
						<div class="mega-section mega-section-specialty">
							<div class="mega-section-header">
								<h2 class="mega-section-title"><?php esc_html_e('SHOP BY SPECIALTY', 'consucorner'); ?></h2>
								<p class="mega-section-desc">
									<?php esc_html_e('ConsuCorner brings multiple medical suppliers, multiple specialties, and hundreds of instruments into one organized platform.', 'consucorner'); ?>
								</p>
							</div>
							<div class="mega-slider-row">
								<button class="mega-arrow mega-scroll-arrow" type="button" data-scroll-direction="prev" aria-label="<?php esc_attr_e('Previous specialty', 'consucorner'); ?>">
									<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
										<use href="#icon-chevron-left" />
									</svg>
								</button>
								<div class="mega-viewport">
									<div class="mega-track">
										<?php
										consucorner_render_mega_menu_cards(
											$all_specialties,
											__('No specialties found yet.', 'consucorner'),
											function ($term) {
												return sprintf(
													' data-specialty-id="%s"',
													esc_attr($term->term_id)
												);
											}
										);
										?>
									</div>
								</div>
								<button class="mega-arrow mega-scroll-arrow" type="button" data-scroll-direction="next" aria-label="<?php esc_attr_e('Next specialty', 'consucorner'); ?>">
									<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
										<use href="#icon-chevron-right" />
									</svg>
								</button>
							</div>
						</div>

						<?php if (! empty($banners)) : ?>
							<div class="mega-banner-row">
								<?php foreach ($banners as $banner) :
									if (empty($banner['image'])) {
										continue;
									}
									$banner_url = ! empty($banner['url']) ? $banner['url'] : '#';
								?>
									<a
										class="mega-banner"
										href="<?php echo esc_url($banner_url); ?>"
										aria-label="<?php echo esc_attr($banner['alt']); ?>">
										<img
											src="<?php echo esc_url($banner['image']); ?>"
											alt="<?php echo esc_attr($banner['alt']); ?>"
											loading="lazy"
											decoding="async" />
									</a>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
<?php
	$html = ob_get_clean();

	set_transient('consucorner_product_mega_menu_html_v9', $html, 12 * HOUR_IN_SECONDS);

	echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
