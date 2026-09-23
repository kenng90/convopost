<?php

namespace Modules\Social\Models;

use App\Models\Company;
use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Social\Database\Factories\SocialPostAnalyticsSnapshotFactory;

class SocialPostAnalyticsSnapshot extends Model
{
    use HasFactory;

    protected $table = 'social_post_analytics_snapshots';

    protected $guarded = [];

    protected $casts = [
        'impressions' => 'integer',
        'reach' => 'integer',
        'likes' => 'integer',
        'comments' => 'integer',
        'shares' => 'integer',
        'clicks' => 'integer',
        'engagement' => 'integer',
        'raw' => 'array',
        'synced_at' => 'datetime',
    ];

    protected static function newFactory(): SocialPostAnalyticsSnapshotFactory
    {
        return SocialPostAnalyticsSnapshotFactory::new();
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function (self $model) {
            if (session('company_id') && ! $model->company_id) {
                $model->company_id = session('company_id');
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

    public function postAccount(): BelongsTo
    {
        return $this->belongsTo(SocialPostAccount::class, 'social_post_account_id');
    }
}
