<?php

namespace SMSGateway;


class Messente {
    // docs at: https://messente.com/documentation/sms-messaging/sending-sms
    public static function sendSMS($gateway_fields, $mobile, $message, $test_call) {
        $username = $gateway_fields['username'];
        $password = $gateway_fields['password'];
        $sender = $gateway_fields['sender'];

        return self::process_sms($username, $password, $sender, $mobile, $message, $test_call);
    }

    public static function process_sms($username, $password, $sender, $mobile, $message, $test_call) {
        $curl = curl_init();
        $params = array(
            'username' => $username,
            'password' => $password,
            'from' => $sender,
            'to' => $mobile,
            'text' => $message,
        );
        $encoded_query = http_build_query($params);
        curl_setopt($curl, CURLOPT_URL, 'https://api2.messente.com/send_sms/?' . $encoded_query);
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

        list($resp, $code) = explode(' ', $result);

        if ($resp != 'OK') {
            return false;
        }

        return $is_success;
    }

}
