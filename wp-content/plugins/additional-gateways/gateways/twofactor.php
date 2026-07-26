<?php

namespace SMSGateway;

require_once 'utils.php';

class TwoFactor
{
    public static $chunks = 50;
    public static $supports_bulk = true;
    public static $bulk_type = 'FIXED_MESSAGE';

    // docs at: https://2fa.api-docs.io/v1/send-promotional-sms/send-promotional-sms
    public static function sendSMS($gateway_fields, $mobile, $message, $test_call)
    {
        $last_sent_or_results = self::process_sms($gateway_fields, $mobile, $message, $test_call);
        if ($test_call) return $last_sent_or_results;

        return true;
    }

    public static function process_sms($gateway_fields, $mobile, $message, $test_call)
    {
        $api_key = $gateway_fields['api_key'];
        $sender = $gateway_fields['sender'];

        $template_ids = array('template-name');
        $params_values = array();
        $template = false;

        if (defined('DIGITS_OTP')) {
            $otp = constant('DIGITS_OTP');
            $params_values = [0 => $otp];
        }

        if (isset($gateway_fields['template-name'])) {
            $template = $gateway_fields;
        } else {
            $whatsapp = wpn_parse_message_template($message, $template_ids);
            $template = $whatsapp['template'];
            $params_values = $whatsapp['params'];
        }

        $params = array();

        if (!empty($params_values)) {
            ksort($params_values);
            foreach ($params_values as $key => $params_value) {
                $key++;
                $params['VAR' . $key] = $params_value;
            }
        }
        $curl = curl_init();
        $post_params = array(
            'From' => $sender,
            'To' => str_replace("+", "", $mobile),
            'Msg' => $message,
        );

        if (!empty($template)) {
            $template_name = $template['template-name'];
            $post_params['TemplateName'] = $template_name;
            if (!empty($params)) {
                $post_params = array_merge($post_params, $params);
            }
        }

        curl_setopt($curl, CURLOPT_URL, 'https://2factor.in/API/V1/' . $api_key . '/ADDON_SERVICES/SEND/TSMS');
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $post_params);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($curl);
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curl_error = curl_errno($curl);
        curl_close($curl);

        if ($test_call) {
            return $result;
        } else {
            return true;
        }
        $is_success = 200 <= $code && $code < 300;

        if ($is_success && $curl_error !== 0) {
            return true;
        } else {
            $failed_sent += $mobiles;
        }


        if ($test_call) return $results;

        return \last_sent_from_failed($messages, $failed_sent);
    }

    public static function sendBulkSMS($gateway_fields, $messages, $test_call)
    {
    }
}
