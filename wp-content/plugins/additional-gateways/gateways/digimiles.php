<?php

namespace SMSGateway;


class Digimiles
{
    // docs at: https://www.digimiles.in/downloads.html
    public static function sendSMS($gateway_fields, $mobile, $message, $test_call)
    {
        return self::process_sms($gateway_fields, $mobile, $message, $test_call);
    }

    public static function process_sms($gateway_fields, $mobile, $message, $test_call)
    {

        $username = $gateway_fields['username'];
        $password = $gateway_fields['password'];
        $sender = $gateway_fields['sender'];

        $entity_id = $gateway_fields['entity_id'];

        $gateway_ip = $gateway_fields['ip_addr'];
        $gateway_ip_port = $gateway_fields['port'];

        $url = $gateway_ip . ':' . $gateway_ip_port;
        $curl = curl_init();
        $params = array(
            'username' => $username,
            'password' => $password,
            'source' => $sender,
            'destination' => $mobile,
            'message' => $message,
            'type' => 0,
            'entityid='=>$entity_id
        );

        if(isset($gateway_fields['template_id'])){
            $params['tempid'] = $gateway_fields['template_id'];
        }else{
            $template_ids = array('message', 'template-id');
            $message_obj = wpn_parse_message_template($message, $template_ids);

            if(is_array($message_obj)){
                $template = $message_obj['template'];
                if(isset($template['template-id'])){
                    $params['tempid'] = $template['template-id'];
                    $params['message'] = $template['message'];

                }
            }
        }





        $encoded_query = http_build_query($params);
        curl_setopt($curl, CURLOPT_URL, 'http://' . $url . '/bulksms/bulksms?' . $encoded_query);
        curl_setopt($curl, CURLOPT_POST, 1);
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
