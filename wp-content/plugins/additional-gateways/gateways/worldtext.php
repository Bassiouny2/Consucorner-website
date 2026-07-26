<?php
namespace SMSGateway;

class WorldText {
	public static function sendSMS( $gateway_fields, $mobile, $message, $test_call ) {
		return self::process_sms( $gateway_fields, $mobile, $message, $test_call );
	}
	public static function process_sms( $gateway_fields, $mobile, $message, $test_call ) {
		$access_token = $gateway_fields['access_token'];
		$account_id = $gateway_fields['account_id'];

		$message = iconv( 'UTF-8', 'ISO-8859-15', $message );

		$curl = curl_init();
		curl_setopt_array(
			$curl,
			array(
				CURLOPT_URL => 'https://sms.world-text.com/v2.0/sms/send?' .
				'id=' . urlencode( $account_id ) .
				'&key=' . urlencode( $access_token ) .
				'&dstaddr=' . urlencode( $mobile ) .
				'&txt=' . urlencode( $message ),
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_CUSTOMREQUEST => 'PUT',
				CURLOPT_HTTPHEADER => array(
					'Content-Type: application/json',
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