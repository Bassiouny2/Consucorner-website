<?php

namespace SMSGateway;


class TextMarketer {
    // docs at: https://wiki.textmarketer.co.uk/display/DevDoc/Sending+SMS
    public static function sendSMS($gateway_fields, $mobile, $message, $test_call) {
        $username = $gateway_fields['username'];
        $password = $gateway_fields['password'];
        $orig = $gateway_fields['orig'];

        return self::process_sms($username, $password, $orig, $mobile, $message, $test_call);
    }

    public static function process_sms($username, $password, $orig, $mobile, $message, $test_call){
        $curl = curl_init();
        $params = array(
            'usernaame' => $username,
            'password' => $password,
            'orig' => $orig,
            'to' => $mobile,
            'message' => $message,
        );
        $encoded_query = http_build_query($params);
        curl_setopt($curl, CURLOPT_URL, 'https://api.textmarketer.co.uk/gateway/?' . $encoded_query);
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
