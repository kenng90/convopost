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
    case Pinterest = 'pinterest';
    case Gbp = 'gbp';
    case X = 'x';

    public function label(): string
    {
        return match ($this) {
            self::Facebook => 'Facebook Page',
            self::Instagram => 'Instagram',
            self::LinkedIn => 'LinkedIn',
            self::TikTok => 'TikTok',
            self::YouTube => 'YouTube',
            self::Threads => 'Threads',
            self::Pinterest => 'Pinterest',
            self::Gbp => 'Google Business Profile',
            self::X => 'X',
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
            self::Pinterest => 'badge-warning',
            self::Gbp => 'badge-success',
            self::X => 'badge-dark',
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
            self::Pinterest,
            self::Gbp,
            self::X,
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
