<?php

namespace SMSGateway;


class EngageSpark {
    public static function sendSMS($gateway_fields, $mobile, $message, $test_call) {
        $api_key = $gateway_fields['api_key'];
        $organization_id = $gateway_fields['organization_id'];
        $sender = $gateway_fields['sender'];

        return self::process_sms($api_key, $organization_id, $sender, $mobile, $message, $test_call);
    }

    public static function process_sms($api_key, $organization_id, $sender, $mobile, $message, $test_call) {
        $curl = curl_init();

        $data = array(
            'message' => $message,
            'to' => $mobile,
            'from' => $sender,
            'orgid' => $organization_id,
        );

        curl_setopt($curl, CURLOPT_URL, 'https://api.engagespark.com/v1/sms/contact');
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt(
            $curl,
            CURLOPT_HTTPHEADER,
            array(
                "Authorization: Token " . $api_key,
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

        if($test_call) return $result;

        if ($curl_error !== 0) {
            return false;
        }

        $is_success = 200 <= $code && $code < 300;

        return $is_success;
    }
}
