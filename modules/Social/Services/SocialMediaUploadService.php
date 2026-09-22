<?php

namespace Modules\Social\Services;

use App\Models\Company;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Social\Models\SocialMediaAsset;

class SocialMediaUploadService
{
    public function store(Company $company, UploadedFile $file, ?User $uploader = null): SocialMediaAsset
    {
        $disk = (string) config('social.media.disk', 'public');
        $directory = trim((string) config('social.media.directory', 'social/media'), '/');
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $filename = Str::uuid()->toString().'.'.$extension;
        $path = $file->storeAs($directory.'/'.$company->id, $filename, $disk);

        [$width, $height] = $this->detectImageDimensions($file);

        return SocialMediaAsset::query()->create([
            'company_id' => $company->id,
            'uploaded_by' => $uploader?->id,
            'disk' => $disk,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType() ?: $file->getClientMimeType(),
            'size' => $file->getSize(),
            'width' => $width,
            'height' => $height,
            'duration' => null,
            'meta' => [],
        ]);
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    protected function detectImageDimensions(UploadedFile $file): array
    {
        $mime = (string) ($file->getMimeType() ?: '');

        if (! str_starts_with($mime, 'image/')) {
            return [null, null];
        }

        $size = @getimagesize($file->getRealPath());

        if (! is_array($size)) {
            return [null, null];
        }

        return [
            isset($size[0]) ? (int) $size[0] : null,
            isset($size[1]) ? (int) $size[1] : null,
        ];
    }

    public function deleteFile(SocialMediaAsset $asset): void
    {
        if ($asset->path && Storage::disk($asset->disk ?: 'public')->exists($asset->path)) {
            Storage::disk($asset->disk ?: 'public')->delete($asset->path);
        }
    }
}
