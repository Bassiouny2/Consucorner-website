<?php

namespace SMSGateway;


class TeleSign
{
    // docs at: https://standard.telesign.com/api-reference/apis/sms-api/send-an-sms/reference
    public static function sendSMS($gateway_fields, $mobile, $message, $test_call)
    {
        return self::process_sms($gateway_fields, $mobile, $message, $test_call);
    }

    public static function process_sms($gateway_fields, $mobile, $message, $test_call)
    {

        $username = $gateway_fields['username'];
        $password = $gateway_fields['password'];
        $sender = $gateway_fields['sender'];


        $curl = curl_init();
        $post_params = array(
            'message' => $message,
            'phone_number' => $mobile,
            'sender_id' => $sender,
            'message_type' => 'ARN'
        );

        curl_setopt($curl, CURLOPT_URL, 'https://rest-api.telesign.com/v1/messaging');
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_USERPWD, $username . ":" . $password);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $post_params);

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
