<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyRoleTemplateModule extends Model
{
    protected $fillable = [
        'company_role_template_id',
        'module_alias',
        'permission',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(CompanyRoleTemplate::class, 'company_role_template_id');
    }
}
