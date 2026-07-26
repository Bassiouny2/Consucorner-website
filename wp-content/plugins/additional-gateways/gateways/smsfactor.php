<?php

namespace SMSGateway;


class SMSFactor {
    // docs a: https://dev.smsfactor.com/fr/api/sms/envoi/message-unitaire
    public static function sendSMS($gateway_fields, $mobile, $message, $test_call) {
        $api_token = $gateway_fields['api_token'];
        $sender = $gateway_fields['sender'];

        return self::process_sms($api_token, $sender, $mobile, $message, $test_call);
    }

    public static function process_sms($api_token, $sender, $mobile, $message, $test_call){
        $curl = curl_init();

        $params = array(
            'text' => $message,
            'sender' => $sender,
            'to' => $mobile,
            'token'=>$api_token
        );

        $encoded_query = http_build_query($params);
        curl_setopt($curl, CURLOPT_URL, 'https://api.smsfactor.com/send?' . $encoded_query);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt(
            $curl,
            CURLOPT_HTTPHEADER,
            array(
                "Content-Type: application/json",
                "Authorization: Bearer " . $api_token,
            )
        );
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
