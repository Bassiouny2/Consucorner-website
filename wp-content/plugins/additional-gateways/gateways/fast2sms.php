<?php

namespace SMSGateway;

class Fast2SMS
{

    public static function sendSMS($gateway_fields, $countrycode, $mobile, $message, $test_call)
    {
        return self::process_sms($gateway_fields, $countrycode, $mobile, $message, $test_call);
    }

    public static function process_sms($gateway_fields, $countrycode, $mobile, $message, $test_call)
    {

        $api_key = $gateway_fields["api_key"];
        $sender_id = $gateway_fields['sender_id'];


        $template_ids = array('template-id');
        $params_values = array();

        if (defined('DIGITS_OTP')) {
            $otp = constant('DIGITS_OTP');
            $params_values = digits_get_wa_gateway_templates($message, $otp);
        }

        if (isset($gateway_fields['template-id'])) {
            $template = $gateway_fields['template-id'];
        } else {
            $parse_template = wpn_parse_message_template($message, $template_ids);
            $template = $parse_template['template'];
            $params_values = $parse_template['params'];
        }

        $params = array();

        if (!empty($params_values)) {
            ksort($params_values);
            foreach ($params_values as $params_value) {
                $params[] = strval($params_value);
            }
        }


        $data = array(
            "authorization" => $api_key,
            "message" => $template,
            "sender_id" => $sender_id,
            "route" => "dlt",
            "numbers" => str_replace("+", "", $mobile),
            "flash" => 0,
        );
        if (!empty($params)) {
            $data['variables_values'] = implode("|", $params);
        }

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://www.fast2sms.com/dev/bulkV2?' . http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_HTTPHEADER => array(
                "content-type: application/json"
            ),
        ));

        $answer = curl_exec($curl);


        if ($test_call) {
            return $answer;
        }

        if (curl_errno($curl)) {
            return false;
        }

        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($http_code != 200) {
            return false;
        }

        curl_close($curl);

        if (empty($answer)) {
            return false;
        }

        return $answer;

    }

}
