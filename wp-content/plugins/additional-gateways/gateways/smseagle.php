<?php

namespace SMSGateway;

class SMSEagle
{
    public static function sendSMS($gateway_fields, $mobile, $message, $test_call)
    {
        return self::process_sms($gateway_fields, $mobile, $message, $test_call);
    }

    public static function process_sms($gateway_fields, $mobile, $message, $test_call)
    {


        $access_token = $gateway_fields['access_token'];
        $smseagle_url = $gateway_fields['smseagle_url'];

        $data = array(
            'to' => array($mobile),
            'text' => $message,
            'priority' => 9,
        );

        $curl = curl_init();


        curl_setopt_array(
            $curl,
            array(
                CURLOPT_URL => $smseagle_url . '/api/v2/messages/sms',
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
                    'access-token:' . $access_token
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