<?php

namespace SMSGateway;


class Tatango {
    // docs at: http://developers.tatango.com/#transactional-messages
    public static function sendSMS($gateway_fields, $mobile, $message, $test_call) {
        $username = $gateway_fields['username'];
        $password = $gateway_fields['password'];

        return self::process_sms($username, $password, $mobile, $message, $test_call);
    }

    public static function process_sms($username, $password, $mobile, $message, $test_call) {
        $curl = curl_init();

        $data = array(
            'transactional_messages' => array(
                'number' => $mobile,
                'content' => $message,
            ),
        );

        curl_setopt($curl, CURLOPT_URL, 'https://app.tatango.com/api/v2/transactional_messages');
        curl_setopt($curl, CURLOPT_USERPWD, $username . ":" . $password);
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
