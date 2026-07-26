<?php


namespace SMSGateway;


class Enablex {

	public static function sendSMS( $gateway_fields, $mobile, $message, $test_call ) {
		return self::process_sms( $gateway_fields, $mobile, $message, $test_call );
	}

	public static function process_sms( $gateway_fields, $mobile, $message, $test_call ) {
		$access_token = $gateway_fields['access_token'];


		$message = iconv( 'UTF-8', 'ISO-8859-15', $message );

		$template_ids = array( 'template_id' );
		$params_values = array();


		if ( defined( 'DIGITS_OTP' ) ) {
			$otp = constant( 'DIGITS_OTP' );
			$params_values = digits_get_wa_gateway_templates( $message, $otp );
		}

		if ( isset( $gateway_fields['template-name'] ) ) {
			$template = $gateway_fields;
		} else {
			$msg = wpn_parse_message_template( $message, $template_ids );
			$template = $msg['template'];
			$params_values = $msg['params'];
		}

		$params = array();

		if ( ! empty( $params_values ) ) {
			ksort( $params_values );
			foreach ( $params_values as $params_value_key => $params_value ) {
				$params[ strval( $params_value_key ) ] = strval( $params_value );
			}
		}

		$data = array(
			'to' => array( $mobile ),
			'template_id' => $template['template_id'],
			'data' => $params,
			'campaign_id' => $gateway_fields['campaign_id'],
			'data_coding' => 'auto'
		);


		$curl = curl_init();

		curl_setopt_array(
			$curl,
			array(
				CURLOPT_URL => 'https://api.enablex.io/sms/v1/messages/',
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_CUSTOMREQUEST => 'PUT',
				CURLOPT_POSTFIELDS => json_encode( $data ),
				CURLOPT_HTTPHEADER => array(
					'Authorization: Basic ' . $access_token,
					'Content-Type: application/json'
				),
			)
		);
		$response = curl_exec( $curl );

		if ( $test_call ) {
			return $response;
		}

		if ( curl_errno( $curl ) ) {
			return false;
		}

		$http_code = curl_getinfo( $curl, CURLINFO_HTTP_CODE );
		if ( $http_code != 200 ) {
			return false;
		}

		curl_close( $curl );

		if ( empty( $response ) ) {
			return false;
		}

		return $response;

	}

}