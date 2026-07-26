<?php

namespace SMSGateway;

require_once 'utils.php';

class MimSMS
{
    public static $chunks = 50;
    public static $supports_bulk = true;
    public static $bulk_type = 'FIXED_MESSAGE';

    // docs at: https://www.mimsms.com/files/Brand%20SMS%20HTTP%20API.pdf
    public static function sendSMS($gateway_fields, $mobile, $message, $test_call)
    {

        $last_sent_or_results = self::process_sms($gateway_fields, [0 => [$mobile => $message]], $test_call);
        if ($test_call) return $last_sent_or_results[0];

        if ($last_sent_or_results === -1) {
            return false;
        }
        return true;
    }

    public static function process_sms($gateway_fields, $messages, $test_call)
    {
        $username = $gateway_fields['username'];
        $api_key = $gateway_fields['api_key'];
        $api_token = $gateway_fields['api_token'];
        $sender = $gateway_fields['sender'];
        $portal = $gateway_fields['portal'];

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
                    $mobiles[] = str_replace("+", "", $mobile);
                }
            }


            if ($portal == 'bd_portal') {
                $params = array(
                    'sendsms' => '',
                    'type' => 'unicode',
                    'apitoken' => $api_token,
                    'apikey' => $api_key,
                    'to' => join(',', $mobiles),
                    'from' => $sender,
                    'text' => $fixed_message,
                );

                $base_url = 'https://www.mimsms.com.bd';
                if (!empty($gateway_fields['base_url'])) {
                    $base_url = $gateway_fields['base_url'];
                }
                $url = $base_url . "/smsAPI?" . http_build_query($params);

                curl_setopt($curl, CURLOPT_URL, $url);

                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'GET');
            } else {
                $post_params = array(
                    'Apikey' => $api_key,
                    'Username' => $username,
                    'TransactionType' => 'T',
                    'MobileNumber' => join(',', $mobiles),
                    'Message' => $fixed_message,
                    'SenderName' => $sender,
                );

                $base_url = 'https://api.mimsms.com';
                if (!empty($gateway_fields['base_url'])) {
                    $base_url = $gateway_fields['base_url'];
                }

                curl_setopt($curl, CURLOPT_URL, $base_url . '/api/SmsSending/Send?' . http_build_query($post_params));
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'GET');
            }

            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
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

    public static function sendBulkSMS($gateway_fields, $messages, $test_call)
    {
        return self::process_sms($gateway_fields, $messages, $test_call);
    }
}
