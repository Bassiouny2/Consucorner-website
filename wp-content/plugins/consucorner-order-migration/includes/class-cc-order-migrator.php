<?php

/**
 * Creates orders on the new site from live API payloads.
 *
 * @package ConsuCorner_Order_Migration
 */

defined('ABSPATH') || exit;

/**
 * Order migrator.
 */
class CC_Order_Migrator
{

	const LOCK_TRANSIENT          = 'cc_order_migration_lock';
	const ID_MAP_OPTION           = 'cc_order_migration_id_map';
	const ATTR_META_PREFIX        = '_wc_order_attribution_';
	const DATE_REPAIR_BATCH_SIZE  = 20;
	const DATE_REPAIR_DONE_META   = '_cc_date_repair_at';

	/**
	 * @var array
	 */
	private $config;

	/**
	 * @var CC_Order_Migration_API
	 */
	private $api;

	/**
	 * @var callable|null
	 */
	private $logger;

	/**
	 * old source order ID => new local order ID (includes trashed orders).
	 *
	 * @var array<int, int>
	 */
	private $migrated_index = array();

	/**
	 * Whether the migrated index was loaded.
	 *
	 * @var bool
	 */
	private $index_loaded = false;

	/**
	 * Runtime stats.
	 *
	 * @var array
	 */
	private $stats = array(
		'imported'  => 0,
		'skipped'   => 0,
		'failed'    => 0,
		'suborders' => 0,
	);

	/**
	 * @param array                  $config Config.
	 * @param CC_Order_Migration_API $api    API client.
	 * @param callable|null          $logger function( string $level, string $message ).
	 */
	public function __construct(array $config, CC_Order_Migration_API $api, $logger = null)
	{
		$this->config = $config;
		$this->api    = $api;
		$this->logger = is_callable($logger) ? $logger : null;
	}

	/**
	 * @return array
	 */
	public function get_stats()
	{
		return $this->stats;
	}

	/**
	 * Run full migration loop.
	 *
	 * @param array $overrides Optional config overrides (dry_run, max_orders, start_page).
	 * @return array Stats.
	 */
	public function run(array $overrides = array())
	{
		if (! function_exists('wc_create_order')) {
			throw new RuntimeException('WooCommerce is not active.');
		}

		if (get_transient(self::LOCK_TRANSIENT)) {
			throw new RuntimeException(__('Another migration is already running. Wait for it to finish or clear the lock.', 'consucorner-order-migration'));
		}

		$config = wp_parse_args($overrides, $this->config);
		$page   = max(1, (int) $config['start_page']);
		$max    = max(0, (int) $config['max_orders']);
		$dry    = ! empty($config['dry_run']);
		$imported_count = 0;

		if (! $dry) {
			set_transient(self::LOCK_TRANSIENT, time(), 30 * MINUTE_IN_SECONDS);
		}

		$this->load_migrated_index();
		$this->log('info', $dry ? 'DRY RUN — no orders will be created.' : 'Starting order migration…');
		$this->log('info', sprintf('Duplicate guard: %d source order(s) already mapped on this site.', count($this->migrated_index)));

		try {
			while (true) {
				if ($max > 0 && $imported_count >= $max) {
					$this->log('info', 'Reached max_orders limit.');
					break;
				}

				$this->log('info', sprintf('Fetching API page %d…', $page));

				try {
					$batch = $this->api->fetch_orders_page($page);
				} catch (RuntimeException $e) {
					$this->log('error', $e->getMessage());
					break;
				}

				$orders = $batch['orders'];
				if (empty($orders)) {
					$this->log('success', 'No more orders on API.');
					break;
				}

				foreach ($orders as $order_data) {
					if ($max > 0 && $imported_count >= $max) {
						break 2;
					}

					$result = $this->import_order($order_data, $dry);
					if ('imported' === $result) {
						++$imported_count;
					}
				}

				if ($page >= (int) $batch['total_pages']) {
					$this->log('success', sprintf('Finished all %d API pages.', $page));
					break;
				}

				++$page;
				if (! empty($config['sleep_seconds'])) {
					sleep((int) $config['sleep_seconds']);
				}
			}
		} finally {
			if (! $dry) {
				delete_transient(self::LOCK_TRANSIENT);
			}
		}

		return $this->stats;
	}

	/**
	 * Import one order payload.
	 *
	 * @param array $old Order JSON from WC API.
	 * @param bool  $dry Dry run.
	 * @return string imported|skipped|failed
	 */
	public function import_order(array $old, $dry = false)
	{
		if (! $this->index_loaded) {
			$this->load_migrated_index();
		}

		$old_id = isset($old['id']) ? absint($old['id']) : 0;
		if (! $old_id) {
			++$this->stats['failed'];
			return 'failed';
		}

		if (! empty($this->config['fetch_full_order']) && empty($old['line_items'])) {
			try {
				$old = $this->api->fetch_order($old_id);
			} catch (RuntimeException $e) {
				$this->log('warning', sprintf('Could not fetch full order #%d: %s', $old_id, $e->getMessage()));
			}
		}

		$skip_reason = $this->get_skip_reason($old);
		if ($skip_reason) {
			++$this->stats['skipped'];
			$this->log('warning', sprintf('Skip source #%d — %s', $old_id, $skip_reason));
			return 'skipped';
		}

		$line_items = $this->map_line_items($old);
		if (empty($line_items)) {
			++$this->stats['failed'];
			$this->log('error', sprintf('Skip #%d — no line items could be mapped (check SKUs).', $old_id));
			return 'failed';
		}

		if ($dry) {
			$this->log('info', sprintf('[DRY] Would import parent order #%d with %d line item(s).', $old_id, count($line_items)));
			++$this->stats['imported'];
			return 'imported';
		}

		$this->enable_import_guards();

		try {
			$new_id = $this->create_local_order($old, $line_items);
			++$this->stats['imported'];
			$this->log('success', sprintf('Imported source #%d → new #%d', $old_id, $new_id));
			return 'imported';
		} catch (Exception $e) {
			++$this->stats['failed'];
			$this->log('error', sprintf('Failed source #%d: %s', $old_id, $e->getMessage()));
			return 'failed';
		} finally {
			$this->disable_import_guards();
		}
	}

