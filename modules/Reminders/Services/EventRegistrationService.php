<?php

namespace Modules\Reminders\Services;

use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Modules\Reminders\Models\EventOccurrence;
use Modules\Reminders\Models\EventRegistration;
use Modules\Wpbox\Traits\Contacts;

class EventRegistrationService
{
    use Contacts;

    public function __construct(
        private readonly EventHostNotificationService $hostNotifications
    ) {
    }

    /**
     * @param  array{occurrence_id: int, phone: string, name: string, party_size?: int, external_id?: string|null}  $payload
     */
    public function register(Company $company, array $payload): EventRegistration
    {
        return DB::transaction(function () use ($company, $payload) {
            session(['company_id' => $company->id]);

            $occurrence = EventOccurrence::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('id', (int) $payload['occurrence_id'])
                ->lockForUpdate()
                ->with('event')
                ->firstOrFail();

            if (! $occurrence->isRegisterable()) {
                throw new \RuntimeException('This event is not open for registration.');
            }

            $partySize = max(1, (int) ($payload['party_size'] ?? 1));

            if ($occurrence->seatsRemaining() < $partySize) {
                throw new \RuntimeException('Not enough seats remaining for this event.');
            }

            $contact = $this->getOrMakeBookingContact($payload['phone'], $company, $payload['name']);

            $existing = EventRegistration::withoutGlobalScopes()
                ->where('event_occurrence_id', $occurrence->id)
                ->where('contact_id', $contact->id)
                ->where('status', EventRegistration::STATUS_CONFIRMED)
                ->first();

            if ($existing) {
                throw new \RuntimeException('You are already registered for this event.');
            }

            $registration = EventRegistration::create([
                'company_id' => $company->id,
                'event_id' => $occurrence->event_id,
                'event_occurrence_id' => $occurrence->id,
                'contact_id' => $contact->id,
                'party_size' => $partySize,
                'status' => EventRegistration::STATUS_CONFIRMED,
                'external_id' => $payload['external_id'] ?? null,
            ]);

            $registration = $registration->fresh(['event', 'occurrence', 'contact']);
            $this->hostNotifications->notifyRegistered($registration);

            return $registration;
        });
    }

    public function cancel(EventRegistration $registration): EventRegistration
    {
        return DB::transaction(function () use ($registration) {
            if ($registration->status === EventRegistration::STATUS_CANCELLED) {
                return $registration;
            }

            $this->deletePendingReminderMessages($registration);

            $registration->update([
                'status' => EventRegistration::STATUS_CANCELLED,
                'cancelled_at' => now(),
            ]);

            $registration = $registration->fresh(['event', 'occurrence', 'contact']);
            $this->hostNotifications->notifyCancelled($registration);

            return $registration;
        });
    }

    private function deletePendingReminderMessages(EventRegistration $registration): void
    {
        \Modules\Wpbox\Models\Message::query()
            ->where('company_id', $registration->company_id)
            ->where(function ($query) use ($registration) {
                $query->where('extra', 'event_reg:'.$registration->id)
                    ->orWhere('extra', (string) $registration->id);
            })
            ->where('status', 0)
            ->delete();
    }
}
