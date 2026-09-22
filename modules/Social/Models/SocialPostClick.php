<?php

namespace Modules\Social\Models;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Social\Database\Factories\SocialPostClickFactory;

class SocialPostClick extends Model
{
    use HasFactory;

    protected $table = 'social_post_clicks';

    protected $guarded = [];

    protected $casts = [
        'clicked_at' => 'datetime',
    ];

    protected static function newFactory(): SocialPostClickFactory
    {
        return SocialPostClickFactory::new();
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function offerLink(): BelongsTo
    {
        return $this->belongsTo(SocialOfferLink::class, 'social_offer_link_id');
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(SocialPost::class, 'social_post_id');
    }
}
