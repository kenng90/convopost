<?php

namespace Modules\Reminders\Services;

use App\Models\Company;
use App\Models\Config;
use Illuminate\Support\Str;

class BookingPublicKeyService
{
    public const CONFIG_KEY = 'BOOKING_PUBLIC_KEY';

    public const PREFIX = 'bk_';

    public function ensureKey(Company $company): string
    {
        $existing = $company->getConfig(self::CONFIG_KEY);

        if (is_string($existing) && str_starts_with($existing, self::PREFIX)) {
            return $existing;
        }

        return $this->rotate($company);
    }

    public function rotate(Company $company): string
    {
        $key = self::PREFIX.Str::random(48);
        $company->setConfig(self::CONFIG_KEY, $key);

        return $key;
    }

    public function companyForKey(string $key): ?Company
    {
        if (! str_starts_with($key, self::PREFIX)) {
            return null;
        }

        $config = Config::query()
            ->where('key', self::CONFIG_KEY)
            ->where('value', $key)
            ->where('model_type', 'App\Models\Company')
            ->orderByDesc('id')
            ->first();

        if (! $config) {
            return null;
        }

        return Company::find($config->model_id);
    }

    public function isBookingKey(?string $value): bool
    {
        return is_string($value) && str_starts_with($value, self::PREFIX);
    }
}
