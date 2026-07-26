<?php

namespace SMSGateway;


class TxtSync
{

    public static function sendSMS($gateway_fields, $mobile, $message, $test_call)
    {
        return self::process_sms($gateway_fields, $mobile, $message, $test_call);
    }

    public static function process_sms($gateway_fields, $mobile, $message, $test_call)
    {
        $api_key = $gateway_fields['api_key'];
        $from = $gateway_fields['from'];

        $curl = curl_init();
        $params = array(
            'From' => $from,
            'To' => $mobile,
            'message' => $message
        );

        curl_setopt($curl, CURLOPT_HTTPHEADER,
            array(
                'Authorization: Basic ' . $api_key
            )
        );

        curl_setopt($curl, CURLOPT_URL, 'https://api.txtsync.com/sms/send');
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($params));

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
