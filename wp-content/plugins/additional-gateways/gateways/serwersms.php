<?php

namespace SMSGateway;


class SerwerSms
{

    public static function sendSMS($gateway_fields, $mobile, $message, $test_call)
    {
        return self::process_sms($gateway_fields, $mobile, $message, $test_call);
    }

    public static function process_sms($gateway_fields, $mobile, $message, $test_call)
    {
        $username = $gateway_fields['username'];
        $password = $gateway_fields['password'];
        $from = $gateway_fields['from'];


        $params = array();
        $params['username'] = $username;
        $params['password'] = $password;
        $params['text'] = $message;
        $params['phone'] = $mobile;
        $params['sender'] = $from;
        $params['system'] = 'client_php';

        $curl = curl_init('https://api2.serwersms.pl/messages/send_sms.json');

        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($params));
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

        $result = json_decode($answer);
        if (isset($result->error)) {
            return false;
        }

        return $result;

    }

}
