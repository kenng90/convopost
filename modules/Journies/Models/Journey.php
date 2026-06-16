<?php

namespace Modules\Journies\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Modules\Wpbox\Models\Contact as WpboxContact;

class Journey extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'description',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Company::class);
    }

    public function stages(): HasMany
    {
        return $this->hasMany(JourneyStage::class)->orderBy('order')->orderBy('id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(JourneyActivity::class);
    }

    public function groupRules(): HasMany
    {
        return $this->hasMany(JourneyGroupRule::class);
    }

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(
            WpboxContact::class,
            'journey_stage_contacts',
            'stage_id',
            'contact_id'
        )->distinct();
    }

    public function contactsCount(): int
    {
        return (int) DB::table('journey_stage_contacts')
            ->whereIn('stage_id', function ($query) {
                $query->select('id')->from('journey_stages')->where('journey_id', $this->id);
            })
            ->distinct()
            ->count('contact_id');
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function ($model) {
            $company_id = session('company_id', null);
            if ($company_id) {
                $model->company_id = $company_id;
            }
        });
    }
}
