<?php

namespace SMSGateway;

class SMSMasivos
{
    // docs at: https://app.smsmasivos.com.mx/api-docs/v2#auth
    public static $auth_performed = false;
    public static $token = '';

    public static function sendSMS($gateway_fields, $countrycode, $mobile, $message, $test_call)
    {
        return self::process_sms($gateway_fields, $countrycode, $mobile, $message, $test_call);
    }

    public static function sendBulkSMS($gateway_fields, $messages, $test_call)
    {

        /// return self::process_sms($gateway_fields, $mobile, $mobile, $message, $test_call);
    }

    public static function process_sms($gateway_fields, $countrycode, $mobile, $messages, $test_call)
    {
        $curl = curl_init();
        $api_key = $gateway_fields['api_key'];


        $post_params = array(
            'numbers' => $mobile,
            'message' => $messages,
            'country_code' => $countrycode,
        );
        curl_setopt($curl, CURLOPT_URL, 'https://api.smsmasivos.com.mx/sms/send');
        curl_setopt(
            $curl,
            CURLOPT_HTTPHEADER,
            array(
                'apikey:' . $api_key,
            )
        );
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $post_params);

        $result = curl_exec($curl);
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curl_error = curl_errno($curl);
        curl_close($curl);

        if ($test_call) {
            $results[] = $result;
        }

        $is_success = 200 <= $code && $code < 300;

        if ($test_call) return $results;

        if ($is_success && $curl_error !== 0) {
            return true;
        } else{
            return false;
        }

    }
}
