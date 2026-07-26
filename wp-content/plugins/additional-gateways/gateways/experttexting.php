<?php

namespace SMSGateway;


class ExpertTexting {
    // docs at: https://github.com/ExpertTexting/ExpertTexting-WebApi-Php-Sample/blob/master/expt.php
    public static function sendSMS($gateway_fields, $mobile, $message, $test_call) {
        $username = $gateway_fields['username'];
        $password = $gateway_fields['password'];
        $api_key = $gateway_fields['api_key'];
        $sender = $gateway_fields['sender'];

        return self::process_sms($username, $password, $api_key, $sender, $mobile, $message, $test_call);
    }

    public static function process_sms($username, $password, $api_key, $sender, $mobile, $message, $test_call) {
        $curl = curl_init();
        $post_params = array(
            'username' => $username,
            'password' => $password,
            'api_key' => $api_key,
            'FROM' => $sender,
            'to' => $mobile,
            'text' => urlencode($message),
        );


        curl_setopt($curl, CURLOPT_URL, 'https://www.experttexting.com/ExptRestApi/sms/json/Message/Send');
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post_params));

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
