<?php

namespace App\Services\VoiceBooking;

use App\Models\Company;
use Illuminate\Support\Facades\Log;
use Modules\Reminders\Models\Source;
use Modules\Reminders\Services\AvailabilityService;
use Modules\Reminders\Services\BookingCatalogService;
use Modules\Reminders\Services\EventCatalogService;
use Modules\Whatsappcall\Models\Call as CallModel;
use Modules\Whatsappcall\Support\CallContactResolver;
use Modules\Wpbox\Models\Contact;

class VoiceBookingToolService
{
    public function __construct(
        protected VoiceBookingSettingsService $settings,
        protected VoiceBookingIdempotencyStore $idempotency,
        protected VoiceBookingOrchestrator $orchestrator,
        protected BookingCatalogService $catalogService,
        protected EventCatalogService $eventCatalogService,
        protected AvailabilityService $availabilityService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function invoke(CallModel $call, string $tool, array $arguments, ?string $toolCallId = null): array
    {
        $company = Company::find($call->company_id);
        if (! $company) {
            return ['ok' => false, 'error' => 'Company not found'];
        }

        if (! $this->settings->isEnabled($company)) {
            return ['ok' => false, 'error' => 'Voice booking is not enabled for this company'];
        }

        $executor = fn () => $this->dispatchTool($call, $company, $tool, $arguments);

        if ($toolCallId) {
            return $this->idempotency->remember($call->id, $toolCallId, $executor);
        }

        return $executor();
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function dispatchTool(CallModel $call, Company $company, string $tool, array $arguments): array
    {
        return match ($tool) {
            'list_bookable_services' => $this->listBookableServices($company),
            'get_available_dates' => $this->getAvailableDates($company, $arguments),
            'get_available_slots' => $this->getAvailableSlots($company, $arguments),
            'list_upcoming_events' => $this->listUpcomingEvents($company),
            'get_event_details' => $this->getEventDetails($company, $arguments),
            'create_appointment_booking' => $this->createAppointmentBooking($call, $company, $arguments),
            'create_event_registration' => $this->createEventRegistration($call, $company, $arguments),
            default => ['ok' => false, 'error' => 'Unknown tool: '.$tool],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function listBookableServices(Company $company): array
    {
        if (! $this->settings->appointmentsEnabled($company)) {
            return ['ok' => true, 'services' => []];
        }

        $services = $this->catalogService->bookableServicesForCompany($company);

        return [
            'ok' => true,
            'services' => collect($services)->map(fn (array $service) => [
                'name' => $service['name'],
                'duration_minutes' => $service['default_duration_minutes'],
                'duration_options' => $service['duration_options'] ?? [],
                'payment_required' => (bool) ($service['payment_required'] ?? false),
                'price' => $service['payment_amount'] ?? null,
                'currency' => $service['payment_currency'] ?? 'KES',
            ])->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function getAvailableDates(Company $company, array $arguments): array
    {
        if (! $this->settings->appointmentsEnabled($company)) {
            return ['ok' => false, 'error' => 'Appointments not enabled'];
        }

        $serviceName = trim((string) ($arguments['service_name'] ?? ''));
        if ($serviceName === '') {
            return ['ok' => false, 'error' => 'service_name is required'];
        }

        $source = $this->resolveSource($company, $serviceName);
        if (! $source) {
            return ['ok' => false, 'error' => 'Service not found'];
        }

        $duration = isset($arguments['duration_minutes']) ? (int) $arguments['duration_minutes'] : null;
        $from = now($source->timezone ?: 'UTC')->startOfDay();
        $to = $from->copy()->addDays((int) $source->max_advance_days);

        return [
            'ok' => true,
            'service_name' => $source->name,
            'dates' => $this->availabilityService->availableDates($source, $from, $to, $duration),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function getAvailableSlots(Company $company, array $arguments): array
    {
        if (! $this->settings->appointmentsEnabled($company)) {
            return ['ok' => false, 'error' => 'Appointments not enabled'];
        }

        $serviceName = trim((string) ($arguments['service_name'] ?? ''));
        $date = trim((string) ($arguments['date'] ?? ''));

        if ($serviceName === '' || $date === '') {
            return ['ok' => false, 'error' => 'service_name and date (Y-m-d) are required'];
        }

        $source = $this->resolveSource($company, $serviceName);
        if (! $source) {
            return ['ok' => false, 'error' => 'Service not found'];
        }

        $duration = isset($arguments['duration_minutes']) ? (int) $arguments['duration_minutes'] : null;
        $slots = $this->availabilityService->slotsForDate($source, $date, $duration);

        return [
            'ok' => true,
            'service_name' => $source->name,
            'date' => $date,
            'slots' => collect($slots)->map(fn (array $slot) => [
                'slot_id' => $slot['id'],
                'title' => $slot['title'],
                'start' => $slot['start'],
                'staff_name' => $slot['staff_name'] ?? null,
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function listUpcomingEvents(Company $company): array
    {
        if (! $this->settings->eventsEnabled($company)) {
            return ['ok' => true, 'events' => []];
        }

        $occurrences = $this->eventCatalogService->upcomingOccurrencesForCompany($company, 15);

        return [
            'ok' => true,
            'events' => collect($occurrences)->map(fn (array $row) => [
                'occurrence_id' => (int) $row['id'],
                'title' => $row['event_title'],
                'starts_at' => $row['starts_at_label'],
                'description' => $row['description'] ?? null,
                'payment_required' => (bool) ($row['payment_required'] ?? false),
                'price' => $row['payment_amount'] ?? null,
                'currency' => $row['payment_currency'] ?? 'KES',
            ])->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function getEventDetails(Company $company, array $arguments): array
    {
        $occurrenceId = (int) ($arguments['occurrence_id'] ?? 0);
        if ($occurrenceId < 1) {
            return ['ok' => false, 'error' => 'occurrence_id is required'];
        }

        $occurrence = $this->eventCatalogService->findRegisterableOccurrence($company, $occurrenceId);
        if (! $occurrence || ! $occurrence->event) {
            return ['ok' => false, 'error' => 'Event not found or not registerable'];
        }

        $formatted = $this->eventCatalogService->formatOccurrence($occurrence, $occurrence->event->timezone);
        $payment = \Modules\Reminders\Support\BookingPaymentConfig::fromEvent($occurrence->event);

        return [
            'ok' => true,
            'occurrence_id' => $occurrence->id,
            'event_title' => $occurrence->event->title,
            'description' => $occurrence->event->description,
            'location' => $occurrence->event->location,
            'starts_at' => $formatted['starts_at_label'],
            'seats_remaining' => $formatted['seats_remaining'],
            'payment_required' => (bool) ($payment['payment_required'] ?? false),
            'price' => $payment['payment_amount'] ?? null,
            'currency' => $payment['payment_currency'] ?? 'KES',
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function createAppointmentBooking(CallModel $call, Company $company, array $arguments): array
    {
        $contact = $this->resolveContact($call, $company);

        return $this->orchestrator->createAppointmentBooking($call, $company, $contact, $arguments);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function createEventRegistration(CallModel $call, Company $company, array $arguments): array
    {
        $contact = $this->resolveContact($call, $company);

        return $this->orchestrator->createEventRegistration($call, $company, $contact, $arguments);
    }

    private function resolveSource(Company $company, string $name): ?Source
    {
        return Source::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('is_bookable', true)
            ->where('name', $name)
            ->first();
    }

    private function resolveContact(CallModel $call, Company $company): Contact
    {
        if ($call->contact_id) {
            $contact = Contact::withoutGlobalScopes()
                ->where('id', $call->contact_id)
                ->where('company_id', $company->id)
                ->first();
            if ($contact) {
                return $contact;
            }
        }

        $contact = CallContactResolver::findByPhone((int) $company->id, $call->wa_user_id);
        if ($contact) {
            return $contact;
        }

        $phone = $call->wa_user_id ?: 'voice-'.$call->id;

        return Contact::withoutGlobalScopes()->firstOrCreate(
            ['company_id' => $company->id, 'phone' => $phone],
            ['name' => 'Voice caller', 'has_chat' => true]
        );
    }
}
