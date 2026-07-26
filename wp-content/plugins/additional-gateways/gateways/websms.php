<?php

namespace SMSGateway;


class WebSMS {
    // docs at: https://developer.websms.com/web-api
    public static function sendSMS($gateway_fields, $mobile, $message, $test_call) {
        $access_token = $gateway_fields['access_token'];

        return self::process_sms($access_token, $mobile, $message, $test_call);
    }

    public static function process_sms($access_token, $mobile, $message, $test_call){
        $curl = curl_init();

        $params = array(
            'recipientAddressList' => $mobile,
            'contentCategory' => 'informational',
            'test' => false,
            'messageContent' => $message,
        );
        $encoded_query = http_build_query($params);
        curl_setopt(
            $curl,
            CURLOPT_URL,
            'https://api.websms.com/rest/smsmessaging/simple?' . $encoded_query
        );
        curl_setopt(
            $curl,
            CURLOPT_HTTPHEADER,
            array(
                'Content-Type: application/x-www-form-urlencoded',
                'Authorization: Bearer ' . $access_token
            )
        );
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $post_params);

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
