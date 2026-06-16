<?php

namespace Modules\Reminders\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Reminders\Support\WorkingHours;

class Source extends Model
{
    use HasFactory;

    public const ASSIGNMENT_CUSTOMER_CHOICE = 'customer_choice';

    public const ASSIGNMENT_ROUND_ROBIN = 'round_robin';

    public const ASSIGNMENT_LEAST_BUSY = 'least_busy';

    protected $table = 'rem_res_sources';

    public $guarded = [];

    protected $casts = [
        'duration_options' => 'array',
        'working_hours' => 'array',
        'default_duration_minutes' => 'integer',
        'buffer_minutes' => 'integer',
        'min_notice_hours' => 'integer',
        'max_advance_days' => 'integer',
        'is_bookable' => 'boolean',
        'sort_order' => 'integer',
        'reminder_before_value' => 'integer',
        'reminder_after_value' => 'integer',
    ];

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'source_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function staffAssignments(): HasMany
    {
        return $this->hasMany(SourceStaff::class, 'source_id');
    }

    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(AppointmentStaff::class, 'rem_source_staff', 'source_id', 'appointment_staff_id')
            ->withPivot(['working_hours', 'is_active'])
            ->withTimestamps();
    }

    /**
     * @return array<int, int>
     */
    public function durationOptions(): array
    {
        $options = collect($this->duration_options ?: [])
            ->map(fn ($value) => (int) $value)
            ->filter(fn ($value) => $value > 0)
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($options !== []) {
            return $options;
        }

        $default = (int) ($this->default_duration_minutes ?: 30);

        return [$default];
    }

    public function normalizedWorkingHours(): array
    {
        return WorkingHours::normalize($this->working_hours);
    }

    public function usesAutoStaffAssignment(): bool
    {
        return in_array($this->staff_assignment_mode, [
            self::ASSIGNMENT_ROUND_ROBIN,
            self::ASSIGNMENT_LEAST_BUSY,
        ], true);
    }

    /**
     * @return array<string, string>
     */
    public static function staffAssignmentModeOptions(): array
    {
        return [
            self::ASSIGNMENT_CUSTOMER_CHOICE => __('Customer picks team member'),
            self::ASSIGNMENT_ROUND_ROBIN => __('Round-robin assignment'),
            self::ASSIGNMENT_LEAST_BUSY => __('Least busy team member'),
        ];
    }

    protected static function booted()
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function ($model) {
            $company_id = session('company_id', null);
            if ($company_id) {
                $model->company_id = $company_id;
            }

            if (! $model->working_hours) {
                $model->working_hours = WorkingHours::default();
            }

            if (! $model->duration_options) {
                $model->duration_options = [(int) ($model->default_duration_minutes ?: 30)];
            }
        });
    }
}
