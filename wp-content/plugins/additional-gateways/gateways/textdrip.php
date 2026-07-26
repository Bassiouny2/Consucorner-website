<?php

namespace SMSGateway;


class TextDrip {

	public static function sendSMS( $gateway_fields, $mobile, $message, $test_call ) {
		return self::process_sms( $gateway_fields, $mobile, $message, $test_call );
	}

	public static function process_sms( $gateway_fields, $mobile, $message, $test_call ) {
		$access_token = $gateway_fields['access_token'];
		$message = iconv( 'UTF-8', 'ISO-8859-15', $message );

		$data = array(
			'receiver' => $mobile,
			'message' => $message
		);

		$curl = curl_init();

		curl_setopt_array(
			$curl,
			array(
				CURLOPT_URL => 'https://api.textdrip.com/api/send-message',
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING => '',
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_TIMEOUT => 0,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST => 'POST',
				CURLOPT_POSTFIELDS => json_encode( $data ),
				CURLOPT_HTTPHEADER => array(
					'Authorization: Bearer ' . $access_token,
					'Accept: application/json',
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