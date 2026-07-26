<?php

namespace SMSGateway;

class MobilenetSa
{

    public static function sendSMS($gateway_fields, $recipient, $message, $test_call)
    {
        return self::apiMobile($gateway_fields, $recipient, $message, $test_call);
    }

    public static function apiMobile($gateway_fields, $mobile, $message, $test_call)
    {


        $userName = $gateway_fields['username'];
        $userPassword = $gateway_fields['password'];
        $sender = $gateway_fields['sender'];

        $mobile = str_replace("+", "", $mobile);

        $array_data = array(
            'userName' => $userName,
            'userPassword' => $userPassword,
            'numbers' => $mobile,
            'userSender' => $sender,
            'msg' => $message,
            'By' => 'standard',
        );


        $curl = curl_init('https://mobile.net.sa/sms/gw/');
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $array_data);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_ENCODING, 'UTF-8');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($curl);
        $results = json_decode($response, true);


        if (curl_errno($curl)) {
            return false;
        }

        curl_close($curl);

        if ($test_call) {
            return $results;
        } else {
            return true;
        }

    }

}
