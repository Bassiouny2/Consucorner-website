<?php

namespace SMSGateway;


class TextMagic {
    // docs at: https://www.textmagic.com/docs/api/send-sms/#how-to-send-bulk-text-messages
    // curl at: https://github.com/textmagic/textmagic-rest-bash/blob/master/tm.sh
    public static function sendSMS($gateway_fields, $mobile, $message, $test_call) {
        $username = $gateway_fields['username'];
        $api_key = $gateway_fields['api_key'];
        $sender = $gateway_fields['sender'];

        return self::process_sms($username, $api_key, $sender, $mobile, $message, $test_call);
    }

    public static function process_sms($username, $api_key, $sender, $mobile, $message, $test_call){
        $curl = curl_init();

        $post_params = array(
            'phones' => $mobile,
            'text' => $message,
            'from' => $sender,
        );
        curl_setopt($curl, CURLOPT_URL, 'https://rest.textmagic.com/api/v2/messages');
        curl_setopt(
            $curl,
            CURLOPT_HTTPHEADER,
            array(
                "X-TM-Username: " . $username,
                "X-TM-Key: " . $api_key,
            )
        );
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $post_params);

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
