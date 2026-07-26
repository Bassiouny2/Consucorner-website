<?php

namespace SMSGateway;


class Foxglove
{

    public static function sendSMS($gateway_fields, $mobile, $message, $test_call)
    {
        return self::process_sms($gateway_fields, $mobile, $message, $test_call);
    }

    public static function process_sms($gateway_fields, $mobile, $message, $test_call)
    {
        $user = $gateway_fields['user'];
        $key = $gateway_fields['key'];
        $account_type = $gateway_fields['account_type'];
        $sender = $gateway_fields['sender'];

        $curl = curl_init();
        $params = array(
            'user' => $user,
            'key' => $key,
            'accusage' => $account_type,
            'mobile' => $mobile,
            'senderid' => $sender,
            'message' => $message,
            'unicode' => 1
        );
        $encoded_query = http_build_query($params);
        curl_setopt($curl, CURLOPT_URL, 'http://www.foxxsms.com/sms//submitsms.jsp?' . $encoded_query);
        curl_setopt($curl, CURLOPT_POST, 1);
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
