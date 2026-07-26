<?php

namespace SMSGateway;


class SMSMode
{

    public static function sendSMS($gateway_fields, $mobile, $message, $test_call)
    {
        return self::process_sms($gateway_fields, $mobile, $message, $test_call);
    }

    public static function process_sms($gateway_fields, $mobile, $message, $test_call)
    {

        $access_token = $gateway_fields['access_token'];
        $emetteur = $gateway_fields['emetteur'];

        $destinataires = str_replace("+", "", $mobile);

        $message = iconv("UTF-8", "ISO-8859-15", $message);
        $fields_string = 'accessToken=' . $access_token . '&message=' . urlencode($message) . '&numero=' . $destinataires . '&emetteur=' . $emetteur;

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_URL, 'https://api.smsmode.com/http/1.6/sendSMS.do');
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $fields_string);
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
