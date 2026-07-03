<?php

namespace App\Services\VoiceBooking;

use Illuminate\Support\Facades\Cache;

class VoiceBookingIdempotencyStore
{
    public function remember(int $callId, string $toolCallId, callable $callback): array
    {
        $key = $this->cacheKey($callId, $toolCallId);

        $cached = Cache::get($key);
        if (is_array($cached)) {
            return array_merge($cached, ['idempotent_replay' => true]);
        }

        $result = $callback();
        if (! is_array($result)) {
            $result = ['ok' => false, 'error' => 'Invalid tool result'];
        }

        Cache::put($key, $result, now()->addHours(24));

        return $result;
    }

    private function cacheKey(int $callId, string $toolCallId): string
    {
        return 'voice_booking:idempotency:'.$callId.':'.sha1($toolCallId);
    }
}
