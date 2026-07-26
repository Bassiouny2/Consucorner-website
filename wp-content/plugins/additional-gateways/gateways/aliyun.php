<?php

namespace SMSGateway;

class Aliyun
{

    public static function sendSMS($gateway_fields, $countrycode, $phone, $message, $test_call)
    {
        return self::process_sms($gateway_fields, $countrycode . $phone, $message, $test_call);
    }

    public static function process_sms($gateway_fields, $phone, $message, $testCall)
    {
        $access_key = $gateway_fields['access_key'];
        $access_secret = $gateway_fields['access_secret'];
        $from = $gateway_fields['from'];

        $region = $gateway_fields['region'];
        $api_version = $gateway_fields['api_version'];

        if (empty($region)) {
            $region = 'cn-hangzhou';
        }

        if (empty($api_version)) {
            $api_version = '2017-05-25';
        }

        $signature = $gateway_fields['signature'];
        $template_code = $gateway_fields['template_code'];


        if (defined('DIGITS_OTP')) {
            $template_param = json_encode(array('code' => DIGITS_OTP), JSON_UNESCAPED_UNICODE);
        } else {
            $template_param = '{}';
        }

        $number = str_replace("+", "", $phone);

        try {
            \AlibabaCloud\Client\AlibabaCloud::accessKeyClient($access_key, $access_secret)
                ->regionId($region)
                ->asDefaultClient();

            $result = \AlibabaCloud\Client\AlibabaCloud::rpc()
                ->product('Dysmsapi')
                ->host('dysmsapi.aliyuncs.com')
                ->version($api_version)
                ->action('SendSms')
                ->method('POST')
                ->options([
                    'query' => [
                        'PhoneNumbers' => $number,
                        'SignName' => $signature,
                        'TemplateCode' => $template_code,
                        'TemplateParam' => $template_param,
                        'RegionId' => $region,
                    ],
                ])
                ->request();

        } catch (\Exception $e) {
            if ($testCall) {
                return $e->getErrorMessage();
            }

            return false;
        }

        if ($testCall) {
            return $result;
        }

        return true;

    }

}
