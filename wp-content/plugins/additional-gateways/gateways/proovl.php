<?php

namespace SMSGateway;


class Proovl {
    // docs at: https://www.proovl.com/sms-api#1
    public static function sendSMS($gateway_fields, $mobile, $message, $test_call) {
        $user = $gateway_fields['user'];
        $token = $gateway_fields['token'];
        $sender = $gateway_fields['sender'];

        return self::process_sms($user, $token, $sender, $mobile, $message, $test_call);
    }

    public static function process_sms($user, $token, $sender, $mobile, $message, $test_call){
        $curl = curl_init();

        $post_params = array(
            'user' => $user,
            'token' => $token,
            'route' => 1,
            'from' => $sender,
            'to' => $mobile,
            'text' => $message,
        );

        curl_setopt($curl, CURLOPT_URL, 'https://www.proovl.com/api/send.php');
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

        $result = explode(';', $result);

        if ($result[0] == 'Error') {
            return false;
        }

        return $is_success;
    }
}
