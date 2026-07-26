<?php

namespace SMSGateway;

require_once './utils.php';

class MobyT {
    // docs at: https://developers.mobyt.it/?_ga=2.225538008.356348271.1569226225-752375728.1568708563#remove-an-alias
    public static $auth_performed = false;
    public static $session_key = '';
    public static $user_key = '';

    public static function sendSMS($gateway_fields, $mobile, $message, $test_call) {
        $username = $gateway_fields['username'];
        $password = $gateway_fields['password'];
        $sender = $gateway_fields['sender'];
        $auth_credentials = self::_obtain_auth_credentials($username, $password);
        $user_key = $auth_credentials['user_key'];
        $session_key = $auth_credentials['session_key'];

        if (!self::$auth_performed) return false;

        return self::process_sms($user_key, $session_key, $sender, $mobile, $message, $test_call);
    }

    public static function sendBulkSMS($gateway_fields, $messages, $test_call) {
        $username = $gateway_fields['username'];
        $password = $gateway_fields['password'];
        $sender = $gateway_fields['sender'];
        $auth_credentials = self::_obtain_auth_credentials($username, $password);
        $user_key = $auth_credentials['user_key'];
        $session_key = $auth_credentials['session_key'];

        if (!self::$auth_performed) return false;

        return self::process_sms($user_key, $session_key, $sender, $mobile, $message, $test_call);
    }

    public static function _obtain_auth_credentials($user, $password) {
        if ($auth_performed) {
            return ['session_key' => self::$session_key, 'user_key' => self::$user_key];
        }
        $curl = curl_init();

        $params = array(
            'username' => $user,
            'password' => $password,
        );
        $encoded_query = http_build_query($params);
        curl_setopt(
            $curl,
            CURLOPT_URL,
            'https://app.mobyt.it/API/v1.0/REST/token?' . $encoded_query
        );
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
            $auth_credentials_raw = explode(';', $result);
            self::$user_key = $auth_credentials_raw[0];
            self::$session_key = $auth_credentials_raw[1];
            self::$auth_performed = true;
            return ['session_key' => self::$session_key, 'user_key' => self::$user_key];
        }

        return '';
    }

    public static function process_sms($user_key, $sesion_id, $sender, $messages, $test_call) {
        $curl = curl_init();
        $chunked_messages = array_chunk($messages, self::$chunks);
        $failed_sent = [];
        $results = [];
        $fixed_message = '';

        foreach($chunked_messages as $message_batch) {
            $mobiles = [];

            foreach($message_batch as $id => $message_descriptor) {
                foreach($message_descriptor as $mobile => $message) {
                    $fixed_message = $message;
                    $mobiles[] = $mobile;
                }
            }

            $data = array(
                'message_type' => 'LL',
                'sender' => $sender,
                'recipient' => $mobiles,
                'message' => $fixed_message,
            );
            $encoded_query = http_build_query($params);
            curl_setopt($curl, CURLOPT_URL, 'https://app.mobyt.it/API/v1.0/REST/sms?'. $encoded_query);
            curl_setopt(
                $curl,
                CURLOPT_HTTPHEADER,
                array(
                    'Content-Type: application/json',
                    'user_key: ' . $user_key,
                    'Session_key: ' . $session_id,
                )
            );
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data, true));

            $result = curl_exec($curl);
            $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curl_error = curl_errno($curl);
            curl_close($curl);

            if($test_call) {
                $results[] = $result;
            }

            $is_success = 200 <= $code && $code < 300;

            if ($is_success && $curl_error !== 0) {
            } else {
                $failed_sent += $mobiles;
            }
        }

        if($test_call) return $results;

        return \last_sent_from_failed($messages, $failed_sent);
    }
}
