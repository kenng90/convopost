<?php

namespace Modules\Reminders\Models;

use App\Models\Company;
use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use HasFactory;

    protected $table = 'rem_departments';

    public $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function appointmentStaff(): HasMany
    {
        return $this->hasMany(AppointmentStaff::class, 'department_id');
    }

    public function sources(): HasMany
    {
        return $this->hasMany(Source::class, 'department_id');
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
