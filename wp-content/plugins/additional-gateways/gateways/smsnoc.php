<?php

namespace SMSGateway;

class SMSNoc
{
    public static function sendSMS($gateway_fields, $mobile, $message, $test_call)
    {
        return self::process_sms($gateway_fields, $mobile, $message, $test_call);
    }

    public static function process_sms($gateway_fields, $mobile, $message, $test_call)
    {


        $api_token = $gateway_fields['api_token'];
        $sender_id = $gateway_fields['sender_id'];


        $data = array(
            'recipient' => str_replace("+", "", $mobile),
            'sender_id' => $sender_id,
            'type' => 'plain',
            'message' => $message
        );

        $curl = curl_init();


        curl_setopt_array(
            $curl,
            array(
                CURLOPT_URL => 'https://app.smsnoc.com/api/v3/sms/send',
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
                    'Authorization: Bearer ' . $api_token
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