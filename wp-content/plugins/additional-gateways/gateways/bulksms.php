<?php

namespace SMSGateway;


class BulkSMS {
    // docs at: https://www.bulksms.com/developer/json/v1/#tag/Message%2Fpaths%2F~1messages%2Fpost
    public static function sendSMS($gateway_fields, $mobile, $message, $test_call) {
        $token_id = $gateway_fields['token_id'];
        $token_secret = $gateway_fields['token_secret'];
        $sender = $gateway_fields['sender'];

        return self::process_sms($token_id, $token_secret, $sender, $mobile, $message, $test_call);
    }

    public static function process_sms($token_id, $token_secret, $sender, $mobile, $message, $test_call) {
        $curl = curl_init();
        $token = base64_encode($token_id . ':' . $token_secret);
        $data = array(
            array(
                'from' => $sender,
                'to' => $mobile,
                'body' => $message,
            ),
        );

        curl_setopt($curl, CURLOPT_URL, 'https://api.bulksms.com/v1/messages?auto-unicode=true');
        curl_setopt(
            $curl,
            CURLOPT_HTTPHEADER,
            array(
                'Content-Type: application/json',
                'Authorization: Basic ' . $token,
            )
        );
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data, true));

        $result = curl_exec($curl);
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curl_error = curl_errno($curl);
        curl_close($curl);

        if($test_call) return $result;

        if ($curl_error !== 0) {
            return false;
        }

        $is_success = 200 <= $code && $code < 300;

        $result = explode(';', $result);

        if ($result[0] == 'Error') {
            return false;
        }

        return $is_success;
    }
}
