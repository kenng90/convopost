<?php

namespace Modules\Social\Services;

use Modules\Social\Models\SocialMediaAsset;
use Modules\Social\Models\SocialPostVersion;
use RuntimeException;

class SocialMediaAssetDeletionService
{
    public function __construct(private readonly SocialMediaUploadService $uploads)
    {
    }

    public function isUsed(SocialMediaAsset $asset): bool
    {
        return SocialPostVersion::query()
            ->where(function ($query) use ($asset) {
                $query->whereJsonContains('media_ids', $asset->id)
                    ->orWhereJsonContains('media_ids', (string) $asset->id);
            })
            ->exists();
    }

    public function deleteIfUnused(SocialMediaAsset $asset): void
    {
        if ($this->isUsed($asset)) {
            throw new RuntimeException(
                __('This media is attached to one or more posts and cannot be deleted.')
            );
        }

        $this->uploads->deleteFile($asset);
        $asset->delete();
    }
}
