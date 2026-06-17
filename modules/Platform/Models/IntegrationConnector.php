<?php

namespace Modules\Platform\Models;

use App\Models\Company;
use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationConnector extends Model
{
    protected $fillable = [
        'company_id',
        'provider',
        'status',
        'credentials',
        'connected_at',
    ];

    protected $casts = [
        'credentials' => 'array',
        'connected_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function ($model) {
            $companyId = session('company_id');
            if ($companyId) {
                $model->company_id = $companyId;
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
