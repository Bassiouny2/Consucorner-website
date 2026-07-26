<?php

namespace SMSGateway;

use digits_rangineSms;
use Exception;

require_once 'class-RangineSms.php';


class RagineSMS
{

    public static function sendSMS($gateway_fields, $countrycode, $mobile, $message, $test_call)
    {
        return self::process_sms($gateway_fields, $countrycode, $mobile, $message, $test_call);
    }

    public static function process_sms($rangineSms, $countrycode, $mobile, $message, $testCall)
    {
        try {
            $sample = $rangineSms['sample'];
            $shop_name = $rangineSms['shopname'];
            if ($countrycode == '+98') {
                if ($sample == 1) {
                    $messagetemplate = "patterncode:7yev7tayz3;company:" . $shop_name . ";code:" . $otp;
                } else {
                    $patterncode = $rangineSms['patterncode'];
                    if ($patterncode !== '') {
                        $rangineSms['patternvars'] = str_replace("\r\n", ";", $rangineSms['patternvars']);
                        $patternvars = str_replace("\n", ";", $rangineSms['patternvars']);
                        $messagetemplate = "patterncode:" . $patterncode . ";" . $patternvars;
                        $messagetemplate = str_replace('%OTP%', $otp, $messagetemplate);
                        $messagetemplate = str_replace('{OTP}', $otp, $messagetemplate);
                    }
                }
                $rangine = new digits_rangineSms($rangineSms);
                $param = array
                (
                    'message' => $messagetemplate,
                    'to' => $mobile,
                    'otp' => $otp,
                );

                $result = $rangine->send($param);
                // Check if the send was successful
            } elseif (isset($rangineSms['internationalapi']) && $rangineSms['internationalapi'] !== '') {
                $rangine = new digits_rangineSms($rangineSms);
                $param = array
                (
                    'message' => $messagetemplate,
                    'to' => str_replace(" ", "", $countrycode . $mobile),
                    'otp' => $otp,
                    'countrycode' => $countrycode,
                    'internationalapi' => $rangineSms['internationalapi']
                );
                $result = $rangine->sendInternational($param);
            } else {
                if ($testCall) return 'You are not allowed to send international SMS. Please contact your provider.';
                return false;
            }
            $response = json_decode($result);
            if (is_numeric($response) || (is_array($response) && $response[0] == '0')) {
                if (is_numeric($response)) $res_code = 0; else $res_code = $response[0];
                if ($testCall) return $success_message . $rangine->errors_describe($res_code);
                return true;
            } else {
                $res_code = $response[0];
                if ($testCall) return $fault_message . $rangine->errors_describe($res_code);

                digitsDebug("Rangine SMS failed :" . json_encode(array('message' => $sms['message'], 'to' => $sms['to'], 'error' => $rangine->errors_describe($res_code))));
                return false;
            }
        } catch (Exception $e) {
            if($testCall){
                return $e->getMessage();
            }
            return false;
        }

    }

}
