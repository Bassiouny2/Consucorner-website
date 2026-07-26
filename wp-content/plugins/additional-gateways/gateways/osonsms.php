<?php

namespace SMSGateway;


class OsonSMS
{

    public static function sendSMS($gateway_fields, $mobile, $message, $test_call)
    {
        return self::process_sms($gateway_fields, $mobile, $message, $test_call);
    }

    public static function process_sms($gateway_fields, $mobile, $message, $test_call)
    {

        $data = array();
        $login = $gateway_fields['login'];
        $from = $gateway_fields['from'];


        $mobile = str_replace("+", "", $mobile);
        $txn_id = md5($message . $mobile);
        $data['login'] = $login;
        $data['from'] = $from;
        $data['phone_number'] = $mobile;
        $data['msg'] = $message;
        $data['txn_id'] = $txn_id;
        $data['str_hash'] = hash('sha256', $txn_id . ';' . $login . ';' . $from . ';' . $mobile . ';sha256');


        $curl = curl_init('https://api.osonsms.com/sendsms_v1.php?'.http_build_query($data));

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

        if (empty($answer)) {
            return false;
        }

        return $answer;

    }

}
