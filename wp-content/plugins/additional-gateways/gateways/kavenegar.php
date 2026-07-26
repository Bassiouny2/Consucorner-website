<?php

namespace SMSGateway;


class Kayenegar
{

    public static function sendSMS($gateway_fields, $mobile, $message, $test_call)
    {
        return self::process_sms($gateway_fields, $mobile, $message, $test_call);
    }

    public static function process_sms($gateway_fields, $mobile, $message, $test_call)
    {

        $api_key = $gateway_fields['api_key'];
        $sender = $gateway_fields['sender'];


        $mobile = str_replace("+", "", $mobile);

        $data = array();
        $data['sender'] = $sender;
        $data['receptor'] = $mobile;
        $data['message'] = $message;

        $curl = curl_init('https://api.kavenegar.com/v1/' . $api_key . '/sms/send.json');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);

        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);


        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);

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
