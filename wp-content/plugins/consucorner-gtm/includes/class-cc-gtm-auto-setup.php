<?php
/**
 * GTM workspace auto-setup via API.
 *
 * @package ConsuCorner_GTM
 */

defined( 'ABSPATH' ) || exit;

/**
 * Auto setup orchestrator.
 */
final class CC_GTM_Auto_Setup {

	/**
	 * Data layer variable definitions: name => dataLayer key.
	 *
	 * @return array<string, string>
	 */
	public static function variable_definitions() {
		return array(
			'DLV - pageType'                 => 'pageType',
			'DLV - loggedIn'                 => 'loggedIn',
			'DLV - cartQuantity'             => 'cartQuantity',
			'DLV - currency'                 => 'currency',
			'DLV - productCategory'          => 'productCategory',
			'DLV - specialty'                => 'specialty',
			'DLV - procedure'                => 'procedure',
			'DLV - ecommerce'                => 'ecommerce',
			'DLV - ecommerce.items'          => 'ecommerce.items',
			'DLV - ecommerce.value'          => 'ecommerce.value',
			'DLV - ecommerce.currency'       => 'ecommerce.currency',
			'DLV - ecommerce.transaction_id' => 'ecommerce.transaction_id',
			'DLV - ecommerce.tax'            => 'ecommerce.tax',
			'DLV - ecommerce.shipping'       => 'ecommerce.shipping',
			'DLV - ecommerce.coupon'         => 'ecommerce.coupon',
			'DLV - ecommerce.payment_type'   => 'ecommerce.payment_type',
			'DLV - search_term'              => 'search_term',
			'DLV - filters'                  => 'filters',
			'DLV - list_id'                  => 'ecommerce.item_list_id',
			'DLV - list_name'                => 'ecommerce.item_list_name',
		);
	}

	/**
	 * Custom event triggers: name => event name.
	 *
	 * @return array<string, string>
	 */
	public static function trigger_definitions() {
		return array(
			'CE - page_context'     => 'page_context',
			'CE - view_item'        => 'view_item',
			'CE - view_item_list'   => 'view_item_list',
			'CE - select_item'      => 'select_item',
			'CE - add_to_cart'      => 'add_to_cart',
			'CE - view_cart'        => 'view_cart',
			'CE - begin_checkout'   => 'begin_checkout',
			'CE - purchase'         => 'purchase',
			'CE - search'           => 'search',
			'CE - filter_products'  => 'filter_products',
			'CE - login'            => 'login',
			'CE - sign_up'          => 'sign_up',
			'CE - add_to_wishlist'  => 'add_to_wishlist',
		);
	}

	/**
	 * @param string $name Variable name.
	 * @param string $key Data layer key.
	 * @return array<string, mixed>
	 */
	public static function build_dlv_payload( $name, $key ) {
		return array(
			'name'      => $name,
			'type'      => 'v',
			'parameter' => array(
				array(
					'type'  => 'integer',
					'key'   => 'dataLayerVersion',
					'value' => '2',
				),
				array(
					'type'  => 'boolean',
					'key'   => 'setDefaultValue',
					'value' => 'false',
				),
				array(
					'type'  => 'template',
					'key'   => 'name',
					'value' => $key,
				),
			),
		);
	}

	/**
	 * @param string $name Trigger name.
	 * @param string $event Event name.
	 * @return array<string, mixed>
	 */
	public static function build_ce_trigger_payload( $name, $event ) {
		return array(
			'name' => $name,
			'type' => 'customEvent',
			'customEventFilter' => array(
				array(
					'type'      => 'equals',
					'parameter' => array(
						array( 'type' => 'template', 'key' => 'arg0', 'value' => '{{_event}}' ),
						array( 'type' => 'template', 'key' => 'arg1', 'value' => $event ),
					),
				),
			),
		);
	}

	/**
	 * All Pages trigger payload.
	 *
	 * @return array<string, mixed>
	 */
	public static function build_all_pages_trigger() {
		return array(
			'name' => 'All Pages',
			'type' => 'pageview',
		);
	}

