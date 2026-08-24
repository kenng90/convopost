<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Wpbox\Models\Contact;

class OutcomeAttribution extends Model
{
    protected $fillable = [
        'company_id',
        'contact_id',
        'sku',
        'event',
        'source',
        'unique_key',
        'revenue',
        'credits_charged',
        'billed_at',
        'guaranteed_until',
        'voided_at',
        'metadata',
    ];

    protected $casts = [
        'revenue' => 'decimal:2',
        'credits_charged' => 'integer',
        'billed_at' => 'datetime',
        'guaranteed_until' => 'datetime',
        'voided_at' => 'datetime',
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

    public function isVoidable(): bool
    {
        return $this->voided_at === null
            && $this->guaranteed_until !== null
            && $this->guaranteed_until->isFuture();
    }
}
