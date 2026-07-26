<?php

namespace SMSGateway;

class AuthKey
{
    public static function sendSMS($gateway_fields, $country_code, $mobile, $message, $test_call)
    {
        return self::process_sms($gateway_fields, $country_code, $mobile, $message, $test_call);
    }

    public static function process_sms($gateway_fields, $country_code, $mobile, $message, $test_call)
    {
        $access_token = $gateway_fields['access_token'];
        $sender_id = $gateway_fields['sender_id'];

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.authkey.io/request?authkey=' .
                urlencode($access_token) .
                '&mobile=' . urlencode($mobile) .
                '&country_code=' . urlencode($country_code) .
                '&sms=' . urlencode($message) .
                '&sender=' . urlencode($sender_id),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
        ));

        $response = curl_exec($curl);

        if ($test_call) {
            return $response;
        }

        if (curl_errno($curl)) {
            return false;
        }

        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($http_code != 200) {
            return false;
        }

        curl_close($curl);

        if (empty($response)) {
            return false;
        }

        return $response;
    }
}