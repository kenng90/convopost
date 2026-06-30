<?php

namespace Modules\Reminders\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    use HasFactory;

    protected $table = 'rem_events';

    public $guarded = [];

    protected $casts = [
        'is_published' => 'boolean',
        'payment_required' => 'boolean',
        'payment_amount' => 'decimal:2',
        'payment_upfront_percent' => 'integer',
        'sort_order' => 'integer',
        'reminder_before_value' => 'integer',
        'reminder_after_value' => 'integer',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(AppointmentStaff::class, 'appointment_staff_id');
    }

    public function occurrences(): HasMany
    {
        return $this->hasMany(EventOccurrence::class, 'event_id');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class, 'event_id');
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Remineder::class, 'event_id');
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
}
