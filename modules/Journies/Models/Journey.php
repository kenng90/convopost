<?php

namespace Modules\Journies\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;

class Journey extends Model
{
    protected $fillable = [
        'company_id',
        'name', 
        'description'
    ];

    /**
     * Get the company that owns the journey
     */
    public function company()
    {
        return $this->belongsTo('App\Models\Company');
    }

    /**
     * Get the stages for this journey
     */
    public function stages()
    {
        return $this->hasMany('Modules\Journies\Models\JourneyStage');
    }

    /**
     * Get the contacts for the journey
     */
    public function contacts()
    {
       return $this->hasManyThrough(
           'Modules\Wpbox\Models\Contact',
           'Modules\Journies\Models\JourneyStage',
           'journey_id',
           'id',
           'id',
           'id'
       )->distinct();
    }

    //Add the global scope to only get the journey for the current company
    protected static function booted()
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function ($model){
            $company_id=session('company_id',null);
            if($company_id){
                $model->company_id=$company_id;
            }
        });
    }
}
