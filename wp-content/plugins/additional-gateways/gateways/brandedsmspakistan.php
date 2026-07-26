<?php

namespace SMSGateway;


class BrandedSMSPakistan
{

    public static function sendSMS($gateway_fields, $mobile, $message, $test_call)
    {
        return self::process_sms($gateway_fields, $mobile, $message, $test_call);
    }

    public static function process_sms($gateway_fields, $mobile, $message, $test_call)
    {
        $mobile = str_replace("+", "", $mobile);

        $data = array();
        $data['email'] = $gateway_fields['email'];
        $data['key'] = $gateway_fields['account_api_key'];
        $data['mask'] = $gateway_fields['mask'];

        $data['to'] = $mobile;
        $data['message'] = $message;


        $curl = curl_init('https://secure.h3techs.com/sms/api/send?' . http_build_query($data));

        /*curl_setopt($curl, CURLOPT_HTTPHEADER, [
            "x-rapidapi-host: " . $gateway_fields['xrapid_api_host'],
            "x-rapidapi-key: " . $gateway_fields['xrapid_api_key'],
        ]);*/

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);

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
