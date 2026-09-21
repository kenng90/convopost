<?php

namespace Modules\Social\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Social\Database\Factories\SocialPostVersionFactory;

class SocialPostVersion extends Model
{
    use HasFactory;

    protected $table = 'social_post_versions';

    protected $guarded = [];

    protected $casts = [
        'media_ids' => 'array',
        'provider_payload' => 'array',
    ];

    protected static function newFactory(): SocialPostVersionFactory
    {
        return SocialPostVersionFactory::new();
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(SocialPost::class, 'social_post_id');
    }
}
