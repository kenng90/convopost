<?php

namespace App\Services\Telephony\Sms;

use App\Services\Telephony\TelephonyProvider;

class SmsProvider
{
    public const HOSTPINNACLE = 'hostpinnacle';

    public const TWILIO = TelephonyProvider::TWILIO;

    public const TELNYX = TelephonyProvider::TELNYX;

    public const UNCONFIGURED = 'unconfigured';

    public static function normalize(?string $provider): string
    {
        $p = strtolower(trim((string) $provider));

        return match ($p) {
            self::TWILIO => self::TWILIO,
            self::TELNYX => self::TELNYX,
            self::HOSTPINNACLE => self::HOSTPINNACLE,
            self::UNCONFIGURED => self::UNCONFIGURED,
            default => self::UNCONFIGURED,
        };
    }
}
