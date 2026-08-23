<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Wpbox\Models\Contact;

class AgentMemory extends Model
{
    protected $fillable = [
        'company_id',
        'contact_id',
        'channel',
        'user_message',
        'reply',
        'tools_used',
        'metadata',
    ];

    protected $casts = [
        'tools_used' => 'array',
        'metadata' => 'array',
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
