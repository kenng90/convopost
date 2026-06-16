<?php

namespace Modules\Journies\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Wpbox\Models\Campaign;
use Modules\Wpbox\Models\Contact as ModelsContact;

class JourneyStage extends Model
{
    protected $fillable = [
        'journey_id',
        'name',
        'order',
        'campaign_id',
        'campaign_delay_minutes',
    ];

    protected $casts = [
        'order' => 'integer',
        'campaign_delay_minutes' => 'integer',
    ];

    public function journey(): BelongsTo
    {
        return $this->belongsTo(Journey::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(ModelsContact::class, 'journey_stage_contacts', 'stage_id', 'contact_id')
            ->withTimestamps();
    }
}
