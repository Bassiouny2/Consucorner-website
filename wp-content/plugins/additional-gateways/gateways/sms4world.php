<?php

namespace SMSGateway;


class SMS4World {

	public static function sendSMS( $gateway_fields, $mobile, $message, $test_call ) {
		return self::process_sms( $gateway_fields, $mobile, $message, $test_call );
	}

	public static function process_sms( $gateway_fields, $mobile, $message, $test_call ) {
		$message = iconv( 'UTF-8', 'ISO-8859-15', $message );

		$message = urlencode( $message );

		$username = urlencode( $gateway_fields['username'] );
		$password = urlencode( $gateway_fields['password'] );
		$sender = urlencode( $gateway_fields['sender'] );
		$mobile = urlencode( $mobile );


		$url = "https://www.sms4world.net/smspro/api.php?do=sendsms&username=$username&password=$password&sender=$sender&message=$message&numbers=$mobile";


		$curl = curl_init();

		curl_setopt_array(
			$curl,
			array(
				CURLOPT_URL => $url,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING => '',
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_TIMEOUT => 0,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
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