<?php

/*
 * Plugin Name: Additional SMS Gateways
 * Description: Additional SMS Gateways for Digits and WPNotif
 * Version: 7.1
 * Plugin URI: https://codecanyon.net/user/unitedover/portfolio
 * Author URI: https://www.unitedover.com/
 * Author: UnitedOver
 * Text Domain: additional-gateways
 * Requires PHP: 5.5
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

require dirname(__FILE__) . '/Puc/v4p6/Factory.php';
require dirname(__FILE__) . '/Puc/v4/Factory.php';
require dirname(__FILE__) . '/Puc/v4p6/Autoloader.php';
new Puc_v4p6_Autoloader();

foreach (
    array(
        'Plugin_UpdateChecker' => 'Puc_v4p6_Plugin_UpdateChecker',
        'Vcs_PluginUpdateChecker' => 'Puc_v4p6_Vcs_PluginUpdateChecker',
    )
    as $pucGeneralClass => $pucVersionedClass
) {
    Puc_v4_Factory::addVersion($pucGeneralClass, $pucVersionedClass, '4.6');

    Puc_v4p6_Factory::addVersion($pucGeneralClass, $pucVersionedClass, '4.6');
}

function digits_addon_additional_gateways()
{
    return 'apisettings';
}

function unitedover_additionalgateway_addon($list)
{
    $list[] = 'additional-gateways';
    return $list;
}

add_filter('digits_addon', 'unitedover_additionalgateway_addon');
add_filter('wpnotif_addon', 'unitedover_additionalgateway_addon');

function unitedover_load_gateways_sdks()
{
    require_once 'vendor/autoload.php';
    if (!class_exists('AdnSms\AdnSmsNotification')) {
        require_once plugin_dir_path(__FILE__) . 'AdnSms/AdnSmsNotification.php';
    }

}


function untdovr_send_sms_with_otp($status, $option_slug, $gateway_id, $countrycode, $mobile, $messagetemplate, $otp, $testCall)
{
    switch ($gateway_id) {
        case 33:
            if (!class_exists('ComposerAutoloaderInit90fceaf4b778149483bc47bcb466a797')) {
                unitedover_load_gateways_sdks();
            }
            $alibaba = get_option($option_slug . '_alibaba_go_china');
            $access_key = $alibaba['access_key'];
            $access_secret = $alibaba['access_secret'];
            $from = $alibaba['from'];
            $template_code = $alibaba['templatecode'];
            $smsupextendcode = $alibaba['smsupextendcode'];

            $template_param = json_encode(array('otp' => $otp));

            $number = str_replace("+", "", $countrycode) . $mobile;

            try {
                AlibabaCloud\Client\AlibabaCloud::accessKeyClient($access_key, $access_secret)
                    ->regionId('ap-southeast-1')
                    ->asDefaultClient();

                $result = AlibabaCloud\Client\AlibabaCloud::rpc()
                    ->product('Dysmsapi')
                    ->host('dysmsapi.ap-southeast-1.aliyuncs.com')
                    ->version('2018-05-01')
                    ->action('SendMessageWithTemplate')
                    ->method('POST')
                    ->options([
                        'query' => [
                            "TemplateCode" => $template_code,
                            "TemplateParam" => $template_param,
                            "SmsUpExtendCode" => $smsupextendcode,
                            "To" => $number,
                            "From" => $from,
                            "Message" => $messagetemplate,
                        ],
                    ])
                    ->request();

            } catch (Exception $e) {
                if ($testCall) {
                    return $e->getErrorMessage();
                }

                return false;
            }
            if ($testCall) {
                return $result;
            }

            return true;
        default:
            return false;
    }

}

add_filter('unitedover_send_sms_with_otp', 'untdovr_send_sms_with_otp', 10, 8);


add_filter('unitedover_send_sms', 'untdovr_send_sms', 10, 7);

function untdovr_send_sms(
    $status, $option_slug, $gateway_id, $countrycode, $mobile, $messagetemplate, $testCall
)
{
    $digpc = get_site_option('dig_purchasecode');
    if (empty($digpc)) {
        $digpc = get_option('wpnotif_purchasecode');
    }
    if (empty($digpc)) return false;


    switch ($gateway_id) {
        case 9:
            $mobily = get_option($option_slug . '_mobily_ws');

            $mobily_mobile = $mobily['mobile'];
            $password = $mobily['password'];
            $sender = $mobily['sender'];

            $data = array(
                'msg' => UnitedOver_convertToUnicode($messagetemplate),
                'mobile' => $mobily_mobile,
                'password' => $password,
                'sender' => $sender,
                'applicationType' => '68',
                'numbers' => str_replace("+", "", $countrycode) . $mobile
            );


            $ch = curl_init();

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            curl_setopt($ch, CURLOPT_URL, 'http://mobily.ws/api/msgSend.php?' . http_build_query($data));
            $result = curl_exec($ch);


            if (curl_errno($ch)) {
                if ($testCall) {
                    return "curl error:" . curl_errno($ch);
                }

                return false;
            }
            curl_close($ch);

            if ($testCall) {
                return $result;
            }

            if ($result === false) {
                return false;
            }

            return true;
        case 18:
            if (!class_exists('ComposerAutoloaderInit90fceaf4b778149483bc47bcb466a797')) {
                unitedover_load_gateways_sdks();
            }
            $alibaba = get_option($option_slug . '_alibaba');
            $access_key = $alibaba['access_key'];
            $access_secret = $alibaba['access_secret'];
            $from = $alibaba['from'];

            $number = str_replace("+", "", $countrycode) . $mobile;

            try {
                AlibabaCloud\Client\AlibabaCloud::accessKeyClient($access_key, $access_secret)
                    ->regionId('ap-southeast-1')
                    ->asDefaultClient();

                $result = AlibabaCloud\Client\AlibabaCloud::rpc()
                    ->product('Dysmsapi')
                    ->host('dysmsapi.ap-southeast-1.aliyuncs.com')
                    ->version('2018-05-01')
                    ->action('SendMessageToGlobe')
                    ->method('POST')
                    ->options([
                        'query' => [
                            "To" => $number,
                            "From" => $from,
                            "Message" => $messagetemplate,
                        ],
                    ])
                    ->request();

            } catch (Exception $e) {
                if ($testCall) {
                    return $e->getErrorMessage();
                }

                return false;
            }

            if ($testCall) {
                return $result;
            }

            return true;
        case 19:
            if (!class_exists('AdnSms\AdnSmsNotification')) {
                unitedover_load_gateways_sdks();
            }
            $adnsms = get_option($option_slug . '_adnsms');
            $api_key = $adnsms['api_key'];
            $api_secret = $adnsms['api_secret'];
            $requestType = 'OTP';
            $messageType = 'UNICODE';
            $number = str_replace("+", "", $countrycode) . $mobile;

            $sms = new AdnSms\AdnSmsNotification($api_key, $api_secret);
            $result = $sms->sendSms($requestType, $messagetemplate, $number, $messageType);
            if ($testCall) {
                return $result;
            }

            return true;
        case 22:
            $targetSms = get_option($option_slug . '_targetsms');
            $login = $targetSms['login'];
            $pwd = $targetSms['password'];
            $sender = $targetSms['sender'];

            $phone = str_replace("+", "", $countrycode) . $mobile;

            $src = '<?xml version="1.0" encoding="utf-8"?>
<request><security><login value="' . $login . '" /><password value="' . $pwd . '" /></security>
<message><sender>' . $sender . '</sender><text>' . $messagetemplate . '</text><abonent phone="' . $phone . '" /></message></request>';

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: text/xml; charset=utf-8'));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CRLF, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $src);
            curl_setopt($ch, CURLOPT_URL, 'https://sms.targetsms.ru/xml/');
            $result = curl_exec($ch);
            curl_close($ch);

            if ($result === false) {
                return false;
            }

            if ($testCall) {
                return $result;
            }

            return true;
        case 23:
            $ghasedak = get_option($option_slug . '_ghasedak');

            $api_key = $ghasedak['api_key'];

            $headers = array(
                'apikey:' . $api_key,
                'Accept: application/json',
                'Content-Type: application/x-www-form-urlencoded',
                'charset: utf-8'
            );

            $params = array(
                "receptor" => $mobile,
                "message" => $messagetemplate
            );


            $ch = curl_init();
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CRLF, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_URL, 'http://api.ghasedak.io/v2/sms/send/simple');
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));

            $result = curl_exec($ch);
            curl_close($ch);

            if ($result === false) {
                return false;
            }

            if ($testCall) {
                return $result;
            }


            return true;
        case 24:
            $farapayamak = get_option($option_slug . '_farapayamak');
            $username = $farapayamak['username'];
            $password = $farapayamak['password'];
            $from = $farapayamak['sender'];

            $phone = str_replace("+", "", $countrycode) . $mobile;

            $params = array(
                "UserName" => $username,
                "PassWord" => $password,
                "From" => $from,
                "To" => $phone,
                "Text" => $messagetemplate,
            );

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded; charset=utf-8'));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CRLF, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
            curl_setopt($ch, CURLOPT_URL, 'http://api.payamak-panel.com/post/Send.asmx');
            $result = curl_exec($ch);
            curl_close($ch);

            if ($result === false) {
                return false;
            }

            if ($testCall) {
                return $result;
            }

            return true;
        case 25:
            if (!function_exists('Aws\parse_ini_file')) {
                unitedover_load_gateways_sdks();
            }
            $sns = get_option($option_slug . '_amazon_sns');

            try {
                $SnSclient = new Aws\Sns\SnsClient([
                    'region' => $sns['region'],
                    'version' => 'latest',
                    'credentials' => [
                        'key' => $sns['access_key'],
                        'secret' => $sns['access_secret'],
                    ],
                ]);

                $args = [
                    'Message' => '',
                    'PhoneNumber' => $countrycode . $mobile,
                    'MessageAttributes' => [
                        'AWS.SNS.SMS.SenderID' => [
                            'DataType' => 'String',
                            'StringValue' => $sns['sender_id'],
                        ],
                        'AWS.SNS.SMS.SMSType' => [
                            'DataType' => 'String',
                            'StringValue' => 'Transactional',
                        ],

                    ],
                ];
                $template_ids = array('message', 'entity-id', 'template-id');

                $template_id = '';
                $entity_id = '';
                $message_obj = wpn_parse_message_template($messagetemplate, $template_ids);
                if (is_array($message_obj) && !empty($message_obj['template'])) {
                    $message_template = $message_obj['template'];
                    $message = $message_template['message'];

                    if (isset($message_template['template-id'])) {
                        $args['MessageAttributes']['AWS.MM.SMS.TemplateId'] = [
                            'DataType' => 'String',
                            'StringValue' => $message_template['template-id'],
                        ];
                    }
                    if (isset($message_template['entity-id'])) {
                        $args['MessageAttributes']['AWS.MM.SMS.EntityId'] = [
                            'DataType' => 'String',
                            'StringValue' => $message_template['entity-id'],
                        ];
                    }
                } else {
                    $message = $messagetemplate;

                    if (isset($sns['template_id'])) {
                        $args['MessageAttributes']['AWS.MM.SMS.TemplateId'] = [
                            'DataType' => 'String',
                            'StringValue' => $sns['template_id'],
                        ];
                    }
                    if (isset($sns['entity_id'])) {
                        $args['MessageAttributes']['AWS.MM.SMS.EntityId'] = [
                            'DataType' => 'String',
                            'StringValue' => $sns['entity_id'],
                        ];
                    }

                }

                if (isset($sns['entity_id'])) {
                    $args['MessageAttributes']['AWS.MM.SMS.EntityId'] = [
                        'DataType' => 'String',
                        'StringValue' => $sns['entity_id'],
                    ];
                }

                $args['Message'] = $message;

                $result = $SnSclient->publish($args);
                if ($testCall) {
                    return $result;
                } else {
                    return true;
                }
            } catch (Aws\Exception\AwsException $e) {
                if ($testCall) {
                    return $e->getMessage();
                } else {
                    return false;
                }
            }

            return true;
        case 28:
            $alfa_cell = get_option($option_slug . '_alfa_cell');

            $api_key = $alfa_cell['api_key'];
            $sender = $alfa_cell['sender'];

            $data = array(
                'msg' => $messagetemplate,
                'apiKey' => $api_key,
                'sender' => $sender,
                'applicationType' => '68',
                'numbers' => str_replace("+", "", $countrycode) . $mobile,
                'lang' => '3',
            );


            $ch = curl_init();

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            curl_setopt($ch, CURLOPT_URL, 'https://www.alfa-cell.com/api/msgSend.php?' . http_build_query($data));
            $result = curl_exec($ch);


            if (curl_errno($ch)) {
                if ($testCall) {
                    return "curl error:" . curl_errno($ch);
                }

                return false;
            }
            curl_close($ch);

            if ($testCall) {
                return $result;
            }

            if ($result === false) {
                return false;
            }

            return true;
        case 29:
            $apicred = get_option($option_slug . '_ibulksms');

            $authKey = $apicred['auth_key'];
            $senderId = $apicred['sender'];


            $message = urlencode($messagetemplate);


            $postData = array(
                'authkey' => $authKey,
                'mobiles' => str_replace("+", "", $countrycode) . $mobile,
                'message' => $message,
                'sender' => $senderId,
                'route' => 4,
                'country' => 0
            );


            $url = "https://manage.ibulksms.in/api/sendhttp.php";
            $ch = curl_init();
            curl_setopt_array($ch, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $postData

            ));
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

            $result = curl_exec($ch);


            if (curl_errno($ch)) {
                if ($testCall) {
                    return "curl error:" . curl_errno($ch);
                }

                return false;
            }
            curl_close($ch);

            if ($testCall) {
                return $result;
            }

            return true;

        case 30:
            if (!function_exists('Aws\parse_ini_file')) {
                unitedover_load_gateways_sdks();
            }

            $pinpoint = get_option($option_slug . '_amazon_pinpoint');
            $app_id = $pinpoint['app_id'];
            $from = $pinpoint['sender_id'];


            try {
                $pinpointClient = new Aws\Pinpoint\PinpointClient([
                    'region' => $pinpoint['region'],
                    'version' => 'latest',
                    'credentials' => [
                        'key' => $pinpoint['access_key'],
                        'secret' => $pinpoint['access_secret'],
                    ],
                ]);

                $sms_body = [
                    'Body' => $messagetemplate,
                    'MessageType' => 'TRANSACTIONAL',
                    'SenderId' => $from,
                ];

                $template_ids = array('message', 'template-id');

                $template_id = '';
                $entity_id = '';
                $message_obj = wpn_parse_message_template($messagetemplate, $template_ids);
                if (is_array($message_obj) && !empty($message_obj['template'])) {
                    $message_template = $message_obj['template'];
                    $message = $message_template['message'];

                    if (isset($message_template['template-id'])) {
                        $sms_body['TemplateId'] = $message_template['template-id'];
                    }
                    if (isset($message_template['entity-id'])) {
                        $sms_body['EntityId'] = $message_template['entity-id'];
                    }
                } else {
                    $message = $messagetemplate;

                    if (isset($pinpoint['template_id'])) {
                        $sms_body['TemplateId'] = $pinpoint['template_id'];
                    }

                }
                if (isset($pinpoint['entity_id'])) {
                    $sms_body['EntityId'] = $pinpoint['entity_id'];
                }

                $sms_body['Body'] = $message;

                $args = [
                    'ApplicationId' => $app_id, // REQUIRED
                    'MessageRequest' => [ // REQUIRED
                        'Addresses' => [
                            $countrycode . $mobile => [
                                'ChannelType' => 'SMS',
                            ],
                        ],
                        'MessageConfiguration' => [ // REQUIRED
                            'SMSMessage' => $sms_body,
                        ]
                    ],
                ];

                $result = $pinpointClient->sendMessages($args);
                if ($testCall) {
                    return $result;
                } else {
                    return true;
                }
            } catch (Aws\Exception\AwsException $e) {
                if ($testCall) {
                    return $e->getMessage();
                } else {
                    return false;
                }
            }

            return true;
        case 34:
            $gateway_fields = get_option($option_slug . '_opersms');

            require_once 'gateways/opersms.php';

            return \SMSGateway\OperSMS::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 35:
            $gateway_fields = get_option($option_slug . '_sparrowsms');

            require_once 'gateways/sparrowsms.php';

            return \SMSGateway\SparrowSMS::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 36:
            $gateway_fields = get_option($option_slug . '_infobip');

            require_once 'gateways/infobip.php';

            return \SMSGateway\InfoBIP::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 37:
            $gateway_fields = get_option($option_slug . '_adpdigital');

            require_once 'gateways/adpdigital.php';

            return \SMSGateway\ADPDigital::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 38:
            $gateway_fields = get_option($option_slug . '_spryng');

            require_once 'gateways/spryng.php';

            return \SMSGateway\Spryng::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 39:
            $gateway_fields = get_option($option_slug . '_karix');

            require_once 'gateways/karix.php';

            return \SMSGateway\Karix::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 40:
            $gateway_fields = get_option($option_slug . '_bandwidth');

            require_once 'gateways/bandwidth.php';

            return \SMSGateway\Bandwidth::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 41:
            $gateway_fields = get_option($option_slug . '_cdyne');

            require_once 'gateways/cdyne.php';

            return \SMSGateway\CDYNE::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 42:
            $gateway_fields = get_option($option_slug . '_engagespark');

            require_once 'gateways/engagespark.php';

            return \SMSGateway\EngageSpark::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 43:
            $gateway_fields = get_option($option_slug . '_kapsystem');

            require_once 'gateways/kapsystem.php';

            return \SMSGateway\KAPSystem::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 44:
            $gateway_fields = get_option($option_slug . '_telestax');

            require_once 'gateways/telestax.php';

            return \SMSGateway\Telestax::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 45:
            $gateway_fields = get_option($option_slug . '_ttagsystems');

            require_once 'gateways/ttagsystems.php';

            return \SMSGateway\TTAGSystems::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 46:
            $gateway_fields = get_option($option_slug . '_wavecell');

            require_once 'gateways/wavecell.php';

            return \SMSGateway\Wavecell::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 47:
            $gateway_fields = get_option($option_slug . '_smsaero');

            require_once 'gateways/smsaero.php';

            return \SMSGateway\SMSAero::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 48:
            $gateway_fields = get_option($option_slug . '_gatewayapi');

            require_once 'gateways/gatewayapi.php';

            return \SMSGateway\GatewayAPI::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 49:
            $gateway_fields = get_option($option_slug . '_agiletelecom');

            require_once 'gateways/agiletelecom.php';

            return \SMSGateway\AgileTelecom::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 50:
            $gateway_fields = get_option($option_slug . '_greentext');

            require_once 'gateways/greentext.php';

            return \SMSGateway\GreenText::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 51:
            $gateway_fields = get_option($option_slug . '_mnotify');

            require_once 'gateways/mnotify.php';

            return \SMSGateway\MNotify::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 52:
            $gateway_fields = get_option($option_slug . '_smsbroadcast');

            require_once 'gateways/smsbroadcast.php';

            return \SMSGateway\SMSBroadcast::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 53:
            $gateway_fields = get_option($option_slug . '_smsgatewayhub');

            require_once 'gateways/smsgatewayhub.php';

            return \SMSGateway\SMSGatewayHub::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 54:
            $gateway_fields = get_option($option_slug . '_thaibulksms');

            require_once 'gateways/thaibulksms.php';

            return \SMSGateway\ThaiBulkSMS::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 55:
            $gateway_fields = get_option($option_slug . '_smscountry');

            require_once 'gateways/smscountry.php';

            return \SMSGateway\SMSCountry::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 56:
            $gateway_fields = get_option($option_slug . '_textmagic');

            require_once 'gateways/textmagic.php';

            return \SMSGateway\TextMagic::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 57:
            $gateway_fields = get_option($option_slug . '_qsms');

            require_once 'gateways/qsms.php';

            return \SMSGateway\QSMS::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 58:
            $gateway_fields = get_option($option_slug . '_smsfactor');

            require_once 'gateways/smsfactor.php';

            return \SMSGateway\SMSFactor::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 59:
            $gateway_fields = get_option($option_slug . '_esms');

            require_once 'gateways/esms.php';

            return \SMSGateway\ESMS::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 60:
            $gateway_fields = get_option($option_slug . '_isms');

            require_once 'gateways/isms.php';

            return \SMSGateway\ISMS::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 61:
            $gateway_fields = get_option($option_slug . '_textplode');

            require_once 'gateways/textplode.php';

            return \SMSGateway\Textplode::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 62:
            $gateway_fields = get_option($option_slug . '_routesms');

            require_once 'gateways/routesms.php';

            return \SMSGateway\RouteSMS::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 63:
            $gateway_fields = get_option($option_slug . '_skebby');

            require_once 'gateways/skebby.php';

            return \SMSGateway\Skebby::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 64:
            $gateway_fields = get_option($option_slug . '_sendhub');

            require_once 'gateways/sendhub.php';

            return \SMSGateway\SendHub::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 132:
            $gateway_fields = get_option($option_slug . '_proovl');

            require_once 'gateways/proovl.php';

            return \SMSGateway\Proovl::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 65:
            $gateway_fields = get_option($option_slug . '_tyntec');

            require_once 'gateways/tyntec.php';

            return \SMSGateway\Tyntec::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 66:
            $gateway_fields = get_option($option_slug . '_bulksmsnigeria');

            require_once 'gateways/bulksmsnigeria.php';

            return \SMSGateway\BulkSMSNigeria::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 67:
            $gateway_fields = get_option($option_slug . '_bulksms');

            require_once 'gateways/bulksms.php';

            return \SMSGateway\BulkSMS::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 68:
            $gateway_fields = get_option($option_slug . '_esendex');

            require_once 'gateways/esendex.php';
            return \SMSGateway\Esendex::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 69:
            $gateway_fields = get_option($option_slug . '_websms');

            require_once 'gateways/websms.php';

            return \SMSGateway\WebSMS::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 70:
            $gateway_fields = get_option($option_slug . '_smsglobal');

            require_once 'gateways/smsglobal.php';

            return \SMSGateway\SMSGlobal::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 71:
            $gateway_fields = get_option($option_slug . '_fortytwo');

            require_once 'gateways/fortytwo.php';

            return \SMSGateway\FortyTwo::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 72:
            $gateway_fields = get_option($option_slug . '_primotexto');

            require_once 'gateways/primotexto.php';

            return \SMSGateway\Primotexto::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 73:
            $gateway_fields = get_option($option_slug . '_spirius');

            require_once 'gateways/spirius.php';

            return \SMSGateway\Spirius::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 74:
            $gateway_fields = get_option($option_slug . '_experttexting');

            require_once 'gateways/experttexting.php';

            return \SMSGateway\ExpertTexting::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 75:
            $gateway_fields = get_option($option_slug . '_jusibe');

            require_once 'gateways/jusibe.php';

            return \SMSGateway\Jusibe::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 76:
            $gateway_fields = get_option($option_slug . '_mensatek');

            require_once 'gateways/mensatek.php';

            return \SMSGateway\Mensatek::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 77:
            $gateway_fields = get_option($option_slug . '_speedsms');

            require_once 'gateways/speedsms.php';

            return \SMSGateway\SpeedSMS::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 78:
            $gateway_fields = get_option($option_slug . '_smsmisr');

            require_once 'gateways/smsmisr.php';

            return \SMSGateway\SMSMISR::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 79:
            $gateway_fields = get_option($option_slug . '_jazzcmt');

            require_once 'gateways/jazzcmt.php';

            return \SMSGateway\JazzCMT::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 80:
            $gateway_fields = get_option($option_slug . '_moceansms');

            require_once 'gateways/moceansms.php';

            return \SMSGateway\MoceanSMS::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 81:
            $gateway_fields = get_option($option_slug . '_sendsms247');

            require_once 'gateways/sendsms247.php';

            return \SMSGateway\SendSMS247::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 82:
            $gateway_fields = get_option($option_slug . '_smscua');

            require_once 'gateways/smscua.php';

            return \SMSGateway\SmscUA::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 83:
            $gateway_fields = get_option($option_slug . '_cpsms');

            require_once 'gateways/cpsmsdk.php';

            return \SMSGateway\CPSMSDK::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 84:
            $gateway_fields = get_option($option_slug . '_1s2u');

            require_once 'gateways/first2u.php';

            return \SMSGateway\First2U::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 85:
            $gateway_fields = get_option($option_slug . '_textanywhere');

            require_once 'gateways/textanywhere.php';

            return \SMSGateway\TextAnywhere::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 86:
            $gateway_fields = get_option($option_slug . '_sms77');

            require_once 'gateways/sms77.php';

            return \SMSGateway\SMS77::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 87:
            $gateway_fields = get_option($option_slug . '_verimor');

            require_once 'gateways/verimor.php';

            return \SMSGateway\Verimor::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 88:
            $gateway_fields = get_option($option_slug . '_labsmobile');

            require_once 'gateways/labsmobile.php';

            return \SMSGateway\LabsMobile::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 89:
            $gateway_fields = get_option($option_slug . '_unisender');

            require_once 'gateways/unisender.php';

            return \SMSGateway\Unisender::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 90:
            $gateway_fields = get_option($option_slug . '_aruba');

            require_once 'gateways/aruba.php';

            return \SMSGateway\Aruba::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 91:
            $gateway_fields = get_option($option_slug . '_comilio');

            require_once 'gateways/comilio.php';

            return \SMSGateway\Comilio::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );

        case 92:
            $gateway_fields = get_option($option_slug . '_smshosting');

            require_once 'gateways/smshosting.php';

            return \SMSGateway\SMSHosting::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 93:
            $gateway_fields = get_option($option_slug . '_gateway');

            require_once 'gateways/gateway.php';

            return \SMSGateway\Gateway::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 94:
            $gateway_fields = get_option($option_slug . '_uwaziimobile');

            require_once 'gateways/uwaziimobile.php';

            return \SMSGateway\UwaziiMobile::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 95:
            $gateway_fields = get_option($option_slug . '_suresms');

            require_once 'gateways/suresms.php';

            return \SMSGateway\SureSMS::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 96:
            $gateway_fields = get_option($option_slug . '_easysendsms');

            require_once 'gateways/easysendsms.php';

            return \SMSGateway\EasysendSMS::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 97:
            $gateway_fields = get_option($option_slug . '_sinch');

            require_once 'gateways/sinch.php';

            return \SMSGateway\Sinch::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 98:
            $gateway_fields = get_option($option_slug . '_smsala');

            require_once 'gateways/smsala.php';

            return \SMSGateway\SMSAla::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 99:
            $gateway_fields = get_option($option_slug . '_smsempresa');

            require_once 'gateways/smsempresa.php';

            return \SMSGateway\SMSEmpresa::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 100:
            $gateway_fields = get_option($option_slug . '_semaphore');

            require_once 'gateways/semaphore.php';

            return \SMSGateway\Semaphore::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 101:
            $gateway_fields = get_option($option_slug . '_wavy');

            require_once 'gateways/wavy.php';

            return \SMSGateway\Wavy::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 102:
            $gateway_fields = get_option($option_slug . '_smsto');

            require_once 'gateways/smsto.php';

            return \SMSGateway\SMSTo::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 103:
            $gateway_fields = get_option($option_slug . '_telnyx');

            require_once 'gateways/telnyx.php';

            return \SMSGateway\Telnyx::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 104:
            $gateway_fields = get_option($option_slug . '_telesign');

            require_once 'gateways/telesign.php';

            return \SMSGateway\TeleSign::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 105:
            $gateway_fields = get_option($option_slug . '_d7networks');

            require_once 'gateways/d7networks.php';

            return \SMSGateway\D7Networks::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 106:
            $gateway_fields = get_option($option_slug . '_ismsindonesia');

            require_once 'gateways/ismsindonesia.php';

            return \SMSGateway\ISMSIndonesia::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 107:
            $gateway_fields = get_option($option_slug . '_sendpk');

            require_once 'gateways/sendpk.php';

            return \SMSGateway\SendPK::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 108:
            $gateway_fields = get_option($option_slug . '_mimsms');

            require_once 'gateways/mimsms.php';

            return \SMSGateway\MimSMS::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 109:
            $gateway_fields = get_option($option_slug . '_openmarket');

            require_once 'gateways/openmarket.php';

            return \SMSGateway\OpenMarket::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 110:
            $gateway_fields = get_option($option_slug . '_mobyt');

            require_once 'gateways/mobyt.php';

            return \SMSGateway\MobyT::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 111:
            $gateway_fields = get_option($option_slug . '_tm4b');

            require_once 'gateways/tm4b.php';

            return \SMSGateway\TM4B::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 112:
            $gateway_fields = get_option($option_slug . '_swiftsmsgateway');

            require_once 'gateways/swiftsmsgateway.php';

            return \SMSGateway\SwiftSMSGateway::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 113:
            $gateway_fields = get_option($option_slug . '_2factor');

            require_once 'gateways/twofactor.php';

            return \SMSGateway\TwoFactor::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 114:
            $gateway_fields = get_option($option_slug . '_gupshup');

            require_once 'gateways/gupshup.php';

            return \SMSGateway\GupShup::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 115:
            $gateway_fields = get_option($option_slug . '_digimiles');

            require_once 'gateways/digimiles.php';

            return \SMSGateway\Digimiles::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 116:
            $gateway_fields = get_option($option_slug . '_callfire');

            require_once 'gateways/callfire.php';

            return \SMSGateway\CallFire::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 117:
            $gateway_fields = get_option($option_slug . '_nowsms');

            require_once 'gateways/nowsms.php';

            return \SMSGateway\NowSMS::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 118:
            $gateway_fields = get_option($option_slug . '_releans');

            require_once 'gateways/releans.php';

            return \SMSGateway\Releans::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 119:
            $gateway_fields = get_option($option_slug . '_zipwhip');

            require_once 'gateways/zipwhip.php';

            return \SMSGateway\ZipWhip::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 120:
            $gateway_fields = get_option($option_slug . '_messagemedia');

            require_once 'gateways/messagemedia.php';

            return \SMSGateway\MessageMedia::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 121:
            $gateway_fields = get_option($option_slug . '_thesmsworks');

            require_once 'gateways/thesmsworks.php';

            return \SMSGateway\TheSMSWorks::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 122:
            $gateway_fields = get_option($option_slug . '_mogreet');

            require_once 'gateways/mogreet.php';

            return \SMSGateway\Mogreet::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 123:
            $gateway_fields = get_option($option_slug . '_46elks');

            require_once 'gateways/fortysixelks.php';

            return \SMSGateway\FortySixElks::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 124:
            $gateway_fields = get_option($option_slug . '_slicktext');

            require_once 'gateways/slicktext.php';

            return \SMSGateway\SlickText::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 125:
            $gateway_fields = get_option($option_slug . '_smsidea');

            require_once 'gateways/smsidea.php';

            return \SMSGateway\SMSIdea::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 126:
            $gateway_fields = get_option($option_slug . '_tatango');

            require_once 'gateways/tatango.php';

            return \SMSGateway\Tatango::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 127:
            $gateway_fields = get_option($option_slug . '_smsedge');

            require_once 'gateways/smsedge.php';

            return \SMSGateway\SMSEdge::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 128:
            $gateway_fields = get_option($option_slug . '_smsmasivos');

            require_once 'gateways/smsmasivos.php';

            return \SMSGateway\SMSMasivos::sendSMS(
                $gateway_fields, $countrycode, $mobile, $messagetemplate, $testCall
            );
        case 129:
            $gateway_fields = get_option($option_slug . '_commzgate');

            require_once 'gateways/commzgate.php';

            return \SMSGateway\Commzgate::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 130:
            $gateway_fields = get_option($option_slug . '_sms123');

            require_once 'gateways/sms123.php';
            return \SMSGateway\SMS123::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 131:
            $gateway_fields = get_option($option_slug . '_sms_ru');
            require_once 'gateways/sms_ru.php';

            return \SMSGateway\SMS_RU::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 133:
            $gateway_fields = get_option($option_slug . '_messente');

            require_once 'gateways/messente.php';

            return \SMSGateway\Messente::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 134:
            $gateway_fields = get_option($option_slug . '_text_marketer');

            require_once 'gateways/textmarketer.php';

            return \SMSGateway\TextMarketer::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 135:
            $gateway_fields = get_option($option_slug . '_spring_edge');

            require_once 'gateways/springedge.php';

            return \SMSGateway\SpringEdge::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 136:
            $gateway_fields = get_option($option_slug . '_signalwire');
            require_once 'gateways/signalwire.php';

            return \SMSGateway\SignalWire::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 137:
            $gateway_fields = get_option($option_slug . '_camoo');
            require_once 'gateways/camoo.php';

            return \SMSGateway\Camoo::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 138:
            $gateway_fields = get_option($option_slug . '_cm_com');
            require_once 'gateways/cm.com.php';
            return \SMSGateway\CM_COM::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 139:
            $gateway_fields = get_option($option_slug . '_ooredoo_sms');
            require_once 'gateways/ooredoo-sms.php';
            return \SMSGateway\Ooredoo_SMS::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 140:
            $gateway_fields = get_option($option_slug . '_max-sms');
            require_once 'gateways/Max-SMS.php';
            return \SMSGateway\Max_SMS::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 141:
            $gateway_fields = get_option($option_slug . '_payam_resan');
            require_once 'gateways/payam_resan.php';
            return \SMSGateway\Payam_Resan::sendSMS(
                $gateway_fields, $countrycode, $mobile, $messagetemplate, $testCall
            );
        case 142:
            $gateway_fields = get_option($option_slug . '_foxglove');
            require_once 'gateways/foxglove.php';
            return \SMSGateway\Foxglove::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 143:
            $gateway_fields = get_option($option_slug . '_txtsync');
            require_once 'gateways/txtsync.php';
            return \SMSGateway\TxtSync::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 144:
            $gateway_fields = get_option($option_slug . '_serwersms');
            require_once 'gateways/serwersms.php';
            return \SMSGateway\SerwerSms::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );

        case 145:
            $gateway_fields = get_option($option_slug . '_orange_gateway');
            require_once 'gateways/orange.php';
            return \SMSGateway\OrangeSms::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );


        case 146:
            $gateway_fields = get_option($option_slug . '_msegat');
            require_once 'gateways/msegat.php';
            return \SMSGateway\MSEGAT::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );

        case 147:
            $gateway_fields = get_option($option_slug . '_altiria');
            require_once 'gateways/altiria.php';
            return \SMSGateway\Altiria::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 148:
            $gateway_fields = get_option($option_slug . '_redsms');
            require_once 'gateways/redsms.php';
            return \SMSGateway\redsms::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 149:
            $gateway_fields = get_option($option_slug . '_osonsms');
            require_once 'gateways/osonsms.php';
            return \SMSGateway\OsonSMS::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 150:
            $gateway_fields = get_option($option_slug . '_dooae');
            require_once 'gateways/dooae.php';
            return \SMSGateway\DooAeSMS::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 151:
            $gateway_fields = get_option($option_slug . '_smsir_gateway');
            require_once 'gateways/smsir_gateway.php';
            return \SMSGateway\SMSIR::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 152:
            $gateway_fields = get_option($option_slug . '_notify_lk');
            require_once 'gateways/notify_lk.php';
            return \SMSGateway\NotifyLK::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 153:
            $gateway_fields = get_option($option_slug . '_malath');
            require_once 'gateways/malath.php';
            return \SMSGateway\Malath::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 154:
            $gateway_fields = get_option($option_slug . '_smsalert');
            require_once 'gateways/smsalert.php';
            return \SMSGateway\SMSAlert::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        /*case 155:
            $gateway_fields = get_option($option_slug . '_rangine');
            require_once 'gateways/rangine.php';
            return \SMSGateway\RagineSMS::sendSMS(
                $gateway_fields, $countrycode, $mobile, $messagetemplate, $testCall
            );*/
        case 156:
            $gateway_fields = get_option($option_slug . '_turkeysms');
            require_once 'gateways/turkeysms.php';
            return \SMSGateway\TurkeySMS::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 157:
            $gateway_fields = get_option($option_slug . '_sozuri');
            require_once 'gateways/sozuri.php';
            return \SMSGateway\Sozuri::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 158:
            $gateway_fields = get_option($option_slug . '_kivalo');
            require_once 'gateways/kivalo.php';
            return \SMSGateway\Kivalo::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );

        case 159:
            $gateway_fields = get_option($option_slug . '_sms_ninja');
            require_once 'gateways/sms_ninja.php';
            return \SMSGateway\SMSNinja::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 160:
            $gateway_fields = get_option($option_slug . '_sms_mode');
            require_once 'gateways/sms_mode.php';
            return \SMSGateway\SMSMode::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 161:
            $gateway_fields = get_option($option_slug . '_brandedsmspakistan');
            require_once 'gateways/brandedsmspakistan.php';
            return \SMSGateway\BrandedSMSPakistan::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 162:
            $gateway_fields = get_option($option_slug . '_sms_routee');
            require_once 'gateways/sms_routee.php';
            return \SMSGateway\Routee::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 163:
            $gateway_fields = get_option($option_slug . '_web2sms237');
            require_once 'gateways/web2sms237.php';
            return \SMSGateway\Web2SMS237::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 164:
            $gateway_fields = get_option($option_slug . '_beeline');
            require_once 'gateways/beeline.php';
            return \SMSGateway\Beeline::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 165:
            $gateway_fields = get_option($option_slug . '_sms_cc');
            require_once 'gateways/sms_cc.php';
            return \SMSGateway\SMSCC::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 166:
            $gateway_fields = get_option($option_slug . '_kavenegar');
            require_once 'gateways/kavenegar.php';
            return \SMSGateway\Kayenegar::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 167:
            $gateway_fields = get_option($option_slug . '_nhn_toast');
            require_once 'gateways/nhn_toast.php';
            return \SMSGateway\NHNToast::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 168:
            $gateway_fields = get_option($option_slug . '_hubtel');
            require_once 'gateways/hubtel.php';
            return \SMSGateway\Hubtel::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 169:
            $gateway_fields = get_option($option_slug . '_globelabs');
            require_once 'gateways/globelabs.php';
            return \SMSGateway\GlobeLabs::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 170:
            $gateway_fields = get_option($option_slug . '_wholesale');
            require_once 'gateways/wholesale.php';
            return \SMSGateway\SMSwholesale::sendSMS(
                $gateway_fields, $countrycode, $mobile, $messagetemplate, $testCall
            );
        case 171:
            $gateway_fields = get_option($option_slug . '_turbosmsbiz');
            require_once 'gateways/turbosms.biz.php';
            return \SMSGateway\TurboSMS::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 172:
            $gateway_fields = get_option($option_slug . '_mobilenetsa');
            require_once 'gateways/mobilenetsa.php';
            return \SMSGateway\MobilenetSa::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 173:
            $gateway_fields = get_option($option_slug . '_eskizeu');
            require_once 'gateways/eskiz_eu.php';
            return \SMSGateway\EskizEu::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 174:
            $gateway_fields = get_option($option_slug . '_oursms');
            require_once 'gateways/oursms.php';
            return \SMSGateway\OurSMS::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 175:
            $gateway_fields = get_option($option_slug . '_beemafrica');
            require_once 'gateways/BeemGateway.php';
            return \SMSGateway\BeemGateway::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 176:
            $gateway_fields = get_option($option_slug . '_turbosmstop');
            require_once 'gateways/turbosms.top.php';
            return \SMSGateway\TurboSMSTop::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 177:
            $gateway_fields = get_option($option_slug . '_quicksmsxyz');
            require_once 'gateways/quicksms.xyz.php';
            return \SMSGateway\QuickSMS::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 178:
            $gateway_fields = get_option($option_slug . '_telnor');
            require_once 'gateways/telnor.php';
            return \SMSGateway\TelnorSMS::sendSMS(
                $gateway_fields, $countrycode . $mobile, $messagetemplate, $testCall
            );
        case 179:
            $gateway_fields = get_option($option_slug . '_fasttosms');
            require_once 'gateways/fast2sms.php';
            return \SMSGateway\Fast2SMS::sendSMS(
                $gateway_fields, $countrycode, $mobile, $messagetemplate, $testCall
            );
        case 180:
            $gateway_fields = get_option($option_slug . '_sendlime');
            require_once 'gateways/sendlime.php';
            return \SMSGateway\SendLime::sendSMS(
                $gateway_fields, $countrycode, $mobile, $messagetemplate, $testCall
            );
        case 181:
            unitedover_load_gateways_sdks();
            $gateway_fields = get_option($option_slug . '_aliyun');
            require_once 'gateways/aliyun.php';
            return \SMSGateway\Aliyun::sendSMS(
                $gateway_fields, $countrycode, $mobile, $messagetemplate, $testCall
            );
        case 182:
            $gateway_fields = get_option($option_slug . '_smsconnexion');
            require_once 'gateways/smsconnexion.php';
            return \SMSGateway\SmsConnexion::sendSMS(
                $gateway_fields, $countrycode, $mobile, $messagetemplate, $testCall
            );
        case 183:
            $gateway_fields = get_option($option_slug . '_afilnet');
            require_once 'gateways/afilnet.php';
            return \SMSGateway\AfilNet::sendSMS(
                $gateway_fields, $countrycode, $mobile, $messagetemplate, $testCall
            );
        case 184:
            $gateway_fields = get_option($option_slug . '_tencent');

            require_once 'gateways/tencent.php';
            return \SMSGateway\Tencent::sendSMS(
                $gateway_fields,
                $countrycode . $mobile, $messagetemplate,
                $testCall
            );

        case 185:
            $gateway_fields = get_option($option_slug . '_sms4world');
            require_once 'gateways/sms4world.php';
            return \SMSGateway\SMS4World::sendSMS(
                $gateway_fields,
                $countrycode . $mobile, $messagetemplate,
                $testCall
            );

        case 186:
            $gateway_fields = get_option($option_slug . '_enablex');
            require_once 'gateways/enablex.php';
            return SMSGateway\Enablex::sendSMS(
                $gateway_fields,
                $countrycode . $mobile, $messagetemplate,
                $testCall
            );

        case 187:
            $gateway_fields = get_option($option_slug . '_trumpia');

            require_once 'gateways/trumpia.php';
            return \SMSGateway\Trumpia::sendSMS(
                $gateway_fields,
                $countrycode . $mobile, $messagetemplate,
                $testCall
            );

        case 188:
            $gateway_fields = get_option($option_slug . '_truedialog');
            require_once 'gateways/truedialog.php';
            return \SMSGateway\Truedialog::sendSMS(
                $gateway_fields,
                $countrycode . $mobile, $messagetemplate,
                $testCall
            );

        case 189:
            $gateway_fields = get_option($option_slug . '_worldtext');

            require_once 'gateways/worldtext.php';
            return \SMSGateway\WorldText::sendSMS(
                $gateway_fields,
                $countrycode . $mobile, $messagetemplate,
                $testCall
            );

        case 190:
            $gateway_fields = get_option($option_slug . '_directsms');

            require_once 'gateways/directsms.php';
            return \SMSGateway\DirectSMS::sendSMS(
                $gateway_fields,
                $countrycode . $mobile, $messagetemplate,
                $testCall
            );

        case 191:
            $gateway_fields = get_option($option_slug . '_textdrip');
            require_once 'gateways/textdrip.php';
            return \SMSGateway\TextDrip::sendSMS(
                $gateway_fields,
                $countrycode . $mobile, $messagetemplate,
                $testCall
            );
        case 192:
            $gateway_fields = get_option($option_slug . '_authkey');
            require_once 'gateways/authkey.php';
            return \SMSGateway\AuthKey::sendSMS(
                $gateway_fields,
                $countrycode, $mobile, $messagetemplate,
                $testCall
            );
        case 193:
            $gateway_fields = get_option($option_slug . '_dexatel');
            require_once 'gateways/dexatel.php';
            return \SMSGateway\DexaTel::sendSMS(
                $gateway_fields,
                $countrycode . $mobile, $messagetemplate,
                $testCall
            );
        case 194:
            $gateway_fields = get_option($option_slug . '_dinahosting');
            require_once 'gateways/dinahosting.php';
            return \SMSGateway\DinaHosting::sendSMS(
                $gateway_fields,
                $countrycode . $mobile, $messagetemplate,
                $testCall
            );
        case 195:
            $gateway_fields = get_option($option_slug . '_mobtexting');
            require_once 'gateways/mobtexting.php';
            return \SMSGateway\MobTexting::sendSMS(
                $gateway_fields,
                $countrycode . $mobile, $messagetemplate,
                $testCall
            );
        case 196:
            $gateway_fields = get_option($option_slug . '_nextsms');
            require_once 'gateways/nextsms.php';
            return \SMSGateway\NextSMS::sendSMS(
                $gateway_fields,
                $countrycode . $mobile, $messagetemplate,
                $testCall
            );
        case 197:
            $gateway_fields = get_option($option_slug . '_octopush');
            require_once 'gateways/octopush.php';
            return \SMSGateway\Octopush::sendSMS(
                $gateway_fields,
                $countrycode . $mobile, $messagetemplate,
                $testCall
            );
        case 198:
            $gateway_fields = get_option($option_slug . '_smseagle');
            require_once 'gateways/smseagle.php';
            return \SMSGateway\SMSEagle::sendSMS(
                $gateway_fields,
                $countrycode . $mobile, $messagetemplate,
                $testCall
            );
        case 199:
            $gateway_fields = get_option($option_slug . '_smstech');
            require_once 'gateways/smstech.php';
            return \SMSGateway\SMSTech::sendSMS(
                $gateway_fields,
                $countrycode . $mobile, $messagetemplate,
                $testCall
            );
        case 200:
            $gateway_fields = get_option($option_slug . '_smsnoc');
            require_once 'gateways/smsnoc.php';
            return \SMSGateway\SMSNoc::sendSMS(
                $gateway_fields,
                $countrycode . $mobile, $messagetemplate,
                $testCall
            );
        default:
            return false;
    }
}


