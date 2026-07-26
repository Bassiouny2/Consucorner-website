<?php

namespace SMSGateway;


class Releans {
    // docs at: https://platform.releans.com/documentation/home/message
    public static function sendSMS($gateway_fields, $mobile, $message, $test_call) {
        $api_key = $gateway_fields['api_key'];
        $sender = $gateway_fields['sender'];

        return self::process_sms($api_key, $sender, $mobile, $message, $test_call);
    }

    public static function process_sms($api_key, $sender, $mobile, $message, $test_call) {
        $curl = curl_init();
        $data = array(
            'sender' => $sender,
            'mobile' => $mobile,
            'content' => $message,
        );
        curl_setopt($curl, CURLOPT_URL, 'https://api.releans.com/v2/message');
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);

        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt(
            $curl,
            CURLOPT_HTTPHEADER,
            array(
                "Authorization: Bearer " . $api_key,
            )
        );
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($curl);
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curl_error = curl_errno($curl);
        curl_close($curl);

        if($test_call) return $result;

        if ($curl_error !== 0) {
            return false;
        }

        $is_success = 200 <= $code && $code < 300;

        return $is_success;
    }

}
