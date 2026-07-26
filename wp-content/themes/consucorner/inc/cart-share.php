<?php
/**
 * Shareable cart restore links.
 *
 * @package ConsuCorner
 */

defined( 'ABSPATH' ) || exit;

const CC_CART_SHARE_TTL       = WEEK_IN_SECONDS;
const CC_CART_SHARE_MAX_ITEMS = 30;
const CC_CART_SHARE_RATE_LIMIT = 10;

/**
 * Client IP for rate limiting (Cloudways-aware).
 *
 * @return string
 */
function cc_cart_share_client_ip() {
	if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
		return sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
	}
	if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
		$parts = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
		return trim( $parts[0] );
	}
	return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';
}

/**
 * Whether this IP may create another share link.
 *
 * @return bool
 */
function cc_cart_share_rate_limit_ok() {
	$ip  = cc_cart_share_client_ip();
	$key = 'cc_cart_share_rate_' . md5( $ip );
	$count = (int) get_transient( $key );
	return $count < CC_CART_SHARE_RATE_LIMIT;
}

/**
 * Increment share creation rate counter.
 */
function cc_cart_share_rate_limit_hit() {
	$ip  = cc_cart_share_client_ip();
	$key = 'cc_cart_share_rate_' . md5( $ip );
	$count = (int) get_transient( $key );
	set_transient( $key, $count + 1, HOUR_IN_SECONDS );
}

/**
 * Build cart snapshot from current session cart.
 *
 * @return array{items: array<int, array<string, mixed>>, skipped: int}
 */
function cc_cart_share_build_snapshot() {
	$items   = array();
	$skipped = 0;

	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return array(
			'items'   => array(),
			'skipped' => 0,
		);
	}

	foreach ( WC()->cart->get_cart() as $cart_item ) {
		if ( count( $items ) >= CC_CART_SHARE_MAX_ITEMS ) {
			$skipped++;
			continue;
		}

		$product_id   = isset( $cart_item['product_id'] ) ? (int) $cart_item['product_id'] : 0;
		$variation_id = isset( $cart_item['variation_id'] ) ? (int) $cart_item['variation_id'] : 0;
		$quantity     = isset( $cart_item['quantity'] ) ? (int) $cart_item['quantity'] : 0;

		if ( $product_id < 1 || $quantity < 1 ) {
			$skipped++;
			continue;
		}

		if ( function_exists( 'cc_is_quote_product' ) && cc_is_quote_product( $product_id ) ) {
			$skipped++;
			continue;
		}

		$product = wc_get_product( $variation_id ? $variation_id : $product_id );
		if ( ! $product || ! $product->is_purchasable() ) {
			$skipped++;
			continue;
		}

		$line = array(
			'product_id'   => $product_id,
			'variation_id' => $variation_id,
			'quantity'     => $quantity,
		);

		if ( ! empty( $cart_item['variation'] ) && is_array( $cart_item['variation'] ) ) {
			$line['variation'] = array_map( 'wc_clean', $cart_item['variation'] );
		}

		$items[] = $line;
	}

	return array(
		'items'   => $items,
		'skipped' => $skipped,
	);
}

/**
 * Create a shareable cart URL.
 *
 * @return string|WP_Error
 */
function cc_cart_share_create_url() {
	if ( ! cc_cart_share_rate_limit_ok() ) {
		return new WP_Error( 'rate_limit', __( 'Too many share links created. Please try again later.', 'consucorner' ) );
	}

	$snapshot = cc_cart_share_build_snapshot();
	if ( empty( $snapshot['items'] ) ) {
		return new WP_Error( 'empty_cart', __( 'Your cart is empty or has no shareable items.', 'consucorner' ) );
	}

	$token = wp_generate_password( 32, false, false );
	set_transient(
		'cc_cart_share_' . $token,
		array(
			'items'   => $snapshot['items'],
			'created' => time(),
		),
		CC_CART_SHARE_TTL
	);

	cc_cart_share_rate_limit_hit();

	$cart_url = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' );
	return add_query_arg( 'cc_share', $token, $cart_url );
}

