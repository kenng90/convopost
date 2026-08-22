<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Wpbox\Models\Contact;

class ConversationWorkspace extends Model
{
    protected $fillable = [
        'company_id',
        'contact_id',
        'sla_started_at',
        'sla_due_at',
        'sla_breached_at',
        'sla_minutes',
        'locked_by',
        'locked_at',
        'csat_score',
        'csat_comment',
        'csat_requested_at',
        'csat_submitted_at',
    ];

    protected $casts = [
        'sla_started_at' => 'datetime',
        'sla_due_at' => 'datetime',
        'sla_breached_at' => 'datetime',
        'locked_at' => 'datetime',
        'csat_requested_at' => 'datetime',
        'csat_submitted_at' => 'datetime',
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

    public function lockedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function isSlaBreached(): bool
    {
        return $this->sla_breached_at !== null
            || ($this->sla_due_at !== null && $this->sla_due_at->isPast());
    }
}
