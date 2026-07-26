<?php

namespace SMSGateway;

use SimpleXMLElement;

class TelnorSMS
{

    public static function sendSMS($gateway_fields, $recipient, $message, $test_call)
    {
        return self::process_sms($gateway_fields, $recipient, $message, $test_call);
    }

    public static function process_sms($gateway_fields, $recipient, $message, $test_call)
    {

        $msisdn = $gateway_fields["msisdn"];
        $password = $gateway_fields["password"];


        $mask = $gateway_fields["mask"];
        $operator_id = $gateway_fields["operator_id"];
        $url = "https://telenorcsms.com.pk:27677/corporate_sms2/api/auth.jsp?";

        $fields = [
            "msisdn" => $msisdn,
            "password" => $password,
        ];
        $fields = http_build_query($fields);
        $url .= $fields;
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        $answer = curl_exec($curl);
        $xmlObject = new SimpleXMLElement($answer);
        $session_ids = (array)$xmlObject->data[0];
        $session_id = $session_ids[0];
        $answer1 = '';
        if ($test_call) {
            $answer1 = $answer;
        }
        if (empty($session_id)) {
            return false;
        }

        curl_close($curl);
        unset($curl);

        $url = "https://telenorcsms.com.pk:27677/corporate_sms2/api/sendsms.jsp?";

        $fields = [
            "session_id" => $session_id,
            "to" => str_replace("+", "", $recipient),
            "text" => $message,
            "unicode" => 'true',
            'mask' => $mask,
            'operator_id' => $operator_id,
        ];

        $fields = http_build_query($fields);
        $url .= $fields;

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        $answer = curl_exec($curl);

        if ($test_call) {
            return $answer1 . PHP_EOL . $answer;
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

        return true;

    }

}