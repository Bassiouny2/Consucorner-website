<?php

namespace SMSGateway;


class Mogreet {
    public static function sendSMS($gateway_fields, $mobile, $message, $test_call) {
        $client_id = $gateway_fields['client_id'];
        $token = $gateway_fields['token'];
        $campaign_id = $gateway_fields['campaign_id'];
        $sender = $gateway_fields['sender'];

        return self::process_sms($client_id, $token, $campaign_id, $sender, $mobile, $message, $test_call);
    }

    public static function process_sms($client_id, $token, $campaign_id, $sender, $mobile, $message, $test_call) {
        $curl = curl_init();
        $post_params = array(
            'client_id' => $client_id,
            'token' => $token,
            'campaign_id' => $campaign_id,
            'from' => $sender,
            'to' => $mobile,
            'message' => $message,
        );
        curl_setopt($curl, CURLOPT_URL, 'https://api.mogreet.com/moms/transaction.send');
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $post_params);
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
