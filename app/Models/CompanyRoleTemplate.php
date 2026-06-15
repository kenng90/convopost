<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompanyRoleTemplate extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'description',
        'is_system',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function modules(): HasMany
    {
        return $this->hasMany(CompanyRoleTemplateModule::class);
    }

    /**
     * @return array<int, array{module_alias: string, permission: string}>
     */
    public function moduleGrants(): array
    {
        return $this->modules()
            ->get(['module_alias', 'permission'])
            ->map(fn (CompanyRoleTemplateModule $module) => [
                'module_alias' => $module->module_alias,
                'permission' => $module->permission,
            ])
            ->all();
    }
}
