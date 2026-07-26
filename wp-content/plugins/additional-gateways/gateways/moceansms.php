<?php

namespace SMSGateway;

require_once 'utils.php';

class MoceanSMS {
    public static $chunks = 50;
    public static $supports_bulk = true;
    public static $bulk_type = 'FIXED_MESSAGE';

    // docs at: https://moceanapi.com/docs/
    public static function sendSMS($gateway_fields, $mobile, $message, $test_call) {
        $api_key = $gateway_fields['api_key'];
        $api_secret = $gateway_fields['api_secret'];
        $sender = $gateway_fields['sender'];

        $last_sent_or_results = self::process_sms($api_key, $api_secret, $sender, [0 => [$mobile => $message]], $test_call);
        if ($test_call) return $last_sent_or_results[0];

        if ($last_sent_or_results === -1) {
            return false;
        }
        return true;
    }

    public static function sendBulkSMS($gateway_fields, $messages, $test_call) {
        $api_key = $gateway_fields['api_key'];
        $api_secret = $gateway_fields['api_secret'];
        $sender = $gateway_fields['sender'];

        return self::process_sms($api_key, $api_secret, $sender, $messages, $test_call);
    }

    public static function process_sms($api_key, $api_secret, $sender, $messages, $test_call) {
        $curl = curl_init();

        $chunked_messages = array_chunk($messages, self::$chunks);
        $results = [];
        $successfully_sent = [];

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
                'mocean-api-key' => $api_key,
                'mocean-api-secret' => $api_secret,
                'mocean-from' => $sender,
                'mocean-to' => join(',', $mobiles),
                'mocean-text' => $fixed_message,
            );

            curl_setopt($curl, CURLOPT_URL, 'https://rest.moceanapi.com/rest/2/sms');
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
                $decoded_result = json_decode($result, true);
                foreach($decoded_result['messages'] as $sent_message) {
                    if ($sent_message['status'] === 0) {
                        $successfully_sent[] = $sent_message['receiver'];
                    }
                }
            }
        }

        if($test_call) return $results;

        return \last_sent_from_successful($messages, $successfully_sent);
    }
}
