<?php

namespace SMSGateway;


class BulkSMSNigeria {
    // docs at: https://www.bulksmsnigeria.com/bulk-sms-api

    public static function sendSMS($gateway_fields, $mobile, $message, $test_call) {
        $api_token = $gateway_fields['api_token'];
        $sender = $gateway_fields['sender'];

        return self::process_sms($api_token, $sender, $mobile, $message, $test_call);
    }

    public static function process_sms($api_token, $sender, $mobile, $message, $test_call) {
        $curl = curl_init();
        $params = array(
            'api_token' => $api_token,
            'from' => $sender,
            'to' => $mobile,
            'body' => $message,
        );
        $encoded_query = http_build_query($params);
        curl_setopt($curl, CURLOPT_URL, 'https://www.bulksmsnigeria.com/api/v1/sms/create?' . $encoded_query);
        curl_setopt($curl, CURLOPT_POST, 1);
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
