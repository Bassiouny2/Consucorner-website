<?php

namespace SMSGateway;


class NotifyLK
{

    public static function sendSMS($gateway_fields, $mobile, $message, $test_call)
    {
        return self::process_sms($gateway_fields, $mobile, $message, $test_call);
    }

    public static function process_sms($gateway_fields, $mobile, $message, $test_call)
    {


        $data = array();
        $user_id = $gateway_fields['user_id'];
        $api_key = $gateway_fields['api_key'];
        $sender_id = $gateway_fields['sender_id'];

        $mobile = str_replace("+", "", $mobile);

        $data['user_id'] = $user_id;
        $data['api_key'] = $api_key;
        $data['sender_id'] = $sender_id;
        $data['to'] = $mobile;
        $data['message'] = $message;


        $curl = curl_init('https://app.notify.lk/api/v1/send?' . http_build_query($data));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        $answer = curl_exec($curl);


        if ($test_call) {
            return $answer;
        }

        if (curl_errno($curl)) {
            return false;
        }

        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($http_code != 200) {
            return false;
        }

        curl_close($curl);

        if (empty($answer)) {
            return false;
        }

        return $answer;

    }

}
