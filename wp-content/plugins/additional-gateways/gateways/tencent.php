<?php

namespace SMSGateway;


class Tencent {

	public static function sendSMS( $gateway_fields, $mobile, $message, $test_call ) {
		return self::process_sms( $gateway_fields, $mobile, $message, $test_call );
	}

	public static function process_sms( $gateway_fields, $mobile, $message, $test_call ) {
		$message = iconv( 'UTF-8', 'ISO-8859-15', $message );


		$secret_id = $gateway_fields['secret_id'];
		$secret_key = $gateway_fields['secret_key'];
		$region = $gateway_fields['region'];
		$template_id = $gateway_fields['template_id'];
		$sign_name = $gateway_fields['sign_name'];
		$sms_sdk_app_id = $gateway_fields['sms_sdk_app_id'];
		$url = "https://sms.tencentcloudapi.com/";

		$params = array(
			"Action" => "SendSms",
			"Version" => "2019-07-11",
			"PhoneNumberSet.0" => $mobile,
			"TemplateID" => $template_id,
			"Sign" => $sign_name,
			"SmsSdkAppid" => $sms_sdk_app_id,
		);

		$timestamp = time();
		$nonce = rand( 1, 1000000 );

		$string_to_sign = "POST{$url}?";
		ksort( $params );
		foreach ( $params as $key => $value ) {
			$string_to_sign .= "{$key}={$value}&";
		}
		$string_to_sign =
			substr( $string_to_sign, 0, -1 )
			. PHP_EOL .
			"content-type:application/json"
			. PHP_EOL .
			"host:sms.tencentcloudapi.com"
			. PHP_EOL .
			"x-tc-timestamp:{$timestamp}"
			. PHP_EOL .
			"x-tc-nonce:{$nonce}"
			. PHP_EOL;

		$signature = base64_encode( hash_hmac( "sha1", $string_to_sign, $secret_key, true ) );

		$headers = array(
			"Content-Type: application/json",
			"Host: sms.tencentcloudapi.com",
			"X-TC-Timestamp: {$timestamp}",
			"X-TC-Region: {$region}",
			"Authorization: TC3-HMAC-SHA1 Credential={$secret_id}/{$timestamp}/sms/tc3_request, SignedHeaders=content-type;host;x-tc-region;x-tc-timestamp, Signature={$signature}",
			"X-TC-Nonce: {$nonce}"
		);

		$options = array(
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => json_encode( $params ),
			CURLOPT_HTTPHEADER => $headers,
		);

		$curl = curl_init();
		curl_setopt_array( $curl, $options );
		$response = curl_exec( $curl );
		curl_close( $curl );

		$response = json_decode( $response, true );


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