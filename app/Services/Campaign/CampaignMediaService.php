<?php

namespace App\Services\Campaign;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Wpbox\Models\Campaign;

class CampaignMediaService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function attachFromPayload(Campaign $campaign, array $payload): void
    {
        if (isset($payload['pdf']) && $payload['pdf'] instanceof UploadedFile) {
            $campaign->media_link = $this->storeFile($payload['pdf']);
            $campaign->save();
        }

        if (isset($payload['imageupload']) && $payload['imageupload'] instanceof UploadedFile) {
            $campaign->media_link = $this->storeFile($payload['imageupload']);
            $campaign->save();
        }
    }

    private function storeFile(UploadedFile $file): string
    {
        $path = $file->store('campaigns', 'public');

        return Storage::disk('public')->url($path);
    }
}
