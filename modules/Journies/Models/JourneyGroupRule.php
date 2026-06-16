<?php

namespace Modules\Journies\Models;

use App\Models\Company;
use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Contacts\Models\Group;

class JourneyGroupRule extends Model
{
    protected $fillable = [
        'company_id',
        'group_id',
        'journey_id',
        'stage_id',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function (self $model) {
            if (! $model->company_id) {
                $model->company_id = session('company_id');
            }
        });
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function journey(): BelongsTo
    {
        return $this->belongsTo(Journey::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(JourneyStage::class, 'stage_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
