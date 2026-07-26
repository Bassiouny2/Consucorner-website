<?php

namespace SMSGateway;

class Octopush
{
    public static function sendSMS($gateway_fields, $mobile, $message, $test_call)
    {
        return self::process_sms($gateway_fields, $mobile, $message, $test_call);
    }

    public static function process_sms($gateway_fields, $mobile, $message, $test_call)
    {


        $api_key = $gateway_fields['api_key'];
        $api_login = $gateway_fields['api_login'];
        $sender_name = $gateway_fields['sender_name'];


        $data = array(
            'recipients' => array(
                'phone_number' => $mobile,
                'first_name' => '',
                'last_name' => '',
            ),
            'text' => $message,
            'type' => 'sms_premium',
            'purpose' => 'alert',
            'sender' => $sender_name
        );

        $curl = curl_init();

        curl_setopt_array(
            $curl,
            array(
                CURLOPT_URL => 'https://api.octopush.com/v1/public/sms-campaign/send',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => json_encode($data),
                CURLOPT_HTTPHEADER => array(
                    'Accept: application/json',
                    'Content-Type: application/json',
                    'api-key:' . $api_key,
                    'api-login:' . $api_login,
                    'cache-control: no-cache'
                ),
            )
        );


        $response = curl_exec($curl);


        if ($test_call) {
            return $response;
        }

        if (curl_errno($curl)) {
            return false;
        }

        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($http_code != 200) {
            return false;
        }

        curl_close($curl);

        if (empty($response)) {
            return false;
        }

        return $response;

    }
}