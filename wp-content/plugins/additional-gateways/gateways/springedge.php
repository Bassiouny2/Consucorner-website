<?php

namespace SMSGateway;


class SpringEdge {
    // docs at: https://plugins.trac.wordpress.org/browser/woocommerce-apg-sms-notifications/trunk/includes/admin/proveedores.php
    public static function sendSMS($gateway_fields, $mobile, $message, $test_call) {
        $api_key = $gateway_fields['api_key'];
        $sender = $gateway_fields['sender'];

        return self::process_sms($api_key, $sender, $mobile, $message, $test_call);
    }

    public static function process_sms($api_key, $sender, $mobile, $message, $test_call){
        $curl = curl_init();
        $params = array(
            'apikey' => $api_key,
            'sender' => $sender,
            'to' => $mobile,
            'message' => $message,
            'formta' => 'json',
        );
        $encoded_query = http_build_query($params);
        curl_setopt($curl, CURLOPT_URL, 'http://instantalerts.co/api/web/send/?' . $encoded_query);
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
