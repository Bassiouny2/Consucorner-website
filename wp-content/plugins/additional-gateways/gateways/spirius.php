<?php

namespace SMSGateway;


class Spirius {
    // docs at: https://dev.spirius.com/messaging/sms-gateway/http/api-examples/http-post/
    public static function sendSMS($gateway_fields, $mobile, $message, $test_call) {
        $username = $gateway_fields['username'];
        $password = $gateway_fields['password'];
        $sender = $gateway_fields['sender'];

        return self::process_sms($username, $password, $sender, $mobile, $message, $test_call);
    }

    public static function process_sms($username, $password, $sender, $mobile, $message, $test_call) {
        $curl = curl_init();
        // Don't send the same message twice a day
        $data = array(
            'user' => $username,
            'pass' => $password,
            'from' => $sender,
            'to' => $mobile,
            'msg' => $message,
        );

        curl_setopt($curl, CURLOPT_URL, 'https://post.spiricom.spirius.com:55001/cgi-bin/sendsms');
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
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

        if($test_call) return $result;

        if ($curl_error !== 0) {
            return false;
        }

        $is_success = 200 <= $code && $code < 300;

        return $is_success;
    }

}
