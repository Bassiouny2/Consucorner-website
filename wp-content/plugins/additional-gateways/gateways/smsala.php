<?php

namespace SMSGateway;


class SMSAla
{
    // docs at: https://smsala.com/documents/smsala-api.pdf
    public static function sendSMS($gateway_fields, $mobile, $message, $test_call)
    {
        $api_id = $gateway_fields['api_id'];
        $api_password = $gateway_fields['api_password'];
        $sender = $gateway_fields['sender'];
        return self::process_sms($api_id, $api_password, $sender, $mobile, $message, $test_call);
    }

    public static function process_sms($api_id, $api_password, $sender, $mobile, $message, $test_call)
    {
        $curl = curl_init();
        $data = array(
            'api_id' => $api_id,
            'api_password' => $api_password,
            'textmessage' => $message,
            'sender_id' => $sender,
            'phonenumber' => str_replace("+", "", $mobile),
            'sms_type' => 'T',
            'encoding' => 'T',
        );

        curl_setopt($curl, CURLOPT_URL, 'https://api.smsala.com/api/SendSMS');
        curl_setopt(
            $curl,
            CURLOPT_HTTPHEADER,
            array(
                'Content-Type: application/json',
            )
        );
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data, true));

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
