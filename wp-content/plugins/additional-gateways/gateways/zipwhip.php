<?php

namespace SMSGateway;

require_once './utils.php';

class ZipWhip {
    // docs at: https://developers.zipwhip.com/?version=latest
    public static $auth_performed = false;
    public static $session_key = '';

    public static function sendSMS($gateway_fields, $mobile, $message, $test_call) {
        $username = $gateway_fields['username'];
        $password = $gateway_fields['password'];
        $session_key = self::_obtain_auth_credentials($username, $password);

        if (!self::$auth_performed) return false;

        return self::process_sms($session_key, $mobile, $message, $test_call);
    }

    public static function sendBulkSMS($gateway_fields, $messages, $test_call) {
        $username = $gateway_fields['username'];
        $password = $gateway_fields['password'];
        $session_key = self::_obtain_auth_credentials($username, $password);

        if (!self::$auth_performed) return false;

        return self::process_sms($session_key, $mobile, $message, $test_call);
    }


    public static function _obtain_auth_credentials($user, $password) {
        if ($auth_performed) {
            return self::$session_key;
        }
        $curl = curl_init();

        $post_params = array(
            'username' => $user,
            'password' => $password,
        );

        curl_setopt(
            $curl,
            CURLOPT_URL,
            'https://api.zipwhip.com/user/login'
        );
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, true);
        $result = curl_exec($curl);
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curl_error = curl_errno($curl);
        curl_close($curl);

        if ($curl_error !== 0) {
            return false;
        }

        $is_success = 200 <= $code && $code < 300;

        if ($is_success) {
            $auth_credentials_decoded = json_decode($result);
            self::$session_key = $auth_credentials_decoded['response'];
            self::$auth_performed = true;
            return $session_key;
        }

        return '';
    }

    public static function process_sms($sesion_id, $messages, $test_call) {
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

            $post_parmas = array(
                'session' => $session_key,
                'contacts' => join(',', $mobiles),
                'body' => $fixed_message,
            );
            $encoded_query = http_build_query($params);
            curl_setopt($curl, CURLOPT_URL, 'https://api.zipwhip.com/message/send');
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);

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
