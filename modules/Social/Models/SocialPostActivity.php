<?php

namespace Modules\Social\Models;

use App\Models\Company;
use App\Models\User;
use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Social\Database\Factories\SocialPostActivityFactory;

class SocialPostActivity extends Model
{
    use HasFactory;

    protected $table = 'social_post_activities';

    protected $guarded = [];

    protected $casts = [
        'meta' => 'array',
    ];

    protected static function newFactory(): SocialPostActivityFactory
    {
        return SocialPostActivityFactory::new();
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
