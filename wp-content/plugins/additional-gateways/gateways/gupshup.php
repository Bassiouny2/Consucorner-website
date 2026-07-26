<?php

namespace SMSGateway;

require_once 'utils.php';

class GupShup
{
    public static $chunks = 50;
    public static $supports_bulk = true;
    public static $bulk_type = 'FIXED_MESSAGE';

    // docs at: https://www.gupshup.io/developer/ent-apis
    public static function sendSMS($gateway_fields, $mobile, $message, $test_call)
    {
        $api_key = $gateway_fields['api_key'];
        $sender = $gateway_fields['sender'];
        $app_id = $gateway_fields['app_id'];

        $mobile = str_replace("+", "", $mobile);
        $last_sent_or_results = self::process_sms($app_id, $api_key, $sender, [0 => [$mobile => $message]], $test_call);
        if ($test_call) return $last_sent_or_results[0];

        if ($last_sent_or_results === -1) {
            return false;
        }
        return true;
    }

    public static function sendBulkSMS($gateway_fields, $messages, $test_call)
    {
        return false;
    }

    public static function process_sms($app_id, $api_key, $sender, $messages, $test_call)
    {
        $curl = curl_init();
        $chunked_messages = array_chunk($messages, self::$chunks);
        $results = [];
        $failed_sent = [];
        $fixed_message = '';

        foreach ($chunked_messages as $message_batch) {
            $mobiles = [];
            $messages = [];
            foreach ($message_batch as $id => $message_descriptor) {
                foreach ($message_descriptor as $mobile => $message) {
                    $fixed_message = $message;
                    $mobiles[] = $mobile;
                }
            }

            $params = array(
                'source' => $sender,
                'destination' => join(',', $mobiles),
                'text' => $fixed_message,
                'api_key' => $api_key,
            );
            $encoded_query = http_build_query($params);


            curl_setopt($curl, CURLOPT_URL, 'http://api.gupshup.io/sms/v1/message/:' . $app_id);

            curl_setopt(
                $curl,
                CURLOPT_HTTPHEADER,
                array(
                    "Content-Type: application/x-www-form-urlencoded",
                    "Authorization: " . $api_key,
                )
            );
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $encoded_query);

            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

            $result = curl_exec($curl);
            $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            print_r($result);
            die();
            $curl_error = curl_errno($curl);
            curl_close($curl);

            if ($test_call) {
                $results[] = $result;
            }

            $is_success = 200 <= $code && $code < 300;

            if ($is_success && $curl_error !== 0) {
            } else {
                $failed_sent += $mobiles;
            }
        }

        if ($test_call) return $results;

        return \last_sent_from_failed($messages, $failed_sent);
    }

    public static function process_whatsapp($gateway_fields, $mobile, $message, $testCall)
    {
        $api_key = $gateway_fields['api_key'];
        $source = $gateway_fields['source'];

        $optin = self::optin_user($gateway_fields, $mobile);
        if ($testCall) {
            print_r($optin);
        }

        $template_ids = array('template-id');
        $params_values = array();

        if (defined('DIGITS_OTP')) {
            $otp = constant('DIGITS_OTP');
            $params_values = digits_get_wa_gateway_templates($message, $otp);
        }

        if (isset($gateway_fields['template_id'])) {
            $template_id = $gateway_fields['template_id'];
        } else {
            $whatsapp = wpn_parse_message_template($message, $template_ids);
            $template = $whatsapp['template'];
            $template_id = $template['template-id'];

            $params_values = $whatsapp['params'];
        }

        $params = array();

        if (!empty($params_values)) {
            ksort($params_values);
            foreach ($params_values as $params_value) {
                $params[] = strval($params_value);
            }
        }


        $data = array(
            'template' => json_encode(
                array('id' => $template_id, 'params' => $params)
            ),
            'source' => str_replace("+", "", $source),
            'destination' => str_replace("+", "", $mobile),
            'channel' => 'whatsapp',
        );

        $query = http_build_query($data);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/x-www-form-urlencoded',
            'apikey: ' . $api_key,
        ));

        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.17 (KHTML, like Gecko) Chrome/24.0.1312.52 Safari/537.17');
        curl_setopt($ch, CURLOPT_URL, 'https://api.gupshup.io/sm/api/v1/template/msg');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
        $result = curl_exec($ch);

        if (curl_errno($ch)) {
            if ($testCall) {
                return "curl error:" . curl_errno($ch);
            } else
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

    public static function optin_user($gateway_fields, $phone)
    {
        $api_key = $gateway_fields['api_key'];
        $app_name = $gateway_fields['app_name'];

        $data = array(
            'user' => str_replace("+", "", $phone),
        );

        $url = 'https://api.gupshup.io/sm/api/v1/app/opt/in/' . $app_name;
        $query = http_build_query($data);
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/x-www-form-urlencoded',
            'apikey: ' . $api_key,
        ));

        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.17 (KHTML, like Gecko) Chrome/24.0.1312.52 Safari/537.17');
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
        $result = curl_exec($ch);

        return $result;
    }
}
