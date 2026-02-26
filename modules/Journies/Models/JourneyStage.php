<?php

namespace Modules\Journies\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Contact;
use Modules\Wpbox\Models\Contact as ModelsContact;

class JourneyStage extends Model
{
    protected $fillable = [
        'journey_id',
        'name',
        'order',
        'campaign_id',
    ];

    /**
     * Get the journey that owns the stage.
     */
    public function journey(): BelongsTo
    {
        return $this->belongsTo(Journey::class);
    }

    /**
     * Get the contacts for the stage.
     */
    public function contacts()
    {
        return $this->belongsToMany(ModelsContact::class, 'journey_stage_contacts', 'stage_id', 'contact_id');
    }
}
