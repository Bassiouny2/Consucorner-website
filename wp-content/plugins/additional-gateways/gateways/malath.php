<?php

namespace SMSGateway;


class Malath
{

    public static function sendSMS($gateway_fields, $mobile, $message, $test_call)
    {
        return self::process_sms($gateway_fields, $mobile, $message, $test_call);
    }

    public static function process_sms($gateway_fields, $mobile, $message, $test_call)
    {


        $data = array();
        $username = $gateway_fields['username'];
        $password = $gateway_fields['password'];
        $sender = $gateway_fields['sender'];

        $mobile = str_replace("+", "", $mobile);

        $data['username'] = $username;
        $data['password'] = $password;
        $data['sender'] = $sender;
        $data['unicode'] = 'U';
        $data['mobile'] = $mobile;
        $data['message'] = UnitedOver_convertToUnicode($message);

        $curl = curl_init('https://sms.malath.net.sa/httpSmsProvider.aspx?' . http_build_query($data));
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

        return true;

    }

}
