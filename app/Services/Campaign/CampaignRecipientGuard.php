<?php

namespace App\Services\Campaign;

use Illuminate\Validation\ValidationException;
use Modules\Wpbox\Models\Campaign;

class CampaignRecipientGuard
{
    public function maxRecipients(): int
    {
        return max(0, (int) config('wpbox.campaign_max_recipients', 1_000_000));
    }

    public function preparationChunkSize(): int
    {
        return max(100, (int) config('wpbox.campaign_preparation_chunk', 2000));
    }

    public function insertChunkSize(): int
    {
        return max(100, (int) config('wpbox.campaign_insert_chunk', 1000));
    }

    /**
     * @throws ValidationException
     */
    public function assertWithinLimit(int $recipientCount, ?Campaign $campaign = null): void
    {
        $max = $this->maxRecipients();

        if ($max <= 0) {
            return;
        }

        if ($recipientCount > $max) {
            throw ValidationException::withMessages([
                'audience' => [__('This campaign exceeds the maximum of :max recipients.', ['max' => number_format($max)])],
            ]);
        }
    }
}
