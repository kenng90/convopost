<?php

namespace App\Models\Messaging;

use App\Enums\MessagingChannelType;
use App\Models\Company;
use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChannelConnection extends Model
{
    protected $table = 'channel_connections';

    protected $guarded = [];

    protected $casts = [
        'credentials' => 'array',
        'capabilities' => 'array',
        'last_webhook_at' => 'datetime',
        'channel' => MessagingChannelType::class,
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function credential(string $key, mixed $default = null): mixed
    {
        return data_get($this->credentials, $key, $default);
    }

    public function accessToken(): string
    {
        return (string) $this->credential('access_token', '');
    }
}
