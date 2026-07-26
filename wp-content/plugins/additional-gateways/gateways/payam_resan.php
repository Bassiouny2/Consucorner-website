<?php

namespace SMSGateway;


class Payam_Resan
{

    public static function sendSMS($gateway_fields, $countrycode, $mobile, $message, $test_call)
    {
        return self::process_sms($gateway_fields, $mobile, $message, $test_call);
    }

    public static function process_sms($gateway_fields, $mobile, $message, $test_call)
    {
        $gateway_uname = $gateway_fields['uname'];
        $gateway_password = $gateway_fields['password'];
        $sender = $gateway_fields['sender'];


        $curl = curl_init();

        $params = array(
            'Username' => $gateway_uname,
            'Password' => $gateway_password,
            'From' => $sender,
            'To' => '0' . str_replace("+", "", ltrim($mobile, '0')),
            'Text' => urlencode($message),
        );
        curl_setopt($curl, CURLOPT_URL, 'https://www.payam-resan.com/APISend.aspx?' . http_build_query($params));
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($params));

        $result = curl_exec($curl);

        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curl_error = curl_errno($curl);
        curl_close($curl);

        if ($test_call) return $result;

        if ($curl_error !== 0) {
            return false;
        }

        $is_success = 200 <= $code && $code < 300;

        return $is_success;


    }

}
