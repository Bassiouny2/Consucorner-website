<?php

namespace SMSGateway\wa;

class Whatsapp_Interakt
{
    public static function sendWhatsapp($gateway_fields, $countrycode, $mobile, $message, $test_call)
    {
        return self::process_whatsapp($gateway_fields, $countrycode, $mobile, $message, $test_call);
    }

    public static function process_whatsapp($gateway_fields, $countryCode, $phoneNumber, $message, $test_call)
    {


        $access_token = $gateway_fields['api_key'];
        $url = 'https://api.interakt.ai/v1/public/message/';

        $template_ids = array('template-name', 'language');
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
                $params[] = array('type' => 'text', 'text' => strval($params_value));
            }
        }

        $curl = curl_init($url);


        $bodyValues = [];
        foreach ($params as $param) {
            $bodyValues[] = $param['text'];
        }

        $data = array(
            'countryCode' => $countryCode,
            'phoneNumber' => $phoneNumber,
            'type' => 'Template',
            'template' => array(
                'name' => $template['template-name'],
                'languageCode' => $template['language'],
                'headerValues' => array(),
                'bodyValues' => $bodyValues,
            )
        );

        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt(
            $curl,
            CURLOPT_HTTPHEADER,
            array(
                'Authorization: Basic ' . $access_token,
                'Content-Type: application/json'
            )
        );


        $response = curl_exec($curl);

        if ($test_call) {
            return $response;
        }


        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);


        if (empty($response))
            return false;

        return true;
    }
}