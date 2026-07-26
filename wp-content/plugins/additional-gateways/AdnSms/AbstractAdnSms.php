<?php
namespace AdnSms;

use Exception;

require_once( __DIR__ . "/AdnSmsInterface.php");

abstract class AbstractAdnSms implements AdnSmsInterface
{


    protected static $requestTypes = [
        'SINGLE_SMS', 'OTP', 'GENERAL_CAMPAIGN', 'MULTIBODY_CAMPAIGN'
    ];
    protected static $messageTypes = [
        'TEXT', 'UNICODE'
    ];
    protected $apiKey;
    protected $apiSecret;
    protected $config;
    protected $recipient;
    protected $message;
    protected $campaignTitle;
    protected $apiUrl;
    protected $requestType;
    protected $messageType = 'TEXT';

    /**
     * @return string
     */
    public function getRequestType()
    {
        return $this->requestType;
    }

    /**
     * mandatory
     * @param $requestType
     * @throws Exception
     */
    public function setRequestType($requestType)
    {
        if (!in_array(strtoupper($requestType), self::$requestTypes)) {
            throw new Exception('Request Type is not appropriate.');
        }

        $this->requestType = $requestType;
    }

    /**
     * mandatory
     * @return string
     */
    public function getMessageType()
    {
        return $this->messageType;
    }

    /**
     * @param $messageType
     */
    public function setMessageType($messageType)
    {
        if (!in_array(strtoupper($messageType), self::$messageTypes)) {
            throw new Exception('Message Type is not appropriate.');
        }

        $this->messageType = $messageType;
    }

    /**
     * @return mixed
     */
    public function getRecipient()
    {
        return $this->recipient;
    }

    /**
     * @param $recipient
     */
    public function setRecipient($recipient)
    {
        $this->recipient = $recipient;
    }

    /**
     * @return mixed
     */
    public function getMessageBody()
    {
        return $this->message;
    }

    /**
     * @param $message
     */
    public function setMessageBody($message)
    {
        $this->message = $message;
    }

    /**
     * @return mixed
     */
    public function getCampaignTitle()
    {
        return $this->campaignTitle;
    }

    /**
     * @param $campaignTitle
     */
    public function setCampaignTitle($campaignTitle)
    {
        $this->campaignTitle = $campaignTitle;
    }

    public function callToApi($data)
    {
        $curl = curl_init();

        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($curl, CURLOPT_URL, $this->getApiUrl());
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            return "cURL Error #:" . $err;
        } else {
            return $response;
        }
    }

    protected function getApiUrl()
    {
        return $this->apiUrl;
    }

    protected function setApiUrl($url)
    {
        $this->apiUrl = 'https://portal.adnsms.com' . $url;
    }

    protected function getApiKey()
    {
        return $this->apiKey;
    }

    protected function setApiKey($apiKey)
    {
        $this->apiKey = $apiKey;
    }

    protected function getApiSecret()
    {
        return $this->apiSecret;
    }

    protected function setApiSecret($apiSecret)
    {
        $this->apiSecret = $apiSecret;
    }
}
