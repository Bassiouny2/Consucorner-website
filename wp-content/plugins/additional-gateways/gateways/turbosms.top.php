<?php

namespace SMSGateway;

class TurboSMSTop
{

    public static function sendSMS($gateway_fields, $phone, $message, $test_call)
    {
        return self::process_sms($gateway_fields, $phone, $message, $test_call);
    }

    public static function process_sms($gateway_fields, $phone, $message, $test_call)
    {

        $api_token = $gateway_fields['api_key'];
        $sender_id = $gateway_fields['sender_id'];


        $url = "https://turbosms.com.bd/smsapi";

        $data = [
            "api_key" => $api_token,
            "type" => "text",
            "contacts" => $phone,
            "senderid" => $sender_id,
            "msg" => $message,
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
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

        return true;

    }

}
