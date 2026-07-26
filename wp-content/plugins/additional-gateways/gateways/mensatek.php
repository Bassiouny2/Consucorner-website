<?php

namespace SMSGateway;


class Mensatek {
    // docs at: https://api.mensatek.com/apiMensatekv5.pdf
    public static function sendSMS($gateway_fields, $mobile, $message, $test_call) {
        $email = $gateway_fields['email'];
        $password = $gateway_fields['password'];
        $sender = $gateway_fields['sender'];

        return self::process_sms($email, $password, $sender, $mobile, $message, $test_call);
    }

    public static function process_sms($email, $password, $sender, $mobile, $message, $test_call) {
        $curl = curl_init();

        $post_params = array(
            'Correo' => $email,
            'Passwd' => $password,
            'Destinatario' => $mobile,
            'Remitente' => $sender,
            'Mensaje' => $message,
        );

        curl_setopt($curl, CURLOPT_URL, 'https://api.mensatek.com/v5/enviar.php');
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $post_params);
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
