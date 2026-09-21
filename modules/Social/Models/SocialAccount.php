<?php

namespace Modules\Social\Models;

use App\Models\Company;
use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Social\Database\Factories\SocialAccountFactory;

class SocialAccount extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'social_accounts';

    protected $guarded = [];

    protected $casts = [
        'scopes' => 'array',
        'meta' => 'array',
        'token_expires_at' => 'datetime',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    protected static function newFactory(): SocialAccountFactory
    {
        return SocialAccountFactory::new();
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

    public function getAccessToken(): ?string
    {
        if (! $this->access_token) {
            return null;
        }

        return decrypt($this->access_token);
    }

    public function setAccessToken(?string $token): void
    {
        $this->access_token = $token === null ? null : encrypt($token);
    }

    public function getRefreshToken(): ?string
    {
        if (! $this->refresh_token) {
            return null;
        }

        return decrypt($this->refresh_token);
    }

    public function setRefreshToken(?string $token): void
    {
        $this->refresh_token = $token === null ? null : encrypt($token);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function hasExpiredToken(): bool
    {
        return $this->token_expires_at !== null && $this->token_expires_at->isPast();
    }
}
