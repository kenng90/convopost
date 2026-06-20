<?php

namespace App\Models\Messaging;

use App\Enums\MessagingChannelType;
use App\Models\Company;
use App\Models\User;
use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Wpbox\Models\Contact;
use Modules\Wpbox\Models\Message;

class Conversation extends Model
{
    protected $table = 'conversations';

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
        'last_reply_at' => 'datetime',
        'last_client_reply_at' => 'datetime',
        'last_support_reply_at' => 'datetime',
        'is_last_message_by_contact' => 'boolean',
        'enabled_ai_bot' => 'boolean',
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

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function channelConnection(): BelongsTo
    {
        return $this->belongsTo(ChannelConnection::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function isWithinServiceWindow(int $hours = 24): bool
    {
        if ($this->last_client_reply_at === null) {
            return false;
        }

        return $this->last_client_reply_at->greaterThan(now()->subHours($hours));
    }
}