	/**
	 * @param string $workspace_path Workspace API path.
	 * @return array<string, mixed>
	 */
	/**
	 * Ensure GTM account/container are saved (auto-match by public ID when missing).
	 *
	 * @return array{ok:bool,message:string,skipped?:bool}
	 */
	public static function ensure_container_selected() {
		$account_id   = (string) CC_GTM_Settings::get( 'gtm_account_id', '' );
		$container_id = (string) CC_GTM_Settings::get( 'gtm_container_api_id', '' );
		if ( $account_id && $container_id ) {
			return array(
				'ok'      => true,
				'message' => __( 'Container already selected.', 'consucorner-gtm' ),
				'skipped' => true,
			);
		}

		if ( ! CC_GTM_Google_Auth::is_connected() ) {
			return array(
				'ok'      => false,
				'message' => __( 'Connect Google before selecting a container.', 'consucorner-gtm' ),
			);
		}

		$public_id = (string) CC_GTM_Settings::get( 'gtm_container_public_id', '' );
		if ( ! $public_id ) {
			$public_id = CC_GTM_Settings::get_gtm_container_id();
		}

		$match = CC_GTM_API::find_container_by_public_id( $public_id );
		if ( empty( $match['ok'] ) ) {
			return array(
				'ok'      => false,
				'message' => $match['message'] ?? __( 'Could not find GTM container.', 'consucorner-gtm' ),
			);
		}

		CC_GTM_Settings::update(
			array(
				'gtm_account_id'          => (string) ( $match['account_id'] ?? '' ),
				'gtm_account_name'        => (string) ( $match['account_name'] ?? '' ),
				'gtm_container_api_id'    => (string) ( $match['container_api_id'] ?? '' ),
				'gtm_container_public_id' => (string) ( $match['container_public_id'] ?? $public_id ),
				'gtm_container_usage_context' => (string) ( $match['usage_context'] ?? 'web' ),
			)
		);
		CC_GTM_Logger::log( 'container_auto_selected', (string) ( $match['container_public_id'] ?? $public_id ) );

		return array(
			'ok'      => true,
			'message' => __( 'GTM container selected automatically.', 'consucorner-gtm' ),
		);
	}

	/**
	 * One-click flow: container → workspace → variables/triggers/tags.
	 *
	 * @param bool $publish Optional publish after version create.
	 * @return array<string, mixed>
	 */
	public static function run_one_click_setup( $publish = false ) {
		$log = array();

		if ( ! CC_GTM_Settings::get_gtm_container_id() ) {
			return array(
				'ok'      => false,
				'message' => __( 'Set a GTM Container ID in Settings first.', 'consucorner-gtm' ),
				'steps'   => $log,
			);
		}

		if ( ! CC_GTM_Google_Auth::is_configured() ) {
			return array(
				'ok'      => false,
				'message' => __( 'Add Google OAuth Client ID and Secret in Settings.', 'consucorner-gtm' ),
				'steps'   => $log,
			);
		}

		if ( ! CC_GTM_Google_Auth::is_connected() ) {
			return array(
				'ok'      => false,
				'message' => __( 'Connect your Google account before running setup.', 'consucorner-gtm' ),
				'steps'   => $log,
			);
		}

		$container = self::ensure_container_selected();
		$log['container'] = $container;
		if ( empty( $container['ok'] ) ) {
			return array(
				'ok'      => false,
				'message' => $container['message'],
				'steps'   => $log,
			);
		}

		if ( ! CC_GTM_API::get_workspace_path() ) {
			$workspace = self::create_workspace();
			$log['workspace'] = $workspace;
			if ( empty( $workspace['ok'] ) ) {
				return array(
					'ok'      => false,
					'message' => $workspace['message'] ?? __( 'Workspace creation failed.', 'consucorner-gtm' ),
					'steps'   => $log,
				);
			}
		} else {
			$log['workspace'] = array(
				'ok'      => true,
				'message' => __( 'Using existing workspace.', 'consucorner-gtm' ),
				'skipped' => true,
			);
		}

		$setup = self::run_full_setup( $publish );
		$log['setup'] = $setup;

		return array(
			'ok'      => ! empty( $setup['ok'] ),
			'message' => $setup['message'] ?? '',
			'summary' => $setup['summary'] ?? array(),
			'steps'   => $log,
		);
	}

