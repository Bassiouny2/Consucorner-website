<?php

namespace SMSGateway;

class Whatsapp_360Dialog
{

    public static function sendWhatsapp($gateway_fields, $mobile, $message, $test_call)
    {
        return self::process_whatsapp($gateway_fields, $mobile, $message, $test_call);
    }

    public static function process_whatsapp($gateway_fields, $mobile, $message, $test_call)
    {

        $access_token = $gateway_fields['api_key'];

        $wa_id = self::get_wa_id($access_token, $mobile, $test_call);

        if (!$wa_id) {
            return false;
        }


        $template_ids = array('template-name', 'namespace', 'language');
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
        $hsm = array(
            'namespace' => $template['namespace'],
            'name' => $template['template-name'],
            'language' => array(
                'policy' => 'deterministic',
                'code' => $template['language']
            ),
            'components' => [
                array(
                    'type' => 'body',
                    'parameters' => $params
                )
            ]
        );
        $data = array(
            'to' => $wa_id,
            'type' => 'template',
            'template' => $hsm,
        );

        $curl = curl_init('https://waba.360dialog.io/v1/messages');
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            "D360-API-KEY: " . $access_token,
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

    static function get_wa_id($access_token, $phone, $test_call)
    {
        $data = array(
            'blocking' => 'wait',
            'contacts[]' => $phone,
        );

        $url = 'https://waba.360dialog.io/v1/contacts';
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            "D360-API-KEY: " . $access_token
        ));

        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);

        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        $result = curl_exec($curl);

        $result = json_decode($result, true);
        if (empty($result) || empty($result['contacts'][0])) {
            if ($test_call) {
                print_r($result);
            }
            return false;
        }
        $contact = $result['contacts'][0];
        $status = $contact['status'];
        if ($status == 'valid') {
            return $contact['wa_id'];
        } else {
            if ($test_call) {
                print_r($result);
            }
            return false;
        }

    }

}
