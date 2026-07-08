<?php

namespace App\Services\Telephony\Sms;

use App\Services\HostPinnacle\HostPinnacleClient;
use App\Services\HostPinnacle\HostPinnacleCredentials;
use App\Services\HostPinnacle\HostPinnacleCreditSync;
use App\Services\Telephony\PhoneNormalizer;
use App\Support\ConvoConnectBrand;

class HostPinnacleSmsSender
{
    public function __construct(
        protected SmsConfig $config,
        protected HostPinnacleClient $client,
        protected HostPinnacleCreditSync $creditSync,
        protected PhoneNormalizer $normalizer,
    ) {
    }

    public function send(string $to, string $body): SmsSendResult
    {
        $credentials = HostPinnacleCredentials::forCompany($this->config->company);
        if ($credentials === null) {
            return SmsSendResult::fail(ConvoConnectBrand::name().' SMS is not provisioned for this organization.');
        }

        if (! $this->creditSync->hasMinimumBalance($this->config->company)) {
            return SmsSendResult::fail(ConvoConnectBrand::name().' SMS balance is too low. Add messaging credits to continue.');
        }

        $mobile = $this->normalizer->forHostPinnacle($to);
        if ($mobile === null) {
            return SmsSendResult::fail('Invalid phone number for SMS delivery.');
        }

        $response = $this->client->sendQuick($credentials, $mobile, $body);
        if (! ($response['ok'] ?? false)) {
            return SmsSendResult::fail(ConvoConnectBrand::sanitizeError($response['error'] ?? ConvoConnectBrand::name().' SMS send failed.'));
        }

        $transactionId = data_get($response, 'data.transactionId');

        return SmsSendResult::ok(is_string($transactionId) ? $transactionId : null);
    }
}
