<?php

namespace Modules\Wpbox\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;

class CampaignTrigger extends Model
{
    protected $table = 'campaign_triggers';

    protected $fillable = [
        'company_id',
        'event_type',
        'campaign_id',
        'is_active',
        'config',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'config' => 'array',
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

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function company()
    {
        return $this->belongsTo(\App\Models\Company::class);
    }
}
