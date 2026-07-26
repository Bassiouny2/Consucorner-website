<?php

namespace SMSGateway;


class DirectSMS {

	public static function sendSMS( $gateway_fields, $mobile, $message, $test_call ) {
		return self::process_sms( $gateway_fields, $mobile, $message, $test_call );
	}

	public static function process_sms( $gateway_fields, $mobile, $message, $test_call ) {
		$message = iconv( 'UTF-8', 'ISO-8859-15', $message );

		$username = $gateway_fields['username'];
		$password = $gateway_fields['password'];
		$sender_id = $gateway_fields['sender_id'];

		$data = array(
			'messageType' => '1-way',
			'senderId' => $sender_id,
			'messageText' => $message,
			'to' => array( $mobile )
		);

		$curl = curl_init();


		curl_setopt_array(
			$curl,
			array(
				CURLOPT_URL => 'https://api.directsms.com.au/s3/rest/sms/send',
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING => '',
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_TIMEOUT => 0,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST => 'POST',
				CURLOPT_POSTFIELDS => json_encode( $data ),
				CURLOPT_HTTPHEADER => array(
					'Accept: application/json',
					'Content-Type: application/json',
					'Username: ' . $username,
					'Password: ' . $password
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