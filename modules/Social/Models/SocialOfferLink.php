<?php

namespace Modules\Social\Models;

use App\Models\Company;
use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Modules\Social\Database\Factories\SocialOfferLinkFactory;

class SocialOfferLink extends Model
{
    use HasFactory;

    protected $table = 'social_offer_links';

    protected $guarded = [];

    protected $casts = [
        'click_count' => 'integer',
        'target_id' => 'integer',
    ];

    protected static function newFactory(): SocialOfferLinkFactory
    {
        return SocialOfferLinkFactory::new();
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function (self $model) {
            if (session('company_id') && ! $model->company_id) {
                $model->company_id = session('company_id');
            }

            if (! $model->tracking_token) {
                $model->tracking_token = Str::random(40);
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(SocialPost::class, 'social_post_id');
    }

    public function destinationUrl(): string
    {
        return (string) ($this->url ?: '/');
    }

    public function recordClick(): void
    {
        $this->increment('click_count');
    }
}
