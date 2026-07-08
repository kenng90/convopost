<?php

namespace App\Services\HostPinnacle;

use App\Models\Company;
use App\Models\Credit;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class HostPinnacleCreditSync
{
    public function __construct(
        private readonly HostPinnacleClient $client,
        private readonly HostPinnacleProvisioner $provisioner,
    ) {
    }

    public function syncCreditPurchase(Credit $credit): void
    {
        if (! config('hostpinnacle.enabled', false)) {
            return;
        }

        $user = $credit->user;
        if ($user === null) {
            return;
        }

        $hostPinnacleCredits = round(
            (float) $credit->credit_amount * (float) config('hostpinnacle.sms_credits_per_messaging_credit', 1),
            2
        );

        if ($hostPinnacleCredits <= 0) {
            return;
        }

        foreach ($this->companiesForUser($user) as $company) {
            $this->syncCreditsToCompany($company, $hostPinnacleCredits, (string) ($credit->source ?? 'credit-purchase'));
        }
    }

    public function syncCreditsToCompany(Company $company, float $credits, string $comment = ''): bool
    {
        if (! config('hostpinnacle.enabled', false)) {
            return false;
        }

        if (HostPinnacleCredentials::forCompany($company) === null) {
            $this->provisioner->provision($company);
        }

        $loginName = trim((string) $company->getConfig('HOSTPINNACLE_SUB_LOGIN', ''));
        if ($loginName === '') {
            return false;
        }

        $response = $this->client->addSubUserCredit($loginName, $credits, $comment);
        if (! ($response['ok'] ?? false)) {
            Log::warning('HostPinnacle credit sync failed.', [
                'company_id' => $company->id,
                'credits' => $credits,
                'error' => $response['error'] ?? 'unknown',
            ]);

            return false;
        }

        return true;
    }

    public function hasMinimumBalance(Company $company): bool
    {
        if (! config('hostpinnacle.enabled', false)) {
            return true;
        }

        $credentials = HostPinnacleCredentials::forCompany($company);
        if ($credentials === null) {
            return false;
        }

        $response = $this->client->readAccountStatus($credentials);
        if (! ($response['ok'] ?? false)) {
            return true;
        }

        $balance = (int) data_get($response, 'data.response.account.smsBalance', data_get($response, 'data.account.smsBalance', 0));

        return $balance >= (int) config('hostpinnacle.min_sms_balance', 1);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Company>
     */
    private function companiesForUser(User $user): \Illuminate\Support\Collection
    {
        $companies = Company::query()->where('user_id', $user->id)->get();
        if ($companies->isNotEmpty()) {
            return $companies;
        }

        if ($user->company_id) {
            $company = Company::query()->find($user->company_id);
            if ($company) {
                return collect([$company]);
            }
        }

        return collect();
    }
}
