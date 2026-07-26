<?php

namespace SMSGateway;

class SMSwholesale
{
    public static function sendSMS($authorization, $country_code, $mobile_no, $message, $testCall)
    {
        return self::processSMS($authorization, $country_code, $mobile_no, $message, $testCall);
    }

    public static function processSMS($authorization, $country_code, $mobile_no, $message, $testCall)
    {

        $username = $authorization['username'];
        $password = $authorization['password'];
        $from = $authorization['from'];
        // Request Parameters
        $to = $country_code . $mobile_no;

        $fields = array(
            'to' => $to,
            'from' => $from,
            'message' => $message,
        );

        $fields_string = json_encode($fields);

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_URL, 'https://app.wholesalesms.com.au/api/v2/send-sms.json');
        curl_setopt($curl, CURLOPT_TIMEOUT, 30); //timeout after 30 seconds
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($curl, CURLOPT_USERPWD, "$username:$password");
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $fields_string);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));

        $curl_response = curl_exec($curl);

        $response = json_decode($curl_response);

        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($testCall) {
            return $response;
        }
        if ($http_code != 200) {
            if (curl_errno($curl)) {
                return curl_error($curl);
            }
            return false;
        } else {
            return true;
        }

    }
}