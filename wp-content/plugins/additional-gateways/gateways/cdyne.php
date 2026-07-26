<?php

namespace SMSGateway;


class CDYNE {
    // docs at: https://cdyne.com/sms/developers/documentation/SendMessage
    public static function sendSMS($gateway_fields, $mobile, $message, $test_call) {
        $license_key = $gateway_fields['license_key'];
        $sender = $gateway_fields['sender'];

        return self::process_sms($license_key, $sender, $mobile, $message, $test_call);
    }

    public static function process_sms($license_key, $sender, $mobile, $message, $test_call) {
        $curl = curl_init();

        $data = array(
            'Body' => $message,
            'LicenseKey' => $license_key,
            'To' => [$mobile],
            'From' => $sender,
            'Concatenate' => false,
            'UseMMS' => false,
        );

        curl_setopt($curl, CURLOPT_URL, 'http://messaging.cdyne.com/Messaging.svc/SendMessage');
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt(
            $curl,
            CURLOPT_HTTPHEADER,
            array(
                "Content-Type: application/json",
            )
        );
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
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
