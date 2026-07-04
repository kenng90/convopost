<?php

namespace Modules\Reminders\Models;

use App\Models\Company;
use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Reminders\Services\BookingMessageContextService;

class Remineder extends Model
{
    use HasFactory;

    protected $table = 'reminders';

    public $guarded = [];

    protected $casts = [
        'is_service_managed' => 'boolean',
    ];

    public function isServiceManaged(): bool
    {
        return (bool) $this->is_service_managed;
    }

    public function managedByServiceLabel(): ?string
    {
        if (! $this->isServiceManaged()) {
            return null;
        }

        if ($this->event_id) {
            return $this->event?->title;
        }

        if (! $this->source_id) {
            return null;
        }

        return $this->source?->name;
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function source()
    {
        return $this->belongsTo(Source::class)->withTrashed();
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function makeMessages(Reservation $reservation)
    {
        app(BookingMessageContextService::class)->sendReservationReminder($this, $reservation);
    }

    public function makeEventRegistrationMessages(EventRegistration $registration): void
    {
        app(BookingMessageContextService::class)->sendEventReminder($this, $registration);
    }

    protected static function booted()
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function ($model) {
            $company_id = session('company_id', null);
            if ($company_id) {
                $model->company_id = $company_id;
            }
        });
    }
}
