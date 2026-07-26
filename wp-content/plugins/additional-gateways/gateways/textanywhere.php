<?php

namespace SMSGateway;

require_once 'utils.php';

class TextAnywhere {
    public static $chunks = 999;
    public static $supports_bulk = true;
    public static $bulk_type = 'FIXED_MESSAGE';

    // docs at: https://developer.textapp.net/HTTPService/Definition.aspx
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
        $results = [];
        $failed_sent = [];
        $fixed_message = '';

        foreach($chunked_messages as $message_batch) {
            $mobiles = [];

            foreach($message_batch as $id => $message_descriptor) {
                foreach($message_descriptor as $mobile => $message) {
                    $fixed_message = $message;
                    $mobiles[] = $mobile;
                }
            }

            $post_params = array(
                'method' => 'sendsms',
                'returncsvstring' => true,
                'externallogin' => $username,
                'password' => $password,
                'originator' => $sender,
                'destinations' => join(',', $mobiles),
                'body' => $fixed_message,
            );

            curl_setopt($curl, CURLOPT_URL, 'http://www.textapp.net/webservice/httpservice.aspx');
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $post_params);
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
                // result will be #{transaction_code}# {number}:{code}
                // where transaction_code == 1 is OK and code == 1 OK
                $exploded_result = expode(' ', $result);
                $transaction_code = $exploded_result[0];

                if ($transaction_code == '#1#') {
                    $dests_with_status = array_slice($exploded_result, 1);
                    foreach($dests_with_status as $dest_with_status) {
                        list($dest, $status) = explode(':', $dest_with_status);

                        if ($status != '1') {
                            $failed_sent[] = $dest;
                        }
                    }
                }
            } else {
                $failed_sent += $mobiles;
            }
        }

        if($test_call) return $results;

        return \last_sent_from_failed($messages, $failed_sent);
    }
}
