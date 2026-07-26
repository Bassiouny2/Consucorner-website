<?php

namespace SMSGateway;


class DooAeSMS
{

    public static function sendSMS($gateway_fields, $mobile, $message, $test_call)
    {
        return self::process_sms($gateway_fields, $mobile, $message, $test_call);
    }

    public static function process_sms($gateway_fields, $mobile, $message, $test_call)
    {


        $data = array();
        $api_mobile = $gateway_fields['mobile'];
        $from = $gateway_fields['from'];
        $password = $gateway_fields['password'];


        $mobile = str_replace("+", "", $mobile);

        $data['mobile'] = $api_mobile;
        $data['password'] = $password;
        $data['numbers'] = $mobile;
        $data['msg'] = $message;
        $data['sender'] = $from;
        $data['applicationType'] = 3;
        $data['dateSend'] = 0;
        $data['timeSend'] = 0;


        $curl = curl_init('https://doo.ae/api/msgSend.php?' . http_build_query($data));
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

        if (empty($answer)) {
            return false;
        }

        return $answer;

    }

}
