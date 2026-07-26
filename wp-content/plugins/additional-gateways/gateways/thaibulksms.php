<?php

namespace SMSGateway;


class ThaiBulkSMS {
    // docs at: https://frontend.thaibulksms.com/download/ThaibulksmsAPIDocument_V1.7%20EN.pdf
    public static function sendSMS($gateway_fields, $mobile, $message, $test_call) {
        $username = $gateway_fields['username'];
        $password = $gateway_fields['password'];
        $sender = $gateway_fields['sender'];

        return self::process_sms($username, $password, $sender, $mobile, $message, $test_call);
    }

    public static function process_sms($username, $password, $sender, $mobile, $message, $test_call){
        $curl = curl_init();

        $post_params = array(
            'username' => $username,
            'password' => $password,
            'msisdn' => $mobile,
            'message' => $message,
            'sender' => $sender,
            'force' => 'standard',
        );

        curl_setopt($curl, CURLOPT_URL, 'http://www.thaibulksms.com/sms_api.php');
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
