<?php

namespace Modules\Social\Enums;

enum SocialProvider: string
{
    case Facebook = 'facebook';
    case Instagram = 'instagram';
    case LinkedIn = 'linkedin';
    case TikTok = 'tiktok';
    case YouTube = 'youtube';
    case Threads = 'threads';

    public function label(): string
    {
        return match ($this) {
            self::Facebook => 'Facebook Page',
            self::Instagram => 'Instagram',
            self::LinkedIn => 'LinkedIn',
            self::TikTok => 'TikTok',
            self::YouTube => 'YouTube',
            self::Threads => 'Threads',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Facebook => 'badge-primary',
            self::Instagram => 'badge-danger',
            self::LinkedIn => 'badge-info',
            self::TikTok => 'badge-dark',
            self::YouTube => 'badge-danger',
            self::Threads => 'badge-secondary',
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
            self::YouTube,
            self::Threads,
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
