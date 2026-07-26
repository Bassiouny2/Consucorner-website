<?php
namespace AdnSms;

use Exception;

require_once( __DIR__ . "/AbstractAdnSms.php");


class AdnSmsNotification extends AbstractAdnSms
{
    /**
     * AdnSmsNotification constructor.
     */
    public function __construct($apikey,$apisecret)
    {

        $this->setApiKey($apikey);
        $this->setApiSecret($apisecret);
    }

    public function checkBalance()
    {
        $this->setApiUrl('/api/v1/secure/check-balance');

        $data = [
            'api_key' => $this->getApiKey(),
            'api_secret' => $this->getApiSecret()
        ];

        $response = $this->callToApi($data);
        return $response;
    }

    public function sendSms($requestType, $message, $recipient, $messageType, $campaignTitle = null)
    {
        $this->setApiUrl('/api/v1/secure/send-sms');
        $this->setRequestType($requestType);
        $this->setMessageBody($message);
        $this->setRecipient($recipient);
        $this->setMessageType($messageType);
        $this->setCampaignTitle($campaignTitle);

        $data = [
            'api_key' => $this->getApiKey(),
            'api_secret' => $this->getApiSecret(),
            'request_type' => $this->getRequestType(),
            'message_type' => $this->getMessageType(),
            'mobile' => $this->getRecipient(),
            'message_body' => $this->getMessageBody()
        ];

        if ($this->getRequestType() == 'GENERAL_CAMPAIGN') {

            $data['campaign_title'] = $this->getCampaignTitle();
        }

        $response = $this->callToApi($data);
	    return $response;
    }

    public function sendBulkSms($message, $recipient, $messageType, $campaignTitle)
    {
        $this->setApiUrl('/api/v1/secure/send-sms');
        $this->setRequestType('GENERAL_CAMPAIGN');
        $this->setMessageBody($message);
        $this->setRecipient($recipient);
        $this->setMessageType($messageType);
        $this->setCampaignTitle($campaignTitle);

        $data = [
            'api_key' => $this->getApiKey(),
            'api_secret' => $this->getApiSecret(),
            'request_type' => $this->getRequestType(),
            'message_type' => $this->getMessageType(),
            'mobile' => $this->getRecipient(),
            'message_body' => $this->getMessageBody()
        ];

        if ($campaignTitle == null) {
            throw new Exception('Campaign Title is required for bulk campaign');
        }
        $data['campaign_title'] = $this->getCampaignTitle();

        $response = $this->callToApi($data);
        return $response;
    }

    public function multibodyCampaign($smsArray, $messageType, $campaignTitle = null)
    {
        $this->setApiUrl('/api/v1/secure/send-sms');
        $this->setRequestType('MULTIBODY_CAMPAIGN');
        $this->setMessageType($messageType);
        $this->setCampaignTitle($campaignTitle);

        $data = [
            'api_key' => $this->getApiKey(),
            'api_secret' => $this->getApiSecret(),
            'request_type' => $this->getRequestType(),
            'message_type' => $this->getMessageType(),
        ];

        if ($campaignTitle != null) {
            $data['campaign_title'] = $this->getCampaignTitle();
        }

        foreach ($smsArray as $key => $sms) {
            if (!(isset($sms['mobile']) && isset($sms['message_body']))) {
                throw new Exception('SMS Array format is not correct.');
            }
            $data["sms[$key][mobile]"] = $sms['mobile'];
            $data["sms[$key][message_body]"] = $sms['message_body'];
        }

        $response = $this->callToApi($data);
	    return $response;
    }

    /**
     * @param $campaignUid
     */
    public function checkCampaignStatus($campaignUid)
    {
        $this->setApiUrl('/api/v1/secure/campaign-status');

        $data = [
            'api_key' => $this->getApiKey(),
            'api_secret' => $this->getApiSecret(),
            'campaign_uid' => $campaignUid
        ];


        $response = $this->callToApi($data);
	    return $response;
    }

    /**
     * @param $smsUid
     */
    public function checkSmsStatus($smsUid)
    {
        $this->setApiUrl('/api/v1/secure/sms-status');

        $data = [
            'api_key' => $this->getApiKey(),
            'api_secret' => $this->getApiSecret(),
            'sms_uid' => $smsUid
        ];

        $response = $this->callToApi($data);
	    return $response;
    }
}
