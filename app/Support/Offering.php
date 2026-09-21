<?php

namespace App\Support;

class Offering
{
    public const MODE_SOCIAL_COMMERCE = 'social_commerce';

    public const MODE_FULL = 'full';

    public static function mode(): string
    {
        $mode = (string) config('offering.mode', self::MODE_SOCIAL_COMMERCE);

        return in_array($mode, [self::MODE_SOCIAL_COMMERCE, self::MODE_FULL], true)
            ? $mode
            : self::MODE_SOCIAL_COMMERCE;
    }

    public static function isSocialCommerce(): bool
    {
        return self::mode() === self::MODE_SOCIAL_COMMERCE;
    }

    public static function isFull(): bool
    {
        return self::mode() === self::MODE_FULL;
    }

    /**
     * WhatsApp CRM / messaging surfaces are available in the UI.
     */
    public static function whatsappEnabled(): bool
    {
        return self::isFull();
    }

    /**
     * WhatsApp-related UI should stay hidden and blocked.
     */
    public static function whatsappDormant(): bool
    {
        return ! self::whatsappEnabled();
    }

    /**
     * @return list<string>
     */
    public static function dormantModules(): array
    {
        $modules = config('offering.whatsapp_dormant_modules', []);

        return is_array($modules) ? array_values(array_filter($modules, 'is_string')) : [];
    }

    /**
     * @return list<string>
     */
    public static function dormantRoutes(): array
    {
        $routes = config('offering.whatsapp_dormant_routes', []);

        return is_array($routes) ? array_values(array_filter($routes, 'is_string')) : [];
    }

    public static function isDormantModule(string $alias): bool
    {
        if (! self::whatsappDormant()) {
            return false;
        }

        return in_array($alias, self::dormantModules(), true);
    }

    public static function isDormantRoute(?string $routeName): bool
    {
        if ($routeName === null || $routeName === '' || ! self::whatsappDormant()) {
            return false;
        }

        return in_array($routeName, self::dormantRoutes(), true);
    }

    public static function socialHomeRoute(): string
    {
        return (string) config('offering.social_home_route', 'social.home');
    }
}