	/**
	 * Whether tags were created in the last setup run.
	 *
	 * @return bool
	 */
	public static function has_tags_from_last_setup() {
		$summary = CC_GTM_Settings::get( 'last_setup_summary', array() );
		if ( ! is_array( $summary ) || empty( $summary ) ) {
			return false;
		}
		foreach ( array( 'variables', 'triggers', 'tags' ) as $section ) {
			if ( empty( $summary[ $section ] ) || ! is_array( $summary[ $section ] ) ) {
				continue;
			}
			$block = $summary[ $section ];
			if ( ! empty( $block['created'] ) || ! empty( $block['skipped'] ) ) {
				return true;
			}
		}
		return false;
	}

	public static function create_workspace( $workspace_path = '' ) {
		$parent = CC_GTM_API::get_container_parent_path();
		if ( ! $parent ) {
			return array(
				'ok'      => false,
				'message' => __( 'Select a GTM account and container first.', 'consucorner-gtm' ),
			);
		}
		$name   = 'ConsuCorner Auto Setup - ' . gmdate( 'Y-m-d' );
		$result = CC_GTM_API::create_workspace( $parent, $name );
		if ( is_wp_error( $result ) ) {
			return array( 'ok' => false, 'message' => $result->get_error_message() );
		}
		if ( empty( $result['workspaceId'] ) ) {
			return array( 'ok' => false, 'message' => __( 'Workspace creation failed.', 'consucorner-gtm' ) );
		}
		CC_GTM_Settings::update(
			array(
				'gtm_workspace_id'   => (string) $result['workspaceId'],
				'gtm_workspace_name' => $name,
			)
		);
		CC_GTM_Logger::log( 'workspace_created', $name, array( 'workspace_id' => (string) $result['workspaceId'] ) );
		return array(
			'ok'      => true,
			'message' => __( 'Workspace created.', 'consucorner-gtm' ),
			'name'    => $name,
		);
	}

