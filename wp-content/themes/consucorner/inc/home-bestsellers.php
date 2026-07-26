<?php

/**
 * Homepage Bestsellers — manual curation + most-ordered auto ranking.
 *
 * @package ConsuCorner
 */

defined('ABSPATH') || exit;

const CC_HOME_BESTSELLERS_PRODUCT_IDS_META = '_cc_home_bestsellers_product_ids';
const CC_HOME_BESTSELLERS_ORDER_COUNT_TRANSIENT = 'cc_home_bestsellers_order_count';

/**
 * Product IDs manually picked on the static front page (admin order preserved).
 *
 * @return int[]
 */
function cc_get_home_manual_bestseller_product_ids()
{
	$page_id = absint(get_option('page_on_front'));
	if (! $page_id) {
		return array();
	}

	$raw = get_post_meta($page_id, CC_HOME_BESTSELLERS_PRODUCT_IDS_META, true);
	if (! is_string($raw) || '' === trim($raw)) {
		return array();
	}

	$ids = array();
	foreach (preg_split('/\s*,\s*/', $raw) as $part) {
		$pid = absint($part);
		if ($pid > 0 && ! in_array($pid, $ids, true)) {
			$ids[] = $pid;
		}
	}

	return $ids;
}

/**
 * WooCommerce order statuses that count toward "most ordered".
 *
 * @return string[]
 */
function cc_home_bestsellers_countable_order_statuses()
{
	return apply_filters(
		'consucorner_home_bestsellers_order_statuses',
		array('wc-processing', 'wc-completed', 'wc-on-hold')
	);
}

/**
 * Ranked map product_id => distinct order count (cached).
 *
 * @param int $fetch_limit Max rows from SQL.
 * @return array<int, int>
 */
function cc_get_home_bestsellers_order_count_rankings($fetch_limit = 80)
{
	$fetch_limit = max(8, absint($fetch_limit));
	$cached      = get_transient(CC_HOME_BESTSELLERS_ORDER_COUNT_TRANSIENT);

	if (is_array($cached)) {
		return $cached;
	}

	global $wpdb;

	$statuses   = cc_home_bestsellers_countable_order_statuses();
	$status_sql = implode(',', array_fill(0, count($statuses), '%s'));
	$lookup     = $wpdb->prefix . 'wc_order_product_lookup';

	if (! $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $lookup))) {
		return array();
	}

	$uses_hpos = class_exists('\Automattic\WooCommerce\Utilities\OrderUtil')
		&& \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();

	if ($uses_hpos) {
		$orders_table = $wpdb->prefix . 'wc_orders';
		$sql          = "
			SELECT lookup.product_id, COUNT(DISTINCT lookup.order_id) AS order_count
			FROM {$lookup} AS lookup
			INNER JOIN {$orders_table} AS orders ON orders.id = lookup.order_id
			INNER JOIN {$wpdb->posts} AS products ON products.ID = lookup.product_id
			WHERE orders.type = 'shop_order'
				AND orders.status IN ({$status_sql})
				AND products.post_type = 'product'
				AND products.post_status = 'publish'
			GROUP BY lookup.product_id
			ORDER BY order_count DESC
			LIMIT %d
		";
		$params = array_merge($statuses, array($fetch_limit));
		$rows   = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
	} else {
		$sql = "
			SELECT lookup.product_id, COUNT(DISTINCT lookup.order_id) AS order_count
			FROM {$lookup} AS lookup
			INNER JOIN {$wpdb->posts} AS orders ON orders.ID = lookup.order_id
			INNER JOIN {$wpdb->posts} AS products ON products.ID = lookup.product_id
			WHERE orders.post_type = 'shop_order'
				AND orders.post_status IN ({$status_sql})
				AND products.post_type = 'product'
				AND products.post_status = 'publish'
			GROUP BY lookup.product_id
			ORDER BY order_count DESC
			LIMIT %d
		";
		$params = array_merge($statuses, array($fetch_limit));
		$rows   = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
	}

	$rankings = array();
	if (is_array($rows)) {
		foreach ($rows as $row) {
			$pid = isset($row['product_id']) ? absint($row['product_id']) : 0;
			if ($pid <= 0) {
				continue;
			}
			$rankings[$pid] = isset($row['order_count']) ? absint($row['order_count']) : 0;
		}
	}

	set_transient(CC_HOME_BESTSELLERS_ORDER_COUNT_TRANSIENT, $rankings, HOUR_IN_SECONDS);

	return $rankings;
}

