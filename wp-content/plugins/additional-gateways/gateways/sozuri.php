<?php

namespace SMSGateway;


class Sozuri
{

    public static function sendSMS($gateway_fields, $mobile, $message, $test_call)
    {
        return self::process_sms($gateway_fields, $mobile, $message, $test_call);
    }

    public static function process_sms($gateway_fields, $mobile, $message, $test_call)
    {


        $api_key = $gateway_fields['api_key'];
        $project = $gateway_fields['project'];
        $sender = $gateway_fields['sender'];
        $sms_type = $gateway_fields['sms_type'];

        $mobile = str_replace("+", "", $mobile);

        $data = array();
        $data['project'] = $project;
        $data['from'] = $sender;
        $data['to'] = $mobile;
        $data['text'] = $message;
        $data['type'] = $sms_type;


        $curl = curl_init('https://sozuri.net/api/v1/messaging');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));


        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            "accept: application/json",
            "authorization: Bearer ".$api_key,
            "content-type: application/json"
        ));

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
