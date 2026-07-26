<?php

namespace SMSGateway;


class SMSCC
{

    public static function sendSMS($gateway_fields, $mobile, $message, $test_call)
    {
        return self::process_sms($gateway_fields, $mobile, $message, $test_call);
    }

    public static function process_sms($gateway_fields, $mobile, $message, $test_call)
    {

        $username = $gateway_fields['username'];
        $password = $gateway_fields['password'];
        $sender = $gateway_fields['sender'];

        $domain = rtrim('/', $gateway_fields['domain']);

        $mobile = str_replace("+", "", $mobile);

        $data = array();
        $data['username'] = $username;
        $data['password'] = $password;
        $data['sender'] = $sender;
        $data['mobile'] = $mobile;
        $data['message'] = $message;
        $data['language'] = self::isArabic($message) ? 2 : 1;

        $curl = curl_init('http://' . $domain . '/api/send.aspx?' . http_build_query($data));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);

        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

        $answer = curl_exec($curl);


        if ($test_call) {
            return $answer;
        }

        if (curl_errno($curl)) {
            return false;
        }

        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($http_code != 200) {
            return false;
        }

        curl_close($curl);

        if (empty($answer)) {
            return false;
        }

        return $answer;

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
