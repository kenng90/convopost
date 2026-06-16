<?php

namespace Modules\Reminders\Models;

use App\Scopes\CompanyScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventOccurrence extends Model
{
    use HasFactory;

    protected $table = 'rem_event_occurrences';

    public $guarded = [];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'registration_opens_at' => 'datetime',
        'registration_closes_at' => 'datetime',
        'capacity' => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class, 'event_occurrence_id');
    }

    public function activeRegistrations(): HasMany
    {
        return $this->registrations()->where('status', EventRegistration::STATUS_CONFIRMED);
    }

    public function seatsUsed(): int
    {
        return (int) $this->activeRegistrations()->sum('party_size');
    }

    public function seatsRemaining(): int
    {
        return max(0, (int) $this->capacity - $this->seatsUsed());
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function isRegisterable(?Carbon $now = null): bool
    {
        $now = $now ?: now($this->event?->timezone ?: 'UTC');

        if ($this->status !== self::STATUS_PUBLISHED) {
            return false;
        }

        if (! $this->event?->is_published) {
            return false;
        }

        if ($this->registration_opens_at && $now->lt($this->registration_opens_at)) {
            return false;
        }

        $closesAt = $this->registration_closes_at ?: $this->starts_at;

        if ($closesAt && $now->gt($closesAt)) {
            return false;
        }

        return $this->seatsRemaining() > 0;
    }

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_COMPLETED = 'completed';

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function (self $model) {
            if (session('company_id') && ! $model->company_id) {
                $model->company_id = session('company_id');
            }
        });
    }
}