function _send_bulk_one_at_a_time($klass, $gateway_fields, $messages, $test_call)
{
    $last_sent = -1;

    foreach ($messages as $index => $message_description) {
        foreach ($message_description as $mobile => $message) {
            $res = $klass::sendSMS($gateway_fields, $mobile, $message, $test_call);

            if ($res) {
                $last_sent = $index;
            } else {
                return $last_sent;
            }
        }
    }

    return $last_sent;
}

/*
 * @returns, index of last message send
 * $messages array example ->
 * here, key is index, and values is a array whose key is a mobile number (with country code) and value is text message
 * array(
 * 1 => array('9xxxxx' => 'Message'),
 * 2 => array('8xxxxx' => 'Message2')
 * )
 * */
function untdovr_send_bulk_sms($option_slug, $gateway_id, $messages, $testCall)
{
    switch ($gateway_id) {
        case 34:
            $gateway_fields = get_option($option_slug . '_opersms');

            require_once 'gateways/opersms.php';

            return _send_bulk_one_at_a_time(
                \SMSGateway\OperSMS::class, $gateway_fields, $messages, $testCall
            );
        case 35:
            $gateway_fields = get_option($option_slug . '_sparrowsms');
            require_once 'gateways/sparrowsms.php';

            return _send_bulk_one_at_a_time(
                \SMSGateway\SparrowSMS::class, $gateway_fields, $messages, $testCall
            );
        case 36:
            $gateway_fields = get_option($option_slug . '_infobip');

            require_once 'gateways/infobip.php';

            return _send_bulk_one_at_a_time(
                \SMSGateway\InfoBip::class, $gateway_fields, $messages, $testCall
            );
        case 37:
            $gateway_fields = get_option($option_slug . '_sprying');

            require_once 'gateways/sprying.php';

            return _send_bulk_one_at_a_time(
                \SMSGateway\ADPDIGITAL::class, $gateway_fields, $messages, $testCall
            );
        case 38:
            $gateway_fields = get_option($option_slug . '_sprying');

            require_once 'gateways/sprying.php';

            return _send_bulk_one_at_a_time(
                \SMSGateway\Spryng::class, $gateway_fields, $messages, $testCall
            );
        case 39:
            $gateway_fields = get_option($option_slug . '_karix');

            require_once 'gateways/karix.php';

            return _send_bulk_one_at_a_time(
                \SMSGateway\Karix::class, $gateway_fields, $messages, $testCall
            );
        case 40:
            $gateway_fields = get_option($option_slug . '_bandwidth');

            require_once 'gateways/bandwidth.php';

            return _send_bulk_one_at_a_time(
                \SMSGateway\Bandwidth::class, $gateway_fields, $messages, $testCall
            );
        case 41:
            $gateway_fields = get_option($option_slug . '_cdyne');

            require_once 'gateways/cdyne.php';

            return _send_bulk_one_at_a_time(
                \SMSGateway\CDYNE::class, $gateway_fields, $messages, $testCall
            );
        case 42:
            $gateway_fields = get_option($option_slug . '_engagespark');

            require_once 'gateways/engagespark.php';

            return _send_bulk_one_at_a_time(
                \SMSGateway\EngageSpark::class, $gateway_fields, $messages, $testCall
            );
        case 43:
            $gateway_fields = get_option($option_slug . '_kapsystem');

            require_once 'gateways/kapsystem.php';

            return _send_bulk_one_at_a_time(
                \SMSGateway\KAPSystem::class, $gateway_fields, $messages, $testCall
            );
        case 44:
            $gateway_fields = get_option($option_slug . '_telestax');

            require_once 'gateways/telestax.php';

            return _send_bulk_one_at_a_time(
                \SMSGateway\Telestax::class, $gateway_fields, $messages, $testCall
            );
        case 45:
            $gateway_fields = get_option($option_slug . '_ttagsystems');

            require_once 'gateways/ttagsystems.php';

            return _send_bulk_one_at_a_time(
                \SMSGateway\TTAGSystems::class, $gateway_fields, $messages, $testCall
            );
        case 46:
            $gateway_fields = get_option($option_slug . '_wavecell');

            require_once 'gateways/wavecell.php';

            return _send_bulk_one_at_a_time(
                \SMSGateway\Wavecell::class, $gateway_fields, $messages, $testCall
            );
        case 47:
            $gateway_fields = get_option($option_slug . '_smsaero');

            require_once 'gateways/smsaero.php';

            return _send_bulk_one_at_a_time(
                \SMSGateway\SMSAero::class, $gateway_fields, $messages, $testCall
            );
        case 48:
            $gateway_fields = get_option($option_slug . '_gatewayapi');

            require_once 'gateways/gatewayapi.php';

            return _send_bulk_one_at_a_time(
                \SMSGateway\GatewayAPI::class, $gateway_fields, $messages, $testCall
            );
        case 49:
            $gateway_fields = get_option($option_slug . '_agiletelecom');

            require_once 'gateways/agiletelecom.php';

            return _send_bulk_one_at_a_time(
                \SMSGateway\AgileTelecom::class, $gateway_fields, $messages, $testCall
            );
        case 50:
            $gateway_fields = get_option($option_slug . '_greentext');

            require_once 'gateways/greentext.php';

            return _send_bulk_one_at_a_time(
                \SMSGateway\GreenText::class, $gateway_fields, $messages, $testCall
            );
        case 51:
            $gateway_fields = get_option($option_slug . '_mnotify');

            require_once 'gateways/mnotify.php';

            return _send_bulk_one_at_a_time(
                \SMSGateway\MNotify::class, $gateway_fields, $messages, $testCall
            );
        case 52:
            $gateway_fields = get_option($option_slug . '_smsbroadcast');

            require_once 'gateways/smsbroadcast.php';

            return _send_bulk_one_at_a_time(
                \SMSGateway\SMSBroadcast::class, $gateway_fields, $messages, $testCall
            );
        case 53:
            $gateway_fields = get_option($option_slug . '_smsgatewayhub');

            require_once 'gateways/smsgatewayhub.php';

            return _send_bulk_one_at_a_time(
                \SMSGateway\SMSGatewayHub::class, $gateway_fields, $messages, $testCall
            );
        case 54:
            $gateway_fields = get_option($option_slug . '_thaibulksms');

            require_once 'gateways/thaibulksms.php';

            return _send_bulk_one_at_a_time(
                \SMSGateway\ThaiBulkSMS::class, $gateway_fields, $messages, $testCall
            );
        case 55:
            $gateway_fields = get_option($option_slug . '_smscountry');

            require_once 'gateways/smscountry.php';

            return _send_bulk_one_at_a_time(
                \SMSGateway\SMSCountry::class, $gateway_fields, $messages, $testCall
            );
        case 56:
            $gateway_fields = get_option($option_slug . '_textmagic');

            require_once 'gateways/textmagic.php';

            return _send_bulk_one_at_a_time(
                \SMSGateway\TextMagic::class, $gateway_fields, $messages, $testCall
            );
        case 57:
            $gateway_fields = get_option($option_slug . '_qsms');

            require_once 'gateways/qsms.php';

            return _send_bulk_one_at_a_time(
                \SMSGateway\QSMS::class, $gateway_fields, $messages, $testCall
            );
        case 58:
            $gateway_fields = get_option($option_slug . '_smsfactor');

            require_once 'gateways/smsfactor.php';

            return _send_bulk_one_at_a_time(
                \SMSGateway\SMSFactor::class, $gateway_fields, $messages, $testCall
            );
        case 59:
            $gateway_fields = get_option($option_slug . '_esms');

            require_once 'gateways/esms.php';

            return _send_bulk_one_at_a_time(
                \SMSGateway\ESMS::class, $gateway_fields, $messages, $testCall
            );
        case 60:
            $gateway_fields = get_option($option_slug . '_isms');

            require_once 'gateways/isms.php';

            return _send_bulk_one_at_a_time(
                \SMSGateway\ISMS::class, $gateway_fields, $messages, $testCall
            );
        case 61:
            $gateway_fields = get_option($option_slug . '_textplode');

            require_once 'gateways/textplode.php';

            return _send_bulk_one_at_a_time(
                \SMSGateway\Textplode::class, $gateway_fields, $messages, $testCall
            );
        case 62:
            $gateway_fields = get_option($option_slug . '_routesms');

            require_once 'gateways/routesms.php';

            return _send_bulk_one_at_a_time(
                \SMSGateway\RouteSMS::class, $gateway_fields, $messages, $testCall
            );
        case 63:
            $gateway_fields = get_option($option_slug . '_skebby');

            require_once 'gateways/skebby.php';

            return _send_bulk_one_at_a_time(
                \SMSGateway\Skebby::class, $gateway_fields, $messages, $testCall
            );
        case 64:
            $gateway_fields = get_option($option_slug . '_sendhub');

            require_once 'gateways/sendhub.php';

            return _send_bulk_one_at_a_time(
                \SMSGateway\SendHub::class, $gateway_fields, $messages, $testCall
            );
        case 132:
            $gateway_fields = get_option($option_slug . '_proovl');

            require_once 'gateways/proovl.php';

            return _send_bulk_one_at_a_time(
                \SMSGateway\Proovl::class, $gateway_fields, $messages, $testCall
            );
        case 65:
            $gateway_fields = get_option($option_slug . '_tyntec');

            require_once 'gateways/tyntec.php';

            return _send_bulk_one_at_a_time(
                \SMSGateway\Tyntec::class, $gateway_fields, $messages, $testCall
            );
        case 66:
            $gateway_fields = get_option($option_slug . '_bulksmsnigeria');

            require_once 'gateways/bulksmsnigeria.php';

            return _send_bulk_one_at_a_time(
                \SMSGateway\BulkSMSNigeria::class, $gateway_fields, $messages, $testCall
            );
        case 67:
            $gateway_fields = get_option($option_slug . '_bulksms');

            require_once 'gateways/bulksms.php';

            return _send_bulk_one_at_a_time(
                \SMSGateway\BulkSMS::class, $gateway_fields, $messages, $testCall
            );
        case 68:
            $gateway_fields = get_option($option_slug . '_esendex');

            require_once 'gateways/esendex.php';

            return _send_bulk_one_at_a_time(
                \SMSGateway\Esendex::class, $gateway_fields, $messages, $testCall
            );
        case 69:
            $gateway_fields = get_option($option_slug . '_websms');

            require_once 'gateways/websms.php';

            return _send_bulk_one_at_a_time(
                \SMSGateway\WebSMS::class, $gateway_fields, $messages, $testCall
            );
        case 70:
            $gateway_fields = get_option($option_slug . '_globalsms');

            require_once 'gateways/smsglobal.php';

            return _send_bulk_one_at_a_time(
                \SMSGateway\GlobalSMS::class, $gateway_fields, $messages, $testCall
            );
        case 71:
            $gateway_fields = get_option($option_slug . '_fortytwo');

            require_once 'gateways/fortytwo.php';

            return _send_bulk_one_at_a_time(
                \SMSGateway\FortyTwo::class, $gateway_fields, $messages, $testCall
            );
        case 72:
            $gateway_fields = get_option($option_slug . '_primotexto');

            require_once 'gateways/primotexto.php';

            return _send_bulk_one_at_a_time(
                \SMSGateway\Primotexto::class, $gateway_fields, $messages, $testCall
            );
        case 73:
            $gateway_fields = get_option($option_slug . '_spirius');

            require_once 'gateways/spirius.php';

            return _send_bulk_one_at_a_time(
                \SMSGateway\Spirius::class, $gateway_fields, $messages, $testCall
            );
        case 74:
            $gateway_fields = get_option($option_slug . '_experttexting');

            require_once 'gateways/experttexting.php';

            return _send_bulk_one_at_a_time(
                \SMSGateway\ExpertTexting::class, $gateway_fields, $messages, $testCall
            );
        case 75:
            $gateway_fields = get_option($option_slug . '_jusibe');

            require_once 'gateways/jusibe.php';

            return _send_bulk_one_at_a_time(
                \SMSGateway\Jusibe::class, $gateway_fields, $messages, $testCall
            );
        case 76:
            $gateway_fields = get_option($option_slug . '_mensatek');

            require_once 'gateways/mensatek.php';

            return _send_bulk_one_at_a_time(
                \SMSGateway\Mensatek::class, $gateway_fields, $messages, $testCall
            );
        case 77:
            $gateway_fields = get_option($option_slug . '_speedsms');

            require_once 'gateways/speedsms.php';

            return _send_bulk_one_at_a_time(
                \SMSGateway\SpeedSMS::class, $gateway_fields, $messages, $testCall
            );
        case 78:
            $gateway_fields = get_option($option_slug . '_smsmisr');

            require_once 'gateways/smsmisr.php';

            return _send_bulk_one_at_a_time(
                \SMSGateway\SMSMISR::class, $gateway_fields, $messages, $testCall
            );
        case 79:
            $gateway_fields = get_option($option_slug . '_jazzcmt');

            require_once 'gateways/jazzcmt.php';

            return _send_bulk_one_at_a_time(
                \SMSGateway\JazzCMT::class, $gateway_fields, $messages, $testCall
            );

        case 80:
            $gateway_fields = get_option($option_slug . '_moceansms');

            require_once 'gateways/moceansms.php';

            return \SMSGateway\MoceanSMS::sendBulkSMS($gateway_fields, $messages, $testCall);
        case 81:
            $gateway_fields = get_option($option_slug . '_sendsms247');

            require_once 'gateways/sendsms247.php';

            return \SMSGateway\SendSMS247::sendBulkSMS($gateway_fields, $messages, $testCall);
        case 82:
            $gateway_fields = get_option($option_slug . '_smscua');

            require_once 'gateways/smscua.php';

            return \SMSGateway\SmscUA::sendBulkSMS($gateway_fields, $messages, $testCall);
        case 83:
            $gateway_fields = get_option($option_slug . '_cpsms');

            require_once 'gateways/cpsmsdk.php';

            return \SMSGateway\CPSMSDK::sendBulkSMS($gateway_fields, $messages, $testCall);
        case 84:
            $gateway_fields = get_option($option_slug . '_1s2u');

            require_once 'gateways/first2u.php';

            return \SMSGateway\First2U::sendBulkSMS($gateway_fields, $messages, $testCall);
        case 85:
            $gateway_fields = get_option($option_slug . '_textanywhere');

            require_once 'gateways/textanywhere.php';

            return \SMSGateway\TextAnywhere::sendBulkSMS($gateway_fields, $messages, $testCall);
        case 86:
            $gateway_fields = get_option($option_slug . '_sms77');

            require_once 'gateways/sms77.php';

            return \SMSGateway\SMS77::sendBulkSMS($gateway_fields, $messages, $testCall);
        case 87:
            $gateway_fields = get_option($option_slug . '_verimor');

            require_once 'gateways/verimor.php';

            return \SMSGateway\Verimor::sendBulkSMS($gateway_fields, $messages, $testCall);
        case 88:
            $gateway_fields = get_option($option_slug . '_labsmobile');

            require_once 'gateways/labsmobile.php';

            return \SMSGateway\LabsMobile::sendBulkSMS($gateway_fields, $messages, $testCall);
        case 89:
            $gateway_fields = get_option($option_slug . '_unisender');

            require_once 'gateways/unisender.php';

            return \SMSGateway\Unisender::sendBulkSMS($gateway_fields, $messages, $testCall);
        case 90:
            $gateway_fields = get_option($option_slug . '_aruba');

            require_once 'gateways/aruba.php';

            return \SMSGateway\Aruba::sendBulkSMS($gateway_fields, $messages, $testCall);
        case 91:
            $gateway_fields = get_option($option_slug . '_comilio');

            require_once 'gateways/comilio.php';

            return \SMSGateway\Comilio::sendBulkSMS($gateway_fields, $messages, $testCall);
        case 92:
            $gateway_fields = get_option($option_slug . '_smshosting');

            require_once 'gateways/smshosting.php';

            return \SMSGateway\SMSHosting::sendBulkSMS($gateway_fields, $messages, $testCall);
        case 93:
            $gateway_fields = get_option($option_slug . '_gateway');

            require_once 'gateways/gateway.php';

            return \SMSGateway\Gateway::sendBulkSMS($gateway_fields, $messages, $testCall);
        case 94:
            $gateway_fields = get_option($option_slug . '_uwaziimobile');

            require_once 'gateways/uwaziimobile.php';

            return \SMSGateway\UwaziiMobile::sendBulkSMS($gateway_fields, $messages, $testCall);
        case 95:
            $gateway_fields = get_option($option_slug . '_suresms');

            require_once 'gateways/suresms.php';

            return _send_bulk_one_at_a_time(
                \SMSGateway\SureSMS::class, $gateway_fields, $messages, $testCall
            );
        case 96:
            $gateway_fields = get_option($option_slug . '_easysendsms');

            require_once 'gateways/easysendsms.php';

            return \SMSGateway\EasysendSMS::sendBulkSMS($gateway_fields, $messages, $testCall);
        case 97:
            $gateway_fields = get_option($option_slug . '_sinch');

            require_once 'gateways/sinch.php';

            return \SMSGateway\Sinch::sendBulkSMS($gateway_fields, $messages, $testCall);
        case 98:
            $gateway_fields = get_option($option_slug . '_smsala');

            require_once 'gateways/smsala.php';

            return _send_bulk_one_at_a_time(
                \SMSGateway\SMSAla::class, $gateway_fields, $messages, $testCall
            );
        case 99:
            $gateway_fields = get_option($option_slug . '_smsempresa');

            require_once 'gateways/smsempresa.php';

            return \SMSGateway\SMSEmpresa::sendBulkSMS($gateway_fields, $messages, $testCall);
        case 100:
            $gateway_fields = get_option($option_slug . '_semaphore');

            require_once 'gateways/semaphore.php';

            return \SMSGateway\Semaphore::sendBulkSMS($gateway_fields, $messages, $testCall);
        case 101:
            $gateway_fields = get_option($option_slug . '_wavy');

            require_once 'gateways/wavy.php';

            return \SMSGateway\Wavy::sendBulkSMS($gateway_fields, $messages, $testCall);
        case 102:
            $gateway_fields = get_option($option_slug . '_smsto');

            require_once 'gateways/smsto.php';

            return \SMSGateway\SMSTo::sendBulkSMS($gateway_fields, $messages, $testCall);
        case 103:
            $gateway_fields = get_option($option_slug . '_telnyx');

            require_once 'gateways/telnyx.php';

            return _send_bulk_one_at_a_time(
                \SMSGateway\Telnyx::class, $gateway_fields, $messages, $testCall
            );
        case 104:
            $gateway_fields = get_option($option_slug . '_telesign');

            require_once 'gateways/telesign.php';

            return _send_bulk_one_at_a_time(
                \SMSGateway\TeleSign::class, $gateway_fields, $messages, $testCall
            );
        case 105:
            $gateway_fields = get_option($option_slug . '_d7networks');

            require_once 'gateways/d7networks.php';

            return \SMSGateway\D7Networks::sendBulkSMS($gateway_fields, $messages, $testCall);
        case 106:
            $gateway_fields = get_option($option_slug . '_ismsindonesia');

            require_once 'gateways/ismsindonesia.php';

            return \SMSGateway\ISMSIndonesia::sendBulkSMS($gateway_fields, $messages, $testCall);
        case 107:
            $gateway_fields = get_option($option_slug . '_sendpk');

            require_once 'gateways/sendpk.php';

            return \SMSGateway\SendPK::sendBulkSMS($gateway_fields, $messages, $testCall);
        case 108:
            $gateway_fields = get_option($option_slug . '_mimsms');

            require_once 'gateways/mimsms.php';

            return \SMSGateway\MimSMS::sendBulkSMS($gateway_fields, $messages, $testCall);
        case 109:
            $gateway_fields = get_option($option_slug . '_openmarket');

            require_once 'gateways/openmarket.php';

            return _send_bulk_one_at_a_time(
                \SMSGateway\OpenMarket::class, $gateway_fields, $messages, $testCall
            );
        case 110:
            $gateway_fields = get_option($option_slug . '_mobyt');

            require_once 'gateways/mobyt.php';

            return \SMSGateway\MobyT::sendBulkSMS($gateway_fields, $messages, $testCall);
        case 111:
            $gateway_fields = get_option($option_slug . '_tm4b');

            require_once 'gateways/tm4b.php';

            return \SMSGateway\TM4B::sendBulkSMS($gateway_fields, $messages, $testCall);
        case 112:
            $gateway_fields = get_option($option_slug . '_swiftsmsgateway');

            require_once 'gateways/swiftsmsgateway.php';

            return \SMSGateway\SwiftSMSGateway::sendBulkSMS($gateway_fields, $messages, $testCall);
        case 113:
            $gateway_fields = get_option($option_slug . '_2factor');

            require_once 'gateways/twofactor.php';

            return \SMSGateway\TwoFactor::sendBulkSMS($gateway_fields, $messages, $testCall);
        case 114:
            $gateway_fields = get_option($option_slug . '_gupshup');

            require_once 'gateways/gupshup.php';

            return \SMSGateway\GupShup::sendBulkSMS($gateway_fields, $messages, $testCall);
        case 115:
            $gateway_fields = get_option($option_slug . '_digimiles');

            require_once 'gateways/digimiles.php';

            return _send_bulk_one_at_a_time(
                \SMSGateway\Digimiles::class, $gateway_fields, $messages, $testCall
            );
        case 116:
            $gateway_fields = get_option($option_slug . '_callfire');

            require_once 'gateways/callfire.php';

            return \SMSGateway\CallFire::sendBulkSMS($gateway_fields, $messages, $testCall);
        case 117:
            $gateway_fields = get_option($option_slug . '_nowsms');

            require_once 'gateways/nowsms.php';

            return \SMSGateway\NowSMS::sendBulkSMS($gateway_fields, $messages, $testCall);
        case 118:
            $gateway_fields = get_option($option_slug . '_releans');

            require_once 'gateways/releans.php';

            return _send_bulk_one_at_a_time(
                \SMSGateway\Releans::class, $gateway_fields, $messages, $testCall
            );
        case 119:
            $gateway_fields = get_option($option_slug . '_zipwhip');

            require_once 'gateways/zipwhip.php';

            return \SMSGateway\ZipWhip::sendBulkSMS($gateway_fields, $messages, $testCall);
        case 120:
            $gateway_fields = get_option($option_slug . '_messagemedia');

            require_once 'gateways/messagemedia.php';

            return \SMSGateway\MessageMedia::sendBulkSMS($gateway_fields, $messages, $testCall);
        case 121:
            $gateway_fields = get_option($option_slug . '_thesmsworks');

            require_once 'gateways/thesmsworks.php';

            return \SMSGateway\TheSMSWorks::sendBulkSMS($gateway_fields, $messages, $testCall);
        case 122:
            $gateway_fields = get_option($option_slug . '_mogreet');

            require_once 'gateways/mogreet.php';

            return _send_bulk_one_at_a_time(
                \SMSGateway\Mogreet::class, $gateway_fields, $messages, $testCall
            );
        case 123:
            $gateway_fields = get_option($option_slug . '_46elks');

            require_once 'gateways/fortysixelks.php';

            return _send_bulk_one_at_a_time(
                \SMSGateway\FortySixElks::class, $gateway_fields, $messages, $testCall
            );
        case 124:
            $gateway_fields = get_option($option_slug . '_slicktext');

            require_once 'gateways/slicktext.php';

            return _send_bulk_one_at_a_time(
                \SMSGateway\SlickText::class, $gateway_fields, $messages, $testCall
            );
        case 125:
            $gateway_fields = get_option($option_slug . '_smsidea');

            require_once 'gateways/smsidea.php';

            return \SMSGateway\SMSIdea::sendBulkSMS($gateway_fields, $messages, $testCall);
        case 126:
            $gateway_fields = get_option($option_slug . '_tatango');

            require_once 'gateways/tatango.php';

            return _send_bulk_one_at_a_time(
                \SMSGateway\Tatango::class, $gateway_fields, $messages, $testCall
            );
        case 127:
            $gateway_fields = get_option($option_slug . '_smsedge');

            require_once 'gateways/smsedge.php';

            return _send_bulk_one_at_a_time(
                \SMSGateway\SMSEdge::class, $gateway_fields, $messages, $testCall
            );
        case 128:
            $gateway_fields = get_option($option_slug . '_smsmasivos');

            require_once 'gateways/smsmasivos.php';

            return \SMSGateway\SMSMasivos::sendBulkSMS($gateway_fields, $messages, $testCall);
        case 129:
            $gateway_fields = get_option($option_slug . '_commzgate');

            require_once 'gateways/commzgate.php';

            return \SMSGateway\Commzgate::sendBulkSMS($gateway_fields, $messages, $testCall);
        case 130:
            $gateway_fields = get_option($option_slug . '_sms123');

            require_once 'gateways/sms123.php';

            return _send_bulk_one_at_a_time(
                \SMSGateway\SMS123::class, $gateway_fields, $messages, $testCall
            );
        case 131:
            $gateway_fields = get_option($option_slug . '_sms_ru');
            require_once 'gateways/sms_ru.php';

            return _send_bulk_one_at_a_time(
                \SMSGateway\SMS_RU::class, $gateway_fields, $messages, $testCall
            );
        case 133:
            $gateway_fields = get_option($option_slug . '_messente');

            require_once 'gateways/messente.php';

            return _send_bulk_one_at_a_time(
                \SMSGateway\Messente::class, $gateway_fields, $messages, $testCall
            );
        case 134:
            $gateway_fields = get_option($option_slug . '_text_marketer');

            require_once 'gateways/textmarketer.php';

            return _send_bulk_one_at_a_time(
                \SMSGateway\TextMarketer::class, $gateway_fields, $messages, $testCall
            );
        case 135:
            $gateway_fields = get_option($option_slug . '_spring_edge');

            require_once 'gateways/springedge.php';

            return _send_bulk_one_at_a_time(
                \SMSGateway\SpringEdge::class, $gateway_fields, $messages, $testCall
            );

        default:
            return false;
    }
}


