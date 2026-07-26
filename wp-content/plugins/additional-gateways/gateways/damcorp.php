<?php

namespace SMSGateway;

class Whatsapp_damcorp
{

    public static function sendWhatsapp($gateway_fields, $mobile, $message, $test_call)
    {
        return self::process_whatsapp($gateway_fields, $mobile, $message, $test_call);
    }

    public static function process_whatsapp($gateway_fields, $mobile, $message, $test_call)
    {

        $access_token = $gateway_fields['api_token'];


        $template_ids = array('template-name');
        $params_values = array();

        if (defined('DIGITS_OTP')) {
            $otp = constant('DIGITS_OTP');
            $params_values = digits_get_wa_gateway_templates($message, $otp);
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
                $params[] = strval($params_value);
            }
        }
        $data = array(
            'token' => $access_token,
            'to' => $mobile,
            'param' => $params,
        );
        $template_name = $template['template-name'];

        $curl = curl_init('https://icwaba.damcorp.id/whatsapp/sendHsm/' . $template_name);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
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

        if ($http_code != 200) {
            return false;
        }

        curl_close($curl);

        if (empty($answer)) {
            return false;
        }

        return true;

    }

}
