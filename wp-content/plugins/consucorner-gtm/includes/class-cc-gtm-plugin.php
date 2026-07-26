<?php
/**
 * Frontend bootstrap and backward-compatible facade.
 *
 * @package ConsuCorner_GTM
 */

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin frontend.
 */
final class CC_GTM_Plugin {

	/**
	 * Boot hooks.
	 */
	public static function init() {
		CC_GTM_RankMath::init();
		CC_GTM_Conflicts::init();

		add_action( 'wp_head', array( __CLASS__, 'print_data_layer_init' ), 0 );
		add_action( 'wp_head', array( __CLASS__, 'print_gtm_head_snippet' ), 1 );
		add_action( 'wp_head', array( __CLASS__, 'print_data_layer_events' ), 20 );
		add_action( 'wp_body_open', array( __CLASS__, 'print_gtm_noscript' ), 1 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ), 5 );
		add_action( 'template_redirect', array( __CLASS__, 'capture_purchase_order_id' ), 99 );
		add_action( 'woocommerce_thankyou', array( __CLASS__, 'capture_purchase_order_from_thankyou' ), 5 );
		add_action( 'wp_footer', array( __CLASS__, 'print_captured_purchase' ), 5 );
		add_action( 'wp_footer', array( __CLASS__, 'maybe_print_debug_overlay' ), 99 );

