<?php
/**
 * Google Tag Manager API v2 client.
 *
 * @package ConsuCorner_GTM
 */

defined( 'ABSPATH' ) || exit;

/**
 * GTM API wrapper.
 */
final class CC_GTM_API {

	const API_BASE = 'https://tagmanager.googleapis.com/tagmanager/v2/';

	/**
	 * @param string               $method HTTP method.
	 * @param string               $path API path (after v2/).
	 * @param array<string, mixed> $body Optional JSON body.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function request( $method, $path, array $body = array() ) {
		$token = CC_GTM_Google_Auth::get_valid_access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$url  = self::API_BASE . ltrim( $path, '/' );
		$args = array(
			'timeout' => 45,
			'method'  => strtoupper( $method ),
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
			),
		);
		if ( ! empty( $body ) && in_array( $args['method'], array( 'POST', 'PUT', 'PATCH' ), true ) ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			CC_GTM_Logger::log( 'api_error', $response->get_error_message(), array( 'path' => $path ) );
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code >= 400 ) {
			$message = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'GTM API request failed.', 'consucorner-gtm' );
			if ( 403 === $code ) {
				$message = __( 'The connected Google account does not have sufficient GTM permissions. Please use an account with Edit permission for this container.', 'consucorner-gtm' );
			} elseif ( 401 === $code ) {
				$message = __( 'Google connection expired. Please reconnect.', 'consucorner-gtm' );
			}
			CC_GTM_Logger::log( 'api_error', $message, array( 'path' => $path, 'code' => (string) $code ) );
			return new WP_Error( 'cc_gtm_api', $message, array( 'status' => $code ) );
		}
		return is_array( $data ) ? $data : array();
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	public static function list_accounts() {
		return self::request( 'GET', 'accounts' );
	}

	/**
	 * @param string $account_id Account ID.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function list_containers( $account_id ) {
		return self::request( 'GET', 'accounts/' . rawurlencode( $account_id ) . '/containers' );
	}

	/**
	 * @param string $parent accounts/X/containers/Y.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function list_workspaces( $parent ) {
		return self::request( 'GET', $parent . '/workspaces' );
	}

	/**
	 * @param string $parent Container parent path.
	 * @param string $name Workspace name.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function create_workspace( $parent, $name ) {
		return self::request(
			'POST',
			$parent . '/workspaces',
			array( 'name' => $name )
		);
	}

	/**
	 * @param string               $workspace_path Workspace path.
	 * @param array<string, mixed> $entity Entity body.
	 * @param string               $type variables|triggers|tags.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function create_entity( $workspace_path, array $entity, $type ) {
		return self::request( 'POST', $workspace_path . '/' . $type, $entity );
	}

	/**
	 * @param string $workspace_path Workspace path.
	 * @param string $type Entity type.
	 * @return array<int, array<string, mixed>>
	 */
	public static function list_entities( $workspace_path, $type ) {
		$result = self::request( 'GET', $workspace_path . '/' . $type );
		if ( is_wp_error( $result ) ) {
			return array();
		}
		// GTM API v2 uses singular collection keys: variable, trigger, tag.
		$singular_keys = array(
			'variables' => 'variable',
			'triggers'  => 'trigger',
			'tags'      => 'tag',
		);
		if ( isset( $singular_keys[ $type ] ) ) {
			$key = $singular_keys[ $type ];
			if ( isset( $result[ $key ] ) && is_array( $result[ $key ] ) ) {
				return $result[ $key ];
			}
		}
		if ( isset( $result[ $type ] ) && is_array( $result[ $type ] ) ) {
			return $result[ $type ];
		}
		return array();
	}

	/**
	 * Extract list items from GTM list* API responses (account, container, etc.).
	 *
	 * @param array<string, mixed>|WP_Error $result API response.
	 * @param string                        $singular Singular key (e.g. account).
	 * @param string                        $plural   Plural key fallback (e.g. accounts).
	 * @return array<int, array<string, mixed>>
	 */
	public static function extract_list( $result, $singular, $plural = '' ) {
		if ( is_wp_error( $result ) || ! is_array( $result ) ) {
			return array();
		}
		if ( isset( $result[ $singular ] ) && is_array( $result[ $singular ] ) ) {
			return $result[ $singular ];
		}
		if ( $plural && isset( $result[ $plural ] ) && is_array( $result[ $plural ] ) ) {
			return $result[ $plural ];
		}
		return array();
	}

