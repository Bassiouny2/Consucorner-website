<?php

namespace SMSGateway;


class AgileTelecom {
    // docs at: http://www.agiletelecom.com/Updates/AgileTelecom_Help_ENG.pdf
    public static function sendSMS($gateway_fields, $mobile, $message, $test_call) {
        $sms_user = $gateway_fields['sms_user'];
        $sms_password = $gateway_fields['sms_password'];
        $sender = $gateway_fields['sender'];

        return self::process_sms($sms_user, $sms_password, $sender, $mobile, $message, $test_call);
    }

    public static function process_sms($sms_user, $sms_password, $sender, $mobile, $message, $test_call) {
        $curl = curl_init();
        $params = array(
            'smsUSER' => $sms_user,
            'smsPASSWORD' => $sms_password,
            'smsSENDER' => $sender,
            'smsNUMBER' => $mobile,
            'smsTEXT' => $message,
            'smsTYPE' => 'file.sms',
        );

        curl_setopt($curl, CURLOPT_URL, 'https://secure.agiletelecom.com/securesend_v1.aspx');
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
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
