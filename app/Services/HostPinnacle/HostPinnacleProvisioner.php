<?php

namespace App\Services\HostPinnacle;

use App\Models\Company;
use App\Services\Telephony\PhoneNormalizer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class HostPinnacleProvisioner
{
    private const MIN_SUB_USER_LOGIN_LENGTH = 5;

    private const MAX_SUB_USER_LOGIN_LENGTH = 15;

    public function __construct(
        private readonly HostPinnacleClient $client,
        private readonly PhoneNormalizer $phoneNormalizer,
    ) {
    }

    public function provision(Company $company): bool
    {
        return $this->provisionCompany($company, skipCreateUser: false);
    }

    public function resume(Company $company): bool
    {
        return $this->provisionCompany($company, skipCreateUser: true);
    }

    private function provisionCompany(Company $company, bool $skipCreateUser): bool
    {
        if (! config('hostpinnacle.enabled', false)) {
            return false;
        }

        if (HostPinnacleCredentials::forCompany($company) !== null) {
            return true;
        }

        if (HostPinnacleCredentials::reseller() === null) {
            Log::warning('HostPinnacle provisioning skipped: reseller credentials missing.', [
                'company_id' => $company->id,
            ]);

            return false;
        }

        $company->loadMissing('user');
        $loginName = $this->subUserLoginName($company);

        if (! $this->isValidLoginName($loginName)) {
            Log::error('HostPinnacle provisioning login name invalid.', [
                'company_id' => $company->id,
                'login' => $loginName,
            ]);

            return false;
        }

        if (! $skipCreateUser) {
            $payload = $this->buildCreateUserPayload($company);
            $validationError = $this->validateCreateUserPayload($payload);

            if ($validationError !== null) {
                Log::error('HostPinnacle provisioning payload invalid.', [
                    'company_id' => $company->id,
                    'error' => $validationError,
                    'payload' => array_merge($payload, ['email' => '[redacted]']),
                ]);

                return false;
            }

            $loginName = (string) $payload['userloginname'];
            $create = $this->client->createSubUser($payload);

            if (! ($create['ok'] ?? false)) {
                $error = (string) ($create['error'] ?? 'unknown');

                if ($this->isExistingUserError($error)) {
                    Log::info('HostPinnacle sub-user already exists, resuming credential sync.', [
                        'company_id' => $company->id,
                        'login' => $loginName,
                    ]);
                } else {
                    Log::error('HostPinnacle sub-user creation failed.', [
                        'company_id' => $company->id,
                        'error' => $error,
                    ]);

                    return false;
                }
            }
        }

        return $this->finalizeSubAccount($company, $loginName);
    }

    private function finalizeSubAccount(Company $company, string $loginName): bool
    {
        $password = $this->generateGatewayPassword();
        $passwordResponse = $this->client->resetSubUserPassword($loginName, $password);
        if (! ($passwordResponse['ok'] ?? false)) {
            Log::error('HostPinnacle sub-user password reset failed.', [
                'company_id' => $company->id,
                'response' => $passwordResponse,
            ]);

            return false;
        }

        $apiKeyResponse = $this->client->createApiKey($loginName, $password);
        $apiKey = $this->client->extractCreatedApiKey($apiKeyResponse);

        if ($apiKey === null) {
            $readResponse = $this->client->readApiKey(new HostPinnacleCredentials($loginName, '', '', $password));
            if ($readResponse['ok'] ?? false) {
                $apiKey = $this->client->extractReadApiKey($readResponse);
            }
        }

        if ($apiKey === null) {
            Log::error('HostPinnacle API key creation failed.', [
                'company_id' => $company->id,
                'create_response' => $apiKeyResponse,
            ]);

            return false;
        }

        $senderId = trim((string) $company->getConfig('HOSTPINNACLE_SENDER_ID', ''));
        if ($senderId === '') {
            $senderId = $this->proposedSenderId($company);
        }

        $existingStatus = strtolower(trim((string) $company->getConfig('HOSTPINNACLE_SENDER_STATUS', '')));
        $senderStatus = in_array($existingStatus, ['approved', 'active', 'pending_approval'], true)
            ? $existingStatus
            : 'pending_approval';

        if (! in_array($existingStatus, ['approved', 'active', 'pending_approval'], true)) {
            $tenantCredentials = new HostPinnacleCredentials($loginName, $apiKey, $senderId, $password);
            $senderResponse = $this->client->createSenderId($tenantCredentials, $senderId);
            $senderStatus = ($senderResponse['ok'] ?? false) ? 'pending_approval' : 'request_failed';
        }

        $company->setMultipleConfig([
            'HOSTPINNACLE_SUB_LOGIN' => $loginName,
            'HOSTPINNACLE_USER_ID' => $loginName,
            'HOSTPINNACLE_API_KEY' => $apiKey,
            'HOSTPINNACLE_PASSWORD' => $password,
            'HOSTPINNACLE_SENDER_ID' => $senderId,
            'HOSTPINNACLE_SENDER_STATUS' => $senderStatus,
        ]);

        return true;
    }

    private function isValidLoginName(string $loginName): bool
    {
        return (bool) preg_match('/^[a-zA-Z0-9]{'.self::MIN_SUB_USER_LOGIN_LENGTH.','.self::MAX_SUB_USER_LOGIN_LENGTH.'}$/', $loginName);
    }

    private function isExistingUserError(string $error): bool
    {
        $normalized = strtolower($error);

        return str_contains($normalized, 'already exist')
            || str_contains($normalized, 'duplicate')
            || str_contains($normalized, 'userloginname')
            || str_contains($normalized, 'login name');
    }

    /**
     * @return array<string, mixed>
     */
    public function buildCreateUserPayload(Company $company): array
    {
        $company->loadMissing('user');

        return [
            'userloginname' => $this->subUserLoginName($company),
            'usertype' => $this->resolvedSubUserType(),
            'email' => $this->gatewayEmail($company),
            'mobileno' => $this->gatewayMobile($company),
            'fullname' => $this->gatewayFullName($company),
            'address' => $this->gatewayAddress($company),
            'city' => $this->gatewayCity($company),
            'region' => $this->gatewayRegion($company),
            'country' => $this->gatewayCountry($company),
            'expirydate' => now()->addYears((int) config('hostpinnacle.sub_user_expiry_years', 5))->format('Y-m-d'),
            'output' => 'json',
        ];
    }

    public function subUserLoginName(Company $company): string
    {
        $stored = trim((string) $company->getConfig('HOSTPINNACLE_SUB_LOGIN', ''));
        if ($stored !== '') {
            return Str::limit($stored, self::MAX_SUB_USER_LOGIN_LENGTH, '');
        }

        $prefix = 'cc'.str_pad((string) $company->id, 3, '0', STR_PAD_LEFT);
        $remaining = self::MAX_SUB_USER_LOGIN_LENGTH - strlen($prefix);
        $base = strtolower(preg_replace('/[^a-z0-9]/', '', (string) $company->subdomain) ?: '');

        if ($remaining <= 0) {
            return Str::limit($prefix, self::MAX_SUB_USER_LOGIN_LENGTH, '');
        }

        $login = $prefix.Str::limit($base, $remaining, '');

        return strlen($login) >= self::MIN_SUB_USER_LOGIN_LENGTH
            ? $login
            : str_pad($login, self::MIN_SUB_USER_LOGIN_LENGTH, '0');
    }

    public function proposedSenderId(Company $company): string
    {
        $candidate = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $company->subdomain) ?? '');

        if (strlen($candidate) < 3) {
            $candidate = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $company->name) ?? '');
        }

        if (strlen($candidate) < 3) {
            $candidate = 'C'.$company->id;
        }

        return Str::limit($candidate, 11, '');
    }

    public function gatewayFullName(Company $company): string
    {
        return $this->sanitizeAlphanumericWithSpaces(
            (string) $company->name,
            'Company '.$company->id,
            80,
        );
    }

    public function gatewayEmail(Company $company): string
    {
        $owner = $company->user;
        $candidates = [
            $owner?->email,
            $company->getConfig('email'),
            'company'.$company->id.'@'.preg_replace('/^www\./', '', parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'convocon.local'),
        ];

        foreach ($candidates as $candidate) {
            $email = strtolower(trim((string) $candidate));
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $email;
            }
        }

        return 'company'.$company->id.'@convocon.local';
    }

    public function gatewayMobile(Company $company): string
    {
        $owner = $company->user;
        $candidates = [
            $company->phone,
            $company->whatsapp_phone,
            $owner?->phone,
        ];

        foreach ($candidates as $candidate) {
            $mobile = $this->phoneNormalizer->forHostPinnacle(is_string($candidate) ? $candidate : null);
            if ($this->isValidGatewayMobile($mobile)) {
                return $mobile;
            }
        }

        $fallback = $this->phoneNormalizer->forHostPinnacle((string) config('hostpinnacle.default_mobile', '254700000000'));

        return $this->isValidGatewayMobile($fallback) ? $fallback : '254700000000';
    }

    public function gatewayAddress(Company $company): string
    {
        $raw = trim((string) ($company->address ?: ''));
        if ($raw === '') {
            return $this->gatewayCity($company).' '.$this->gatewayCountry($company);
        }

        $lines = preg_split('/\R+/', $raw) ?: [];
        $lines = array_slice($lines, 0, 4);
        $sanitized = [];

        foreach ($lines as $line) {
            $value = $this->sanitizeAlphanumericWithSpaces((string) $line, '', 120);
            if ($value !== '') {
                $sanitized[] = $value;
            }
        }

        return $sanitized !== [] ? implode("\n", $sanitized) : $this->gatewayCity($company).' '.$this->gatewayCountry($company);
    }

    public function gatewayCity(Company $company): string
    {
        return $this->sanitizeAlphanumericWithSpaces(
            (string) $company->getConfig('city', config('hostpinnacle.default_city', 'Nairobi')),
            (string) config('hostpinnacle.default_city', 'Nairobi'),
            80,
        );
    }

    public function gatewayRegion(Company $company): string
    {
        return $this->sanitizeAlphanumericWithSpaces(
            (string) $company->getConfig('region', config('hostpinnacle.default_region', 'Nairobi')),
            (string) config('hostpinnacle.default_region', 'Nairobi'),
            80,
        );
    }

    public function gatewayCountry(Company $company): string
    {
        return $this->sanitizeAlphanumericWithSpaces(
            (string) $company->getConfig('country', config('hostpinnacle.default_country', 'Kenya')),
            (string) config('hostpinnacle.default_country', 'Kenya'),
            80,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function validateCreateUserPayload(array $payload): ?string
    {
        $login = (string) ($payload['userloginname'] ?? '');
        if (! preg_match('/^[a-zA-Z0-9]{'.self::MIN_SUB_USER_LOGIN_LENGTH.','.self::MAX_SUB_USER_LOGIN_LENGTH.'}$/', $login)) {
            return 'Username must be 5-15 alphanumeric characters.';
        }

        if (! filter_var((string) ($payload['email'] ?? ''), FILTER_VALIDATE_EMAIL)) {
            return 'A valid email address is required.';
        }

        $mobile = (string) ($payload['mobileno'] ?? '');
        if (! $this->isValidGatewayMobile($mobile)) {
            return 'Mobile number must be numeric and use international format (e.g. 254712345678).';
        }

        if (trim((string) ($payload['fullname'] ?? '')) === '') {
            return 'Full name is required.';
        }

        if (trim((string) ($payload['address'] ?? '')) === '') {
            return 'Address is required.';
        }

        if (substr_count((string) ($payload['address'] ?? ''), "\n") > 3) {
            return 'Address may contain at most 4 lines.';
        }

        foreach (['city', 'region', 'country', 'expirydate'] as $field) {
            if (trim((string) ($payload[$field] ?? '')) === '') {
                return ucfirst($field).' is required.';
            }
        }

        if (! in_array((string) ($payload['usertype'] ?? ''), ['customer', 'reseller'], true)) {
            return 'User account type must be customer or reseller.';
        }

        return null;
    }

    private function isValidGatewayMobile(?string $mobile): bool
    {
        if ($mobile === null || $mobile === '') {
            return false;
        }

        if (! ctype_digit($mobile)) {
            return false;
        }

        $countryCode = (string) config('hostpinnacle.default_country_code', '254');

        return str_starts_with($mobile, $countryCode) && strlen($mobile) >= 11 && strlen($mobile) <= 15;
    }

    private function sanitizeAlphanumericWithSpaces(string $value, string $fallback, int $maxLength): string
    {
        $sanitized = trim((string) preg_replace('/\s+/', ' ', preg_replace('/[^A-Za-z0-9 ]+/', '', $value)));

        if ($sanitized === '') {
            $sanitized = $fallback;
        }

        return Str::limit($sanitized, $maxLength, '');
    }

    private function resolvedSubUserType(): string
    {
        $type = strtolower(trim((string) config('hostpinnacle.sub_user_type', 'customer')));

        return in_array($type, ['customer', 'reseller'], true) ? $type : 'customer';
    }

    /**
     * HostPinnacle requires 1 uppercase, 1 lowercase, 1 number, 1 special, min 8 chars,
     * but rejects &, @, #, %, +, /, = (API auth encoding issues).
     */
    public function generateGatewayPassword(int $length = 12): string
    {
        $length = max(8, $length);
        $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $lower = 'abcdefghijkmnpqrstuvwxyz';
        $digits = '23456789';
        $specials = '!*_-';
        $all = $upper.$lower.$digits.$specials;

        $chars = [
            $upper[random_int(0, strlen($upper) - 1)],
            $lower[random_int(0, strlen($lower) - 1)],
            $digits[random_int(0, strlen($digits) - 1)],
            $specials[random_int(0, strlen($specials) - 1)],
        ];

        for ($i = count($chars); $i < $length; $i++) {
            $chars[] = $all[random_int(0, strlen($all) - 1)];
        }

        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }

        return implode('', $chars);
    }
}
