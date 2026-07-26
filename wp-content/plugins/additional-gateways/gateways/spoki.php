<?php

namespace SMSGateway;

class Whatsapp_spoki
{

    public static function sendWhatsapp($gateway_fields, $mobile, $message, $test_call)
    {
        return self::process_whatsapp($gateway_fields, $mobile, $message, $test_call);
    }

    public static function process_whatsapp($gateway_fields, $mobile, $message, $test_call)
    {

        $template_ids = array('type', 'template', 'automation');
        $api_key = $gateway_fields['api_key'];

        $obj = wpn_parse_message_template($message, $template_ids);

        $data = array(
            'phone' => $mobile,
        );

        $template = $obj['template'];

        if(empty($template)){
            if($test_call){
                return 'Invalid template';
            }
            return false;
        }

        if ($template['type'] == 'automation') {
            $url = 'https://app.spoki.it/wpnotif/automation/';
            $data['automation'] = $template['automation'];
        } else {
            $url = 'https://app.spoki.it/wpnotif/send/';
            $data['template'] = $template['template'];
        }
        $data['custom_fields'] = $obj['params'];

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'X-Secret: ' . $api_key
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
