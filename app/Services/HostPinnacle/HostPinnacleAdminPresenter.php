<?php

namespace App\Services\HostPinnacle;

use App\Models\Company;
use App\Services\Telephony\Sms\SmsAvailability;
use App\Services\Telephony\Sms\SmsConfig;
use App\Support\ConvoConnectBrand;

class HostPinnacleAdminPresenter
{
    public function __construct(
        private readonly HostPinnacleClient $client,
        private readonly HostPinnacleProvisioner $provisioner,
        private readonly SmsAvailability $smsAvailability,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function summaryForCompany(Company $company): array
    {
        $credentials = HostPinnacleCredentials::forCompany($company);
        $senderStatus = trim((string) $company->getConfig('HOSTPINNACLE_SENDER_STATUS', ''));

        return [
            'company_id' => $company->id,
            'company_name' => $company->name,
            'subdomain' => $company->subdomain,
            'owner_name' => $company->user?->name,
            'owner_email' => $company->user?->email,
            'provisioned' => $credentials !== null,
            'sender_id' => trim((string) $company->getConfig('HOSTPINNACLE_SENDER_ID', '')),
            'sender_status' => $senderStatus !== '' ? $senderStatus : 'not_provisioned',
            'sms_ready' => $this->smsAvailability->isReady($company),
            'resolved_provider' => ConvoConnectBrand::displayProvider($this->smsAvailability->resolveProvider($company)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detailForCompany(Company $company, bool $fetchLive = false): array
    {
        $company->loadMissing('user');
        $summary = $this->summaryForCompany($company);
        $credentials = HostPinnacleCredentials::forCompany($company);

        $apiKey = trim((string) $company->getConfig('HOSTPINNACLE_API_KEY', ''));
        $password = trim((string) $company->getConfig('HOSTPINNACLE_PASSWORD', ''));

        $detail = array_merge($summary, [
            'platform_enabled' => (bool) config('hostpinnacle.enabled', false),
            'reseller_configured' => HostPinnacleCredentials::reseller() !== null,
            'sub_login' => trim((string) $company->getConfig('HOSTPINNACLE_SUB_LOGIN', '')),
            'user_id' => trim((string) $company->getConfig('HOSTPINNACLE_USER_ID', '')),
            'api_key' => $apiKey,
            'api_key_masked' => $this->maskSecret($apiKey),
            'password' => $password,
            'password_masked' => $password !== '' ? str_repeat('•', min(12, strlen($password))) : '',
            'status_message' => $this->smsAvailability->statusMessage($company),
            'expected_sub_login' => $this->provisioner->subUserLoginName($company),
            'sms_config_ready' => SmsConfig::forCompany($company)->smsReady(),
            'webhook_url' => url('/webhook/sms/convoconnect/dlr'),
            'live_account' => null,
            'live_sender_ids' => [],
            'live_error' => null,
        ]);

        if ($fetchLive && $credentials !== null) {
            $accountResponse = $this->client->readAccountStatus($credentials);
            if ($accountResponse['ok'] ?? false) {
                $detail['live_account'] = data_get($accountResponse, 'data.response.account')
                    ?? data_get($accountResponse, 'data.account');
            } else {
                $detail['live_error'] = ConvoConnectBrand::sanitizeError($accountResponse['error'] ?? __('Unable to fetch live account status.'));
            }

            $senderResponse = $this->client->readSenderIds($credentials);
            if ($senderResponse['ok'] ?? false) {
                $detail['live_sender_ids'] = data_get($senderResponse, 'data.response.senderidList')
                    ?? data_get($senderResponse, 'data.senderidList')
                    ?? [];
            }
        }

        return $detail;
    }

    public function maskSecret(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (strlen($value) <= 8) {
            return str_repeat('•', strlen($value));
        }

        return substr($value, 0, 4).str_repeat('•', max(4, strlen($value) - 8)).substr($value, -4);
    }

    public function senderStatusBadgeClass(string $status): string
    {
        return match (strtolower($status)) {
            'approved', 'active', 'success' => 'success',
            'pending_approval' => 'warning',
            'request_failed', 'failed' => 'danger',
            default => 'secondary',
        };
    }
}
