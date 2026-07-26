<?php

namespace SMSGateway;


class Skebby
{
    // docs at: https://developers.skebby.it/?php#send-an-sms-message
    public static $auth_performed = false;
    public static $session_key = '';
    public static $user_key = '';

    public static function sendSMS($gateway_fields, $mobile, $message, $test_call)
    {
        $user = $gateway_fields['username'];
        $password = $gateway_fields['password'];
        $sender = $gateway_fields['sender'];
        $auth_credentials = self::_obtain_auth_credentials($user, $password);
        $user_key = $auth_credentials['user_key'];
        $session_key = $auth_credentials['session_key'];

        return self::process_sms($user_key, $session_key, $sender, $mobile, $message, $test_call);
    }

    public static function _obtain_auth_credentials($user, $password)
    {
        $curl = curl_init();

        $params = array(
            'user' => $user,
            'password' => $password,
        );
        //$encoded_query = http_build_query($params);
        curl_setopt(
            $curl,
            CURLOPT_URL,
            'https://api.skebby.it/API/v1.0/REST/login'
        );
        curl_setopt($curl, CURLOPT_USERPWD, $user . ':' . $password);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($curl);
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curl_error = curl_errno($curl);
        curl_close($curl);

        if ($curl_error !== 0) {
            return false;
        }

        $is_success = 200 <= $code && $code < 300;

        if ($is_success) {
            $auth_credentials_raw = explode(";", $result);
            self::$user_key = $auth_credentials_raw[0];
            self::$session_key = $auth_credentials_raw[1];
            self::$auth_performed = true;
            return ['session_key' => self::$session_key, 'user_key' => self::$user_key];
        }

        return '';
    }

    public static function process_sms($user_key, $session_key, $sender, $mobile, $message, $test_call)
    {
        $curl = curl_init();

        $data = array(
            'message_type' => 'GP',
            'recipient' => [$mobile],
            'sender' => $sender,
            'message' => $message,
        );

        curl_setopt(
            $curl,
            CURLOPT_URL,
            'https://api.skebby.it/API/v1.0/REST/sms'
        );
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt(
            $curl,
            CURLOPT_HTTPHEADER,
            array(
                'Content-Type: application/json',
                'user_key: ' . $user_key,
                'session_key: ' . $session_key,
            )
        );
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($curl);
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curl_error = curl_errno($curl);
        curl_close($curl);

        if ($test_call) return $result;

        if ($curl_error !== 0) {
            return false;
        }

        $is_success = 200 <= $code && $code < 300;

        return $is_success;
    }
}
