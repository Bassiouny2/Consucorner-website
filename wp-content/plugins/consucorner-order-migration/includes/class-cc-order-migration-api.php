<?php
/**
 * WooCommerce REST API client for the source (live) site.
 *
 * @package ConsuCorner_Order_Migration
 */

defined( 'ABSPATH' ) || exit;

/**
 * Fetches orders from the old site.
 */
class CC_Order_Migration_API {

	/**
	 * Config array.
	 *
	 * @var array
	 */
	private $config;

	/**
	 * Constructor.
	 *
	 * @param array $config Migration config.
	 */
	public function __construct( array $config ) {
		$this->config = $config;
	}

	/**
	 * Build request headers.
	 *
	 * @return array
	 */
	private function headers() {
		$auth = base64_encode( $this->config['consumer_key'] . ':' . $this->config['consumer_secret'] );

		return array(
			'Authorization' => 'Basic ' . $auth,
			'Content-Type'  => 'application/json',
		);
	}

	/**
	 * GET request to source WooCommerce API.
	 *
	 * @param string $path   Path after /wp-json/wc/v3/.
	 * @param array  $query  Query args.
	 * @return array{body: array, headers: array, code: int}
	 * @throws RuntimeException On HTTP or JSON errors.
	 */
	public function get( $path, array $query = array() ) {
		$base = trailingslashit( $this->config['source_url'] ) . 'wp-json/wc/v3/' . ltrim( $path, '/' );
		$url  = add_query_arg( $query, $base );

		$response = wp_remote_get(
			$url,
			array(
				'headers' => $this->headers(),
				'timeout' => 120,
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new RuntimeException( $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 ) {
			$message = is_array( $body ) && isset( $body['message'] ) ? $body['message'] : wp_remote_retrieve_body( $response );
			throw new RuntimeException( sprintf( 'API error %d: %s', $code, $message ) );
		}

		return array(
			'body'    => is_array( $body ) ? $body : array(),
			'headers' => wp_remote_retrieve_headers( $response ),
			'code'    => $code,
		);
	}

	/**
	 * Fetch one page of orders.
	 *
	 * @param int $page Page number.
	 * @return array{orders: array, total_pages: int, total: int}
	 */
	public function fetch_orders_page( $page ) {
		$statuses = $this->config['statuses'];
		$query    = array(
			'per_page' => $this->config['per_page'],
			'page'     => max( 1, (int) $page ),
			'orderby'  => 'date',
			'order'    => 'asc',
		);

		if ( is_array( $statuses ) && 1 === count( $statuses ) && 'any' === $statuses[0] ) {
			$query['status'] = 'any';
		} elseif ( ! empty( $statuses ) ) {
			$query['status'] = implode( ',', array_map( 'sanitize_key', $statuses ) );
		}

		$result  = $this->get( 'orders', $query );
		$headers = $result['headers'];

		$total_pages = 1;
		$total       = count( $result['body'] );

		if ( is_object( $headers ) && method_exists( $headers, 'offsetGet' ) ) {
			if ( $headers->offsetExists( 'x-wp-totalpages' ) ) {
				$total_pages = (int) $headers->offsetGet( 'x-wp-totalpages' );
			}
			if ( $headers->offsetExists( 'x-wp-total' ) ) {
				$total = (int) $headers->offsetGet( 'x-wp-total' );
			}
		}

		return array(
			'orders'      => $result['body'],
			'total_pages' => max( 1, $total_pages ),
			'total'       => $total,
		);
	}

	/**
	 * Fetch a single order by ID (full detail).
	 *
	 * @param int $order_id Old order ID.
	 * @return array
	 */
	public function fetch_order( $order_id ) {
		$result = $this->get( 'orders/' . absint( $order_id ) );
		return $result['body'];
	}
}
