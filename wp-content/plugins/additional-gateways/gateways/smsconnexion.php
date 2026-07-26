<?php

namespace SMSGateway;

class SmsConnexion
{

    public static function sendSMS($gateway_fields, $countrycode, $phone, $message, $test_call)
    {
        return self::process_sms($gateway_fields, $countrycode . $phone, $message, $test_call);
    }

    public static function process_sms($gateway_fields, $phone, $message, $test_call)
    {

        $access_token = $gateway_fields['access_token'];
        $sender = $gateway_fields['from'];

        $url = "https://api.sms.cx/sms";


        $data = array(
            "to" => $phone,
            "from" => $sender,
            "text" => $message,
        );

        $headers = [
            'X-API-KEY: ' . $access_token,
            'Content-Type: application/json'
        ];
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

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
