<?php

namespace SMSGateway;


class SMSMISR
{
    // docs at: https://smsmisr.com/API
    public static function sendSMS($gateway_fields, $mobile, $message, $test_call)
    {

        return self::process_sms($gateway_fields, $mobile, $message, $test_call);
    }

    public static function process_sms($gateway_fields, $mobile, $message, $test_call)
    {

        $username = $gateway_fields['username'];
        $password = $gateway_fields['password'];
        $sender = $gateway_fields['sender'];;

        $environment = $test_call ? 1 : 2;
        $curl = curl_init();
        $params = array(
            'username' => $username,
            'password' => $password,
            'sender' => $sender,
            'language' => self::isArabic($message) ? 2 : 1,
            'Mobile' => str_replace("+", "", $mobile),
            'message' => $message,
            'environment' => $environment
        );


        curl_setopt($curl, CURLOPT_URL, 'https://smsmisr.com/api/SMS/');
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($params));

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

    public static function isArabic($string)
    {
        $arabicCount = 0;
        $englishCount = 0;
        $noNumbers = preg_replace('/[0-9]+/', '', $string);
        $noBracketsHyphen = array('(', ')', '-');
        $clean = trim(str_replace($noBracketsHyphen, '', $noNumbers));
        $array = explode(" ", $clean);
        for ($i = 0; $i <= count($array); $i++) {
            $checkLang = preg_match('/\p{Arabic}/u', $array[$i]);
            if ($checkLang == 1) {
                ++$arabicCount;
            } else {
                ++$englishCount;
            }
        }
        return $arabicCount >= $englishCount;
    }
}
