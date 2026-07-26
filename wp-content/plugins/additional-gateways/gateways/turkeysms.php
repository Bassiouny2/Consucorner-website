<?php

namespace SMSGateway;


class TurkeySMS
{

    public static function sendSMS($gateway_fields, $mobile, $message, $test_call)
    {
        return self::process_sms($gateway_fields, $mobile, $message, $test_call);
    }

    public static function process_sms($gateway_fields, $mobile, $message, $test_call)
    {


        $data = array();
        $api_key = $gateway_fields['api_key'];
        $sender = $gateway_fields['sender'];

        $mobile = str_replace("+", "", $mobile);

        $data['api_key'] = $api_key;
        $data['title'] = $sender;
        $data['mobile'] = $mobile;
        $data['text'] = $message;
        $data['report'] = 1;
        $data['response_type'] = 'array';


        $query = http_build_query($data);
        $curl = curl_init('https://turkeysms.com.tr/api/v3/get/get.php?'.$query);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "GET");

        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);


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
