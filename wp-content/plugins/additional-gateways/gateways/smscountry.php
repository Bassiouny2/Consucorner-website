<?php

namespace SMSGateway;


class SMSCountry {
    // docs at: https://www.smscountry.com/bulk-smsc-api-documentation
    public static function sendSMS($gateway_fields, $mobile, $message, $test_call) {
        return self::process_sms($gateway_fields, $mobile, $message, $test_call);
    }

    public static function process_sms($gateway_fields, $mobile, $message, $test_call){

        $username = $gateway_fields['user'];
        $password = $gateway_fields['password'];
        $sender = $gateway_fields['sender'];

        $curl = curl_init();
        $params = array(
            'user' => $username,
            'passwd' => $password,
            'sid' => $sender,
            'mobilenumber' => $mobile,
            'message' => $message,
            'mtype' => 'LNG',
            'DR' => 'N',
        );
        $encoded_query = http_build_query($params);
        curl_setopt($curl, CURLOPT_URL, 'http://api.smscountry.com/SMSCwebservice_bulk.aspx?' . $encoded_query);
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
