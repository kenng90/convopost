<?php

namespace Modules\Wpbox\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;

class CampaignSegment extends Model
{
    protected $table = 'campaign_segments';

    protected $fillable = [
        'company_id',
        'name',
        'filters',
    ];

    protected $casts = [
        'filters' => 'array',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function ($model) {
            $companyId = session('company_id', null);

            if ($companyId) {
                $model->company_id = $companyId;
            }
        });
    }

    public function company()
    {
        return $this->belongsTo(\App\Models\Company::class);
    }
}
