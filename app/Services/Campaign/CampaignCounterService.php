<?php

namespace App\Services\Campaign;

use Illuminate\Support\Facades\Cache;
use Modules\Wpbox\Models\Campaign;

class CampaignCounterService
{
    public function bufferSent(int $campaignId, int $count = 1): void
    {
        if ($count <= 0) {
            return;
        }

        Cache::increment('campaign_sent_buffer:'.$campaignId, $count);
    }

    public function flushAll(): int
    {
        $flushed = 0;
        $prefix = config('cache.prefix', '').'campaign_sent_buffer:';

        if (! method_exists(Cache::getStore(), 'getRedis')) {
            return 0;
        }

        try {
            $redis = Cache::getStore()->getRedis();
            $keys = $redis->keys('*campaign_sent_buffer:*');

            foreach ($keys as $key) {
                $normalized = str_replace($prefix, '', (string) $key);
                $campaignId = (int) str_replace('campaign_sent_buffer:', '', basename(str_replace('\\', '/', $normalized)));

                if ($campaignId <= 0) {
                    continue;
                }

                $bufferKey = 'campaign_sent_buffer:'.$campaignId;
                $count = (int) Cache::pull($bufferKey, 0);

                if ($count > 0) {
                    Campaign::withoutGlobalScopes()->whereKey($campaignId)->increment('sended_to', $count);
                    $flushed++;
                }
            }
        } catch (\Throwable) {
            return 0;
        }

        return $flushed;
    }

    public function flushCampaign(int $campaignId): void
    {
        $count = (int) Cache::pull('campaign_sent_buffer:'.$campaignId, 0);

        if ($count > 0) {
            Campaign::withoutGlobalScopes()->whereKey($campaignId)->increment('sended_to', $count);
        }
    }
}
