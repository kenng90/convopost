<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyMembershipModule extends Model
{
    protected $fillable = [
        'company_membership_id',
        'module_alias',
        'permission',
    ];

    public function membership(): BelongsTo
    {
        return $this->belongsTo(CompanyMembership::class, 'company_membership_id');
    }
}
