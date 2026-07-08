<?php

namespace Modules\Reminders\Models;

use App\Models\Company;
use App\Models\User;
use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Reminders\Services\GoogleCalendarService;
use Modules\Reminders\Support\WorkingHours;

class AppointmentStaff extends Model
{
    use HasFactory;

    protected $table = 'rem_appointment_staff';

    public $guarded = [];

    protected $casts = [
        'working_hours' => 'array',
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(
            Department::class,
            'rem_appointment_staff_department',
            'appointment_staff_id',
            'department_id'
        )->withTimestamps();
    }

    public function departmentNamesLabel(): string
    {
        $names = $this->relationLoaded('departments')
            ? $this->departments->pluck('name')
            : $this->departments()->orderBy('name')->pluck('name');

        if ($names->isNotEmpty()) {
            return $names->implode(', ');
        }

        return $this->department?->name ?? '';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sourceAssignments(): HasMany
    {
        return $this->hasMany(SourceStaff::class, 'appointment_staff_id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'appointment_staff_id');
    }

    public function calendarUser(): ?User
    {
        return $this->user;
    }

    public function hasConnectedCalendar(): bool
    {
        $user = $this->calendarUser();

        return $user && app(GoogleCalendarService::class)->isConnected($user);
    }

    public function normalizedWorkingHours(): array
    {
        return WorkingHours::normalize($this->working_hours);
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
