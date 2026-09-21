<?php

namespace Modules\Social\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Social\Database\Factories\SocialPostAccountFactory;

class SocialPostAccount extends Model
{
    use HasFactory;

    protected $table = 'social_post_accounts';

    protected $guarded = [];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    protected static function newFactory(): SocialPostAccountFactory
    {
        return SocialPostAccountFactory::new();
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(SocialPost::class, 'social_post_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class, 'social_account_id');
    }

    public function markPublished(?string $providerPostId = null): void
    {
        $this->forceFill([
            'status' => 'published',
            'provider_post_id' => $providerPostId ?? $this->provider_post_id,
            'error' => null,
            'published_at' => now(),
        ])->save();
    }

    public function markFailed(string $error): void
    {
        $this->forceFill([
            'status' => 'failed',
            'error' => $error,
        ])->save();
    }
}
