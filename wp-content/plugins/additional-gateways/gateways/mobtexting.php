<?php

namespace SMSGateway;

class MobTexting
{
    public static function sendSMS($gateway_fields, $mobile, $message, $test_call)
    {
        return self::process_sms($gateway_fields, $mobile, $message, $test_call);
    }

    public static function process_sms($gateway_fields, $mobile, $message, $test_call)
    {


        $access_token = $gateway_fields['access_token'];
        $sender_name = $gateway_fields['sender_name'];


        $data = array(
            'to' => $mobile,
            'service' => 'T',
            'sender' => $sender_name,
            'message' => $message
        );

        $curl = curl_init();


        curl_setopt_array(
            $curl,
            array(
                CURLOPT_URL => 'https://portal.mobtexting.com/api/v2/sms/send',
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
                    'Authorization: Bearer ' . $access_token
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