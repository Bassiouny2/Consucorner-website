<?php

namespace SMSGateway;


class Web2SMS237
{

    public static function sendSMS($gateway_fields, $mobile, $message, $test_call)
    {
        return self::process_sms($gateway_fields, $mobile, $message, $test_call);
    }

    public static function process_sms($gateway_fields, $mobile, $message, $test_call)
    {

        $access_token = $gateway_fields['access_token'];;

        $data = array();
        $data['phone'] = $mobile;
        $data['sender_id'] = $gateway_fields['sender'];
        $data['text'] = $message;
        $data['flash'] = false;


        $curl = curl_init('https://api.web2sms237.com/sms/send');
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            "Authorization: Bearer " . $access_token,
            "Content-Type: application/json"
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
