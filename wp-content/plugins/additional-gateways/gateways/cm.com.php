<?php

namespace SMSGateway;


if (!defined('ABSPATH')) {
    exit;
}

unitedover_load_gateways_sdks();

class CM_COM
{
    public static function sendSMS($gateway_fields, $mobile, $message, $test_call)
    {
        return self::process_sms($gateway_fields, $mobile, $message, $test_call);
    }

    public static function process_sms($gateway_fields, $mobile, $message, $test_call)
    {


        $api_key = $gateway_fields['api_key'];
        $sender = $gateway_fields['sender'];

        $client = new \CMText\TextClient($api_key);

        $result = $client->SendMessage($message, $sender, [$mobile]);

        if ($test_call) {
            return $result;
        }

        return true;

    }

}
