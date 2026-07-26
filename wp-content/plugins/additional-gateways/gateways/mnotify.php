<?php

namespace SMSGateway;


class MNotify {
    // docs at: http://developer.mnotify.com/SMS_API/Send_Single_SMS
    public static function sendSMS($gateway_fields, $mobile, $message, $test_call) {
        $api_key = $gateway_fields['api_key'];
        $sender = $gateway_fields['sender'];

        return self::process_sms($api_key, $sender, $mobile, $message, $test_call);
    }

    public static function process_sms($api_key, $sender, $mobile, $message, $test_call){
        $curl = curl_init();
        $params = array(
            'key' => $api_key,
            'sender_id' => $sender,
            'to' => $mobile,
            'msg' => $message,
        );
        $encoded_query = http_build_query($params);
        curl_setopt($curl, CURLOPT_URL, 'https://apps.mnotify.net/smsapi?' . $encoded_query);
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
