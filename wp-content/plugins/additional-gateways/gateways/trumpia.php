<?php


namespace SMSGateway;


class Trumpia {

	public static function sendSMS( $gateway_fields, $mobile, $message, $test_call ) {
		return self::process_sms( $gateway_fields, $mobile, $message, $test_call );
	}

	public static function process_sms( $gateway_fields, $mobile, $message, $test_call ) {
		$access_token = $gateway_fields['access_token'];
		$username = $gateway_fields['username'];


		$message = iconv( 'UTF-8', 'ISO-8859-15', $message );



		$data = array(
			'mobile_number' => $mobile,
			'message' => $message
		);


		$curl = curl_init();

		curl_setopt_array(
			$curl,
			array(
				CURLOPT_URL => "http://api.trumpia.com/rest/v1/$username/sms",
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_CUSTOMREQUEST => 'PUT',
				CURLOPT_POSTFIELDS => json_encode( $data ),
				CURLOPT_HTTPHEADER => array(
					'X-Apikey:  ' . $access_token,
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