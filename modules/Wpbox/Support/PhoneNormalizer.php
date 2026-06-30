<?php

namespace Modules\Wpbox\Support;

use App\Models\Company;

class PhoneNormalizer
{
    public const DEFAULT_COUNTRY_ISO = 'ke';

    public const DEFAULT_DIAL_CODE = '254';

    /** @var array<string, string> */
    private const ISO_TO_DIAL = [
        'ke' => '254',
        'ug' => '256',
        'tz' => '255',
        'ng' => '234',
        'za' => '27',
        'gb' => '44',
        'us' => '1',
    ];

    public function isoForCompany(Company $company): string
    {
        $iso = strtolower((string) $company->getConfig('BOOKING_DEFAULT_PHONE_COUNTRY', self::DEFAULT_COUNTRY_ISO));

        return array_key_exists($iso, self::ISO_TO_DIAL) ? $iso : self::DEFAULT_COUNTRY_ISO;
    }

    public function dialCodeForCompany(Company $company): string
    {
        return self::ISO_TO_DIAL[$this->isoForCompany($company)] ?? self::DEFAULT_DIAL_CODE;
    }

    public function normalize(string $phone, ?string $defaultDialCode = null): string
    {
        $defaultDialCode = $defaultDialCode ?? self::DEFAULT_DIAL_CODE;
        $digits = preg_replace('/\D+/', '', trim($phone)) ?? '';

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '0')) {
            return $defaultDialCode.substr($digits, 1);
        }

        if (strlen($digits) >= 11) {
            return $digits;
        }

        if (strlen($digits) === 9 && $defaultDialCode === '254' && (str_starts_with($digits, '7') || str_starts_with($digits, '1'))) {
            return $defaultDialCode.$digits;
        }

        if (! str_starts_with($digits, $defaultDialCode) && strlen($digits) <= 10) {
            return $defaultDialCode.$digits;
        }

        return $digits;
    }

    /**
     * @return array<int, string>
     */
    public function lookupCandidates(string $phone, ?string $defaultDialCode = null): array
    {
        $defaultDialCode = $defaultDialCode ?? self::DEFAULT_DIAL_CODE;
        $trimmed = trim($phone);
        $strippedPlus = ltrim($trimmed, '+');
        $normalized = $this->normalize($phone, $defaultDialCode);

        $candidates = [
            $trimmed,
            $strippedPlus,
            '+'.$strippedPlus,
        ];

        if ($normalized !== '') {
            $candidates[] = $normalized;
            $candidates[] = '+'.$normalized;
        }

        if (str_starts_with($normalized, $defaultDialCode) && strlen($normalized) > strlen($defaultDialCode)) {
            $national = substr($normalized, strlen($defaultDialCode));
            $candidates[] = '0'.$national;
            $candidates[] = $national;
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    /**
     * @return array<string, string>
     */
    public static function countryOptions(): array
    {
        return [
            'ke' => 'Kenya (+254)',
            'ug' => 'Uganda (+256)',
            'tz' => 'Tanzania (+255)',
            'ng' => 'Nigeria (+234)',
            'za' => 'South Africa (+27)',
            'gb' => 'United Kingdom (+44)',
            'us' => 'United States (+1)',
        ];
    }
}
