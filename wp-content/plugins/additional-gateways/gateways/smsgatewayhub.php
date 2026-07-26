<?php

namespace SMSGateway;


class SMSGatewayHub
{
    // docs at: https://www.smsgatewayhub.com/https-api.aspx
    public static function sendSMS($gateway_fields, $mobile, $message, $test_call)
    {
        return self::process_sms($gateway_fields, $mobile, $message, $test_call);
    }

    public static function process_sms($gateway_fields, $mobile, $message, $test_call)
    {
        $api_key = $gateway_fields['api_key'];
        $sender = $gateway_fields['sender'];
        $entityId = $gateway_fields['entity-id'];


        if(isset($gateway_fields['dlt-template-id'])){
            $template_id = $gateway_fields['dlt-template-id'];
        }

        $curl = curl_init();

        $template_ids = array('template-id','message');

        $message_obj = wpn_parse_message_template($message, $template_ids);

        $params = array(
            'APIKey' => $api_key,
            'senderid' => $sender,
            'channel' => 2,
            'number' => str_replace("+", "", $mobile),
            'DCS' => 0,
            'flashsms' => 0
        );

        if (is_array($message_obj) && !empty($message_obj['template'])) {

            $message_template = $message_obj['template'];

            $message = $message_template['message'];
            if (isset($message_template['template-id'])) {
                $template_id = $message_template['template-id'];
            }
        }


        $params['EntityId'] = $entityId;
        $params['dlttemplateid'] = $template_id;


        $params['text'] = $message;
        $encoded_query = http_build_query($params);

        curl_setopt($curl, CURLOPT_URL, 'https://www.smsgatewayhub.com/api/mt/SendSMS?' . $encoded_query);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($curl);
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curl_error = curl_errno($curl);
        curl_close($curl);

        if ($test_call) return $result;

        if ($curl_error !== 0) {
            return false;
        }

        $is_success = 200 <= $code && $code < 300;

        return $is_success;
    }

}
