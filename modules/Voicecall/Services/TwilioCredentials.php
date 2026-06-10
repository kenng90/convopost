<?php

namespace Modules\Voicecall\Services;

use App\Models\Company;

class TwilioCredentials
{
    public function __construct(
        public string $accountSid,
        public string $authToken,
    ) {
    }

    public static function fromCompany(Company $company): ?self
    {
        $sid = self::readConfig($company, 'TWILIO_ACCOUNT_SID');
        $token = self::readConfig($company, 'TWILIO_AUTH_TOKEN');

        if ($sid === '' || $token === '') {
            return null;
        }

        return new self($sid, $token);
    }

    private static function readConfig(Company $company, string $key): string
    {
        $value = $company->getConfig($key, '');

        return trim(is_string($value) ? $value : (string) ($value ?? ''));
    }
}
