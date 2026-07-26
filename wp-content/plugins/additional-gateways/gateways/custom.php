<?php

namespace SMSGateway;

class CustomGateway
{

    public static function process_message($gateway, $countrycode,$mobile, $messagetemplate, $testCall)
    {
        $url = $gateway['gateway_url'];
        $http_method = $gateway['http_method'];
        $send_body_data = $gateway['send_body_data'];
        $http_headers = explode(',', $gateway['http_header']);

        $attrs = stripslashes($gateway['gateway_attributes']);

        $sender_id = $gateway['sender_id'];

        $number_type = $gateway['phone_number'];

        if (strtolower($http_method) == 'get') {
            // $sender_id = rawurlencode($sender_id);
        }

        $template_ids = array('message', 'template-id');

        $template_id = '';
        $message_obj = wpn_parse_message_template($messagetemplate, $template_ids);
        if (is_array($message_obj) && !empty($message_obj['template'])) {
            $message_template = $message_obj['template'];
            $message = $message_template['message'];

            if (isset($message_template['template-id'])) {
                $template_id = $message_template['template-id'];
            }
        } else {
            $message = $messagetemplate;
        }


        if (!empty($gateway['encode_message'])) {
            $encode_message = $gateway['encode_message'];
            if ($encode_message == 1) {
                $message = urlencode($message);
            } else if ($encode_message == 2) {
                $message = UnitedOver_convertToUnicode($message);
            }else if ($encode_message == 3) {
                $message = rawurlencode($message);
            }
        }

        if ($number_type == 1) {
            $to = $countrycode . $mobile;
        } else if ($number_type == 2) {
            $to = str_replace("+", "", $countrycode) . $mobile;
        } else {
            $to = $mobile;
        }


        $attrs = str_replace(array("\r", "\n"), '', $attrs);
        $attrs = explode(',', $attrs);

        $placeholder = array('{to}', '{sender_id}', '{message}', '{template_id}');
        $placeholder_values = array($to, $sender_id, $message, $template_id);
        $url = str_replace($placeholder, $placeholder_values, $url);

        if (sizeof($attrs) == 1) {
            $attrs = implode(",", $attrs);
            $data = str_replace($placeholder, $placeholder_values, $attrs);
        } else {
            $data = array();
            foreach ($attrs as $attr) {
                $params = explode(':', $attr);
                if (empty($params)) continue;

                $params = explode(':', $attr, 2);
                if (empty($params)) continue;
                $attr_value = '';
                if (isset($params[1]))
                    $attr_value = str_replace($placeholder, $placeholder_values, $params[1]);

                $data[trim($params[0])] = trim($attr_value);
            }
        }

        $ch = curl_init();

        if (strtolower($http_method) == 'get') {
            $url = $url . '?' . http_build_query($data);
        } else {
            if ($send_body_data == 1 && is_array($data)) {
                $data = json_encode($data);
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }

        if (!empty($http_headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $http_headers);
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $http_method);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);

        curl_close($ch);

        if ($testCall) {
            return $response;
        }

        if ($response === false) {
            return false;
        }

        return true;
    }

}
