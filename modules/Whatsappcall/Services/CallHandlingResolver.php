<?php

namespace Modules\Whatsappcall\Services;

use App\Models\Company;
use Carbon\Carbon;

class CallHandlingResolver
{
    public const MODE_LIVE = 'live';

    public const MODE_AI = 'ai';

    public const MODE_AI_AFTER_HOURS = 'ai_after_hours';

    public function resolve(Company $company): string
    {
        $mode = $company->getConfig('whatsapp_call_handling', self::MODE_LIVE);

        if (! in_array($mode, [self::MODE_LIVE, self::MODE_AI, self::MODE_AI_AFTER_HOURS], true)) {
            return self::MODE_LIVE;
        }

        if ($mode === self::MODE_AI_AFTER_HOURS) {
            return $this->isWithinBusinessHours($company) ? self::MODE_LIVE : self::MODE_AI;
        }

        return $mode;
    }

    public function shouldUseAi(Company $company): bool
    {
        if ($this->resolve($company) !== self::MODE_AI) {
            return false;
        }

        return app(CompanyVoiceOpenAiKeyResolver::class)->isConfigured($company);
    }

    public function isWithinBusinessHours(Company $company): bool
    {
        if ($company->getConfig('whatsapp_calling_hours_status', 'DISABLED') !== 'ENABLED') {
            return true;
        }

        $timezone = $company->getConfig('whatsapp_calling_timezone_id', 'UTC');
        $now = Carbon::now($timezone);
        $day = strtoupper($now->format('l'));

        $weekly = json_decode($company->getConfig('whatsapp_calling_weekly_operating_hours', '[]'), true) ?: [];
        $today = collect($weekly)->firstWhere('day_of_week', $day);

        if (! $today) {
            return false;
        }

        $open = $this->hhmmToMinutes($today['open_time'] ?? '0000');
        $close = $this->hhmmToMinutes($today['close_time'] ?? '2359');
        $current = ((int) $now->format('H')) * 60 + (int) $now->format('i');

        if ($open <= $close) {
            return $current >= $open && $current <= $close;
        }

        return $current >= $open || $current <= $close;
    }

    private function hhmmToMinutes(string $hhmm): int
    {
        $hhmm = preg_replace('/\D/', '', $hhmm);
        $hhmm = str_pad($hhmm, 4, '0', STR_PAD_LEFT);

        return ((int) substr($hhmm, 0, 2)) * 60 + (int) substr($hhmm, 2, 2);
    }
}
