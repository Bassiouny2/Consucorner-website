<?php

namespace SMSGateway;


class SwiftSMSGateway {
    // docs at: https://www.swiftsmsgateway.com/developers/
    public static function sendSMS($gateway_fields, $mobile, $message, $test_call) {
        $account_key = $gateway_fields['account_key'];

        return self::process_sms($account_key, $mobile, $message, $test_call);
    }

    public static function process_sms($account_key, $mobile, $message, $test_call) {
        $curl = curl_init();
        $params = array(
            'AccountKey' => $account_key,
            'CellNumber' => $mobile,
            'MessageBody' => $message,
        );
        $encoded_query = http_build_query($params);
        curl_setopt($curl, CURLOPT_URL, 'https://secure.smsgateway.ca/SendSMS.aspx?' . $encoded_query);
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
