<?php
/**
 * Conflict guard for other Google Tag Manager plugins.
 *
 * The storefront must load exactly one GTM container. If another GTM plugin is
 * active it injects a second container and a duplicate ecommerce dataLayer,
 * which slows the page down and double-counts every event. This guard detects
 * those plugins, silences their frontend output automatically (so the site is
 * fast and accurate out of the box), and lets the admin deactivate them for a
 * clean setup.
 *
 * @package ConsuCorner_GTM
 */

defined( 'ABSPATH' ) || exit;

/**
 * Detect and neutralize conflicting GTM plugins.
 */
final class CC_GTM_Conflicts {

	/**
	 * Known conflicting plugins: plugin file => label.
	 *
	 * @return array<string, string>
	 */
	public static function known() {
		return array(
			'duracelltomi-google-tag-manager/duracelltomi-google-tag-manager-for-wordpress.php' => 'GTM4WP (Google Tag Manager for WordPress)',
			'gtm-kit/gtm-kit.php'                          => 'GTM Kit',
			'metronet-tag-manager/metronet-tag-manager.php' => 'Google Tag Manager (Metronet)',
			'google-tag-manager-for-wordpress/google-tag-manager-for-wordpress.php' => 'Google Tag Manager for WordPress',
		);
	}

	/**
	 * Register frontend suppression when the guard is enabled.
	 */
	public static function init() {
		if ( ! CC_GTM_Settings::get( 'conflict_guard', true ) ) {
			return;
		}
		self::suppress_gtm4wp();
	}

	/**
	 * Ensure plugin API helpers are loaded.
	 */
	private static function load_plugin_api() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
	}

	/**
	 * @param string $file Plugin file.
	 * @return bool
	 */
	public static function is_active( $file ) {
		self::load_plugin_api();
		return is_plugin_active( $file );
	}

	/**
	 * Active conflicting plugins.
	 *
	 * @return array<int, array{file:string,label:string}>
	 */
	public static function get_active_conflicts() {
		$out = array();
		foreach ( self::known() as $file => $label ) {
			if ( self::is_active( $file ) ) {
				$out[] = array(
					'file'  => $file,
					'label' => $label,
				);
			}
		}
		return $out;
	}

	/**
	 * @return bool
	 */
	public static function has_conflicts() {
		return ! empty( self::get_active_conflicts() );
	}

	/**
	 * Whether GTM4WP specifically is active (it is the one we can auto-silence).
	 *
	 * @return bool
	 */
	public static function is_gtm4wp_active() {
		return self::is_active( 'duracelltomi-google-tag-manager/duracelltomi-google-tag-manager-for-wordpress.php' );
	}

	/**
	 * Silence GTM4WP frontend output (container snippet + dataLayer) so only our
	 * single container runs. The plugin stays active to avoid breaking anything
	 * that references it, but it stops emitting a second container.
	 */
	private static function suppress_gtm4wp() {
		if ( is_admin() || ! self::is_gtm4wp_active() ) {
			return;
		}
		add_filter( 'gtm4wp_get_the_gtm_tag', '__return_empty_string', 99 );
		add_filter( 'gtm4wp_get_the_gtm_tag_body', '__return_empty_string', 99 );
		add_filter( 'gtm4wp_compile_datalayer', array( __CLASS__, 'empty_datalayer' ), 99 );
	}

	/**
	 * Force GTM4WP dataLayer to empty.
	 *
	 * @param mixed $data_layer Existing dataLayer.
	 * @return array<string, mixed>
	 */
	public static function empty_datalayer( $data_layer ) {
		return array();
	}

	/**
	 * Deactivate one or more conflicting plugins.
	 *
	 * @param array<int, string> $files Plugin files to deactivate.
	 * @return array{ok:bool,deactivated:array<int,string>,message:string}
	 */
	public static function deactivate( array $files ) {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return array(
				'ok'          => false,
				'deactivated' => array(),
				'message'     => __( 'Permission denied.', 'consucorner-gtm' ),
			);
		}
		self::load_plugin_api();
		$known       = self::known();
		$deactivated = array();
		foreach ( $files as $file ) {
			$file = (string) $file;
			if ( ! isset( $known[ $file ] ) || ! is_plugin_active( $file ) ) {
				continue;
			}
			deactivate_plugins( $file );
			$deactivated[] = $known[ $file ];
			CC_GTM_Logger::log( 'conflict_deactivated', $known[ $file ], array( 'plugin' => $file ) );
		}
		if ( empty( $deactivated ) ) {
			return array(
				'ok'          => false,
				'deactivated' => array(),
				'message'     => __( 'No conflicting plugins were deactivated.', 'consucorner-gtm' ),
			);
		}
		return array(
			'ok'          => true,
			'deactivated' => $deactivated,
			'message'     => sprintf(
				/* translators: %s: comma-separated plugin names */
				__( 'Deactivated: %s', 'consucorner-gtm' ),
				implode( ', ', $deactivated )
			),
		);
	}
}
