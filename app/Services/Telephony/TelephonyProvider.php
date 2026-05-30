<?php

namespace App\Services\Telephony;

class TelephonyProvider
{
    public const TWILIO = 'twilio';

    public const TELNYX = 'telnyx';

    public static function normalize(?string $provider): string
    {
        $p = strtolower(trim((string) $provider));

        return $p === self::TWILIO ? self::TWILIO : self::TELNYX;
    }
}
