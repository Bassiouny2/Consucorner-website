<?php

namespace SMSGateway;


class SMSNinja
{

    public static function sendSMS($gateway_fields, $mobile, $message, $test_call)
    {
        return self::process_sms($gateway_fields, $mobile, $message, $test_call);
    }

    public static function process_sms($gateway_fields, $mobile, $message, $test_call)
    {

        $api_key = $gateway_fields['api_key'];
        $device = $gateway_fields['device'];
        $sim = $gateway_fields['sim'];

        $mobile = str_replace("+", "", $mobile);

        $data = array();
        $data['key'] = $api_key;
        $data['phone'] = $mobile;
        $data['message'] = $message;

        if(!empty($device)) {
            $data['device'] = $device;
        }
        
        if(!empty($sim)) {
            $data['sim'] = $sim;
        }

        $curl = curl_init('https://sms.ninja/api/send?' . http_build_query($data));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);

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
