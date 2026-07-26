<?php

namespace SMSGateway;


class MSEGAT
{

    public static function sendSMS($gateway_fields, $mobile, $message, $test_call)
    {
        return self::process_sms($gateway_fields, $mobile, $message, $test_call);
    }

    public static function process_sms($gateway_fields, $mobile, $message, $test_call)
    {

        $data = array();
        $data['userName'] = $gateway_fields['username'];
        $data['apiKey'] = $gateway_fields['api_key'];
        $data['userSender'] = $gateway_fields['sender'];
        $data['numbers'] = str_replace("+", "", $mobile);
        $data['msg'] = $message;
        $data['msgEncoding'] = 'UTF8';


        $curl = curl_init('https://www.msegat.com/gw/sendsms.php');

        curl_setopt(
            $curl,
            CURLOPT_HTTPHEADER,
            array('Content-Type: application/json')
        );

        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
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
