<?php

namespace SMSGateway;


class SMS_RU
{
    // docs at: http://sms.ru/api/send
    public static function sendSMS($gateway_fields, $mobile, $message, $test_call)
    {
        return self::process_sms($gateway_fields, $mobile, $message, $test_call);
    }

    public static function process_sms($gateway_fields, $mobile, $message, $test_call)
    {
        $api_id = $gateway_fields['api_id'];
        $from = $gateway_fields['from'];

        $curl = curl_init();
        $params = array(
            "api_id" => $api_id,
            "msg" => $message,
            "to" => $mobile,
            "from" => $from,
            "json" => 1
        );
        $encoded_query = http_build_query($params);

        curl_setopt($curl, CURLOPT_URL, 'https://sms.ru/sms/send');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($params));

        $result = curl_exec($curl);
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curl_error = curl_errno($curl);
        curl_close($curl);

        if ($test_call) {
            return $result;
        }

        if ($curl_error !== 0) {
            return false;
        }

        $decoded_result = json_decode($result, true);

        if ($decoded_result["status"] == "ok") {
            return true;
        }

        $is_success = 200 <= $code && $code < 300;

        return $is_success;
    }

}
