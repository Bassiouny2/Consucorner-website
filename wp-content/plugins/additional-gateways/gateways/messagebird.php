<?php


namespace SMSGateway;


class MessageBird
{
    public static function sendWhatsapp($gateway_fields, $mobile, $message, $test_call)
    {
        return self::process_whatsapp($gateway_fields, $mobile, $message, $test_call);
    }

    public static function process_whatsapp($gateway_fields, $mobile, $message, $testCall)
    {
        $accesskey = $gateway_fields['accesskey'];
        $channel_id = $gateway_fields['channel_id'];

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
                $params[] = array('default' => $params_value);
            }
        }
        $hsm = array(
            'namespace' => $template['namespace'],
            'templateName' => $template['template-name'],
            'language' => array(
                'policy' => 'deterministic',
                'code' => $template['language']
            ),
            'params' => $params
        );
        $data = array(
            'to' => $mobile,
            'type' => 'hsm',
            'from' => $channel_id,
            'content' => array('hsm' => $hsm),
        );

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: AccessKey ' . $accesskey
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        curl_setopt($ch, CURLOPT_URL, 'https://conversations.messagebird.com/v1/send');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        $result = curl_exec($ch);
        curl_close($ch);

        if (curl_errno($ch)) {
            if ($testCall) {
                return "curl error:" . curl_errno($ch);
            }

            return false;
        }

        if ($testCall) {
            return $result;
        }

        if ($result === false) {
            return false;
        }

        return true;

    }
}
