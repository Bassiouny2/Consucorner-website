<?php

namespace SMSGateway;


if (!defined('ABSPATH')) {
    exit;
}

use Exception;


unitedover_load_gateways_sdks();


class SignalWire
{
    public static function sendSMS($gateway_fields, $mobile, $message, $test_call)
    {
        return self::process_sms($gateway_fields, $mobile, $message, $test_call);
    }

    public static function process_sms($gateway_fields, $mobile, $message, $test_call)
    {

        $space_url = $gateway_fields['space_url'];
        $api_token = $gateway_fields['api_token'];
        $project_id = $gateway_fields['project_id'];
        $sender = $gateway_fields['sender'];

        try {
            $client = new \SignalWire\Rest\Client($project_id, $api_token, array("signalwireSpaceUrl" => $space_url));
            $result = $client->messages->create(
                $mobile,
                array(
                    'From' => $sender,
                    'Body' => $message
                )
            );
        } catch (Exception $e) {
            if ($test_call) {
                return $e->getMessage();
            }

            return false;
        }

        if ($test_call) {
            return $result;
        }

        return true;

    }

}
