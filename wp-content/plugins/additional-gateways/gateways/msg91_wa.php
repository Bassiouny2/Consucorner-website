<?php

namespace SMSGateway;

class MSG91_WhatsApp
{

    public static function sendWhatsapp($gateway_fields, $mobile, $message, $test_call)
    {
        return self::process_whatsapp($gateway_fields, $mobile, $message, $test_call);
    }

    public static function process_whatsapp($gateway_fields, $mobile, $message, $test_call)
    {

        $auth_key = $gateway_fields['auth_key'];
        $from = $gateway_fields['from'];
        $namespace = $gateway_fields['namespace'];

        $template_ids = array('language', 'template-name');
        $params_values = array();

        if (defined('DIGITS_OTP')) {
            $otp = constant('DIGITS_OTP');
            $params_values[] = $otp;
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
            foreach ($params_values as $params_value) {
                $params[] = array('type' => 'text', 'text' => strval($params_value));
            }
        }

        $template_name = $template['template-name'];
        $payload = array(
            "to" => $mobile,
            "type" => "template",
            "template" => [
                "name" => $template_name,
                "language" => [
                    "code" => $template['language'],
                    "policy" => "deterministic"
                ],
                "components" => [
                    [
                        "type" => "body",
                        "parameters" => $params
                    ]
                ],
            ],
            "messaging_product" => "whatsapp"
        );

        $data = array(
            "integrated_number" => $from,
            "content_type" => "template",
            "payload" => $payload
        );

        $url = 'https://api.msg91.com/api/v5/whatsapp/whatsapp-outbound-message/';
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'accept: application/json',
            "authkey: " . $auth_key,
            'content-type: application/json'
        ));

        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");

        $answer = curl_exec($curl);

        if ($test_call) {
            return $answer;
        }

        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);

        if (empty($answer)) {
            return false;
        }

        return true;

    }

}
