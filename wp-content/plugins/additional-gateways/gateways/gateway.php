<?php

namespace SMSGateway;

require_once 'utils.php';

class Gateway
{
    public static $chunks = 50;
    public static $supports_bulk = true;
    public static $bulk_type = 'FIXED_MESSAGE';

    // docs at: https://gateway.sa/wp-content/uploads/SendingAPI-English-Gateway.sa_.pdf
    public static function sendSMS($gateway_fields, $mobile, $message, $test_call)
    {

        return self::process_sms($gateway_fields, $mobile, $message, $test_call);

    }

    public static function sendBulkSMS($gateway_fields, $messages, $test_call)
    {
        return self::process_sms($gateway_fields, '', $messages, $test_call);
    }

    public static function process_sms($gateway_fields, $mobile, $messages, $test_call)
    {

        $username = $gateway_fields['username'];
        $password = $gateway_fields['password'];
        $sender = $gateway_fields['sender'];

        $curl = curl_init();
        $post_params = array(
            'api_id' => $username,
            'api_password' => $password,
            'sender_id' => $sender,
            'sms_type' => 'T',
            'encoding' => 'T',
            'textmessage' => $messages,
            'phonenumber' => str_replace("+", "", $mobile),
        );


        $template_ids = array('template-id', 'message');
        $params_values = array();

        if (!empty($gateway_fields['template_id']) && defined('DIGITS_OTP')) {
            $post_params['templateid'] = $gateway_fields['template_id'];
            $post_params['V1'] = constant('DIGITS_OTP');
        } else {
            $message_obj = wpn_parse_message_template($messages, $template_ids);
            if (!empty($message_obj['template'])) {
                $template = $message_obj['template'];
                $post_params['templateid'] = $template['template-id'];
                $post_params['textmessage'] = $template['message'];

                if (!empty($message_obj['params'])) {
                    $params_values = $message_obj['params'];
                    $post_params = array_merge($params_values, $post_params);
                }

            }
        }


        curl_setopt($curl, CURLOPT_URL, 'https://rest.gateway.sa/api/SendSMS?' . http_build_query($post_params));
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($curl);
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curl_error = curl_errno($curl);
        curl_close($curl);

        if ($test_call) {
            return $result;
        }

        $is_success = 200 <= $code && $code < 300;

        return true;

    }
}
