<?php
namespace SMSGateway;

class Truedialog {
	public static function sendSMS( $gateway_fields, $mobile, $message, $test_call ) {
		return self::process_sms( $gateway_fields, $mobile, $message, $test_call );
	}
	public static function process_sms( $gateway_fields, $mobile, $message, $test_call ) {
		$access_token = $gateway_fields['access_token'];
		$account_id = $gateway_fields['account_id'];
		$message = iconv( 'UTF-8', 'ISO-8859-15', $message );

		$data = array(
			'Channels' => array( 22 ),
			'Targets' => array( $mobile ),
			'Message' => $message,
			'Execute' => 'true'
		);

		$curl = curl_init();
		curl_setopt_array(
			$curl,
			array(
				CURLOPT_URL => "https://api.truedialog.com/api/v2.1/account/$account_id/action-pushcampaign",
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_CUSTOMREQUEST => 'POST',
				CURLOPT_POSTFIELDS => json_encode( $data ),
				CURLOPT_HTTPHEADER => array(
					'Authorization: Basic ' . $access_token,
					'Content-Type: application/json',
					'Accept: application/json'
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