	/**
	 * Run full setup in current workspace.
	 *
	 * @param bool $publish Whether to publish (requires explicit admin confirmation elsewhere).
	 * @return array<string, mixed>
	 */
	public static function run_full_setup( $publish = false ) {
		$ws = CC_GTM_API::get_workspace_path();
		if ( ! $ws ) {
			return array(
				'ok'      => false,
				'message' => __( 'Create or select a workspace first.', 'consucorner-gtm' ),
			);
		}

		$summary = array(
			'variables' => array( 'created' => array(), 'skipped' => array(), 'errors' => array() ),
			'triggers'  => array( 'created' => array(), 'skipped' => array(), 'errors' => array() ),
			'tags'      => array( 'created' => array(), 'skipped' => array(), 'errors' => array() ),
		);

		$existing_vars = self::index_by_name( CC_GTM_API::list_entities( $ws, 'variables' ) );
		foreach ( self::variable_definitions() as $name => $key ) {
			if ( isset( $existing_vars[ $name ] ) ) {
				$summary['variables']['skipped'][] = $name;
				CC_GTM_Logger::log( 'variable_skipped', $name );
				continue;
			}
			$res = CC_GTM_API::create_entity( $ws, self::build_dlv_payload( $name, $key ), 'variables' );
			if ( is_wp_error( $res ) ) {
				$summary['variables']['errors'][ $name ] = $res->get_error_message();
			} else {
				$summary['variables']['created'][] = $name;
				CC_GTM_Logger::log( 'variable_created', $name );
			}
		}

		$existing_tr = self::index_by_name( CC_GTM_API::list_entities( $ws, 'triggers' ) );
		$trigger_ids = array();
		foreach ( self::trigger_definitions() as $name => $event ) {
			if ( isset( $existing_tr[ $name ] ) ) {
				$summary['triggers']['skipped'][] = $name;
				$trigger_ids[ $name ] = (string) $existing_tr[ $name ]['triggerId'];
				continue;
			}
			$res = CC_GTM_API::create_entity( $ws, self::build_ce_trigger_payload( $name, $event ), 'triggers' );
			if ( is_wp_error( $res ) ) {
				$summary['triggers']['errors'][ $name ] = $res->get_error_message();
			} else {
				$summary['triggers']['created'][] = $name;
				$trigger_ids[ $name ] = (string) $res['triggerId'];
				CC_GTM_Logger::log( 'trigger_created', $name );
			}
		}

		// Ensure All Pages trigger for config/remarketing.
		$all_pages_id = '';
		foreach ( $existing_tr as $name => $tr ) {
			if ( 'All Pages' === $name || ( isset( $tr['type'] ) && 'pageview' === $tr['type'] ) ) {
				$all_pages_id = (string) $tr['triggerId'];
				break;
			}
		}
		if ( ! $all_pages_id ) {
			$res = CC_GTM_API::create_entity( $ws, self::build_all_pages_trigger(), 'triggers' );
			if ( ! is_wp_error( $res ) && ! empty( $res['triggerId'] ) ) {
				$all_pages_id = (string) $res['triggerId'];
				$summary['triggers']['created'][] = 'All Pages';
			}
		}

		// Refresh trigger map after creates.
		$existing_tr = self::index_by_name( CC_GTM_API::list_entities( $ws, 'triggers' ) );
		foreach ( self::trigger_definitions() as $name => $event ) {
			if ( isset( $existing_tr[ $name ]['triggerId'] ) ) {
				$trigger_ids[ $name ] = (string) $existing_tr[ $name ]['triggerId'];
			}
		}

		$existing_tags = self::index_by_name( CC_GTM_API::list_entities( $ws, 'tags' ) );
		self::create_ga4_tags( $ws, $trigger_ids, $all_pages_id, $existing_tags, $summary );
		self::create_meta_tags( $ws, $trigger_ids, $all_pages_id, $existing_tags, $summary );
		self::create_ads_tags( $ws, $trigger_ids, $all_pages_id, $existing_tags, $summary );

		$version = CC_GTM_API::create_version( $ws );
		if ( is_wp_error( $version ) ) {
			$summary['version_error'] = $version->get_error_message();
		} else {
			$summary['version'] = $version;
			CC_GTM_Logger::log( 'version_created', __( 'GTM container version created.', 'consucorner-gtm' ) );
		}

		if ( $publish && ! empty( $version['containerVersion']['path'] ) ) {
			$pub = CC_GTM_API::publish_version( $version['containerVersion']['path'] );
			if ( is_wp_error( $pub ) ) {
				$summary['publish_error'] = $pub->get_error_message();
				CC_GTM_Logger::log( 'publish_failed', $pub->get_error_message() );
			} else {
				$summary['published'] = true;
				CC_GTM_Logger::log( 'publish_success', __( 'GTM container published.', 'consucorner-gtm' ) );
			}
		}

		CC_GTM_Settings::update( array( 'last_setup_summary' => $summary ) );

		return array(
			'ok'      => true,
			'message' => __( 'GTM setup created successfully. Please open GTM Preview Mode and test before publishing.', 'consucorner-gtm' ),
			'summary' => $summary,
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $entities Entities.
	 * @return array<string, array<string, mixed>>
	 */
	private static function index_by_name( array $entities ) {
		$out = array();
		foreach ( $entities as $entity ) {
			if ( ! empty( $entity['name'] ) ) {
				$out[ $entity['name'] ] = $entity;
			}
		}
		return $out;
	}

	/**
	 * @param string                             $ws Workspace path.
	 * @param array<string, string>              $trigger_ids Trigger IDs by name.
	 * @param string                             $all_pages_id All pages trigger ID.
	 * @param array<string, array<string,mixed>> $existing_tags Existing tags.
	 * @param array<string, mixed>               $summary Summary ref.
	 */
	private static function create_ga4_tags( $ws, $trigger_ids, $all_pages_id, $existing_tags, &$summary ) {
		if ( ! CC_GTM_Settings::get( 'ga4_enabled', false ) ) {
			return;
		}
		$mid = (string) CC_GTM_Settings::get( 'ga4_measurement_id', '' );
		if ( ! CC_GTM_Settings::validate_ga4_id( $mid ) ) {
			return;
		}

		$tag_name = 'GA4 - Google Tag';
		if ( ! isset( $existing_tags[ $tag_name ] ) && $all_pages_id ) {
			$config_params = array(
				array( 'type' => 'template', 'key' => 'tagId', 'value' => $mid ),
			);

			// Route GA4 through the server-side GTM container when configured.
			$server_url = CC_GTM_Settings::get_server_container_url();
			if ( $server_url ) {
				$config_params[] = array(
					'type' => 'list',
					'key'  => 'configSettingsTable',
					'list' => array(
						array(
							'type' => 'map',
							'map'  => array(
								array( 'type' => 'template', 'key' => 'parameter', 'value' => 'server_container_url' ),
								array( 'type' => 'template', 'key' => 'parameterValue', 'value' => $server_url ),
							),
						),
					),
				);
			}

			$res = CC_GTM_API::create_entity(
				$ws,
				array(
					'name'              => $tag_name,
					'type'              => 'googtag',
					'parameter'         => $config_params,
					'firingTriggerId'   => array( $all_pages_id ),
				),
				'tags'
			);
			if ( is_wp_error( $res ) ) {
				$summary['tags']['errors'][ $tag_name ] = $res->get_error_message();
			} else {
				$summary['tags']['created'][] = $tag_name;
				$existing_tags[ $tag_name ] = $res;
				if ( $server_url ) {
					CC_GTM_Logger::log( 'ga4_server_routing', $server_url );
				}
			}
		} else {
			$summary['tags']['skipped'][] = $tag_name;
		}

		$events = array(
			'view_item',
			'view_item_list',
			'select_item',
			'add_to_cart',
			'view_cart',
			'begin_checkout',
			'purchase',
			'search',
			'login',
			'sign_up',
			'add_to_wishlist',
		);
		foreach ( $events as $event ) {
			$tag_name = 'GA4 - ' . $event;
			$ce       = 'CE - ' . $event;
			if ( isset( $existing_tags[ $tag_name ] ) ) {
				$summary['tags']['skipped'][] = $tag_name;
				continue;
			}
			if ( empty( $trigger_ids[ $ce ] ) ) {
				continue;
			}
			$params = array(
				array( 'type' => 'template', 'key' => 'eventName', 'value' => $event ),
				array( 'type' => 'boolean', 'key' => 'sendEcommerceData', 'value' => 'true' ),
			);
			// Pull ecommerce data straight from the dataLayer for commerce events.
			if ( in_array( $event, array( 'view_item', 'add_to_cart', 'view_cart', 'begin_checkout', 'purchase', 'view_item_list', 'select_item' ), true ) ) {
				$params[] = array( 'type' => 'template', 'key' => 'getEcommerceDataFrom', 'value' => 'dataLayer' );
			}
			$res = CC_GTM_API::create_entity(
				$ws,
				array(
					'name'            => $tag_name,
					'type'            => 'gaawe',
					'parameter'       => $params,
					'firingTriggerId' => array( $trigger_ids[ $ce ] ),
				),
				'tags'
			);
			if ( is_wp_error( $res ) ) {
				$summary['tags']['errors'][ $tag_name ] = $res->get_error_message();
			} else {
				$summary['tags']['created'][] = $tag_name;
				CC_GTM_Logger::log( 'tag_created', $tag_name );
			}
		}
	}

	/**
	 * Meta Pixel custom HTML tags.
	 *
	 * @param string                             $ws Workspace.
	 * @param array<string, string>              $trigger_ids Triggers.
	 * @param string                             $all_pages_id All pages.
	 * @param array<string, array<string,mixed>> $existing_tags Tags.
	 * @param array<string, mixed>               $summary Summary.
	 */
	private static function create_meta_tags( $ws, $trigger_ids, $all_pages_id, $existing_tags, &$summary ) {
		if ( ! CC_GTM_Settings::get( 'meta_enabled', false ) ) {
			return;
		}
		$pixel = (string) CC_GTM_Settings::get( 'meta_pixel_id', '' );
		if ( ! CC_GTM_Settings::validate_meta_pixel_id( $pixel ) ) {
			return;
		}

		$base_name = 'Meta - Base Pixel';
		if ( ! isset( $existing_tags[ $base_name ] ) && $all_pages_id ) {
			$html = "<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','" . esc_js( $pixel ) . "');fbq('track','PageView');</script>";
			$res  = CC_GTM_API::create_entity(
				$ws,
				array(
					'name'            => $base_name,
					'type'            => 'html',
					'parameter'       => array(
						array( 'type' => 'template', 'key' => 'html', 'value' => $html ),
					),
					'firingTriggerId' => array( $all_pages_id ),
				),
				'tags'
			);
			if ( is_wp_error( $res ) ) {
				$summary['tags']['errors'][ $base_name ] = $res->get_error_message();
			} else {
				$summary['tags']['created'][] = $base_name;
			}
		}

		$map = array(
			'Meta - ViewContent'           => array( 'view_item', "fbq('track','ViewContent',{value:{{DLV - ecommerce.value}},currency:'{{DLV - ecommerce.currency}}',content_type:'product'});" ),
			'Meta - AddToCart'             => array( 'add_to_cart', "fbq('track','AddToCart',{value:{{DLV - ecommerce.value}},currency:'{{DLV - ecommerce.currency}}',content_type:'product'});" ),
			'Meta - InitiateCheckout'      => array( 'begin_checkout', "fbq('track','InitiateCheckout',{value:{{DLV - ecommerce.value}},currency:'{{DLV - ecommerce.currency}}'});" ),
			'Meta - Purchase'              => array( 'purchase', "fbq('track','Purchase',{value:{{DLV - ecommerce.value}},currency:'{{DLV - ecommerce.currency}}'});" ),
			'Meta - Search'                => array( 'search', "fbq('track','Search',{search_string:'{{DLV - search_term}}'});" ),
			'Meta - CompleteRegistration'  => array( 'sign_up', "fbq('track','CompleteRegistration');" ),
		);
		foreach ( $map as $tag_name => $pair ) {
			list( $event, $code ) = $pair;
			$ce = 'CE - ' . $event;
			if ( isset( $existing_tags[ $tag_name ] ) || empty( $trigger_ids[ $ce ] ) ) {
				$summary['tags']['skipped'][] = $tag_name;
				continue;
			}
			$html = '<script>' . $code . '</script>';
			$res  = CC_GTM_API::create_entity(
				$ws,
				array(
					'name'            => $tag_name,
					'type'            => 'html',
					'parameter'       => array(
						array( 'type' => 'template', 'key' => 'html', 'value' => $html ),
					),
					'firingTriggerId' => array( $trigger_ids[ $ce ] ),
				),
				'tags'
			);
			if ( is_wp_error( $res ) ) {
				$summary['tags']['errors'][ $tag_name ] = $res->get_error_message();
			} else {
				$summary['tags']['created'][] = $tag_name;
			}
		}
	}

	/**
	 * Google Ads tags (simplified conversion linker + conversion tags).
	 *
	 * @param string                             $ws Workspace.
	 * @param array<string, string>              $trigger_ids Triggers.
	 * @param string                             $all_pages_id All pages.
	 * @param array<string, array<string,mixed>> $existing_tags Tags.
	 * @param array<string, mixed>               $summary Summary.
	 */
	private static function create_ads_tags( $ws, $trigger_ids, $all_pages_id, $existing_tags, &$summary ) {
		if ( ! CC_GTM_Settings::get( 'google_ads_enabled', false ) ) {
			return;
		}
		$conv_id = (string) CC_GTM_Settings::get( 'google_ads_conversion_id', '' );
		if ( ! CC_GTM_Settings::validate_ads_id( $conv_id ) ) {
			return;
		}
		$purchase_label = (string) CC_GTM_Settings::get( 'google_ads_purchase_label', '' );
		$atc_label      = (string) CC_GTM_Settings::get( 'google_ads_atc_label', '' );

		$remarketing = 'Google Ads - Remarketing';
		if ( ! isset( $existing_tags[ $remarketing ] ) && $all_pages_id ) {
			$res = CC_GTM_API::create_entity(
				$ws,
				array(
					'name'            => $remarketing,
					'type'            => 'sp',
					'parameter'       => array(
						array( 'type' => 'boolean', 'key' => 'enableConversionLinker', 'value' => 'true' ),
						array( 'type' => 'template', 'key' => 'conversionId', 'value' => $conv_id ),
					),
					'firingTriggerId' => array( $all_pages_id ),
				),
				'tags'
			);
			if ( is_wp_error( $res ) ) {
				$summary['tags']['errors'][ $remarketing ] = $res->get_error_message();
			} else {
				$summary['tags']['created'][] = $remarketing;
			}
		}

		if ( $purchase_label && ! empty( $trigger_ids['CE - purchase'] ) ) {
			$name = 'Google Ads - Purchase Conversion';
			if ( ! isset( $existing_tags[ $name ] ) ) {
				$res = CC_GTM_API::create_entity(
					$ws,
					array(
						'name'            => $name,
						'type'            => 'awct',
						'parameter'       => array(
							array( 'type' => 'template', 'key' => 'conversionId', 'value' => $conv_id ),
							array( 'type' => 'template', 'key' => 'conversionLabel', 'value' => $purchase_label ),
							array( 'type' => 'template', 'key' => 'conversionValue', 'value' => '{{DLV - ecommerce.value}}' ),
							array( 'type' => 'template', 'key' => 'currencyCode', 'value' => '{{DLV - ecommerce.currency}}' ),
							array( 'type' => 'template', 'key' => 'orderId', 'value' => '{{DLV - ecommerce.transaction_id}}' ),
						),
						'firingTriggerId' => array( $trigger_ids['CE - purchase'] ),
					),
					'tags'
				);
				if ( is_wp_error( $res ) ) {
					$summary['tags']['errors'][ $name ] = $res->get_error_message();
				} else {
					$summary['tags']['created'][] = $name;
				}
			}
		}

		if ( $atc_label && ! empty( $trigger_ids['CE - add_to_cart'] ) ) {
			$name = 'Google Ads - Add To Cart Conversion';
			if ( ! isset( $existing_tags[ $name ] ) ) {
				$res = CC_GTM_API::create_entity(
					$ws,
					array(
						'name'            => $name,
						'type'            => 'awct',
						'parameter'       => array(
							array( 'type' => 'template', 'key' => 'conversionId', 'value' => $conv_id ),
							array( 'type' => 'template', 'key' => 'conversionLabel', 'value' => $atc_label ),
							array( 'type' => 'template', 'key' => 'conversionValue', 'value' => '{{DLV - ecommerce.value}}' ),
							array( 'type' => 'template', 'key' => 'currencyCode', 'value' => '{{DLV - ecommerce.currency}}' ),
						),
						'firingTriggerId' => array( $trigger_ids['CE - add_to_cart'] ),
					),
					'tags'
				);
				if ( is_wp_error( $res ) ) {
					$summary['tags']['errors'][ $name ] = $res->get_error_message();
				} else {
					$summary['tags']['created'][] = $name;
				}
			}
		}
	}

	/**
	 * Manual checklist for export.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_manual_checklist() {
		return array(
			'variables' => array_keys( self::variable_definitions() ),
			'triggers'  => array_keys( self::trigger_definitions() ),
			'ga4_tags'  => array_merge( array( 'GA4 - Google Tag' ), array_map(
				function ( $e ) {
					return 'GA4 - ' . $e;
				},
				array( 'view_item', 'view_item_list', 'select_item', 'add_to_cart', 'view_cart', 'begin_checkout', 'purchase', 'search', 'login', 'sign_up', 'add_to_wishlist' )
			) ),
			'meta_tags' => array( 'Meta - Base Pixel', 'Meta - ViewContent', 'Meta - AddToCart', 'Meta - InitiateCheckout', 'Meta - Purchase', 'Meta - Search', 'Meta - CompleteRegistration' ),
			'ads_tags'  => array( 'Google Ads - Remarketing', 'Google Ads - Purchase Conversion', 'Google Ads - Add To Cart Conversion' ),
		);
	}

	/**
	 * Minimal container template JSON for manual import reference.
	 *
	 * @return string
	 */
	public static function export_template_json() {
		$checklist = self::get_manual_checklist();
		return wp_json_encode( $checklist, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	}
}