/**
 * Keep only product IDs that match a tax_query, preserving input order.
 *
 * @param int[]              $product_ids Product IDs in priority order.
 * @param array<int, mixed>  $tax_query   WP_Query tax_query branch.
 * @return int[]
 */
function cc_filter_product_ids_by_tax_query(array $product_ids, array $tax_query)
{
	$product_ids = array_values(array_filter(array_map('absint', $product_ids)));
	if (empty($product_ids) || empty($tax_query)) {
		return array();
	}

	$query = new WP_Query(
		array(
			'post_type'              => 'product',
			'post_status'            => 'publish',
			'posts_per_page'         => count($product_ids),
			'post__in'               => $product_ids,
			'orderby'                => 'post__in',
			'ignore_sticky_posts'    => true,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'tax_query'              => isset($tax_query['relation']) ? $tax_query : array($tax_query),
		)
	);

	return array_map('absint', (array) $query->posts);
}

/**
 * Auto-pick bestsellers by distinct order count, with interest-profile bias.
 *
 * @param int                $limit   Number of products.
 * @param array<string, mixed> $profile Interest profile from cc_read_user_interest_profile().
 * @return int[]
 */
function cc_get_most_ordered_home_bestseller_product_ids($limit = 8, array $profile = array())
{
	$limit    = max(1, absint($limit));
	$rankings = cc_get_home_bestsellers_order_count_rankings(max($limit * 6, 48));
	$ranked   = array_keys($rankings);
	$matched  = array();

	$tax_query = function_exists('cc_build_interest_tax_query') ? cc_build_interest_tax_query($profile) : array();

	if (! empty($tax_query) && ! empty($ranked)) {
		$personalized = cc_filter_product_ids_by_tax_query($ranked, $tax_query);
		foreach ($personalized as $pid) {
			$matched[] = $pid;
			if (count($matched) >= $limit) {
				return $matched;
			}
		}
	}

	if (! empty($ranked)) {
		foreach ($ranked as $pid) {
			if (in_array($pid, $matched, true)) {
				continue;
			}
			$matched[] = $pid;
			if (count($matched) >= $limit) {
				break;
			}
		}
	}

	if (count($matched) >= $limit) {
		return array_slice($matched, 0, $limit);
	}

	$fresh = new WP_Query(
		array(
			'post_type'              => 'product',
			'post_status'            => 'publish',
			'posts_per_page'         => $limit,
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'post__not_in'           => $matched,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);

	foreach ((array) $fresh->posts as $pid) {
		$pid = absint($pid);
		if ($pid > 0 && ! in_array($pid, $matched, true)) {
			$matched[] = $pid;
		}
		if (count($matched) >= $limit) {
			break;
		}
	}

	return array_slice($matched, 0, $limit);
}

/**
 * Resolve homepage bestseller product IDs (manual override or auto).
 *
 * @param int                $limit   Number of products.
 * @param array<string, mixed> $profile Interest profile.
 * @return array{ids: int[], source: string, personalized: bool}
 */
function cc_get_home_bestsellers_product_ids($limit = 8, array $profile = array())
{
	$limit  = max(1, absint($limit));
	$manual = cc_get_home_manual_bestseller_product_ids();

	if (! empty($manual)) {
		$valid = array();
		foreach ($manual as $pid) {
			$product = wc_get_product($pid);
			if (! $product || 'publish' !== get_post_status($pid)) {
				continue;
			}
			$valid[] = $pid;
			if (count($valid) >= $limit) {
				break;
			}
		}

		if (! empty($valid)) {
			return array(
				'ids'           => $valid,
				'source'        => 'manual',
				'personalized'  => false,
			);
		}
	}

	$tax_query = function_exists('cc_build_interest_tax_query') ? cc_build_interest_tax_query($profile) : array();

	return array(
		'ids'          => cc_get_most_ordered_home_bestseller_product_ids($limit, $profile),
		'source'       => 'order_count',
		'personalized' => ! empty($tax_query),
	);
}

/**
 * Render manual bestsellers product picker on the Home page meta box.
 *
 * @param int $post_id Front page ID.
 */
function cc_render_home_bestsellers_product_picker($post_id)
{
	$selected = cc_get_home_manual_bestseller_product_ids();
	?>
	<p style="margin:10px 0 2px;font-size:12px;color:#555"><em><?php esc_html_e('— Bestsellers products —', 'consucorner'); ?></em></p>
	<p>
		<label for="cc_home_bestsellers_product_ids"><strong><?php esc_html_e('Bestsellers products', 'consucorner'); ?></strong></label><br />
		<select
			class="wc-product-search"
			multiple="multiple"
			style="width:100%;"
			id="cc_home_bestsellers_product_ids"
			name="cc_home_bestsellers_product_ids[]"
			data-placeholder="<?php esc_attr_e('Search products by name…', 'consucorner'); ?>"
			data-action="woocommerce_json_search_products_and_variations">
			<?php
			foreach ($selected as $pid) {
				$product = wc_get_product($pid);
				if (! $product) {
					continue;
				}
				?>
				<option value="<?php echo esc_attr((string) $pid); ?>" selected="selected"><?php echo esc_html($product->get_formatted_name()); ?></option>
				<?php
			}
			?>
		</select>
	</p>
	<p style="margin:4px 0 10px;color:#646970;font-size:12px">
		<?php esc_html_e('Search and select products by name. Display order follows your selection order. Leave empty to show products ranked by how often they were ordered (not total units sold).', 'consucorner'); ?>
	</p>
	<?php
}

/**
 * Save manual bestsellers selection from the Home page meta box.
 *
 * @param int $post_id Front page ID.
 */
function cc_save_home_bestsellers_product_ids($post_id)
{
	if (
		! isset($_POST['_cc_meta_nonce'])
		|| ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_cc_meta_nonce'])), '_cc_save_meta')
	) {
		return;
	}

	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return;
	}

	if (! current_user_can('edit_post', $post_id)) {
		return;
	}

	$raw = isset($_POST['cc_home_bestsellers_product_ids'])
		? (array) wp_unslash($_POST['cc_home_bestsellers_product_ids'])
		: array();

	$ids = array();
	foreach ($raw as $value) {
		$pid = absint($value);
		if ($pid > 0 && ! in_array($pid, $ids, true)) {
			$ids[] = $pid;
		}
	}

	if (empty($ids)) {
		delete_post_meta($post_id, CC_HOME_BESTSELLERS_PRODUCT_IDS_META);
	} else {
		update_post_meta($post_id, CC_HOME_BESTSELLERS_PRODUCT_IDS_META, implode(',', $ids));
	}

	delete_transient(CC_HOME_BESTSELLERS_ORDER_COUNT_TRANSIENT);
}

