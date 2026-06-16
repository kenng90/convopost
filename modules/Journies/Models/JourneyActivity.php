<?php

namespace Modules\Journies\Models;

use App\Models\Company;
use App\Models\User;
use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Wpbox\Models\Contact;

class JourneyActivity extends Model
{
    public const ACTION_MOVED = 'moved';

    public const ACTION_ADDED = 'added';

    public const ACTION_REMOVED = 'removed';

    protected $fillable = [
        'company_id',
        'journey_id',
        'stage_id',
        'contact_id',
        'user_id',
        'action',
        'source',
        'from_stage_id',
        'campaign_id',
        'campaign_status',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
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

    public function journey(): BelongsTo
    {
        return $this->belongsTo(Journey::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(JourneyStage::class, 'stage_id');
    }

    public function fromStage(): BelongsTo
    {
        return $this->belongsTo(JourneyStage::class, 'from_stage_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
