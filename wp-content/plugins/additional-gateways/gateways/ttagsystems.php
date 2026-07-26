<?php

namespace SMSGateway;


class TTAGSystems {
    public static $session_id_obtained = false;
    public static $session_id = '';

    public static function sendSMS($gateway_fields, $mobile, $message, $test_call) {
        $user = $gateway_fields['user'];
        $password = $gateway_fields['password'];
        $sender = $gateway_fields['sender'];
        $session_id = self::_obtain_session_id($user, $password);

        if (!self::$session_id_obtained) return false;

        return self::process_sms($session_id, $sender, $mobile, $message, $test_call);
    }

    public static function _obtain_session_id($user, $password) {
        if ($session_id_obtained) {
            return $session_id;
        }
        $curl = curl_init();

        $data = array(
            'user' => $user,
            'password' => $password,
        );

        curl_setopt(
            $curl,
            CURLOPT_URL,
            'http://api.ttagsystems.com/api/rest/auth'
        );
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt(
            $curl,
            CURLOPT_HTTPHEADER,
            array(
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

        if ($curl_error !== 0) {
            return false;
        }

        $is_success = 200 <= $code && $code < 300;

        if ($is_success) {
            $result = json_decode($result);
            self::$session_id = $result['session_id'];
            self::$session_id_obtained = true;
            return self::$session_id;
        }

        return '';
    }

    public static function process_sms($session_id, $sender, $mobile, $message, $test_call) {
        $curl = curl_init();

        $data = array(
            'session_id' => $session_id,
            'to' => $mobile,
            'from' => $sender,
            'text' => $messaage,
        );

        curl_setopt(
            $curl,
            CURLOPT_URL,
            'http://api.ttagsystems.com/api/rest/message'
        );
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt(
            $curl,
            CURLOPT_HTTPHEADER,
            array(
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
