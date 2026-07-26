<?php
/**
 * Rank Math analytics guard.
 *
 * @package ConsuCorner_GTM
 */

defined( 'ABSPATH' ) || exit;

/**
 * Prevent duplicate GA4/gtag from Rank Math frontend.
 */
final class CC_GTM_RankMath {

	/**
	 * Register filter.
	 */
	public static function init() {
		if ( ! CC_GTM_Settings::get( 'rankmath_guard', true ) ) {
			return;
		}
		add_filter( 'pre_option_rank_math_google_analytic_options', array( __CLASS__, 'disable_frontend_gtag' ) );
	}

	/**
	 * @param mixed $value Option value.
	 * @return mixed
	 */
	public static function disable_frontend_gtag( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		$value['install_code'] = false;
		return $value;
	}

	/**
	 * Whether Rank Math would install analytics code (for dashboard).
	 *
	 * @return bool
	 */
	public static function is_frontend_analytics_enabled() {
		$value = get_option( 'rank_math_google_analytic_options', array() );
		if ( ! is_array( $value ) ) {
			return false;
		}
		return ! empty( $value['install_code'] );
	}
}
