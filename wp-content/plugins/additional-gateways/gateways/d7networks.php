<?php

namespace SMSGateway;

require_once 'utils.php';

class D7Networks
{
    public static $chunks = 50;
    public static $supports_bulk = true;
    public static $bulk_type = 'FIXED_MESSAGE';

    // docs at: https://d7networks.com/docs/apis/http/index.html
    public static function sendSMS($gateway_fields, $mobile, $message, $test_call)
    {


        $result = self::process_sms($gateway_fields, $mobile, $message, $test_call);

        return $result;
    }

    public static function sendBulkSMS($gateway_fields, $messages, $test_call)
    {
        $username = $gateway_fields['username'];
        $password = $gateway_fields['password'];
        $sender = $gateway_fields['sender'];

        /*return self::process_sms($username, $password, $sender, $messages, $test_call);*/
    }

    public static function process_sms($gateway_fields, $mobile, $message, $test_call)
    {

        $username = $gateway_fields['username'];
        $password = $gateway_fields['password'];
        $sender = $gateway_fields['sender'];
        $curl = curl_init();

        $authorization_token = base64_encode($username . ':' . $password);

        $data = array(
            'to' => str_replace("+", "", $mobile),
            'content' => $message,
            'from' => $sender,
        );

        curl_setopt($curl, CURLOPT_URL, 'https://rest-api.d7networks.com/secure/send');
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt(
            $curl,
            CURLOPT_HTTPHEADER,
            array(
                'Content-Type: application/json',
                'Authorization: Basic ' . $authorization_token,
            )
        );
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($curl);
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        $curl_error = curl_errno($curl);
        curl_close($curl);

        if ($test_call) {

            return $result;
        }

        $is_success = 200 <= $code && $code < 300;

        if ($is_success && $curl_error !== 0) {
            return true;
        }

        return false;
    }
}
