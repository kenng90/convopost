<?php

namespace Modules\Social\Models;

use App\Models\Company;
use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Social\Database\Factories\SocialQueueSlotFactory;

class SocialQueueSlot extends Model
{
    use HasFactory;

    protected $table = 'social_queue_slots';

    protected $guarded = [];

    protected $casts = [
        'weekday' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function newFactory(): SocialQueueSlotFactory
    {
        return SocialQueueSlotFactory::new();
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function (self $model) {
            if (session('company_id') && ! $model->company_id) {
                $model->company_id = session('company_id');
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function weekdayLabel(): string
    {
        return match ($this->weekday) {
            0 => __('Sunday'),
            1 => __('Monday'),
            2 => __('Tuesday'),
            3 => __('Wednesday'),
            4 => __('Thursday'),
            5 => __('Friday'),
            6 => __('Saturday'),
            default => (string) $this->weekday,
        };
    }
}
