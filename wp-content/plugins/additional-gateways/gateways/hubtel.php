<?php

namespace SMSGateway;

use ApiHost;
use BasicAuth;
use Exception;
use HttpResponse;
use MessageResponse;
use MessagingApi;

include_once 'Hubtel/Api.php';


class Hubtel
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

        $mobile = str_replace("+", "", $mobile);

        $auth = new BasicAuth($username, $password);
        $apiHost = new ApiHost($auth);
        $messagingApi = new MessagingApi($apiHost);
        try {
            // Send a quick message
            $messageResponse = $messagingApi->sendQuickMessage($sender, $mobile, $message);

            if ($messageResponse instanceof MessageResponse) {
                if($test_call) return $messageResponse->getStatus();
            } elseif ($messageResponse instanceof HttpResponse) {
                if($test_call) return $messageResponse->getStatus();
            }
            return true;
        } catch (Exception $ex) {
            if($test_call) return $ex->getTraceAsString();
            return false;
        }

        return true;

    }

}
