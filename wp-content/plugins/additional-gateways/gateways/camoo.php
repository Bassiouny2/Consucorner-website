<?php

namespace SMSGateway;

use Camoo\Sms\Exception\CamooSmsException;
use Complex\Exception;

if (!defined('ABSPATH')) {
    exit;
}

unitedover_load_gateways_sdks();

class Camoo
{
    public static function sendSMS($gateway_fields, $mobile, $message, $test_call)
    {
        return self::process_sms($gateway_fields, $mobile, $message, $test_call);
    }

    public static function process_sms($gateway_fields, $mobile, $message, $test_call)
    {


        try {
            $api_key = $gateway_fields['api_key'];
            $api_secret = $gateway_fields['api_secret'];
            $sender = $gateway_fields['sender'];

            $oMessage = \Camoo\Sms\Message::create($api_key, $api_secret);
            $oMessage->from = $sender;
            $oMessage->to = $mobile;
            $oMessage->message = $message;
            $send = $oMessage->send();

            if ($test_call) {
                return $send;
            }
        }catch (CamooSmsException $e){
            if($test_call) return $e->getMessage();

            return false;
        }

        return true;

    }

}
