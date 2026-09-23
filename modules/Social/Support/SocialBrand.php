<?php

namespace Modules\Social\Support;

use App\Models\Company;
use Illuminate\Support\Facades\Auth;
use Modules\Wpsupportlanding\Support\UnganishaBrand;

class SocialBrand
{
    /**
     * Platform product names that should not be treated as a white-label rename.
     *
     * @var list<string>
     */
    private const PLATFORM_DEFAULT_NAMES = [
        'unganisha',
        'unganisha hub',
        'unganisha social',
        'convoconnect',
        'mauzochat',
    ];

    /**
     * Display name for the Social product (page titles, nav copy).
     */
    public static function productName(?Company $company = null): string
    {
        $company ??= Auth::user()?->currentCompany();

        if ($company && self::usesCustomDomain($company)) {
            return trim($company->name) !== ''
                ? trim($company->name).' Social'
                : __('Social');
        }

        $siteName = self::configuredSiteName();

        if ($siteName !== null) {
            return str_ends_with(mb_strtolower($siteName), ' social')
                ? $siteName
                : $siteName.' Social';
        }

        return UnganishaBrand::name().' Social';
    }

    /**
     * Short platform / workspace brand for body copy ("publish from X").
     */
    public static function platformName(?Company $company = null): string
    {
        $company ??= Auth::user()?->currentCompany();

        if ($company && self::usesCustomDomain($company)) {
            return trim($company->name) !== '' ? trim($company->name) : __('your workspace');
        }

        return self::configuredSiteName() ?? UnganishaBrand::name();
    }

    public static function usesCustomDomain(?Company $company = null): bool
    {
        $company ??= Auth::user()?->currentCompany();

        if (! $company) {
            return false;
        }

        $domain = trim((string) $company->getConfig('domain', ''));

        return strlen($domain) > 3;
    }

    public static function logoUrl(?Company $company = null): ?string
    {
        $company ??= Auth::user()?->currentCompany();

        if ($company && self::usesCustomDomain($company)) {
            $logo = $company->logom ?? null;

            if (is_string($logo) && $logo !== '') {
                return $logo;
            }
        }

        $siteLogo = config('settings.logo') ?: config('global.site_logo');

        return is_string($siteLogo) && $siteLogo !== '' ? $siteLogo : null;
    }

    protected static function configuredSiteName(): ?string
    {
        $siteName = trim((string) config('settings.site_name', ''));

        if ($siteName === '' || self::isPlatformDefaultName($siteName)) {
            return null;
        }

        return $siteName;
    }

    protected static function isPlatformDefaultName(string $name): bool
    {
        return in_array(mb_strtolower(trim($name)), self::PLATFORM_DEFAULT_NAMES, true);
    }
}
