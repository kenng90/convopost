<?php

namespace Modules\Social\Models;

use App\Models\Company;
use App\Models\User;
use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Social\Database\Factories\SocialPostFactory;

class SocialPost extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'social_posts';

    protected $guarded = [];

    protected $casts = [
        'label_ids' => 'array',
        'scheduled_at' => 'datetime',
        'published_at' => 'datetime',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    protected static function newFactory(): SocialPostFactory
    {
        return SocialPostFactory::new();
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

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(SocialPostVersion::class, 'social_post_id');
    }

    public function defaultVersion(): HasOne
    {
        return $this->hasOne(SocialPostVersion::class, 'social_post_id')
            ->where('provider', 'default');
    }

    public function postAccounts(): HasMany
    {
        return $this->hasMany(SocialPostAccount::class, 'social_post_id');
    }

    public function accounts(): BelongsToMany
    {
        return $this->belongsToMany(
            SocialAccount::class,
            'social_post_accounts',
            'social_post_id',
            'social_account_id'
        )->withPivot(['provider_post_id', 'status', 'error', 'published_at'])
            ->withTimestamps();
    }

    public function offerLink(): HasOne
    {
        return $this->hasOne(SocialOfferLink::class, 'social_post_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(SocialPostActivity::class, 'social_post_id')->orderByDesc('id');
    }

    public function analyticsSnapshots(): HasMany
    {
        return $this->hasMany(SocialPostAnalyticsSnapshot::class, 'social_post_id')->orderByDesc('synced_at');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(SocialComment::class, 'social_post_id')->orderByDesc('commented_at');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isScheduled(): bool
    {
        return $this->status === 'scheduled';
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isPendingApproval(): bool
    {
        return $this->approval_status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->approval_status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->approval_status === 'rejected';
    }

    public function labels(): \Illuminate\Support\Collection
    {
        $ids = collect($this->label_ids ?? [])->map(fn ($id) => (int) $id)->filter()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return SocialLabel::withoutGlobalScopes()
            ->where('company_id', $this->company_id)
            ->whereIn('id', $ids->all())
            ->orderBy('name')
            ->get();
    }
}
