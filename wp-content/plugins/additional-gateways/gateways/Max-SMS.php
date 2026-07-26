<?php

namespace SMSGateway;


class Max_SMS
{

    public static function sendSMS($gateway_fields, $mobile, $message, $test_call)
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
            'uname' => $gateway_uname,
            'pass' => $gateway_password,
            'to' => json_encode(array($mobile)),
            'from' => $sender,
            'message' => $message,
            'op' => 'send',
        );

        curl_setopt($curl, CURLOPT_URL, 'https://ippanel.com/services.jspd');
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
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