$untdovr_additional_gateways = Puc_v4_Factory::buildUpdateChecker(
    'https://bridge.unitedover.com/updates/changelog/addons.php?addon=additional-gateways',
    __FILE__,
    'additional-gateways'
);

$untdovr_additional_gateways->addQueryArgFilter('untdovr_additional_gateways_filter_update_checks');
function untdovr_additional_gateways_filter_update_checks($queryArgs)
{

    $digpc = get_site_option('dig_purchasecode');
    if (empty($digpc)) {
        $queryArgs['license_key'] = get_option('wpnotif_purchasecode');
        $queryArgs['license_type'] = get_option('wpnotif_license_type', 1);
    } else {
        $queryArgs['license_key'] = $digpc;
        $queryArgs['license_type'] = get_site_option('dig_license_type', 1);
    }

    $queryArgs['request_site'] = network_home_url();

    $plugin_data = get_plugin_data(__FILE__);
    $plugin_version = $plugin_data['Version'];

    $queryArgs['version'] = $plugin_version;
    return $queryArgs;
}


add_filter('unitedover_wpnotif_send_whatsapp', 'untdovr_wpnotif_send_whatsapp_message', 10, 7);
function untdovr_wpnotif_send_whatsapp_message(
    $status, $option_slug, $gateway_id, $countrycode, $mobile, $message, $testCall
)
{
    $digpc = get_site_option('dig_purchasecode');
    if (empty($digpc)) {
        $digpc = get_option('wpnotif_purchasecode');
    }
    if (empty($digpc)) return false;
    $prefix = 'whatsapp';
    $phone = $countrycode . $mobile;

    $options = $whatsapp = get_option('wpnotif_whatsapp');

    switch ($gateway_id) {
        case 3:
            $gateway_fields = array('accesskey' => $options['messagebird_accesskey'], 'channel_id' => $options['messagebird_channel_id']);

            require_once 'gateways/messagebird.php';

            return \SMSGateway\MessageBird::sendWhatsapp($gateway_fields, $phone, $message, $testCall);

        case 4:
            $gateway_fields = array('uid' => $options['karix_uid'], 'token' => $options['karix_token'], 'sender' => $options['karix_sender']);

            require_once 'gateways/karix.php';

            return \SMSGateway\Karix::process_whatsapp($gateway_fields, $phone, $message, $testCall);
        case 5:
            $gateway_fields = array(
                'api_key' => $options['gupshup_api_key'],
                'source' => $options['gupshup_source'],
                'app_name' => $options['gupshup_app_name'],
            );

            require_once 'gateways/gupshup.php';

            return \SMSGateway\GupShup::process_whatsapp($gateway_fields, $phone, $message, $testCall);

        case 6:
            $gateway_fields = array(
                'api_key' => $options['360dialog_api_key'],
            );

            require_once 'gateways/360dialog.io.php';

            return \SMSGateway\Whatsapp_360Dialog::process_whatsapp($gateway_fields, $phone, $message, $testCall);
        case 7:
            $gateway_fields = array(
                'api_token' => $options['damcorp_api_token'],
            );
            require_once 'gateways/damcorp.php';
            return \SMSGateway\Whatsapp_damcorp::process_whatsapp($gateway_fields, $phone, $message, $testCall);
        case 8:
            $gateway_fields = array(
                'api_key' => $options['spoki_api_key'],
            );

            require_once 'gateways/spoki.php';
            return \SMSGateway\Whatsapp_spoki::process_whatsapp($gateway_fields, $phone, $message, $testCall);
        case 9:
            $gateway_fields = array(
                'base_url' => $options['wati_base_url'],
                'access_token' => $options['wati_access_token'],
            );

            require_once 'gateways/wati.php';
            return \SMSGateway\Whatsapp_wati::process_whatsapp($gateway_fields, $phone, $message, $testCall);
        case 10:
            $gateway_fields = array(
                'access_token' => $options['whatsapp_cloud_access_token'],
                'from_number_id' => $options['whatsapp_from_number_id'],
            );
            require_once 'gateways/whatsapp_cloud.php';
            return \SMSGateway\WhatsappCloud::process_whatsapp($gateway_fields, $phone, $message, $testCall);
        case 11:
            $gateway_fields = array(
                'auth_key' => $options['msg91_whatsapp_auth_key'],
                'from' => $options['msg91_whatsapp_from'],
                'namespace' => $options['msg91_whatsapp_template_namespace'],
            );
            require_once 'gateways/msg91_wa.php';
            return \SMSGateway\MSG91_WhatsApp::process_whatsapp($gateway_fields, $phone, $message, $testCall);
        case 12:
            $gateway_fields = array(
                'gateway_url' => $options['wa_custom_gateway_url'],
                'http_header' => $options['wa_custom_http_header'],
                'http_method' => $options['wa_custom_http_method'],
                'gateway_attributes' => $options['wa_custom_gateway_attributes'],
                'send_body_data' => $options['wa_custom_send_body_data'],
                'encode_message' => $options['wa_custom_encode_message'],
                'phone_number' => $options['wa_custom_phone_number'],
                'sender_id' => $options['wa_custom_sender_id'],
            );

            require_once 'gateways/custom.php';
            return \SMSGateway\CustomGateway::process_message($gateway_fields, $countrycode, $mobile, $message, $testCall);

        case 13:
            $gateway_fields = array('api_key' => $options['chat_api_access_token'],
                'instance_id' => $options['chat_api_instance_id'],);
            require_once 'gateways/wa/chatapi.php';
            return \SMSGateway\wa\Whatsapp_Chat_API::process_whatsapp($gateway_fields, $phone, $message, $testCall);
        case 14:
            $gateway_fields = array('api_key' => $options['interakt_access_token']);
            require_once 'gateways/wa/interakt.php';
            return \SMSGateway\wa\Whatsapp_Interakt::process_whatsapp($gateway_fields, $countrycode, $mobile, $message, $testCall);
        case 15:
            $gateway_fields = array('api_key' => $options['kaylera_access_token'],);
            require_once 'gateways/wa/kaylera.php';
            return \SMSGateway\wa\Whatsapp_Kaylera::process_whatsapp($gateway_fields, $phone, $message, $testCall);
        case 16:
            $gateway_fields = array('api_key' => $options['wa_team_access_token'],
                'url' => $options['wa_team_url']);
            require_once 'gateways/wa/wateam.php';
            return \SMSGateway\wa\Whatsapp_WaTeam::process_whatsapp($gateway_fields, $phone, $message, $testCall);
        default:
            return false;
    }

}

