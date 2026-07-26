<?php

namespace SMSGateway;


class OperSMS {
    // docs at: http://opersms.uz/ru/page/2
    public static function sendSMS($gateway_fields, $mobile, $message, $test_call) {
        $login = $gateway_fields['login'];
        $password = $gateway_fields['password'];

        return self::process_sms($login, $password, $mobile, $message, $test_call);
    }

    public static function process_sms($login, $password, $mobile, $message, $test_call){
        $data = array(
            array("phone" => $mobile, "text" => $message)
        );

        $curl = curl_init();

        $post_params = array(
            'login' => $login,
            'password' => $password,
            'data' => json_encode($data),
        );

        curl_setopt($curl, CURLOPT_URL, 'http://83.69.139.182:8080');
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
