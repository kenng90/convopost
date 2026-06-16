<?php

namespace Modules\Wpbox\Support;

use Illuminate\Support\Facades\Cache;
use Modules\Wpbox\Models\Campaign;
use Modules\Wpbox\Models\Reply;

class BotRulesCache
{
    public static function cacheKey(int $companyId): string
    {
        return "bot_rules:{$companyId}";
    }

    /**
     * @return array{0: \Illuminate\Support\Collection, 1: \Illuminate\Support\Collection}
     */
    public static function get(int $companyId): array
    {
        return Cache::remember(self::cacheKey($companyId), 600, function () use ($companyId) {
            return [
                Reply::where('type', '!=', 1)->where('company_id', $companyId)->get(),
                Campaign::where('is_bot', 1)->where('is_bot_active', 1)->where('company_id', $companyId)->get(),
            ];
        });
    }

    public static function forget(int $companyId): void
    {
        Cache::forget(self::cacheKey($companyId));
    }
}
