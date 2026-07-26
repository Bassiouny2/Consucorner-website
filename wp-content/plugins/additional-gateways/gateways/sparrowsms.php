<?php

namespace SMSGateway;


class SparrowSMS {
    // docs at: https://sparrowsms.readthedocs.io/en/latest/outgoing_sendsms/
    public static function sendSMS($gateway_fields, $mobile, $message, $test_call) {
        $token = $gateway_fields['token'];
        $sender = $gateway_fields['sender'];

        return self::process_sms($token, $sender, $mobile, $message, $test_call);
    }

    public static function process_sms($token, $sender, $mobile, $message, $test_call){
        $curl = curl_init();

        $post_params = array(
            'token' => $token,
            'from' => $sender,
            'to' => $mobile,
            'text' => $message,
        );

        curl_setopt($curl, CURLOPT_URL, 'http://api.sparrowsms.com/v2/sms/');
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
