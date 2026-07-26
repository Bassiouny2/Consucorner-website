<?php

namespace SMSGateway;


class GatewayAPI {
    public static function sendSMS($gateway_fields, $mobile, $message, $test_call) {
        $api_secret = $gateway_fields['api_secret'];
        $api_key = $gateway_fields['api_key'];
        $sender = $gateway_fields['sender'];

        return self::process_sms($email, $api_key, $api_secret, $sender, $mobile, $message, $test_call);
    }

    public static function process_sms($email, $api_key, $api_secret, $sender, $mobile, $message, $test_call) {
        $curl = curl_init();
        // Don't send the same message twice a day
        $data = array(
            'sender' => $sender,
            'recipients' => ['msisdn' => $mobile],
            'message' => $message,
        );

        curl_setopt($curl, CURLOPT_URL, 'https://gatewayapi.com/rest/mtsms');
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_USERPWD, $api_key . ":" . $api_secret);
        curl_setopt(
            $curl,
            CURLOPT_HTTPHEADER,
            array(
                "Content-Type: application/json",
            )
        );
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($curl);
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curl_error = curl_errno($curl);
        curl_close($curl);

        if($test_call) return $result;

        if ($curl_error !== 0) {
            return false;
        }

        $is_success = 200 <= $code && $code < 300;

        return $is_success;
    }

}
