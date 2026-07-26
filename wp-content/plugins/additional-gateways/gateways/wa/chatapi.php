<?php

namespace SMSGateway\wa;

class Whatsapp_Chat_API
{

  public static function sendWhatsapp(
    $gateway_fields,
    $mobile,
    $message,
    $test_call
  ) {
    return self::process_whatsapp(
      $gateway_fields,
      $mobile,
      $message,
      $test_call
    );
  }

  public static function process_whatsapp(
    $gateway_fields,
    $mobile,
    $message,
    $test_call
  ) {
    $api_key = $gateway_fields['api_key'];
    $instance_id = $gateway_fields['instance_id'];


    $template_ids = array('template-name', 'namespace', 'language');
    $params_values = array();


    if (defined('DIGITS_OTP')) {
      $otp = constant('DIGITS_OTP');
      $params_values = digits_get_wa_gateway_templates($message, $otp);
    }

    if (isset($gateway_fields['template-name'])) {
      $template = $gateway_fields;
    } else {
      $whatsapp = wpn_parse_message_template($message, $template_ids);
      $template = $whatsapp['template'];
      $params_values = $whatsapp['params'];
    }

    $params = array();


    if (!empty($params_values)) {
      ksort($params_values);
      foreach ($params_values as $params_value) {
        $params[] = array('type' => 'text', 'text' => strval($params_value));
      }
    }


    $url = "https://api.chat-api.com/instance{$instance_id}/sendTemplate?token={$api_key}";


    $data = [
      'phone' => $mobile,
      'template' => $template['template-name'],
      'namespace' => $template['namespace'],
      'language' => [
        'code' => $template['language'],
        'policy' => 'deterministic'
      ]
    ];

    $options = stream_context_create(
      [
        'http' => [
          'method' => 'POST',
          'header' => 'Content-type: application/json',
          'content' => json_encode($data)
        ]
      ]
    );

    $response = file_get_contents($url, false, $options);

    if ($test_call) {
      return $response;
    }

    if (empty($response))
      return false;

    return true;
  }
}