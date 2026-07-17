<?php

namespace App\Support;

/**
 * Meta WhatsApp Flow categories (Graph API).
 *
 * @see https://developers.facebook.com/docs/whatsapp/flows
 */
class WhatsappFlowCategory
{
    public const ALLOWED = [
        'SIGN_UP',
        'SIGN_IN',
        'APPOINTMENT_BOOKING',
        'LEAD_GENERATION',
        'SHOPPING',
        'CONTACT_US',
        'CUSTOMER_SUPPORT',
        'SURVEY',
        'OTHER',
    ];

    /**
     * Legacy / display aliases → Meta enum.
     *
     * @var array<string, string>
     */
    private const ALIASES = [
        'APPOINTMENT' => 'APPOINTMENT_BOOKING',
        'BOOKING' => 'APPOINTMENT_BOOKING',
        'FEEDBACK' => 'SURVEY',
        'SUPPORT' => 'CUSTOMER_SUPPORT',
        'SHOP' => 'SHOPPING',
        'LEAD' => 'LEAD_GENERATION',
        'REGISTER' => 'SIGN_UP',
        'LOGIN' => 'SIGN_IN',
        'CONTACT' => 'CONTACT_US',
    ];

    public static function normalize(?string $category): string
    {
        $raw = strtoupper(trim((string) $category));
        if ($raw === '') {
            return 'OTHER';
        }

        if (in_array($raw, self::ALLOWED, true)) {
            return $raw;
        }

        return self::ALIASES[$raw] ?? 'OTHER';
    }

    /**
     * @return list<string>
     */
    public static function forMetaApi(?string $category): array
    {
        return [self::normalize($category)];
    }
}