add_filter('unitedover_send_whatsapp_message', 'untdovr_send_whatsapp_message', 10, 7);

function untdovr_send_whatsapp_message(
    $status, $option_slug, $gateway_id, $countrycode, $mobile, $message, $testCall
)
{
    $digpc = get_site_option('dig_purchasecode');
    if (empty($digpc)) {
        $digpc = get_option('wpnotif_purchasecode');
    }
    if (empty($digpc)) return false;

    $prefix = 'whatsapp';
    $phone = $countrycode . $mobile;

    switch ($gateway_id) {
        case 3:
            $gateway_fields = get_option($option_slug . '_' . $prefix . 'messagebird');

            require_once 'gateways/messagebird.php';

            return \SMSGateway\MessageBird::sendWhatsapp($gateway_fields, $phone, $message, $testCall);

        case 4:
            $gateway_fields = get_option($option_slug . '_' . $prefix . 'karix');

            require_once 'gateways/karix.php';

            return \SMSGateway\Karix::process_whatsapp($gateway_fields, $phone, $message, $testCall);
        case 5:
            $gateway_fields = get_option($option_slug . '_' . $prefix . 'gupshup');

            require_once 'gateways/gupshup.php';

            return \SMSGateway\GupShup::process_whatsapp($gateway_fields, $phone, $message, $testCall);
        case 6:
            $gateway_fields = get_option($option_slug . '_' . $prefix . 'threesixtydialog');

            require_once 'gateways/360dialog.io.php';

            return \SMSGateway\Whatsapp_360Dialog::process_whatsapp($gateway_fields, $phone, $message, $testCall);
        case 7:
            $gateway_fields = get_option($option_slug . '_' . $prefix . 'damcorp');

            require_once 'gateways/damcorp.php';
            return \SMSGateway\Whatsapp_damcorp::process_whatsapp($gateway_fields, $phone, $message, $testCall);

        case 8:
            $gateway_fields = get_option($option_slug . '_' . $prefix . 'spoki');
            require_once 'gateways/spoki.php';
            return \SMSGateway\Whatsapp_spoki::process_whatsapp($gateway_fields, $phone, $message, $testCall);

        case 9:
            $gateway_fields = get_option($option_slug . '_' . $prefix . 'wati');
            require_once 'gateways/wati.php';
            return \SMSGateway\Whatsapp_wati::process_whatsapp($gateway_fields, $phone, $message, $testCall);
        case 10:
            $gateway_fields = get_option($option_slug . '_' . $prefix . 'whatsapp_cloud');

            require_once 'gateways/whatsapp_cloud.php';
            return \SMSGateway\WhatsappCloud::process_whatsapp($gateway_fields, $phone, $message, $testCall);
        case 11:
            $gateway_fields = get_option($option_slug . '_' . $prefix . 'msg91_whatsapp');
            require_once 'gateways/msg91_wa.php';
            return \SMSGateway\MSG91_WhatsApp::process_whatsapp($gateway_fields, $phone, $message, $testCall);

        case 12:
            $gateway_fields = get_option($option_slug . '_' . $prefix . 'custom_gateway_whatsapp');

            require_once 'gateways/custom.php';
            return \SMSGateway\CustomGateway::process_message($gateway_fields, $countrycode, $mobile, $message, $testCall);

        case 13:
            $gateway_fields = get_option($option_slug . '_' . $prefix . 'chat_api_whatsapp');

            require_once 'gateways/wa/chatapi.php';
            return \SMSGateway\wa\Whatsapp_Chat_API::process_whatsapp($gateway_fields, $phone, $message, $testCall);
        case 14:
            $gateway_fields = get_option($option_slug . '_' . $prefix . 'interakt_whatsapp');
            require_once 'gateways/wa/interakt.php';
            return \SMSGateway\wa\Whatsapp_Interakt::process_whatsapp($gateway_fields, $countrycode, $mobile, $message, $testCall);
        case 15:
            $gateway_fields = get_option($option_slug . '_' . $prefix . 'kaylera_whatsapp');
            require_once 'gateways/wa/kaylera.php';
            return \SMSGateway\wa\Whatsapp_Kaylera::process_whatsapp($gateway_fields, $phone, $message, $testCall);
        case 16:
            $gateway_fields = get_option($option_slug . '_' . $prefix . 'wa_team_whatsapp');
            require_once 'gateways/wa/wateam.php';
            return \SMSGateway\wa\Whatsapp_WaTeam::process_whatsapp($gateway_fields, $phone, $message, $testCall);
        default:
            return false;
    }
}