<?php

namespace SMSGateway;


class SMSIR
{

    public static function sendSMS($gateway_fields, $mobile, $message, $test_call)
    {
        return self::process_sms($gateway_fields, $mobile, $message, $test_call);
    }

    public static function process_sms($gateway_fields, $mobile, $message, $test_call)
    {


        $data = array();
        $username = $gateway_fields['username'];
        $password = $gateway_fields['password'];


        $mobile = str_replace("+", "", $mobile);

        $data['user'] = $username;
        $data['pass'] = $password;
        $data['to'] = $mobile;
        $data['text'] = $message;
        $data['lineNo'] = $gateway_fields['line_no'];


        $curl = curl_init('https://ip.sms.ir/SendMessage.ashx?' . http_build_query($data));
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
