<?php

namespace SMSGateway;

class QuickSMS
{

    public static function sendSMS($gateway_fields, $recipient, $message, $test_call)
    {
        return self::process_sms($gateway_fields, $recipient, $message, $test_call);
    }

    public static function process_sms($gateway_fields, $recipient, $message, $test_call)
    {

        $access_token = $gateway_fields['access_token'];
        $sender_id = $gateway_fields['sender_id'];


        $url = "http://srv1.quicksms.xyz/smsapi";

        $recipient = str_replace("+", "", $recipient);

        $data = [
            "api_key" => $access_token,
            "type" => "text",
            "contacts" => $recipient,
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

        return $answer;

    }

}
