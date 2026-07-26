<?php

namespace SMSGateway;

class OurSMS
{

    public static function sendSMS($gateway_fields, $mobile, $message, $test_call)
    {
        return self::process_sms($gateway_fields, $mobile, $message, $test_call);
    }

    public static function process_sms($gateway_fields, $mobile, $message, $test_call)
    {
        $api_token = $gateway_fields['api_token'];

        $mobile = str_replace("+", "", $mobile);

        $data = array();
        $data['body'] = $message;
        $data['dests'] = [$mobile];
        $data['src'] = $gateway_fields['sender'];
        $data['msgClass'] = 'transactional';

        $curl = curl_init('https://api.oursms.com/msgs/sms');

        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $api_token,
            'Content-Type: application/json'
        ));
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);

        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");

        $answer = curl_exec($curl);

        if ($test_call) {
            return $answer;
        }

        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);


        if ($http_code != 200) {
            return false;
        }

        curl_close($curl);

        if (empty($answer)) {
            return false;
        }

        return true;

    }

}

