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

    /**
     * Managed by a service/event that is missing or archived — safe to remove from the list.
     */
    public function isOrphanedManagedRule(): bool
    {
        if (! $this->isServiceManaged()) {
            return false;
        }

        if ($this->event_id) {
            return $this->event === null;
        }

        if ($this->source_id) {
            $source = $this->source;

            return $source === null || $source->trashed();
        }

        return true;
    }

    /**
     * Whether the client-notifications list may delete this rule.
     */
    public function canDeleteFromList(): bool
    {
        if (! $this->isServiceManaged()) {
            return true;
        }

        return $this->isOrphanedManagedRule();
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

    /**
     * Whether this rule should schedule messages for an appointment reservation.
     */
    public function appliesToReservation(Reservation $reservation): bool
    {
        // Event-scoped rules never apply to appointments.
        if ($this->event_id !== null) {
            return false;
        }

        if ($this->source_id !== null && (int) $this->source_id !== (int) $reservation->source_id) {
            return false;
        }

        // Company-wide / "all services" rules: skip event-oriented campaigns
        // (e.g. Event reminder attached with no source/event), which otherwise
        // send broken messages with literal "event_title" placeholders.
        if ($this->source_id === null && $this->campaignUsesEventTitleField()) {
            return false;
        }

        return true;
    }

    /**
     * Whether this rule should schedule messages for an event registration.
     */
    public function appliesToEventRegistration(EventRegistration $registration): bool
    {
        // Service-scoped rules never apply to events.
        if ($this->source_id !== null) {
            return false;
        }

        if ($this->event_id !== null && (int) $this->event_id !== (int) $registration->event_id) {
            return false;
        }

        // Company-wide rules: skip appointment-oriented campaigns so both
        // "Appointment reminder" and "Event reminder" don't fire for events.
        if ($this->event_id === null && $this->campaignIsAppointmentOriented()) {
            return false;
        }

        return true;
    }

    private function campaignUsesEventTitleField(): bool
    {
        $match = $this->campaignVariablesMatchValues();

        return in_array((string) BookingMessageContextService::FIELD_EVENT_TITLE, $match, true)
            || in_array(BookingMessageContextService::FIELD_EVENT_TITLE, $match, true);
    }

    private function campaignIsAppointmentOriented(): bool
    {
        if ($this->campaignUsesEventTitleField()) {
            return false;
        }

        $campaign = $this->campaign()->first();
        if (! $campaign) {
            return false;
        }

        $name = mb_strtolower((string) $campaign->name);

        return str_contains($name, 'appointment');
    }

    /**
     * @return array<int, mixed>
     */
    private function campaignVariablesMatchValues(): array
    {
        $campaign = $this->campaign()->first();
        if (! $campaign) {
            return [];
        }

        $match = json_decode($campaign->variables_match ?: '{}', true);
        if (! is_array($match)) {
            return [];
        }

        $values = [];
        array_walk_recursive($match, function ($value) use (&$values) {
            $values[] = $value;
        });

        return $values;
    }

    public function campaign()
    {
        return $this->belongsTo(\Modules\Wpbox\Models\Campaign::class, 'campaign_id');
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
