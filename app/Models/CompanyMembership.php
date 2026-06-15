<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompanyMembership extends Model
{
    public const ROLE_MANAGER = 'manager';

    public const ROLE_AGENT = 'agent';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INVITED = 'invited';

    public const STATUS_SUSPENDED = 'suspended';

    protected $fillable = [
        'user_id',
        'company_id',
        'role',
        'status',
        'invited_by',
        'accepted_at',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function modules(): HasMany
    {
        return $this->hasMany(CompanyMembershipModule::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(CompanyMembershipAuditLog::class, 'target_membership_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isManager(): bool
    {
        return $this->role === self::ROLE_MANAGER;
    }

    public function isAgent(): bool
    {
        return $this->role === self::ROLE_AGENT;
    }

    public function hasModule(string $alias, string $minimumPermission = 'view'): bool
    {
        $module = $this->modules()->where('module_alias', $alias)->first();

        if ($module === null) {
            return false;
        }

        if ($minimumPermission === 'manage') {
            return $module->permission === 'manage';
        }

        return in_array($module->permission, ['view', 'manage'], true);
    }

    /**
     * @return array<int, string>
     */
    public function grantedModuleAliases(): array
    {
        return $this->modules()->pluck('module_alias')->all();
    }
}
