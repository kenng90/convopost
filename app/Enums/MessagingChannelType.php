<?php

namespace App\Enums;

enum MessagingChannelType: string
{
    case Whatsapp = 'whatsapp';
    case Instagram = 'instagram';
    case Messenger = 'messenger';
    case Tiktok = 'tiktok';

    public function label(): string
    {
        return match ($this) {
            self::Whatsapp => 'WhatsApp',
            self::Instagram => 'Instagram',
            self::Messenger => 'Messenger',
            self::Tiktok => 'TikTok',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Whatsapp => 'badge-success',
            self::Instagram => 'badge-danger',
            self::Messenger => 'badge-primary',
            self::Tiktok => 'badge-dark',
        };
    }

    public static function tryFromString(?string $value): ?self
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::tryFrom($value);
    }
}
