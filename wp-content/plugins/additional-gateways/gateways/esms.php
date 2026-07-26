<?php

namespace SMSGateway;


class ESMS
{
    // docs at: https://esms.vn/blog/3-buoc-de-co-the-gui-tin-nhan-tu-website-ung-dung-cua-ban-bang-sms-api-cua-esmsvn
    public static function sendSMS($gateway_fields, $mobile, $message, $test_call)
    {
        return self::process_sms($gateway_fields, $mobile, $message, $test_call);
    }

    public static function process_sms($gateway_fields, $mobile, $message, $test_call)
    {

        $api_key = $gateway_fields['api_key'];
        $api_secret = $gateway_fields['api_secret'];
        $brandname = $gateway_fields['brandname'];

        $curl = curl_init();
        $params = array(
            'ApiKey' => $api_key,
            'SecretKey' => $api_secret,
            'Brandname' => $brandname,
            'Phone' => $mobile,
            'Content' => $message,
            'SmsType' => 2,
        );

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://rest.esms.vn/MainService.svc/json/SendMultipleMessage_V4_post_json/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($params),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));
        $result = curl_exec($curl);
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curl_error = curl_errno($curl);
        curl_close($curl);

        if ($test_call) return $result;

        if ($curl_error !== 0) {
            return false;
        }

        $is_success = 200 <= $code && $code < 300;

        return $is_success;
    }

}
