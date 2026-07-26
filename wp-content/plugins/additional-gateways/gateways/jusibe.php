<?php

namespace SMSGateway;


class Jusibe
{
    // docs at: http://jusibe.com/docs/
    public static function sendSMS($gateway_fields, $mobile, $message, $test_call)
    {
        return self::process_sms($gateway_fields, $mobile, $message, $test_call);
    }

    public static function process_sms($gateway_fields, $mobile, $message, $test_call)
    {
        $curl = curl_init();

        $api_key = $gateway_fields['api_key'];
        $token = $gateway_fields['token'];
        $sender = $gateway_fields['sender'];


        $post_params = array(
            'to' => $mobile,
            'from' => $sender,
            'message' => $message,
        );

        curl_setopt($curl, CURLOPT_URL, 'https://jusibe.com/smsapi/send_sms');
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_USERPWD, $api_key . ":" . $token);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $post_params);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

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
