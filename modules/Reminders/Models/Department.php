<?php

namespace Modules\Reminders\Models;

use App\Models\Company;
use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Reminders\Support\WorkingHours;

class Department extends Model
{
    use HasFactory;

    protected $table = 'rem_departments';

    public $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'working_hours' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function appointmentStaff(): HasMany
    {
        return $this->hasMany(AppointmentStaff::class, 'department_id');
    }

    public function appointmentStaffMembers(): BelongsToMany
    {
        return $this->belongsToMany(
            AppointmentStaff::class,
            'rem_appointment_staff_department',
            'department_id',
            'appointment_staff_id'
        )->withTimestamps();
    }

    public function sources(): HasMany
    {
        return $this->hasMany(Source::class, 'department_id');
    }

    public function closures(): HasMany
    {
        return $this->hasMany(BookingClosure::class, 'department_id');
    }

    public function normalizedWorkingHours(): array
    {
        return WorkingHours::normalize($this->working_hours);
    }

    public function effectiveTimezone(): string
    {
        return $this->timezone ?: config('app.timezone', 'UTC');
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function (self $model) {
            if (session('company_id') && ! $model->company_id) {
                $model->company_id = session('company_id');
            }

            if (! $model->working_hours) {
                $model->working_hours = WorkingHours::default();
            }
        });
    }
}