	/**
	 * Normalize a container's usageContext array to a single label.
	 *
	 * @param array<string, mixed> $container Container payload.
	 * @return string web|server|amp|ios|android (best guess, defaults to web).
	 */
	public static function usage_context_label( array $container ) {
		$ctx = isset( $container['usageContext'] ) ? (array) $container['usageContext'] : array();
		$ctx = array_map( 'strtolower', array_map( 'strval', $ctx ) );
		foreach ( array( 'server', 'web', 'amp', 'ios', 'android' ) as $known ) {
			if ( in_array( $known, $ctx, true ) ) {
				return $known;
			}
		}
		return $ctx ? (string) $ctx[0] : 'web';
	}

	/**
	 * Find container by public GTM ID (GTM-XXXX) across accessible accounts.
	 *
	 * @param string $public_id Public container ID.
	 * @return array{ok:bool,message:string,account_id?:string,account_name?:string,container_api_id?:string,container_public_id?:string}|WP_Error
	 */
	public static function find_container_by_public_id( $public_id ) {
		$public_id = strtoupper( sanitize_text_field( $public_id ) );
		if ( ! CC_GTM_Settings::validate_gtm_id( $public_id ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'Invalid GTM container ID.', 'consucorner-gtm' ),
			);
		}

		$accounts = self::extract_list( self::list_accounts(), 'account', 'accounts' );
		foreach ( $accounts as $acc ) {
			$path = isset( $acc['path'] ) ? (string) $acc['path'] : '';
			if ( ! preg_match( '#accounts/([^/]+)#', $path, $m ) ) {
				continue;
			}
			$account_id   = $m[1];
			$account_name = isset( $acc['name'] ) ? (string) $acc['name'] : $account_id;
			$containers   = self::extract_list( self::list_containers( $account_id ), 'container', 'containers' );
			foreach ( $containers as $container ) {
				$pid = isset( $container['publicId'] ) ? strtoupper( (string) $container['publicId'] ) : '';
				if ( $pid === $public_id ) {
					return array(
						'ok'                    => true,
						'message'               => __( 'Container matched.', 'consucorner-gtm' ),
						'account_id'            => $account_id,
						'account_name'          => $account_name,
						'container_api_id'      => isset( $container['containerId'] ) ? (string) $container['containerId'] : '',
						'container_public_id'   => $pid,
						'usage_context'         => self::usage_context_label( $container ),
					);
				}
			}
		}

		return array(
			'ok'      => false,
			'message' => sprintf(
				/* translators: %s: GTM public container ID */
				__( 'Container %s was not found in any account accessible to the connected Google user.', 'consucorner-gtm' ),
				$public_id
			),
		);
	}

	/**
	 * @param string $workspace_path Workspace path.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function create_version( $workspace_path ) {
		return self::request(
			'POST',
			$workspace_path . ':create_version',
			array(
				'name'        => 'ConsuCorner Auto Setup ' . gmdate( 'Y-m-d H:i' ),
				'notes'       => 'Created by ConsuCorner GTM plugin. Test in Preview before publishing.',
				'publish'     => false,
			)
		);
	}

	/**
	 * @param string $path Container version path.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function publish_version( $path ) {
		return self::request( 'POST', $path . ':publish' );
	}

	/**
	 * Build container parent path from settings.
	 *
	 * @return string
	 */
	public static function get_container_parent_path() {
		$account_id   = (string) CC_GTM_Settings::get( 'gtm_account_id', '' );
		$container_id = (string) CC_GTM_Settings::get( 'gtm_container_api_id', '' );
		if ( ! $account_id || ! $container_id ) {
			return '';
		}
		return 'accounts/' . $account_id . '/containers/' . $container_id;
	}

	/**
	 * @return string
	 */
	public static function get_workspace_path() {
		$parent = self::get_container_parent_path();
		$ws     = (string) CC_GTM_Settings::get( 'gtm_workspace_id', '' );
		if ( ! $parent || ! $ws ) {
			return '';
		}
		return $parent . '/workspaces/' . $ws;
	}
}
