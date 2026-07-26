<?php

namespace SMSGateway\wa;

class Whatsapp_Kaylera
{
    public static function sendWhatsapp(
        $gateway_fields,
        $mobile,
        $message,
        $test_call
    )
    {
        return self::process_whatsapp(
            $gateway_fields,
            $mobile,
            $message,
            $test_call
        );
    }

    public static function process_whatsapp(
        $gateway_fields,
        $mobile,
        $message,
        $test_call
    )
    {
        $api_key = $gateway_fields['api_key'];
        $url = 'https://global.kaleyra.com/api/v4/?';

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


        $bodyValues = [];
        foreach ($params as $param) {
            $bodyValues[] = $param['text'];
        }

        $curl = curl_init();
        curl_setopt_array(
            $curl,
            array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => array(
                    'method' => 'wa',
                    'api_key' => $api_key,
                    'body' => json_encode(
                        array(
                            'to' => $mobile,
                            'type' => 'template',
                            'template' => array(
                                'namespace' => $template['namespace'],
                                'template_name' => $template['template-name'],
                                'policy' => 'deterministic',
                                'lang_code' => $template['language'],
                                'params' => $bodyValues,
                                'ttl' => '86400'
                            )
                        )
                    ),
                    'format' => 'json'
                ),
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