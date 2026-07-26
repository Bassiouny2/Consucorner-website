<?php

namespace SMSGateway;


class Karix
{
    // docs at: http://docs.karix.io/v2/
    // supports bulk with fixed message
    public static function sendSMS($gateway_fields, $mobile, $message, $test_call)
    {
        return self::process_sms($gateway_fields, $mobile, $message, $test_call);
    }

    public static function process_sms($gateway_fields, $mobile, $message, $test_call)
    {
        $gateway_fields['channel'] = 'sms';
        return self::process($gateway_fields, $mobile, $message, $test_call);
    }

    public static function process_whatsapp($gateway_fields, $mobile, $message, $test_call)
    {
        $gateway_fields['channel'] = 'whatsapp';
        return self::process($gateway_fields, $mobile, $message, $test_call);
    }

    public static function process($gateway_fields, $mobile, $message, $test_call)
    {
        $uid = $gateway_fields['uid'];
        $token = $gateway_fields['token'];
        $sender = $gateway_fields['sender'];
        $channel = $gateway_fields['channel'];

        $curl = curl_init();
        // http://docs.karix.io/v2/#operation/sendMessage
        $data = array(
            'channel' => $channel,
            'source' => $sender,
            'destination' => [$mobile],
            'content' => array('text' => $message),
        );

        curl_setopt($curl, CURLOPT_URL, 'https://api.karix.io/message/');
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        // http://docs.karix.io/v2/#section/Authentication
        curl_setopt($curl, CURLOPT_USERPWD, $uid . ":" . $token);
        curl_setopt(
            $curl,
            CURLOPT_HTTPHEADER,
            array(
                "Content-Type: application/json",
            )
        );
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
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
