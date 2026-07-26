<?php

namespace SMSGateway;

require_once 'utils.php';

class SendSMS247 {
    public static $chunks = 50;
    public static $supports_bulk = true;
    public static $bulk_type = 'FIXED_MESSAGE';

    // docs at: https://sendsms247.com/index.php/api
    public static function sendSMS($gateway_fields, $mobile, $message, $test_call) {
        $username = $gateway_fields['username'];
        $password = $gateway_fields['password'];
        $sender = $gateway_fields['sender'];

        $last_sent_or_results = self::process_sms($username, $password, $sender, [0 => [$mobile => $message]], $test_call);
        if ($test_call) return $last_sent_or_results[0];

        if ($last_sent_or_results === -1) {
            return false;
        }
        return true;
    }

    public static function sendBulkSMS($gateway_fields, $messages, $test_call) {
        $username = $gateway_fields['username'];
        $password = $gateway_fields['password'];
        $sender = $gateway_fields['sender'];

        return self::process_sms($username, $password, $sender, $messages, $test_call);
    }

    public static function process_sms($username, $password, $sender, $messages, $test_call) {
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

            $params = array(
                'username' => $api_key,
                'password' => $api_secret,
                'sender' => $sender,
                'recipient' => join(',', $mobiles),
                'message' => $fixed_message,
            );
            $encoded_query = http_build_query($params);
            curl_setopt($curl, CURLOPT_URL, 'http://www.sendsms247.com/components/com_smsreseller/smsapi.php?'. $encoded_query);
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

            $result = curl_exec($curl);
            $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curl_error = curl_errno($curl);
            curl_close($curl);

            if($test_call) {
                $results[] = $result;
            }

            $is_success = 200 <= $code && $code < 300;

            if ($is_success && $curl_error !== 0) {
                list($result, $credits, $failed_numbers) = explode(' ', $result);
                $failed_numbers = explode(',', $failed_numbers);
                foreach($failed_numbers as $failed_number) {
                    $failed_sent[] = $failed_number;
                }
            } else {
                $failed_sent += $mobiles;
            }
        }

        if($test_call) return $results;

        return \last_sent_from_failed($messages, $failed_sent);
    }
}
