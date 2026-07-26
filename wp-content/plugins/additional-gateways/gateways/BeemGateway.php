<?php
namespace SMSGateway;



class BeemGateway
{

    public static function sendSMS($gateway_fields, $mobile, $message, $test_call)
    {
        return self::process_sms($gateway_fields, $mobile, $message, $test_call);
    }

    public static function process_sms($gateway_fields, $mobile, $message, $test_call)
    {



        $access_token = $gateway_fields['api_key'];
        $secret_key = $gateway_fields['secret_key'];

        $data = array();
        $data['source_addr'] = $gateway_fields['source_addr'];
        $data['message'] = $message;
        $data['encoding'] = 0;
        $data['recipients'] =  [ array('recipient_id' => 1,'dest_addr'=>$mobile )] ;


        $curl = curl_init('https://apisms.beem.africa/v1/send');
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            CURLOPT_POST => TRUE,
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_HTTPHEADER => array(
                'Authorization:Basic ' . base64_encode($access_token .':'.$secret_key),
                'Content-Type: application/json'
            )));
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);

        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");

        $answer = curl_exec($curl);

        if ($test_call) {
            return $answer;
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


