<?php

namespace SMSGateway;


class SlickText {
    // docs at: https://api.slicktext.com/messages.php#3

    public static function sendSMS($gateway_fields, $mobile, $message, $test_call) {
        $username = $gateway_fields['username'];
        $private_key = $gateway_fields['private_key'];

        return self::process_sms($username, $private_key, $mobile, $message, $test_call);
    }

    public static function process_sms($username, $private_key, $mobile, $message, $test_call) {
        $authentication_token = base64_encode($username . ':' . $private_key);
        $curl = curl_init();
        $post_params = array(
            'action' => 'SEND',
            'contact' => $mobile,
            'body' => $message,
        );
        curl_setopt($curl, CURLOPT_URL, 'https://api.slicktext.com/v1/messages/');
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt(
            $curl,
            CURLOPT_HTTPHEADER,
            array(
                'Authorization: Basic ' . $authentication_token,
            )
        );
        curl_setopt($curl, CURLOPT_POSTFIELDS, $post_params);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
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
