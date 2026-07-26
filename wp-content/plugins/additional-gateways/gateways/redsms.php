<?php

namespace SMSGateway;


class redsms
{

    public static function sendSMS($gateway_fields, $mobile, $message, $test_call)
    {
        return self::process_sms($gateway_fields, $mobile, $message, $test_call);
    }

    public static function process_sms($gateway_fields, $mobile, $message, $test_call)
    {

        $login = $gateway_fields['login'];
        $apiKey = $gateway_fields['api_key'];
        $ts = microtime() . rand(0, 10000);


        $data = array();
        $data['from'] = $gateway_fields['from'];
        $data['to'] = str_replace("+", "", $mobile);
        $data['text'] = $message;
        $data['route'] = 'sms';


        $curl = curl_init('https://cp.redsms.ru/api/message');

        curl_setopt(
            $curl,
            CURLOPT_HTTPHEADER,
            array('login: ' . $login,
                'ts: ' . $ts,
                'sig: ' . md5(implode('', $data) . $ts . $apiKey),)
        );

        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
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
