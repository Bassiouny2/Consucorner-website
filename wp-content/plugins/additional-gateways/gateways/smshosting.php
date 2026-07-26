<?php

namespace SMSGateway;

require_once 'utils.php';

class SMSHosting {
    public static $chunks = 50;
    public static $supports_bulk = true;
    public static $bulk_type = 'FIXED_MESSAGE';

    // docs at: https://help.smshosting.it/it/docs/sms-rest-api/invio
    public static function sendSMS($gateway_fields, $mobile, $message, $test_call) {
        $auth_key = $gateway_fields['auth_key'];
        $auth_secret = $gateway_fields['auth_secret'];
        $sender = $gateway_fields['sender'];

        $last_sent_or_results = self::process_sms($auth_key, $auth_secret, $sender, [0 => [$mobile => $message]], $test_call);
        if ($test_call) return $last_sent_or_results[0];

        if ($last_sent_or_results === -1) {
            return false;
        }
        return true;
    }

    public static function sendBulkSMS($gateway_fields, $messages, $test_call) {
        $auth_key = $gateway_fields['auth_key'];
        $auth_secret = $gateway_fields['auth_secret'];
        $sender = $gateway_fields['sender'];

        return self::process_sms($auth_key, $auth_secret, $sender, $messages, $test_call);
    }

    public static function process_sms($auth_key, $auth_secret, $sender, $messages, $test_call) {
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
                'from' => $sender,
                'to' => join(',', $mobiles),
                'text' => $fixed_message,
            );

            curl_setopt($curl, CURLOPT_URL, 'https://api.smshosting.it/rest/api/sms/send');
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_USERPWD, $auth_key . ":" . $auth_secret);

            curl_setopt(
                $curl,
                CURLOPT_HTTPHEADER,
                array(
                    'Content-Type: application/x-www-form-urlencoded',
                )
            );

            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post_params));
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
                $decoded_result = json_decode($result);

                $smss = $decoded_result['sms'];
                foreach($smss as $sms) {
                    if ($sms['status'] != 'INSERTED') {
                        $failed_sent[] = $sms['to'];
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
