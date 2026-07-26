<?php

namespace SMSGateway;


class SMSGlobal {
    // docs at: https://www.smsglobal.com/http-api/
    public static function sendSMS($gateway_fields, $mobile, $message, $test_call) {
        $user = $gateway_fields['user'];
        $password = $gateway_fields['password'];
        $sender = $gateway_fields['sender'];

        return self::process_sms($user, $password, $sender, $mobile, $message, $test_call);
    }

    public static function process_sms($user, $password, $sender, $mobile, $message, $test_call){
        $curl = curl_init();
        $params = array(
            'user' => $user,
            'password' => $password,
            'from' => $sender,
            'to' => $mobile,
            'text' => $message,
            'action'=>'sendsms',
        );
        $encoded_query = http_build_query($params);
        curl_setopt($curl, CURLOPT_URL, 'https://api.smsglobal.com/http-api.php?' . $encoded_query);

        curl_setopt($curl, CURLOPT_POST, 1);
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
