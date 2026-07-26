<?php

namespace SMSGateway;

class AfilNet
{

    public static function sendSMS($gateway_fields, $countrycode, $phone, $message, $test_call)
    {
        return self::process_sms($gateway_fields, $countrycode . $phone, $message, $test_call);
    }

    public static function process_sms($gateway_fields, $phone, $message, $test_call)
    {

        $username = $gateway_fields['username'];
        $password = $gateway_fields['password'];
        $sender = $gateway_fields['from'];

        $phone = str_replace("+", "", $phone);
        $url = "https://www.afilnet.com/api/http/";


        $data = array(
            'class' => 'sms',
            'method' => 'sendsms',
            'user' => $username,
            'password' => $password,
            "to" => $phone,
            "from" => $sender,
            "sms" => $message,
        );


        $url .= http_build_query($data);
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $answer = curl_exec($ch);


        if ($test_call) {
            return $answer;
        }

        if (curl_errno($ch)) {
            return false;
        }

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($http_code != 200) {
            return false;
        }

        curl_close($ch);

        if (empty($answer)) {
            return false;
        }

        return $answer;

    }

}