	/**
	 * Why a source order should be skipped (empty string = do not skip).
	 *
	 * @param array $old Source order payload.
	 * @return string
	 */
	private function get_skip_reason(array $old)
	{
		$old_id = isset($old['id']) ? absint($old['id']) : 0;

		if ($this->is_already_migrated($old_id)) {
			$local_id = $this->migrated_index[$old_id];
			return sprintf('already imported as #%d', $local_id);
		}

		if (! empty($this->config['skip_source_trash']) && ! empty($old['status']) && 'trash' === $old['status']) {
			return 'source order is in trash';
		}

		if (! empty($this->config['parent_only']) && $this->is_source_sub_order($old)) {
			$parent = isset($old['parent_id']) ? absint($old['parent_id']) : 0;
			return $parent ? sprintf('Dokan/WC sub-order (parent %d)', $parent) : 'Dokan/WC sub-order';
		}

		return '';
	}

	/**
	 * Detect Dokan or WooCommerce child orders on the source site.
	 *
	 * @param array $old Source order.
	 * @return bool
	 */
	private function is_source_sub_order(array $old)
	{
		if (! empty($old['parent_id']) && absint($old['parent_id']) > 0) {
			return true;
		}

		if (! empty($old['meta_data']) && is_array($old['meta_data'])) {
			foreach ($old['meta_data'] as $meta) {
				if (empty($meta['key'])) {
					continue;
				}
				$key = (string) $meta['key'];
				if ('_dokan_parent_order_id' === $key && ! empty($meta['value'])) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * @param int $old_id Source order ID.
	 * @return bool
	 */
	private function is_already_migrated($old_id)
	{
		$old_id = absint($old_id);
		if (isset($this->migrated_index[$old_id])) {
			return true;
		}

		$local_id = $this->find_local_order_by_old_id($old_id);
		if ($local_id) {
			$this->migrated_index[$old_id] = $local_id;
			return true;
		}

		return false;
	}

	/**
	 * Load all source→local mappings (any order status, including trash).
	 *
	 * @return void
	 */
	public function load_migrated_index()
	{
		if ($this->index_loaded) {
			return;
		}

		$stored = get_option(self::ID_MAP_OPTION, array());
		if (is_array($stored)) {
			foreach ($stored as $old => $new) {
				$this->migrated_index[absint($old)] = absint($new);
			}
		}

		global $wpdb;
		$meta_key = $this->config['migration_meta_key'];
		$meta_tbl = $wpdb->prefix . 'wc_orders_meta';
		$ord_tbl  = $wpdb->prefix . 'wc_orders';

		if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $meta_tbl)) === $meta_tbl) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT o.id AS new_id, om.meta_value AS old_id
					FROM {$ord_tbl} o
					INNER JOIN {$meta_tbl} om ON om.order_id = o.id
					WHERE om.meta_key = %s",
					$meta_key
				),
				ARRAY_A
			);

			if (is_array($rows)) {
				foreach ($rows as $row) {
					$old = absint($row['old_id']);
					$new = absint($row['new_id']);
					if ($old && $new) {
						$this->migrated_index[$old] = $new;
					}
				}
			}
		} else {
			$all_statuses = array_keys(wc_get_order_statuses());
			$all_statuses[] = 'trash';
			$page           = 1;

			do {
				$batch = wc_get_orders(
					array(
						'limit'      => 200,
						'page'       => $page,
						'status'     => $all_statuses,
						'meta_key'   => $meta_key,
						'return'     => 'ids',
						'paginate'   => true,
					)
				);

				if (empty($batch->orders)) {
					break;
				}

				foreach ($batch->orders as $new_id) {
					$order = wc_get_order($new_id);
					if (! $order) {
						continue;
					}
					$old = absint($order->get_meta($meta_key));
					if ($old) {
						$this->migrated_index[$old] = (int) $new_id;
					}
				}

				++$page;
			} while ($page <= (int) $batch->max_num_pages);
		}

		update_option(self::ID_MAP_OPTION, $this->migrated_index, false);
		$this->index_loaded = true;
	}

	/**
	 * Persist mapping immediately to block duplicate imports.
	 *
	 * @param int $old_id Source order ID.
	 * @param int $new_id Local order ID.
	 * @return void
	 */
	private function record_migration($old_id, $new_id)
	{
		$old_id = absint($old_id);
		$new_id = absint($new_id);
		if (! $old_id || ! $new_id) {
			return;
		}

		$this->migrated_index[$old_id] = $new_id;
		update_option(self::ID_MAP_OPTION, $this->migrated_index, false);
	}

	/**
	 * Find local order by old ID meta (any status).
	 *
	 * @param int $old_id Old order ID.
	 * @return int Local order ID or 0.
	 */
	public function find_local_order_by_old_id($old_id)
	{
		$all_statuses = array_keys(wc_get_order_statuses());
		$all_statuses[] = 'trash';

		$orders = wc_get_orders(
			array(
				'limit'      => 1,
				'status'     => $all_statuses,
				'meta_query' => array(
					array(
						'key'     => $this->config['migration_meta_key'],
						'value'   => (string) absint($old_id),
						'compare' => '=',
					),
				),
				'return'     => 'ids',
			)
		);

		return ! empty($orders[0]) ? (int) $orders[0] : 0;
	}

	/**
	 * Map API line items to local products.
	 *
	 * @param array $old Old order.
	 * @return array<int, array>
	 */
	private function map_line_items(array $old)
	{
		$mapped = array();

		if (empty($old['line_items']) || ! is_array($old['line_items'])) {
			return $mapped;
		}

		foreach ($old['line_items'] as $item) {
			$sku        = isset($item['sku']) ? trim((string) $item['sku']) : '';
			$product_id = $sku ? (int) wc_get_product_id_by_sku($sku) : 0;

			if (! $product_id) {
				$this->log(
					'warning',
					sprintf(
						'  Line item not mapped — SKU "%s" (%s)',
						$sku ? $sku : 'empty',
						isset($item['name']) ? $item['name'] : 'unknown'
					)
				);
				continue;
			}

			$mapped[] = array(
				'product_id'   => $product_id,
				'quantity'     => max(1, (int) ($item['quantity'] ?? 1)),
				'subtotal'     => (string) ($item['subtotal'] ?? '0'),
				'total'        => (string) ($item['total'] ?? '0'),
				'subtotal_tax' => (string) ($item['subtotal_tax'] ?? '0'),
				'total_tax'    => (string) ($item['total_tax'] ?? '0'),
				'name'         => isset($item['name']) ? $item['name'] : '',
			);
		}

		return $mapped;
	}

	/**
	 * Create WC order on this site.
	 *
	 * @param array $old        Old order payload.
	 * @param array $line_items Mapped line items.
	 * @return int New order ID.
	 * @throws Exception On failure.
	 */
	private function create_local_order(array $old, array $line_items)
	{
		$old_id      = absint($old['id']);
		$customer_id = $this->resolve_customer_id($old);
		$order       = wc_create_order(
			array(
				'customer_id' => $customer_id,
				'status'      => 'pending',
			)
		);

		if (is_wp_error($order)) {
			throw new Exception($order->get_error_message());
		}

		$order->set_created_via('cc-order-migration');

		$meta_key = $this->config['migration_meta_key'];
		$order->update_meta_data($meta_key, $old_id);
		if (! empty($old['order_key'])) {
			$order->update_meta_data('_cc_migrated_from_order_key', sanitize_text_field($old['order_key']));
		}
		if (! empty($this->config['source_site_label'])) {
			$order->update_meta_data('_cc_migrated_from_site', sanitize_text_field($this->config['source_site_label']));
		}
		$order->save();

		// Record before Dokan split so a retry cannot create duplicates.
		$this->record_migration($old_id, $order->get_id());

		foreach ($line_items as $row) {
			$product = wc_get_product($row['product_id']);
			if (! $product) {
				continue;
			}

			$item_id = $order->add_product(
				$product,
				$row['quantity'],
				array(
					'subtotal' => $row['subtotal'],
					'total'    => $row['total'],
				)
			);

			if ($item_id) {
				$order_item = $order->get_item($item_id);
				if ($order_item instanceof WC_Order_Item_Product) {
					$order_item->set_subtotal_tax($row['subtotal_tax']);
					$order_item->set_total_tax($row['total_tax']);
					$order_item->save();
				}
			}
		}

		$this->apply_addresses($order, $old);
		$this->apply_shipping_lines($order, $old);
		$this->apply_fee_lines($order, $old);
		$this->apply_coupon_lines($order, $old);

		if (! empty($old['customer_note'])) {
			$order->set_customer_note(wp_kses_post($old['customer_note']));
		}

		if (! empty($old['payment_method'])) {
			$order->set_payment_method(sanitize_text_field($old['payment_method']));
		}
		if (! empty($old['payment_method_title'])) {
			$order->set_payment_method_title(sanitize_text_field($old['payment_method_title']));
		}
		if (! empty($old['transaction_id'])) {
			$order->set_transaction_id(sanitize_text_field($old['transaction_id']));
		}

		$order->set_currency(! empty($old['currency']) ? $old['currency'] : get_woocommerce_currency());

		if (isset($old['discount_total'])) {
			$order->set_discount_total($old['discount_total']);
		}
		if (isset($old['discount_tax'])) {
			$order->set_discount_tax($old['discount_tax']);
		}
		if (isset($old['shipping_total'])) {
			$order->set_shipping_total($old['shipping_total']);
		}
		if (isset($old['shipping_tax'])) {
			$order->set_shipping_tax($old['shipping_tax']);
		}
		if (isset($old['cart_tax'])) {
			$order->set_cart_tax($old['cart_tax']);
		}
		if (isset($old['total'])) {
			$order->set_total($old['total']);
		} else {
			$order->calculate_totals(false);
		}

		$this->apply_order_attribution($order, $old);

		$order->update_meta_data('_cc_migrated_at', current_time('mysql'));

		$status = ! empty($old['status']) ? sanitize_key($old['status']) : 'completed';
		if ('trash' === $status) {
			$status = 'cancelled';
		}

		// Status must be set before dates: set_status() overwrites date_completed/date_paid
		// when transitioning to completed/processing. Re-apply source dates afterward.
		$order->set_status($status);
		self::apply_dates_to_order($order, $old);
		$order->save();

		self::agent_debug_log(
			'H1-set-status-overwrites',
			'import dates after set_status',
			array(
				'local_id'  => $order->get_id(),
				'source_id' => $old_id,
				'status'    => $status,
				'completed' => $order->get_date_completed() ? $order->get_date_completed()->date('Y-m-d H:i:s') : null,
				'created'   => $order->get_date_created() ? $order->get_date_created()->date('Y-m-d H:i:s') : null,
			),
			'import'
		);

		$this->maybe_split_dokan_orders($order->get_id(), $line_items);
		$synced_suborders = self::sync_sub_order_dates_from_parent($order);
		self::sync_sub_order_attribution_from_parent($order);
		if ($synced_suborders > 0) {
			$this->log(
				'info',
				sprintf(
					/* translators: 1: sub-order count, 2: parent order ID */
					_n(
						'Synced dates for %1$d Dokan sub-order from parent #%2$d.',
						'Synced dates for %1$d Dokan sub-orders from parent #%2$d.',
						$synced_suborders,
						'consucorner-order-migration'
					),
					$synced_suborders,
					$order->get_id()
				)
			);
		}

		return $order->get_id();
	}

	/**
	 * Copy WooCommerce order attribution meta from the source payload.
	 *
	 * WooCommerce uses these _wc_order_attribution_* keys to render the Origin
	 * column and the order attribution metabox.
	 *
	 * @param WC_Order $order Order.
	 * @param array    $old   Source order payload.
	 * @return int Number of attribution fields copied.
	 */
	private function apply_order_attribution(WC_Order $order, array $old)
	{
		$meta = self::extract_order_attribution_meta($old);
		if (empty($meta)) {
			return 0;
		}

		self::apply_order_attribution_meta($order, $meta);
		return count($meta);
	}

	/**
	 * Extract WooCommerce attribution meta from a source order payload.
	 *
	 * @param array $old Source order payload.
	 * @return array<string, mixed>
	 */
	private static function extract_order_attribution_meta(array $old)
	{
		if (empty($old['meta_data']) || ! is_array($old['meta_data'])) {
			return array();
		}

		$meta = array();
		foreach ($old['meta_data'] as $item) {
			if (empty($item['key']) || 0 !== strpos((string) $item['key'], self::ATTR_META_PREFIX)) {
				continue;
			}

			$key = sanitize_key((string) $item['key']);
			if (0 !== strpos($key, self::ATTR_META_PREFIX) || ! array_key_exists('value', $item)) {
				continue;
			}

			$meta[$key] = self::sanitize_meta_value($item['value']);
		}

		return $meta;
	}

	/**
	 * Apply attribution meta to an order.
	 *
	 * @param WC_Order $order Order.
	 * @param array    $meta  Attribution meta.
	 * @return void
	 */
	private static function apply_order_attribution_meta(WC_Order $order, array $meta)
	{
		foreach ($meta as $key => $value) {
			$order->update_meta_data($key, $value);
		}
	}

	/**
	 * Sanitize source meta values while preserving scalar/array shape.
	 *
	 * @param mixed $value Meta value.
	 * @return mixed
	 */
	private static function sanitize_meta_value($value)
	{
		if (function_exists('wc_clean')) {
			return wc_clean($value);
		}

		if (is_array($value)) {
			return array_map(array(__CLASS__, 'sanitize_meta_value'), $value);
		}

		return is_scalar($value) ? sanitize_text_field((string) $value) : '';
	}

	/**
	 * Resolve customer user ID by billing email.
	 *
	 * @param array $old Old order.
	 * @return int
	 */
	private function resolve_customer_id(array $old)
	{
		if (! empty($old['billing']['email'])) {
			$user = get_user_by('email', sanitize_email($old['billing']['email']));
			if ($user) {
				return (int) $user->ID;
			}
		}

		return 0;
	}

	/**
	 * @param WC_Order $order Order.
	 * @param array    $old   Payload.
	 * @return void
	 */
	private function apply_addresses(WC_Order $order, array $old)
	{
		if (! empty($old['billing']) && is_array($old['billing'])) {
			$order->set_billing($this->sanitize_address($old['billing']));
		}
		if (! empty($old['shipping']) && is_array($old['shipping'])) {
			$order->set_shipping($this->sanitize_address($old['shipping']));
		}
	}

	/**
	 * @param array $address Address array.
	 * @return array
	 */
	private function sanitize_address(array $address)
	{
		$allowed = array(
			'first_name',
			'last_name',
			'company',
			'address_1',
			'address_2',
			'city',
			'state',
			'postcode',
			'country',
			'email',
			'phone',
		);
		$clean = array();
		foreach ($allowed as $key) {
			if (isset($address[$key])) {
				$clean[$key] = is_string($address[$key]) ? wp_kses_post($address[$key]) : $address[$key];
			}
		}
		return $clean;
	}

	/**
	 * @param WC_Order $order Order.
	 * @param array    $old   Payload.
	 * @return void
	 */
	private function apply_shipping_lines(WC_Order $order, array $old)
	{
		if (empty($old['shipping_lines']) || ! is_array($old['shipping_lines'])) {
			return;
		}

		foreach ($old['shipping_lines'] as $ship) {
			$item = new WC_Order_Item_Shipping();
			$item->set_method_title(isset($ship['method_title']) ? $ship['method_title'] : '');
			$item->set_method_id(isset($ship['method_id']) ? $ship['method_id'] : '');
			$item->set_total(isset($ship['total']) ? $ship['total'] : 0);
			if (! empty($ship['total_tax'])) {
				$item->set_total_tax($ship['total_tax']);
			}
			$order->add_item($item);
		}
	}

	/**
	 * @param WC_Order $order Order.
	 * @param array    $old   Payload.
	 * @return void
	 */
	private function apply_fee_lines(WC_Order $order, array $old)
	{
		if (empty($old['fee_lines']) || ! is_array($old['fee_lines'])) {
			return;
		}

		foreach ($old['fee_lines'] as $fee) {
			$item = new WC_Order_Item_Fee();
			$item->set_name(isset($fee['name']) ? $fee['name'] : __('Fee', 'consucorner-order-migration'));
			$item->set_total(isset($fee['total']) ? $fee['total'] : 0);
			if (! empty($fee['total_tax'])) {
				$item->set_total_tax($fee['total_tax']);
			}
			$order->add_item($item);
		}
	}

	/**
	 * @param WC_Order $order Order.
	 * @param array    $old   Payload.
	 * @return void
	 */
	private function apply_coupon_lines(WC_Order $order, array $old)
	{
		if (empty($old['coupon_lines']) || ! is_array($old['coupon_lines'])) {
			return;
		}

		foreach ($old['coupon_lines'] as $coupon) {
			$item = new WC_Order_Item_Coupon();
			$item->set_code(isset($coupon['code']) ? $coupon['code'] : '');
			$item->set_discount(isset($coupon['discount']) ? $coupon['discount'] : 0);
			$item->set_discount_tax(isset($coupon['discount_tax']) ? $coupon['discount_tax'] : 0);
			$order->add_item($item);
		}
	}

	/**
	 * Apply WooCommerce order dates from a source REST payload.
	 *
	 * @param WC_Order $order Local order.
	 * @param array    $old   Source order payload.
	 * @return void
	 */
	private static function apply_dates_to_order(WC_Order $order, array $old)
	{
		$dates = self::extract_source_date_payload($old);

		foreach (array('date_created', 'date_modified', 'date_completed', 'date_paid') as $field) {
			if (empty($dates[$field])) {
				continue;
			}

			$datetime = wc_string_to_datetime($dates[$field]);
			if (! $datetime) {
				continue;
			}

			switch ($field) {
				case 'date_created':
					$order->set_date_created($datetime);
					break;
				case 'date_modified':
					$order->set_date_modified($datetime);
					break;
				case 'date_completed':
					$order->set_date_completed($datetime);
					break;
				case 'date_paid':
					$order->set_date_paid($datetime);
					break;
			}
		}
	}

	/**
	 * Normalize date fields from a WooCommerce REST order payload.
	 *
	 * @param array $old Source order payload.
	 * @return array<string, string>
	 */
	private static function extract_source_date_payload(array $old)
	{
		$payload = array();

		$field_map = array(
			'date_created'   => array('date_created', 'date_created_gmt'),
			'date_modified'  => array('date_modified', 'date_modified_gmt'),
			'date_completed' => array('date_completed', 'date_completed_gmt'),
			'date_paid'      => array('date_paid', 'date_paid_gmt'),
		);

		foreach ($field_map as $canonical => $keys) {
			foreach ($keys as $key) {
				if (! empty($old[$key]) && is_string($old[$key])) {
					$payload[$canonical] = $old[$key];
					break;
				}
			}
		}

		if (empty($old['meta_data']) || ! is_array($old['meta_data'])) {
			return $payload;
		}

		$meta_map = array(
			'date_completed' => array('_date_completed', '_completed_date'),
			'date_paid'      => array('_date_paid', '_paid_date'),
			'date_created'   => array('_date_created'),
			'date_modified'  => array('_date_modified'),
		);

		foreach ($meta_map as $canonical => $meta_keys) {
			if (! empty($payload[$canonical])) {
				continue;
			}

			foreach ($old['meta_data'] as $meta) {
				if (empty($meta['key']) || ! in_array($meta['key'], $meta_keys, true)) {
					continue;
				}

				$value = isset($meta['value']) ? (string) $meta['value'] : '';
				if ('' !== $value) {
					$payload[$canonical] = $value;
					break;
				}
			}
		}

		return $payload;
	}

	/**
	 * Compare order datetime with a source date string (minute precision).
	 *
	 * @param WC_DateTime|null $order_date Order datetime.
	 * @param string           $source_raw Source date string.
	 * @return bool
	 */
	private static function order_date_matches_source($order_date, $source_raw)
	{
		if (! $order_date || '' === $source_raw) {
			return false;
		}

		$source = wc_string_to_datetime($source_raw);
		if (! $source) {
			return false;
		}

		return $order_date->format('Y-m-d H:i') === $source->format('Y-m-d H:i');
	}

	/**
	 * Append NDJSON debug logs for this migration session.
	 *
	 * @param string               $hypothesis_id Hypothesis id.
	 * @param string               $message       Log message.
	 * @param array<string, mixed> $data          Payload.
	 * @param string               $run_id        Run id.
	 * @return void
	 */
	private static function agent_debug_log($hypothesis_id, $message, array $data, $run_id = 'repair')
	{
		$log_path = ABSPATH . 'debug-207f52.log';
		$entry    = array(
			'sessionId'    => '207f52',
			'runId'        => $run_id,
			'hypothesisId' => $hypothesis_id,
			'location'     => 'class-cc-order-migrator.php',
			'message'      => $message,
			'data'         => $data,
			'timestamp'    => (int) round(microtime(true) * 1000),
		);

		// #region agent log
		file_put_contents($log_path, wp_json_encode($entry) . "\n", FILE_APPEND | LOCK_EX);
		// #endregion
	}

	/**
	 * Split parent order into Dokan sub-orders when multiple vendors exist.
	 *
	 * @param int   $order_id   Parent order ID.
	 * @param array $line_items Mapped line items.
	 * @return void
	 */
	private function maybe_split_dokan_orders($order_id, array $line_items)
	{
		if (! function_exists('dokan') || ! dokan()->order) {
			return;
		}

		$vendor_ids = array();
		foreach ($line_items as $row) {
			$author = (int) get_post_field('post_author', $row['product_id']);
			if ($author > 0) {
				$vendor_ids[$author] = true;
			}
		}

		if (count($vendor_ids) < 2) {
			dokan()->order->maybe_split_orders($order_id, false);
			return;
		}

		dokan()->order->maybe_split_orders($order_id, true);
		++$this->stats['suborders'];
	}

	/**
	 * Sync dates from a migrated parent order to its Dokan child orders.
	 *
	 * Dokan creates sub-orders as fresh orders, so their created date defaults to
	 * the import date unless the migration corrects it after splitting.
	 *
	 * @param WC_Order $parent_order Parent order.
	 * @return int Number of child orders updated.
	 */
	private static function sync_sub_order_dates_from_parent(WC_Order $parent_order)
	{
		$child_orders = self::get_child_orders($parent_order);
		if (empty($child_orders)) {
			return 0;
		}

		$updated = 0;
		foreach ($child_orders as $child_order) {
			if (! $child_order instanceof WC_Order) {
				continue;
			}

			$child_order->set_date_created($parent_order->get_date_created());
			$child_order->set_date_modified($parent_order->get_date_modified());
			$child_order->set_date_completed($parent_order->get_date_completed());
			$child_order->set_date_paid($parent_order->get_date_paid());
			$child_order->save();

			++$updated;
		}

		return $updated;
	}

	/**
	 * Sync WooCommerce attribution meta from a parent order to Dokan child orders.
	 *
	 * @param WC_Order $parent_order Parent order.
	 * @return int Number of child orders updated.
	 */
	private static function sync_sub_order_attribution_from_parent(WC_Order $parent_order)
	{
		$meta = self::get_order_attribution_meta($parent_order);
		if (empty($meta)) {
			return 0;
		}

		$child_orders = self::get_child_orders($parent_order);
		if (empty($child_orders)) {
			return 0;
		}

		$updated = 0;
		foreach ($child_orders as $child_order) {
			if (! $child_order instanceof WC_Order) {
				continue;
			}

			self::apply_order_attribution_meta($child_order, $meta);
			$child_order->save();
			++$updated;
		}

		return $updated;
	}

	/**
	 * Get attribution meta currently stored on an order.
	 *
	 * @param WC_Order $order Order.
	 * @return array<string, mixed>
	 */
	private static function get_order_attribution_meta(WC_Order $order)
	{
		$meta = array();
		foreach ($order->get_meta_data() as $item) {
			$data = $item->get_data();
			if (empty($data['key']) || 0 !== strpos((string) $data['key'], self::ATTR_META_PREFIX)) {
				continue;
			}

			$meta[(string) $data['key']] = isset($data['value']) ? $data['value'] : '';
		}

		return $meta;
	}

	/**
	 * Get Dokan child orders for a parent order with a WooCommerce fallback.
	 *
	 * @param WC_Order $parent_order Parent order.
	 * @return WC_Order[]
	 */
	private static function get_child_orders(WC_Order $parent_order)
	{
		if (function_exists('dokan') && dokan()->order && is_callable(array(dokan()->order, 'get_child_orders'))) {
			$child_orders = dokan()->order->get_child_orders($parent_order);
			return is_array($child_orders) ? $child_orders : array();
		}

		return wc_get_orders(
			array(
				'type'   => 'shop_order',
				'parent' => $parent_order->get_id(),
				'limit'  => -1,
			)
		);
	}

	/**
	 * Repair parent + Dokan sub-order dates from the source site API.
	 *
	 * Only orders with the migration meta key are touched. Native orders created
	 * on this site are never modified. Processes up to $limit orders per call;
	 * orders already repaired are skipped via DATE_REPAIR_DONE_META.
	 *
	 * @param int $limit Maximum parent orders to process, 0 for all remaining.
	 * @return array{orders:int,children:int,unchanged:int,missing:int,failed:int,processed:int,remaining:int,complete:bool}
	 */
	public static function sync_migrated_order_dates_from_source($limit = null)
	{
		if (! function_exists('wc_get_orders')) {
			throw new RuntimeException('WooCommerce is not active.');
		}

		if (null === $limit) {
			$limit = self::DATE_REPAIR_BATCH_SIZE;
		}

		$config = CC_Order_Migration_Config::get();
		$api    = new CC_Order_Migration_API($config);
		$limit  = max(0, absint($limit));
		$stats  = array(
			'orders'    => 0,
			'children'  => 0,
			'unchanged' => 0,
			'missing'   => 0,
			'failed'    => 0,
			'processed' => 0,
			'remaining' => 0,
			'complete'  => false,
		);

		do {
			$chunk_size = (0 === $limit) ? 50 : min(50, $limit - $stats['processed']);
			if ($chunk_size <= 0) {
				break;
			}

			$order_ids = self::get_pending_date_repair_order_ids($chunk_size);
			if (empty($order_ids)) {
				break;
			}

			foreach ($order_ids as $order_id) {
				++$stats['processed'];
				$order = wc_get_order($order_id);
				if (! $order) {
					++$stats['failed'];
					continue;
				}

				$old_id = absint($order->get_meta($config['migration_meta_key'], true));
				if (! $old_id) {
					++$stats['failed'];
					self::mark_date_repair_done($order);
					continue;
				}

				try {
					$source_order = $api->fetch_order($old_id);
				} catch (Exception $e) {
					++$stats['failed'];
					continue;
				}

				$source_dates = self::extract_source_date_payload($source_order);
				if (empty($source_dates)) {
					++$stats['missing'];
					self::mark_date_repair_done($order);
					continue;
				}

				$before = array(
					'created'   => $order->get_date_created() ? $order->get_date_created()->date('Y-m-d H:i:s') : null,
					'completed' => $order->get_date_completed() ? $order->get_date_completed()->date('Y-m-d H:i:s') : null,
					'paid'      => $order->get_date_paid() ? $order->get_date_paid()->date('Y-m-d H:i:s') : null,
				);

				$already_correct = true;
				foreach (array('date_created', 'date_completed', 'date_paid') as $field) {
					if (empty($source_dates[$field])) {
						continue;
					}

					$getter = 'get_' . $field;
					if (! self::order_date_matches_source($order->$getter(), $source_dates[$field])) {
						$already_correct = false;
						break;
					}
				}

				if ($already_correct) {
					$child_updates = self::sync_sub_order_dates_from_parent($order);
					if ($child_updates > 0) {
						$stats['children'] += $child_updates;
					} else {
						++$stats['unchanged'];
					}
					self::mark_date_repair_done($order);
					continue;
				}

				self::apply_dates_to_order($order, $source_order);
				$order->save();

				$after = array(
					'created'   => $order->get_date_created() ? $order->get_date_created()->date('Y-m-d H:i:s') : null,
					'completed' => $order->get_date_completed() ? $order->get_date_completed()->date('Y-m-d H:i:s') : null,
					'paid'      => $order->get_date_paid() ? $order->get_date_paid()->date('Y-m-d H:i:s') : null,
				);

				self::agent_debug_log(
					'H2-repair-from-source',
					'repaired migrated order dates',
					array(
						'local_id'     => $order_id,
						'source_id'    => $old_id,
						'before'       => $before,
						'after'        => $after,
						'source_dates' => $source_dates,
					),
					'repair'
				);

				++$stats['orders'];
				$stats['children'] += self::sync_sub_order_dates_from_parent($order);
				self::mark_date_repair_done($order);
			}
		} while (0 === $limit);

		$stats['remaining'] = self::count_date_repair_remaining();
		$stats['complete']  = 0 === $stats['remaining'];

		self::agent_debug_log(
			'H3-batch-repair',
			'date repair batch finished',
			array(
				'limit'     => $limit > 0 ? $limit : 'all',
				'processed' => $stats['processed'],
				'remaining' => $stats['remaining'],
				'orders'    => $stats['orders'],
			),
			'repair'
		);

		return $stats;
	}

	/**
	 * Count migrated orders still waiting for date repair.
	 *
	 * @return int
	 */
	public static function count_date_repair_remaining()
	{
		if (! function_exists('wc_get_orders')) {
			return 0;
		}

		$results = wc_get_orders(
			array_merge(
				self::get_date_repair_pending_query_args(),
				array(
					'limit'    => 1,
					'paginate' => true,
					'return'   => 'ids',
				)
			)
		);

		return isset($results->total) ? (int) $results->total : 0;
	}

	/**
	 * Base wc_get_orders args for pending date repair queue.
	 *
	 * @return array<string, mixed>
	 */
	private static function get_date_repair_pending_query_args()
	{
		$config       = CC_Order_Migration_Config::get();
		$all_statuses = array_keys(wc_get_order_statuses());
		$all_statuses[] = 'trash';

		return array(
			'status'     => $all_statuses,
			'orderby'    => 'ID',
			'order'      => 'ASC',
			'meta_query' => array(
				'relation' => 'AND',
				array(
					'key'     => $config['migration_meta_key'],
					'compare' => 'EXISTS',
				),
				array(
					'key'     => self::DATE_REPAIR_DONE_META,
					'compare' => 'NOT EXISTS',
				),
			),
		);
	}

	/**
	 * @param int $limit Max order IDs to return.
	 * @return int[]
	 */
	private static function get_pending_date_repair_order_ids($limit)
	{
		$limit = max(1, absint($limit));

		$order_ids = wc_get_orders(
			array_merge(
				self::get_date_repair_pending_query_args(),
				array(
					'limit'  => $limit,
					'return' => 'ids',
				)
			)
		);

		return is_array($order_ids) ? array_map('absint', $order_ids) : array();
	}

	/**
	 * Mark a migrated order as date-repaired so the next batch skips it.
	 *
	 * @param WC_Order $order Order.
	 * @return void
	 */
	private static function mark_date_repair_done(WC_Order $order)
	{
		$order->update_meta_data(self::DATE_REPAIR_DONE_META, current_time('mysql'));
		$order->save();
	}

	/**
	 * Repair dates for already-imported Dokan sub-orders.
	 *
	 * @param int $limit Maximum parent orders to scan, 0 for all.
	 * @return array{parents:int,children:int,failed:int}
	 */
	public static function sync_migrated_sub_order_dates($limit = 0)
	{
		if (! function_exists('wc_get_orders')) {
			throw new RuntimeException('WooCommerce is not active.');
		}

		$config       = wp_parse_args(CC_Order_Migration_Config::get_raw(), CC_Order_Migration_Config::defaults());
		$all_statuses = array_keys(wc_get_order_statuses());
		$all_statuses[] = 'trash';
		$page        = 1;
		$processed   = 0;
		$limit       = max(0, absint($limit));
		$stats       = array(
			'parents'  => 0,
			'children' => 0,
			'failed'   => 0,
		);

		do {
			$batch = wc_get_orders(
				array(
					'limit'      => 100,
					'page'       => $page,
					'status'     => $all_statuses,
					'meta_key'   => $config['migration_meta_key'],
					'return'     => 'ids',
					'paginate'   => true,
				)
			);

			if (empty($batch->orders)) {
				break;
			}

			foreach ($batch->orders as $order_id) {
				if ($limit > 0 && $processed >= $limit) {
					break 2;
				}

				++$processed;
				$parent_order = wc_get_order($order_id);
				if (! $parent_order) {
					++$stats['failed'];
					continue;
				}

				$updated = self::sync_sub_order_dates_from_parent($parent_order);
				if ($updated > 0) {
					++$stats['parents'];
					$stats['children'] += $updated;
				}
			}

			++$page;
		} while ($page <= (int) $batch->max_num_pages);

		return $stats;
	}

	/**
	 * Repair WooCommerce attribution meta for already-imported orders.
	 *
	 * Fetches the source order through the REST API, copies _wc_order_attribution_*
	 * meta to the migrated parent order, then mirrors it to Dokan sub-orders.
	 *
	 * @param int $limit Maximum parent orders to scan, 0 for all.
	 * @return array{orders:int,children:int,missing:int,failed:int}
	 */
	public static function sync_migrated_order_attribution($limit = 0)
	{
		if (! function_exists('wc_get_orders')) {
			throw new RuntimeException('WooCommerce is not active.');
		}

		$config       = CC_Order_Migration_Config::get();
		$api          = new CC_Order_Migration_API($config);
		$all_statuses = array_keys(wc_get_order_statuses());
		$all_statuses[] = 'trash';
		$page        = 1;
		$processed   = 0;
		$limit       = max(0, absint($limit));
		$stats       = array(
			'orders'   => 0,
			'children' => 0,
			'missing'  => 0,
			'failed'   => 0,
		);

		do {
			$batch = wc_get_orders(
				array(
					'limit'      => 50,
					'page'       => $page,
					'status'     => $all_statuses,
					'meta_key'   => $config['migration_meta_key'],
					'return'     => 'ids',
					'paginate'   => true,
				)
			);

			if (empty($batch->orders)) {
				break;
			}

			foreach ($batch->orders as $order_id) {
				if ($limit > 0 && $processed >= $limit) {
					break 2;
				}

				++$processed;
				$order = wc_get_order($order_id);
				if (! $order) {
					++$stats['failed'];
					continue;
				}

				$old_id = absint($order->get_meta($config['migration_meta_key'], true));
				if (! $old_id) {
					++$stats['failed'];
					continue;
				}

				try {
					$source_order = $api->fetch_order($old_id);
				} catch (Exception $e) {
					++$stats['failed'];
					continue;
				}

				$meta = self::extract_order_attribution_meta($source_order);
				if (empty($meta)) {
					++$stats['missing'];
					continue;
				}

				self::apply_order_attribution_meta($order, $meta);
				$order->save();

				++$stats['orders'];
				$stats['children'] += self::sync_sub_order_attribution_from_parent($order);
			}

			++$page;
		} while ($page <= (int) $batch->max_num_pages);

		return $stats;
	}

	/**
	 * Permanently delete trashed orders created by this migration.
	 *
	 * @return int Number deleted.
	 */
	public static function purge_trashed_migrations()
	{
		$config     = CC_Order_Migration_Config::get();
		$deleted    = 0;
		$all_status = array('trash');

		$page = 1;
		do {
			$batch = wc_get_orders(
				array(
					'limit'      => 100,
					'page'       => $page,
					'status'     => $all_status,
					'meta_key'   => $config['migration_meta_key'],
					'return'     => 'ids',
					'paginate'   => true,
				)
			);

			if (empty($batch->orders)) {
				break;
			}

			foreach ($batch->orders as $order_id) {
				$order = wc_get_order($order_id);
				if ($order) {
					$order->delete(true);
					++$deleted;
				}
			}

			++$page;
		} while ($page <= (int) $batch->max_num_pages);

		delete_option(self::ID_MAP_OPTION);
		return $deleted;
	}

	/**
	 * Count migrated parent orders on this site.
	 *
	 * @return int
	 */
	public static function count_migrated()
	{
		$config  = CC_Order_Migration_Config::get();
		$results = wc_get_orders(
			array(
				'limit'      => 1,
				'meta_key'   => $config['migration_meta_key'],
				'paginate'   => true,
				'return'     => 'ids',
			)
		);

		return isset($results->total) ? (int) $results->total : 0;
	}

	/**
	 * @return void
	 */
	public static function clear_lock()
	{
		delete_transient(self::LOCK_TRANSIENT);
	}

	/**
	 * Disable stock reduction and customer emails during import.
	 *
	 * @return void
	 */
	private function enable_import_guards()
	{
		add_filter('woocommerce_can_reduce_order_stock', '__return_false', 9999);
		add_filter('woocommerce_email_enabled_new_order', '__return_false', 9999);
		add_filter('woocommerce_email_enabled_customer_processing_order', '__return_false', 9999);
		add_filter('woocommerce_email_enabled_customer_completed_order', '__return_false', 9999);
	}

	/**
	 * @return void
	 */
	private function disable_import_guards()
	{
		remove_filter('woocommerce_can_reduce_order_stock', '__return_false', 9999);
		remove_filter('woocommerce_email_enabled_new_order', '__return_false', 9999);
		remove_filter('woocommerce_email_enabled_customer_processing_order', '__return_false', 9999);
		remove_filter('woocommerce_email_enabled_customer_completed_order', '__return_false', 9999);
	}

	/**
	 * @param string $level   info|success|warning|error.
	 * @param string $message Message.
	 * @return void
	 */
	private function log($level, $message)
	{
		if ($this->logger) {
			call_user_func($this->logger, $level, $message);
		}
	}
}
