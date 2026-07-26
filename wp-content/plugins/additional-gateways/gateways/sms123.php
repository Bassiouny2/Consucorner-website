<?php

namespace SMSGateway;


class SMS123 {
    // docs at: https://www.sms123.net/apiIntegration.php
    public static function sendSMS($gateway_fields, $mobile, $message, $test_call) {
        $api_key = $gateway_fields['api_key'];

        return self::process_sms($api_key, $mobile, $message, $test_call);
    }

    public static function process_sms($api_key, $mobile, $message, $test_call){
        $curl = curl_init();
        // Don't send the same message twice a day
        $unique_id = time() . sha1($message);
        $params = array(
            "apiKey" => $api_key,
            "messageContent" => $message,
            "recipients" => str_replace("+","",$mobile),
            "referenceId" => $unique_id,
        );

        $encoded_query = http_build_query($params);

        curl_setopt($curl, CURLOPT_URL, 'https://sms123.net/api/send.php?'.$encoded_query);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);

        $result = curl_exec($curl);
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curl_error = curl_errno($curl);
        curl_close($curl);


        if($test_call) return $result;

        if ($curl_error !== 0) {
            return false;
        }

        $decoded_result = json_decode($result, true);

        if ($decoded_result["status"] == "ok") return true;

        $is_success = 200 <= $code && $code < 300;

        return $is_success;
    }

}
