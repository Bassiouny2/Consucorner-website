<?php

namespace SMSGateway;

class Whatsapp_wati
{

    public static function sendWhatsapp($gateway_fields, $mobile, $message, $test_call)
    {
        return self::process_whatsapp($gateway_fields, $mobile, $message, $test_call);
    }

    public static function process_whatsapp($gateway_fields, $mobile, $message, $test_call)
    {

        $api_key = $gateway_fields['access_token'];
        $url = $gateway_fields['base_url'] . '/api/v1/sendTemplateMessage';

        $template_ids = array('broadcast-name', 'template-name');

        $data = array();

        if (defined('DIGITS_OTP')) {
            $otp = constant('DIGITS_OTP');
            $params_values = digits_get_wa_gateway_templates($message, $otp);

            $data['broadcast_name'] = $gateway_fields['broadcast-name'];
            $data['template_name'] = $gateway_fields['template-name'];
            $data['parameters'] = array();

            $data['parameters'][] = array('name' => 'otp', 'value' => $otp);

            if (sizeof($params_values) > 1) {
                $blog_name = get_option('blogname');
                $data['parameters'][] = array('name' => 'name', 'value' => $blog_name);
            }

        } else {
            $obj = wpn_parse_message_template($message, $template_ids);


            $template = $obj['template'];
            $data['broadcast_name'] = $template['broadcast-name'];
            $data['template_name'] = $template['template-name'];

            $params = array();
            foreach ($obj['params'] as $key => $value) {
                $params[] = ['name' => $key, 'value' => $value];
            }
            $data['parameters'] = $params;

        }

        $get = array(
            'whatsappNumber' => $mobile
        );
        $url .= '?' . http_build_query($get);

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-Type: text/json',
            'Authorization: ' . $api_key
        ));

        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");

        $answer = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($test_call) {
            return $answer;
        }

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