		if ( is_admin() ) {
			CC_GTM_Admin::init();
		}
	}

	/**
	 * dataLayer bootstrap.
	 */
	public static function print_data_layer_init() {
		if ( is_admin() || wp_doing_ajax() || ! CC_GTM_Settings::get( 'enable_datalayer', true ) ) {
			return;
		}
		echo "<script>window.dataLayer=window.dataLayer||[];</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Base origin used to load GTM. Defaults to Google; uses the first-party
	 * server container domain when first-party loading is enabled.
	 *
	 * @return string Origin without trailing slash.
	 */
	public static function get_loader_origin() {
		if ( CC_GTM_Settings::get( 'first_party_loader', false ) ) {
			$server_url = CC_GTM_Settings::get_server_container_url();
			if ( $server_url ) {
				return $server_url;
			}
		}
		return 'https://www.googletagmanager.com';
	}

	/**
	 * GTM head snippet.
	 */
	public static function print_gtm_head_snippet() {
		if ( is_admin() || wp_doing_ajax() || ! CC_GTM_Settings::get( 'enable_gtm', true ) ) {
			return;
		}
		$id     = esc_js( CC_GTM_Settings::get_gtm_container_id() );
		$origin = esc_js( self::get_loader_origin() );
		?>
		<!-- Google Tag Manager -->
		<script>
		(function(w,d,s,l,i,g){w[l]=w[l]||[];w[l].push({'gtm.start':
		new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
		j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
		g+'/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
		})(window,document,'script','dataLayer','<?php echo esc_js( $id ); ?>','<?php echo esc_js( $origin ); ?>');
		</script>
		<!-- End Google Tag Manager -->
		<?php
	}

	/**
	 * GTM noscript.
	 */
	public static function print_gtm_noscript() {
		if ( is_admin() || wp_doing_ajax() || ! CC_GTM_Settings::get( 'enable_gtm', true ) || ! CC_GTM_Settings::get( 'enable_noscript', true ) ) {
			return;
		}
		$url = self::get_loader_origin() . '/ns.html?id=' . rawurlencode( CC_GTM_Settings::get_gtm_container_id() );
		?>
		<!-- Google Tag Manager (noscript) -->
		<noscript><iframe src="<?php echo esc_url( $url ); ?>"
		height="0" width="0" style="display:none;visibility:hidden" title="<?php esc_attr_e( 'Google Tag Manager', 'consucorner-gtm' ); ?>"></iframe></noscript>
		<!-- End Google Tag Manager (noscript) -->
		<?php
	}

	/**
	 * Enqueue cc-gtm.js.
	 */
	public static function enqueue_scripts() {
		if ( is_admin() || ! CC_GTM_Settings::get( 'enable_datalayer', true ) ) {
			return;
		}
		$path = CC_GTM_PLUGIN_DIR . 'assets/cc-gtm.js';
		$ver  = file_exists( $path ) ? (string) filemtime( $path ) : '2.0.0';
		wp_enqueue_script(
			'consucorner-gtm',
			CC_GTM_PLUGIN_URL . 'assets/cc-gtm.js',
			array(),
			$ver,
			true
		);
		wp_localize_script(
			'consucorner-gtm',
			'ccGtmConfig',
			array(
				'currency'    => CC_GTM_WooCommerce::get_currency(),
				'listLimit'   => CC_GTM_WooCommerce::list_limit(),
				'pageType'    => CC_GTM_Datalayer::get_page_type(),
				'searchQuery' => is_search() ? get_search_query() : '',
				'listContext' => CC_GTM_Datalayer::get_list_context(),
				'debug'       => (bool) CC_GTM_Settings::get( 'debug_mode', false ),
			)
		);
	}

	/**
	 * Server-side dataLayer events.
	 */
	public static function print_data_layer_events() {
		if ( is_admin() || wp_doing_ajax() || ! CC_GTM_Settings::get( 'enable_datalayer', true ) ) {
			return;
		}
		CC_GTM_Datalayer::print_route_events();
	}

	/**
	 * @param int $order_id Order ID.
	 */
	public static function capture_purchase_order_from_thankyou( $order_id ) {
		if ( ! CC_GTM_Settings::get( 'enable_ecommerce', true ) ) {
			return;
		}
		CC_GTM_Datalayer::capture_purchase_order_from_thankyou( $order_id );
	}

	/**
	 * Capture purchase order ID.
	 */
	public static function capture_purchase_order_id() {
		if ( ! CC_GTM_Settings::get( 'enable_ecommerce', true ) ) {
			return;
		}
		CC_GTM_Datalayer::capture_purchase_order_id();
	}

	/**
	 * Print purchase event in footer.
	 */
	public static function print_captured_purchase() {
		if ( ! CC_GTM_Settings::get( 'enable_ecommerce', true ) ) {
			return;
		}
		CC_GTM_Datalayer::print_captured_purchase();
	}

	/**
	 * Admin-only debug overlay.
	 */
	public static function maybe_print_debug_overlay() {
		if ( ! CC_GTM_Settings::get( 'debug_mode', false ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$events = CC_GTM_Datalayer::get_debug_events();
		if ( empty( $events ) ) {
			return;
		}
		$labels = array();
		foreach ( array_slice( $events, -8 ) as $row ) {
			$labels[] = isset( $row['event'] ) ? esc_html( $row['event'] ) : '';
		}
		?>
		<div id="cc-gtm-debug" style="position:fixed;bottom:12px;left:12px;z-index:99999;background:#111;color:#0f0;font:12px/1.4 monospace;padding:8px 10px;border-radius:6px;max-width:280px;opacity:.92">
			<strong>CC GTM Debug</strong><br>
			<?php echo esc_html( implode( ' → ', array_filter( $labels ) ) ); ?>
		</div>
		<?php
	}

	/* Backward compatibility — delegate to datalayer / woocommerce. */

	/**
	 * @param array<string, mixed> $payload Payload.
	 */
	public static function print_data_layer_push( array $payload ) {
		CC_GTM_Datalayer::print_push( $payload );
	}

	/**
	 * @return string
	 */
	public static function get_currency() {
		return CC_GTM_WooCommerce::get_currency();
	}

	/**
	 * @return string
	 */
	public static function get_page_type() {
		return CC_GTM_Datalayer::get_page_type();
	}

	/**
	 * @return array{productCategory:?string,specialty:?string,procedure:?string}
	 */
	public static function get_archive_context_slugs() {
		return CC_GTM_Datalayer::get_archive_context_slugs();
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function build_page_context_payload() {
		return CC_GTM_Datalayer::build_page_context_payload();
	}

	/**
	 * @return array{item_list_id:string,item_list_name:string}
	 */
	public static function get_list_context() {
		return CC_GTM_Datalayer::get_list_context();
	}

	/**
	 * @return bool
	 */
	public static function should_push_initial_item_list() {
		return CC_GTM_Datalayer::should_push_initial_item_list();
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function build_view_item_payload() {
		return CC_GTM_Datalayer::build_view_item_payload();
	}

	/**
	 * @param string $event Event.
	 * @return array<string, mixed>
	 */
	public static function build_cart_event_payload( $event ) {
		return CC_GTM_Datalayer::build_cart_event_payload( $event );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function build_view_item_list_payload() {
		return CC_GTM_Datalayer::build_view_item_list_payload();
	}

	/**
	 * @return WC_Order|null
	 */
	public static function resolve_thankyou_order() {
		return CC_GTM_Datalayer::resolve_thankyou_order();
	}

	/**
	 * @param int $order_id Order ID.
	 * @return array<string, mixed>|null
	 */
	public static function build_purchase_payload_for_order( $order_id ) {
		return CC_GTM_Datalayer::build_purchase_payload_for_order( $order_id );
	}

	/**
	 * @param int $order_id Order ID.
	 */
	public static function mark_purchase_tracked( $order_id ) {
		CC_GTM_Datalayer::mark_purchase_tracked( $order_id );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function build_purchase_payload() {
		$order = CC_GTM_Datalayer::resolve_thankyou_order();
		if ( ! $order ) {
			return null;
		}
		return CC_GTM_Datalayer::build_purchase_payload_for_order( $order->get_id() );
	}

	/**
	 * @param mixed $value Rank Math option.
	 * @return mixed
	 */
	public static function disable_rank_math_frontend_gtag( $value ) {
		return CC_GTM_RankMath::disable_frontend_gtag( $value );
	}
}
