<?php

namespace Modules\Social\Enums;

enum SocialProvider: string
{
    case Facebook = 'facebook';
    case Instagram = 'instagram';
    case LinkedIn = 'linkedin';
    case TikTok = 'tiktok';

    public function label(): string
    {
        return match ($this) {
            self::Facebook => 'Facebook Page',
            self::Instagram => 'Instagram',
            self::LinkedIn => 'LinkedIn',
            self::TikTok => 'TikTok',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Facebook => 'badge-primary',
            self::Instagram => 'badge-danger',
            self::LinkedIn => 'badge-info',
            self::TikTok => 'badge-dark',
        };
    }

    /**
     * @return list<self>
     */
    public static function publishable(): array
    {
        return [
            self::Facebook,
            self::Instagram,
            self::LinkedIn,
            self::TikTok,
        ];
    }

    public static function tryFromString(?string $value): ?self
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::tryFrom($value);
    }
}
