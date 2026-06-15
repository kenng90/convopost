<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyMembershipAuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'actor_user_id',
        'target_membership_id',
        'action',
        'metadata',
        'ip_address',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function targetMembership(): BelongsTo
    {
        return $this->belongsTo(CompanyMembership::class, 'target_membership_id');
    }
}
