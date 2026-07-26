<?php

namespace SMSGateway;


class Greentext {
    // docs at: https://developer.textapp.net/HTTPService/Methods_SendSMS.aspx
    public static function sendSMS($gateway_fields, $mobile, $message, $test_call) {
        $client_id = $gateway_fields['client_id'];
        $password = $gateway_fields['password'];
        $sender = $gateway_fields['sender'];

        return self::process_sms($client_id, $password, $sender, $mobile, $message, $test_call);
    }

    public static function process_sms($client_id, $password, $sender, $mobile, $message, $test_call) {
        $curl = curl_init();
        $params = array(
            'externalLogin' => $client_id,
            'password' => $password,
            'returnCSVString' => true,
            'clientBillingReference' => 0,
            'clientMessageReference' => 0,
            'originator' => $sender,
            'destinations' => [$mobile],
            'body' => $message,
            'validity' => 72,
            'characterSetId' => 2,
            'replyMethodId' => 1,
            'replyData' => '',
            'statusNotificationURL' => '',
            'method' => 'SendSMS',
        );
        $encoded_query = http_build_query($params);
        curl_setopt($curl, CURLOPT_URL, 'https://www.textapp.net/webservice/httpservice.aspx?' . $encoded_query);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($curl);
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curl_error = curl_errno($curl);
        curl_close($curl);

        if($test_call) return $result;

        if ($curl_error !== 0) {
            return false;
        }

        $is_success = 200 <= $code && $code < 300;

        return $is_success;
    }

}
