<?php

namespace SMSGateway;

require_once 'utils.php';

class Aruba
{
    public static $chunks = 50;
    public static $supports_bulk = true;
    public static $bulk_type = 'FIXED_MESSAGE';

    // docs at: https://hosting.aruba.it/servizio-sms/sviluppatori-api-sdk-sms-aruba.aspx
    public static function sendSMS($gateway_fields, $mobile, $message, $test_call)
    {
        $username = $gateway_fields['username'];
        $psasword = $gateway_fields['password'];
        $sender = $gateway_fields['sender'];

        $last_sent_or_results = self::process_sms($username, $password, $sender, [0 => [$mobile => $message]], $test_call);
        if ($test_call) return $last_sent_or_results[0];

        if ($last_sent_or_results === -1) {
            return false;
        }
        return true;
    }

    public static function sendBulkSMS($gateway_fields, $messages, $test_call)
    {
        $username = $gateway_fields['username'];
        $password = $gateway_fields['password'];
        $sender = $gateway_fields['sender'];

        return self::process_sms($username, $password, $sender, $messages, $test_call);
    }

    public static function get_login($username, $password, $test_call)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://smspanel.aruba.it/API/v1.0/REST/login');

        curl_setopt($ch, CURLOPT_USERPWD, $username . ':' . $password);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);

        if ($test_call) {
            print_R($response);
        }
        if ($info['http_code'] != 201) {
            return false;
        }
        $values = explode(";", $response);
        return array('user_key' => $values[0], 'session_key' => $values[1]);
    }

    public static function process_sms($username, $password, $sender, $messages, $test_call)
    {

        $auth = self::get_login($username, $password, $test_call);
        if (!$auth) {
            return false;
        }
        $curl = curl_init();

        $chunked_messages = array_chunk($messages, self::$chunks);
        $results = [];
        $failed_sent = [];
        $fixed_message = '';

        foreach ($chunked_messages as $message_batch) {
            $mobiles = [];

            foreach ($message_batch as $id => $message_descriptor) {
                foreach ($message_descriptor as $mobile => $message) {
                    $fixed_message = $message;
                    $mobiles[] = $mobile;
                }
            }

            $post_params = array(
                'sender' => $sender,
                'recipient' => join(',', $mobiles),
                'message' => $fixed_message,
                'message_type' => 'N',
            );

            curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                'Content-type: application/json',
                'user_key: ' . $auth['user_key'],
                'Session_key: ' . $auth['session_key'],
            ));

            curl_setopt($curl, CURLOPT_URL, 'https://smspanel.aruba.it/API/v1.0/REST/sms');
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $post_params);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

            $result = curl_exec($curl);
            $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curl_error = curl_errno($curl);
            curl_close($curl);

            if ($test_call) {
                $results[] = $result;
            }

            $is_success = 200 <= $code && $code < 300;

            if ($is_success && $curl_error !== 0) {
            } else {
                $failed_sent += $mobiles;
            }
        }

        if ($test_call) return $results;

        return \last_sent_from_failed($messages, $failed_sent);
    }
}
