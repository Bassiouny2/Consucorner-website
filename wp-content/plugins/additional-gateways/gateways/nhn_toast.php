<?php

namespace SMSGateway;


class NHNToast
{

    public static function sendSMS($gateway_fields, $mobile, $message, $test_call)
    {
        return self::process_sms($gateway_fields, $mobile, $message, $test_call);
    }

    public static function process_sms($gateway_fields, $mobile, $message, $test_call)
    {

        $secret_key = $gateway_fields['secret_key'];
        $sender = $gateway_fields['sender'];
        $app_key = $gateway_fields['app_key'];


        $mobile = str_replace("+", "", $mobile);

        $data = array();

        $data['body'] = $message;
        $data['sendNo'] = $sender;
        $data['recipientList'] = [array('recipientNo' => $mobile)];

        $curl = curl_init('https://api-sms.cloud.toast.com/sms/v2.4/appKeys/' . $app_key . '/sender/sms');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);

        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            "Content-Type: application/json;charset=UTF-8",
            "X-Secret-Key:" . $secret_key
        ));

        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));

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