/**
 * AJAX: create cart share link.
 */
function cc_ajax_create_cart_share() {
	check_ajax_referer( 'cc_cart_share', 'nonce' );

	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		wp_send_json_error( array( 'message' => __( 'Cart is not available.', 'consucorner' ) ) );
	}

	$url = cc_cart_share_create_url();
	if ( is_wp_error( $url ) ) {
		wp_send_json_error( array( 'message' => $url->get_error_message() ) );
	}

	wp_send_json_success(
		array(
			'url' => esc_url_raw( $url ),
		)
	);
}
add_action( 'wp_ajax_cc_create_cart_share', 'cc_ajax_create_cart_share' );
add_action( 'wp_ajax_nopriv_cc_create_cart_share', 'cc_ajax_create_cart_share' );

/**
 * Restore cart from share token.
 */
function cc_cart_share_maybe_restore() {
	if ( ! isset( $_GET['cc_share'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}

	if ( ! function_exists( 'is_cart' ) || ! is_cart() ) {
		return;
	}

	$token = sanitize_text_field( wp_unslash( $_GET['cc_share'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! preg_match( '/^[a-zA-Z0-9]{16,64}$/', $token ) ) {
		return;
	}

	$data = get_transient( 'cc_cart_share_' . $token );
	if ( ! is_array( $data ) || empty( $data['items'] ) || ! is_array( $data['items'] ) ) {
		if ( function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( __( 'This shared cart link has expired or is invalid.', 'consucorner' ), 'error' );
		}
		wp_safe_redirect( remove_query_arg( 'cc_share' ) );
		exit;
	}

	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return;
	}

	WC()->cart->empty_cart();

	$added   = 0;
	$skipped = 0;

	foreach ( $data['items'] as $line ) {
		$product_id   = isset( $line['product_id'] ) ? (int) $line['product_id'] : 0;
		$variation_id = isset( $line['variation_id'] ) ? (int) $line['variation_id'] : 0;
		$quantity     = isset( $line['quantity'] ) ? (int) $line['quantity'] : 0;
		$variation    = isset( $line['variation'] ) && is_array( $line['variation'] ) ? $line['variation'] : array();

		if ( $product_id < 1 || $quantity < 1 ) {
			$skipped++;
			continue;
		}

		if ( function_exists( 'cc_is_quote_product' ) && cc_is_quote_product( $product_id ) ) {
			$skipped++;
			continue;
		}

		$product = wc_get_product( $variation_id ? $variation_id : $product_id );
		if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
			$skipped++;
			continue;
		}

		$key = WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variation );
		if ( $key ) {
			$added++;
		} else {
			$skipped++;
		}
	}

	if ( function_exists( 'wc_add_notice' ) ) {
		if ( $added > 0 ) {
			wc_add_notice(
				sprintf(
					/* translators: %d: number of products */
					_n( '%d product was added to your cart from a shared link.', '%d products were added to your cart from a shared link.', $added, 'consucorner' ),
					$added
				),
				'success'
			);
		}
		if ( $skipped > 0 ) {
			wc_add_notice(
				__( 'Some items from the shared cart could not be added (unavailable or quote-only).', 'consucorner' ),
				'notice'
			);
		}
		if ( $added < 1 ) {
			wc_add_notice( __( 'No items could be restored from this shared cart link.', 'consucorner' ), 'error' );
		}
	}

	wp_safe_redirect( remove_query_arg( 'cc_share' ) );
	exit;
}
add_action( 'template_redirect', 'cc_cart_share_maybe_restore', 5 );

/**
 * Enqueue cart share UI assets on cart and checkout.
 */
function cc_cart_share_enqueue_assets() {
	$on_cart     = function_exists( 'is_cart' ) && is_cart();
	$on_checkout = function_exists( 'is_checkout' ) && is_checkout() && ( ! function_exists( 'is_wc_endpoint_url' ) || ! is_wc_endpoint_url( 'order-received' ) );

	if ( ! $on_cart && ! $on_checkout ) {
		return;
	}

	$theme_uri = get_template_directory_uri();
	$theme_dir = get_template_directory();

	wp_enqueue_script(
		'consucorner-cart-share',
		$theme_uri . '/assets/js/cart-share.js',
		array(),
		file_exists( $theme_dir . '/assets/js/cart-share.js' )
			? (string) filemtime( $theme_dir . '/assets/js/cart-share.js' )
			: _S_VERSION,
		true
	);

	wp_enqueue_style(
		'consucorner-cart-share',
		$theme_uri . '/assets/css/cart-share.css',
		array(),
		file_exists( $theme_dir . '/assets/css/cart-share.css' )
			? (string) filemtime( $theme_dir . '/assets/css/cart-share.css' )
			: _S_VERSION
	);

	wp_localize_script(
		'consucorner-cart-share',
		'ccCartShare',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'cc_cart_share' ),
			'action'  => 'cc_create_cart_share',
			'strings' => array(
				'title'       => __( 'Share your cart', 'consucorner' ),
				'description' => __( 'Send this link so someone else can load the same products in their cart.', 'consucorner' ),
				'copy'        => __( 'Copy link', 'consucorner' ),
				'copied'      => __( 'Copied!', 'consucorner' ),
				'share'       => __( 'Share', 'consucorner' ),
				'close'       => __( 'Close', 'consucorner' ),
				'loading'     => __( 'Creating link…', 'consucorner' ),
				'error'       => __( 'Could not create share link. Please try again.', 'consucorner' ),
			),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'cc_cart_share_enqueue_assets', 30 );

/**
 * Render cart share modal (cart + checkout).
 */
function cc_render_cart_share_modal() {
	$on_cart     = function_exists( 'is_cart' ) && is_cart();
	$on_checkout = function_exists( 'is_checkout' ) && is_checkout() && ( ! function_exists( 'is_wc_endpoint_url' ) || ! is_wc_endpoint_url( 'order-received' ) );

	if ( ! $on_cart && ! $on_checkout ) {
		return;
	}
	?>
	<div id="cc-cart-share-modal" class="cc-cart-share-modal" hidden aria-hidden="true">
		<div class="cc-cart-share-modal__backdrop" data-cc-cart-share-close></div>
		<div class="cc-cart-share-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="cc-cart-share-modal-title">
			<button type="button" class="cc-cart-share-modal__close" data-cc-cart-share-close aria-label="<?php esc_attr_e( 'Close', 'consucorner' ); ?>">&times;</button>
			<h2 id="cc-cart-share-modal-title" class="cc-cart-share-modal__title"><?php esc_html_e( 'Share your cart', 'consucorner' ); ?></h2>
			<p class="cc-cart-share-modal__desc"><?php esc_html_e( 'Send this link so someone else can load the same products in their cart.', 'consucorner' ); ?></p>
			<label class="screen-reader-text" for="cc-cart-share-url"><?php esc_html_e( 'Share link', 'consucorner' ); ?></label>
			<input type="text" id="cc-cart-share-url" class="cc-cart-share-modal__input" readonly value="" />
			<p class="cc-cart-share-modal__error" hidden></p>
			<div class="cc-cart-share-modal__actions">
				<button type="button" class="cc-cart-share-modal__copy" data-cc-cart-share-copy><?php esc_html_e( 'Copy link', 'consucorner' ); ?></button>
				<button type="button" class="cc-cart-share-modal__native" data-cc-cart-share-native hidden><?php esc_html_e( 'Share', 'consucorner' ); ?></button>
				<button type="button" class="cc-cart-share-modal__dismiss" data-cc-cart-share-close><?php esc_html_e( 'Close', 'consucorner' ); ?></button>
			</div>
		</div>
	</div>
	<?php
}
add_action( 'wp_footer', 'cc_render_cart_share_modal', 20 );
