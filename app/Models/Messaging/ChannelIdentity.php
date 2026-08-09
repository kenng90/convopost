<?php

namespace App\Models\Messaging;

use App\Enums\MessagingChannelType;
use App\Models\Company;
use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Wpbox\Models\Contact;

class ChannelIdentity extends Model
{
    protected $table = 'channel_identities';

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
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
}
