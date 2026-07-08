<?php

namespace App\Services\HostPinnacle;

class HostPinnacleCredentials
{
    public function __construct(
        public string $userId,
        public string $apiKey,
        public string $senderId,
        public string $password = '',
    ) {
    }

    public static function forCompany(\App\Models\Company $company): ?self
    {
        $userId = trim((string) $company->getConfig('HOSTPINNACLE_USER_ID', ''));
        $apiKey = trim((string) $company->getConfig('HOSTPINNACLE_API_KEY', ''));
        $senderId = trim((string) $company->getConfig('HOSTPINNACLE_SENDER_ID', ''));
        $password = trim((string) $company->getConfig('HOSTPINNACLE_PASSWORD', ''));

        if ($userId === '' || $apiKey === '' || $senderId === '') {
            return null;
        }

        return new self($userId, $apiKey, $senderId, $password);
    }

    public static function reseller(): ?self
    {
        if (! config('hostpinnacle.enabled', false)) {
            return null;
        }

        $userId = trim((string) config('hostpinnacle.reseller_user_id', ''));
        $apiKey = trim((string) config('hostpinnacle.reseller_api_key', ''));
        $password = trim((string) config('hostpinnacle.reseller_password', ''));

        if ($userId === '' || ($apiKey === '' && $password === '')) {
            return null;
        }

        return new self($userId, $apiKey, '', $password);
    }
}