/**
 * Enqueue WooCommerce product search on the static front page editor.
 *
 * @param string $hook Admin hook suffix.
 */
function cc_home_bestsellers_admin_assets($hook)
{
	if (! in_array($hook, array('post.php', 'post-new.php'), true)) {
		return;
	}

	global $post;
	if (! $post || 'page' !== $post->post_type) {
		return;
	}

	if (absint(get_option('page_on_front')) !== (int) $post->ID) {
		return;
	}

	wp_enqueue_style('woocommerce_admin_styles');
	wp_enqueue_script('selectWoo');
	wp_enqueue_script('wc-enhanced-select');

	if (! wp_script_is('wc-enhanced-select', 'done') && ! wp_scripts()->get_data('wc-enhanced-select', 'data')) {
		wp_localize_script(
			'wc-enhanced-select',
			'wc_enhanced_select_params',
			array(
				'i18n_no_matches'           => _x('No matches found', 'enhanced select', 'woocommerce'),
				'i18n_ajax_error'           => _x('Loading failed', 'enhanced select', 'woocommerce'),
				'i18n_input_too_short_1'    => _x('Please enter 1 or more characters', 'enhanced select', 'woocommerce'),
				'i18n_input_too_short_n'    => _x('Please enter %qty% or more characters', 'enhanced select', 'woocommerce'),
				'i18n_input_too_long_1'     => _x('Please delete 1 character', 'enhanced select', 'woocommerce'),
				'i18n_input_too_long_n'     => _x('Please delete %qty% characters', 'enhanced select', 'woocommerce'),
				'i18n_selection_too_long_1' => _x('You can only select 1 item', 'enhanced select', 'woocommerce'),
				'i18n_selection_too_long_n' => _x('You can only select %qty% items', 'enhanced select', 'woocommerce'),
				'i18n_load_more'            => _x('Loading more results&hellip;', 'enhanced select', 'woocommerce'),
				'i18n_searching'            => _x('Searching&hellip;', 'enhanced select', 'woocommerce'),
				'ajax_url'                  => admin_url('admin-ajax.php'),
				'search_products_nonce'     => wp_create_nonce('search-products'),
				'search_customers_nonce'    => wp_create_nonce('search-customers'),
				'search_categories_nonce'   => wp_create_nonce('search-categories'),
				'search_pages_nonce'        => wp_create_nonce('search-pages'),
			)
		);
	}
}
add_action('admin_enqueue_scripts', 'cc_home_bestsellers_admin_assets');
