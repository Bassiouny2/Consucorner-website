<?php

namespace SMSGateway;


class OrangeSms
{

    public static function sendSMS($gateway_fields, $mobile, $message, $test_call)
    {
        return self::process_sms($gateway_fields, $mobile, $message, $test_call);
    }

    public static function process_sms($gateway_fields, $mobile, $message, $test_call)
    {
        $access_token = $gateway_fields['access_token'];
        $sender = $gateway_fields['sender'];


        $params = array();

        $data = array();
        $data['address'] = 'tel:' . $mobile;
        $data['senderAddress'] = 'tel:+' . $sender;
        $data['senderName'] = $gateway_fields['sender_name'];
        $data['outboundSMSTextMessage'] = array(
            'message' => $message
        );

        $params['outboundSMSMessageRequest'] = $data;


        $curl = curl_init('https://api.orange.com/smsmessaging/v1/outbound/tel%3A%2B' . $sender . '/requests');

        curl_setopt(
            $curl,
            CURLOPT_HTTPHEADER,
            array(
                'Authorization: Bearer ' . $access_token,
                'Content-Type: application/json'
            )
        );
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);


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

        $result = json_decode($answer);
        if (isset($result->error)) {
            return false;
        }

        return $result;

    }

}
