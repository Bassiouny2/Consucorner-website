<?php

namespace SMSGateway;


class Telestax {
    public static function sendSMS($gateway_fields, $mobile, $message, $test_call) {
        $account_sid = $gateway_fields['account_sid'];
        $auth_token = $gateway_fields['auth_token'];
        $sender = $gateway_fields['sender'];

        return self::process_sms($account_sid, $auth_token, $sender, $mobile, $message, $test_call);
    }

    public static function process_sms($account_sid, $auth_token, $sender, $mobile, $message, $test_call) {
        $curl = curl_init();

        $data = array(
            'Body' => $message,
            'To' => $mobile,
            'From' => $sender,
            'orgid' => $organization_id,
        );

        curl_setopt(
            $curl,
            CURLOPT_URL,
            'https://cloud.restcomm.com/restcomm/2012-04-24/Accounts/' . $account_sid . '/SMS/Messages'
        );
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt(
            $curl,
            CURLOPT_HTTPHEADER,
            array(
                'Authorization: ' . $account_sid . ':' . $auth_token,
                'Content-Type: application/json',
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
