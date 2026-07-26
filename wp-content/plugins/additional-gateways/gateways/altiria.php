<?php

namespace SMSGateway;


class Altiria
{

    public static function sendSMS($gateway_fields, $mobile, $message, $test_call)
    {
        return self::process_sms($gateway_fields, $mobile, $message, $test_call);
    }

    public static function process_sms($gateway_fields, $mobile, $message, $test_call)
    {

        $login = $gateway_fields['login'];
        $domainId = $gateway_fields['domainId'];
        $password = $gateway_fields['password'];
        $from = $gateway_fields['sender_id'];


        $data = array();
        $data['cmd'] = 'sendsms';
        $data['login'] = $login;
        $data['passwd'] = $password;
        $data['msg'] = $message;
        $data['dest'] = str_replace("+", "", $mobile);
        $data['senderId'] = $from;
        $data['source'] = 'wpdigits';

        if(!empty($gateway_fields['domainId'])) {
            $data['domainId'] = $domainId;
        }

        $curl = curl_init('http://www.altiria.net/api/http');

        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-type: application/x-www-form-urlencoded; charset=UTF-8'));
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
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
