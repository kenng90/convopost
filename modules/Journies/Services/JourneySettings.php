<?php

namespace Modules\Journies\Services;

use App\Models\Company;

class JourneySettings
{
    public function __construct(private ?Company $company = null)
    {
    }

    public static function for(?Company $company): self
    {
        return new self($company);
    }

    public function isEnabled(): bool
    {
        return $this->bool('JOURNEYS_ENABLED', true);
    }

    public function confirmBeforeSend(): bool
    {
        return $this->bool('JOURNEYS_CONFIRM_BEFORE_SEND', true);
    }

    public function autoEnrollNewContacts(): bool
    {
        return $this->bool('JOURNEYS_AUTO_ENROLL_NEW_CONTACTS', false);
    }

    public function staffCanManage(): bool
    {
        return $this->bool('JOURNEYS_STAFF_CAN_MANAGE', true);
    }

    public function defaultJourneyId(): ?int
    {
        $value = $this->company?->getConfig('JOURNEYS_DEFAULT_JOURNEY_ID');

        return $value !== null && $value !== '' ? (int) $value : null;
    }

    private function bool(string $key, bool $default): bool
    {
        if (! $this->company) {
            return $default;
        }

        return filter_var($this->company->getConfig($key, $default ? 'true' : 'false'), FILTER_VALIDATE_BOOLEAN);
    }
}
