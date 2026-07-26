<?php

namespace SMSGateway;


class SMSAero
{
    public static function sendSMS($gateway_fields, $mobile, $message, $test_call)
    {

        return self::process_sms($gateway_fields, $mobile, $message, $test_call);
    }

    public static function process_sms($gateway_fields, $mobile, $message, $test_call)
    {

        $email = $gateway_fields['email'];
        $api_key = $gateway_fields['api_key'];
        $sender = $gateway_fields['sender'];

        $curl = curl_init();
        // Don't send the same message twice a day
        $data = array(
            'channel' => 'INTERNATIONAL',
            'sign' => $sender,
            'number' => $mobile,
            'text' => $message,
        );

        curl_setopt($curl, CURLOPT_URL, 'https://gate.smsaero.ru/v2/sms/send');
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($curl, CURLOPT_USERPWD, $email . ":" . $api_key);
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


        if ($curl_error !== 0) {
            if ($test_call) return $curl_error;
            return false;
        }
        if ($test_call) return $result;

        $is_success = 200 <= $code && $code < 300;

        return $is_success;
    }

}
