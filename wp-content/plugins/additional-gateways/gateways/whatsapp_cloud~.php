<?php

namespace SMSGateway;

class WhatsappCloud
{

    public static function sendWhatsapp($gateway_fields, $mobile, $message, $test_call)
    {
        return self::process_whatsapp($gateway_fields, $mobile, $message, $test_call);
    }

    public static function process_whatsapp($gateway_fields, $mobile, $message, $test_call)
    {

        $access_token = $gateway_fields['access_token'];
        $from_number_id = $gateway_fields['from_number_id'];

        $template_ids = array('template-name', 'namespace', 'language');
        $params_values = array();

        $otp = false;
        $component = array();

        if (defined('DIGITS_OTP')) {
            $otp = constant('DIGITS_OTP');
            $params_values = digits_get_wa_gateway_templates($message, $otp);
        }

        if (isset($gateway_fields['template-name'])) {
            $template = $gateway_fields;
        } else {
            $whatsapp = wpn_parse_message_template($message, $template_ids);

            if (empty($whatsapp) || !is_array($whatsapp)) {
                return false;
            }
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

        $component[] = array(
            'type' => 'body',
            'parameters' => $params
        );
        if (!empty($otp)) {
            $component[] = array(
                'type' => 'button',
                'sub_type' => 'url',
                'index' => '0',
                'parameters' => [array(
                    "type" => "text",
                    "text" => $otp,
                )]
            );
        }

        $hsm = array(
            //'namespace' => $template['namespace'],
            'name' => $template['template-name'],
            'language' => array(
                'policy' => 'deterministic',
                'code' => $template['language']
            ),
            'components' => $component
        );

        $data = array(
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'type' => 'template',
            'to' => $mobile,
            'template' => $hsm,
        );

        $url = 'https://graph.facebook.com/v15.0/' . $from_number_id . '/messages';
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            "Authorization: Bearer " . $access_token,
            'Content-Type: application/json'
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
