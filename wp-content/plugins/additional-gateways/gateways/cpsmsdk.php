<?php

namespace SMSGateway;

require_once 'utils.php';

class CPSMSDK {
    public static $chunks = 50;
    public static $supports_bulk = true;
    public static $bulk_type = 'FIXED_MESSAGE';

    // docs at: https://api.cpsms.dk/documentation/index.html#send
    public static function sendSMS($gateway_fields, $mobile, $message, $test_call) {
        $username = $gateway_fields['username'];
        $api_token = $gateway_fields['api_token'];
        $sender = $gateway_fields['sender'];

        $last_sent_or_results = self::process_sms($username, $api_token, $sender, [0 => [$mobile => $message]], $test_call);
        if ($test_call) return $last_sent_or_results[0];

        if ($last_sent_or_results === -1) {
            return false;
        }
        return true;
    }

    public static function sendBulkSMS($gateway_fields, $messages, $test_call) {
        $username = $gateway_fields['username'];
        $api_token = $gateway_fields['api_token'];
        $sender = $gateway_fields['sender'];

        return self::process_sms($username, $api_token, $sender, $messages, $test_call);
    }

    public static function process_sms($username, $api_token, $sender, $messages, $test_call) {
        $curl = curl_init();
        $failed_sent = [];
        $chunked_messages = array_chunk($messages, self::$chunks);
        $results = [];
        $fixed_message = '';
        $authorization_token = base64_encode($username . ':' . $api_token);
        foreach($chunked_messages as $message_batch) {
            $mobiles = [];

            foreach($message_batch as $id => $message_descriptor) {
                foreach($message_descriptor as $mobile => $message) {
                    $fixed_message = $message;
                    $mobiles[] = $mobile;
                }
            }

            $data = array(
                'from' => $sender,
                'to' => $mobiles,
                'message' => $fixed_message,
            );

            curl_setopt($curl, CURLOPT_URL, 'https://api.cpsms.dk/v2/send');
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($curl, CURLOPT_POST, 1);

            curl_setopt(
                $curl,
                CURLOPT_HTTPHEADER,
                array(
                    "Content-Type: application/json",
                    "Authorization: Basic " . $authorization_token,
                )
            );
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
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
                $decoded_result = json_decode($result, true);
                foreach($decoded_result as $status => $description) {
                    if ($status === 'error') {
                        $failed_sent[] = $description['to'];
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
