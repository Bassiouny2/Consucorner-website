<?php

namespace SMSGateway;

class EskizEu
{

    public static function sendSMS($gateway_fields, $mobile, $message, $test_call)
    {
        return self::process_sms($gateway_fields, $mobile, $message, $test_call);
    }

    public static function process_sms($gateway_fields, $mobile, $message, $test_call)
    {

        $email = $gateway_fields['username'];
        $password = $gateway_fields['password'];
        $from = $gateway_fields['sender'];

        $token_key = 'eskiz_token_' . $email;
        $token_obj = get_option($token_key, array());

        $refresh = false;
        if (isset($token_obj['time'])) {
            $duration = time() - $token_obj['time'];
            if($duration > 2505600){
                $refresh = true;
            }
        }

        if (empty($token_obj) || $refresh) {
            $token = self::getAuthorisationToken($email, $password);
            if ($token['status']) {
                $token = $token['data'];
                $token_obj = array('time' => time());
                $token_obj['token'] = $token;
                update_option($token_key, $token_obj);
            } else {

                if ($test_call) {
                    return $token['data'];
                } else {
                    return false;
                }
            }
        }

        $headers = array(
            "Authorization: Bearer " . $token_obj['token']
        );

        $mobile = str_replace("+", "", $mobile);
        $array_data = array(
            "mobile_phone" => $mobile,
            "message" => $message,
            "from" => $from,
        );

        $url = 'https://notify.eskiz.uz/api/message/sms/send';
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $array_data);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($curl);
        $data = json_decode($response);
        if (isset($data->status_code) && $data->status_code == 405) {
            delete_option($token_key);
            return false;
        }

        if (curl_errno($curl)) {
            if ($test_call) {
                return curl_error($curl);
            }
            return false;
        }

        curl_close($curl);


        if (empty($response)) {
            return false;
        }
        if ($test_call) {
            return $response;
        }
        return true;
    }

    public static function getAuthorisationToken($email, $password)
    {
        $details = array('email' => $email, 'password' => $password);
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://notify.eskiz.uz/api/auth/login',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $details,
        ));

        $response = curl_exec($curl);
        $response = json_decode($response);
        curl_close($curl);
        if (isset($response->data->token)) {
            return array('status' => true, 'data' => $response->data->token);
        } else {
            return array('status' => false, 'data' => $response);
        }

    }

}

