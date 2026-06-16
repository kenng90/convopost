<?php

namespace Modules\Reminders\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SourceStaff extends Model
{
    protected $table = 'rem_source_staff';

    public $guarded = [];

    protected $casts = [
        'working_hours' => 'array',
        'is_active' => 'boolean',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function appointmentStaff(): BelongsTo
    {
        return $this->belongsTo(AppointmentStaff::class, 'appointment_staff_id');
    }
}
