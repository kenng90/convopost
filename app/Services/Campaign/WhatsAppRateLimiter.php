<?php

namespace App\Services\Campaign;

use Illuminate\Support\Facades\Cache;

class WhatsAppRateLimiter
{
    public function perSecondLimit(): int
    {
        return max(1, (int) config('wpbox.whatsapp_messages_per_second', 80));
    }

    public function acquire(string $phoneNumberId, int $attempts = 50): bool
    {
        $limit = $this->perSecondLimit();

        for ($i = 0; $i < $attempts; $i++) {
            $key = 'whatsapp_rate:'.$phoneNumberId.':'.now()->format('YmdHis');
            $count = (int) Cache::get($key, 0);

            if ($count < $limit) {
                Cache::put($key, $count + 1, now()->addSeconds(2));

                return true;
            }

            usleep(50_000);
        }

        return false;
    }
}
