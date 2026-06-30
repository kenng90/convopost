<?php

namespace Modules\Flowmaker\Models\Nodes;

use App\Models\Company;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Flowmaker\Jobs\ResumeFlowFromMpesa;
use Modules\Flowmaker\Models\Contact;
use Modules\Reminders\Models\Reservation;
use Modules\Reminders\Models\Source;
use Modules\Reminders\Services\AvailabilityService;
use Modules\Reminders\Services\BookingCatalogService;
use Modules\Reminders\Services\BookingPaymentService;
use Modules\Reminders\Services\ReservationBookingService;
use Modules\Reminders\Support\BookingPaymentConfig;

class BookAppointment extends Node
{
    private const LIST_LIMIT = 10;

    public function listenForReply($message, $data): void
    {
        $contactId = is_object($data) ? $data->contact_id : $data['contact_id'];
        $contact = Contact::find($contactId);

        if (! $contact) {
            return;
        }

        $paymentOutcome = $contact->getContactStateValue($this->flow_id, $this->stateKey('payment_outcome'));

        if ($paymentOutcome !== null && $paymentOutcome !== '') {
            $contact->clearContactState($this->flow_id, $this->stateKey('payment_outcome'));
            $contact->clearContactState($this->flow_id, 'current_node');

            $handle = $paymentOutcome === 'success' ? 'success' : 'error';
            $next = $this->getNextNodeId($handle);

            if ($next) {
                $next->process($message, $data);
            }

            return;
        }

        $extraData = is_object($data) ? ($data->extra ?? '') : ($data['extra'] ?? '');

        if ($extraData === null || $extraData === '') {
            return;
        }

        $selection = $this->parseListItemId((string) $extraData);

        if ($selection === null) {
            return;
        }

        $this->storeSelection($contact, $selection['step'], $selection['value']);
        $contact->clearContactState($this->flow_id, 'current_node');
        $this->advanceWizard($contact, $message, $data);
    }

    public function process($message, $data): array
    {
        if ($this->isStartNode) {
            $this->listenForReply($message, $data);

            return ['success' => true];
        }

        $contactId = is_object($data) ? $data->contact_id : $data['contact_id'];
        $contact = Contact::find($contactId);

        if (! $contact) {
            return ['success' => false];
        }

        $company = Company::find($contact->company_id);

        if (! $company) {
            return ['success' => false];
        }

        $this->clearWizardState($contact);
        $this->seedFixedSettings($contact, $company);
        $contact->setContactState($this->flow_id, 'current_node', $this->id);

        return $this->advanceWizard($contact, $message, $data);
    }

    public static function notifyPaymentOutcome(int $flowId, int $contactId, string $nodeId, string $outcome): void
    {
        $contact = Contact::find($contactId);

        if (! $contact) {
            return;
        }

        $contact->setContactState($flowId, "ba_{$nodeId}_payment_outcome", $outcome);
        $contact->setContactState($flowId, 'current_node', $nodeId);
        ResumeFlowFromMpesa::dispatch($flowId, $contactId)->onQueue('flows');
    }

    private function advanceWizard(Contact $contact, $message, $data): array
    {
        $company = Company::find($contact->company_id);

        if (! $company) {
            return ['success' => false];
        }

        $source = $this->resolveSelectedSource($company, $contact);

        if (! $source) {
            return $this->promptServiceSelection($contact, $company);
        }

        if (! $this->getState($contact, 'duration_minutes')) {
            $duration = $this->resolveDurationMinutes($source, $contact);

            if ($duration === null) {
                return $this->promptDurationSelection($contact, $source);
            }

            $this->setState($contact, 'duration_minutes', (string) $duration);
        }

        if (! $this->getState($contact, 'selected_date')) {
            return $this->promptDateSelection($contact, $source);
        }

        if (! $this->getState($contact, 'slot_id')) {
            return $this->promptSlotSelection($contact, $source);
        }

        return $this->finalizeBooking($contact, $company, $source, $message, $data);
    }

    private function finalizeBooking(Contact $contact, Company $company, Source $source, $message, $data): array
    {
        $payload = [
            'phone' => $contact->phone,
            'name' => $contact->name ?: $contact->phone,
            'source' => $source->name,
            'slot_id' => $this->getState($contact, 'slot_id'),
            'duration_minutes' => (int) $this->getState($contact, 'duration_minutes'),
        ];

        $paymentConfig = BookingPaymentConfig::fromSource($source);

        try {
            if ($paymentConfig['payment_required']) {
                if (! app(BookingPaymentService::class)->mpesaConfigured($company)) {
                    $contact->sendMessage(__('Online payment is not available right now. Please contact us for help.'), false, false, 'TEXT');
                    $this->routeToHandle($contact, 'error', $message, $data);

                    return ['success' => false];
                }

                $payload['flow_context'] = [
                    'flow_id' => $this->flow_id,
                    'flow_node_id' => $this->id,
                    'contact_id' => $contact->id,
                ];

                app(BookingPaymentService::class)->initiateAppointmentPaymentForFlow($company, $payload);
                $contact->sendMessage(__('Check your phone and enter your M-Pesa PIN to complete payment and confirm your booking.'), false, false, 'TEXT');
                $contact->setContactState($this->flow_id, 'current_node', $this->id);

                return ['success' => true];
            }

            $reservation = app(ReservationBookingService::class)->book($company, $payload);
            $reservation->update(['payment_status' => BookingPaymentConfig::STATUS_NOT_REQUIRED]);
            $this->completeSuccess($contact, $reservation, $message, $data);
        } catch (\Throwable $exception) {
            Log::warning('Book appointment node failed', [
                'contact_id' => $contact->id,
                'node_id' => $this->id,
                'error' => $exception->getMessage(),
            ]);

            $contact->sendMessage($exception->getMessage(), false, false, 'TEXT');
            $this->routeToHandle($contact, 'error', $message, $data);
        }

        return ['success' => true];
    }

    public function completeSuccess(Contact $contact, Reservation $reservation, $message, $data): void
    {
        $reservation->loadMissing(['source', 'appointmentStaffMember']);
        $this->storeReservationVariables($contact, $reservation);

        $settings = $this->getDataAsArray()['settings'] ?? [];
        $successMessage = $settings['success_message'] ?? __('Your appointment for :service on :date at :time is confirmed.', [
            'service' => $reservation->source?->name ?? __('your service'),
            'date' => $reservation->start_date?->timezone($reservation->source?->timezone ?: 'UTC')->format('M j, Y'),
            'time' => $reservation->start_date?->timezone($reservation->source?->timezone ?: 'UTC')->format('g:i A'),
        ]);

        $contact->sendMessage($contact->changeVariables($successMessage, $this->flow_id), false, false, 'TEXT');
        $this->clearWizardState($contact);
        $contact->clearContactState($this->flow_id, 'current_node');
        $this->routeToHandle($contact, 'success', $message, $data);
    }

    private function promptServiceSelection(Contact $contact, Company $company): array
    {
        $services = app(BookingCatalogService::class)->bookableServicesForCompany($company);

        if ($services === []) {
            $contact->sendMessage(__('No bookable services are available right now.'), false, false, 'TEXT');
            $this->routeToHandle($contact, 'error', '', new \stdClass());

            return ['success' => false];
        }

        $settings = $this->getDataAsArray()['settings'] ?? [];

        return $this->sendList(
            $contact,
            $settings['header'] ?? __('Book appointment'),
            $settings['body'] ?? __('Choose a service to continue.'),
            $settings['footer'] ?? '',
            $settings['buttonText'] ?? __('View services'),
            __('Services'),
            collect($services)->take(self::LIST_LIMIT)->map(fn (array $service) => [
                'id' => $this->listItemId('service', $service['name']),
                'title' => $service['name'],
                'description' => $service['payment_required']
                    ? ($service['payment_upfront_percent'] < 100
                        ? __(':amount :currency now (:percent% of :total)', [
                            'amount' => number_format((float) $service['payment_amount']),
                            'currency' => $service['payment_currency'],
                            'percent' => $service['payment_upfront_percent'],
                            'total' => number_format((float) $service['payment_total_amount']).' '.$service['payment_currency'],
                        ])
                        : __(':amount :currency', [
                            'amount' => number_format((float) $service['payment_amount']),
                            'currency' => $service['payment_currency'],
                        ]))
                    : __(':minutes min', ['minutes' => $service['default_duration_minutes']]),
            ])->all()
        );
    }

    private function promptDurationSelection(Contact $contact, Source $source): array
    {
        $options = $source->durationOptions();
        $settings = $this->getDataAsArray()['settings'] ?? [];

        return $this->sendList(
            $contact,
            $settings['duration_header'] ?? __('Duration'),
            $settings['duration_body'] ?? __('How long should this appointment be?'),
            '',
            $settings['buttonText'] ?? __('Choose duration'),
            __('Duration'),
            collect($options)->take(self::LIST_LIMIT)->map(fn (int $minutes) => [
                'id' => $this->listItemId('duration', (string) $minutes),
                'title' => __(':minutes minutes', ['minutes' => $minutes]),
                'description' => '',
            ])->all()
        );
    }

    private function promptDateSelection(Contact $contact, Source $source): array
    {
        $duration = (int) $this->getState($contact, 'duration_minutes');
        $from = now($source->timezone ?: 'UTC')->startOfDay();
        $to = $from->copy()->addDays((int) $source->max_advance_days);
        $dates = app(AvailabilityService::class)->availableDates($source, $from, $to, $duration);

        if ($dates === []) {
            $contact->sendMessage(__('No available dates right now. Please try again later.'), false, false, 'TEXT');
            $this->routeToHandle($contact, 'unavailable', '', new \stdClass());

            return ['success' => false];
        }

        $settings = $this->getDataAsArray()['settings'] ?? [];
        $timezone = $source->timezone ?: 'UTC';

        return $this->sendList(
            $contact,
            $settings['date_header'] ?? __('Select date'),
            $settings['date_body'] ?? __('Pick a day for your appointment.'),
            '',
            $settings['buttonText'] ?? __('Choose date'),
            __('Dates'),
            collect($dates)->take(self::LIST_LIMIT)->map(function (string $date) use ($timezone) {
                $label = Carbon::parse($date, $timezone)->format('D, M j, Y');

                return [
                    'id' => $this->listItemId('date', $date),
                    'title' => $label,
                    'description' => $date,
                ];
            })->all()
        );
    }

    private function promptSlotSelection(Contact $contact, Source $source): array
    {
        $duration = (int) $this->getState($contact, 'duration_minutes');
        $date = $this->getState($contact, 'selected_date');
        $slots = app(AvailabilityService::class)->slotsForDate($source, $date, $duration);

        if ($slots === []) {
            $contact->sendMessage(__('No times are available for that date. Please choose another day.'), false, false, 'TEXT');
            $this->setState($contact, 'selected_date', '');
            $this->setState($contact, 'slot_id', '');

            return $this->promptDateSelection($contact, $source);
        }

        $settings = $this->getDataAsArray()['settings'] ?? [];

        return $this->sendList(
            $contact,
            $settings['slot_header'] ?? __('Select time'),
            $settings['slot_body'] ?? __('Choose an available time slot.'),
            '',
            $settings['buttonText'] ?? __('Choose time'),
            __('Times'),
            collect($slots)->take(self::LIST_LIMIT)->map(fn (array $slot) => [
                'id' => $this->listItemId('slot', $slot['id']),
                'title' => $slot['title'],
                'description' => '',
            ])->all()
        );
    }

    /**
     * @param  array<int, array{id: string, title: string, description: string}>  $rows
     */
    private function sendList(
        Contact $contact,
        string $header,
        string $body,
        string $footer,
        string $buttonText,
        string $sectionTitle,
        array $rows
    ): array {
        $company = Company::find($contact->company_id);
        $token = $company?->getConfig('plain_token', '') ?? '';

        $payload = [
            'token' => $token,
            'phone' => $contact->phone,
            'message' => (string) $contact->changeVariables($body, $this->flow_id),
            'header' => (string) ($contact->changeVariables($header, $this->flow_id) ?? ''),
            'footer' => (string) ($contact->changeVariables($footer, $this->flow_id) ?? ''),
            'action' => [
                'button' => (string) $contact->changeVariables($buttonText, $this->flow_id),
                'sections' => [[
                    'title' => $sectionTitle,
                    'rows' => collect($rows)->map(fn (array $row) => [
                        'id' => $row['id'],
                        'title' => (string) ($row['title'] ?? ''),
                        'description' => (string) ($row['description'] ?? ''),
                    ])->all(),
                ]],
            ],
        ];

        $contact->setContactState($this->flow_id, 'current_node', $this->id);

        try {
            Http::post(config('app.url').'/api/wpbox/sendlistmessage', $payload);
        } catch (\Throwable $exception) {
            Log::error('Book appointment list message failed', ['error' => $exception->getMessage()]);
        }

        return ['success' => true];
    }

    private function storeSelection(Contact $contact, string $step, string $value): void
    {
        match ($step) {
            'service' => $this->setState($contact, 'source_name', $value),
            'duration' => $this->setState($contact, 'duration_minutes', $value),
            'date' => $this->setState($contact, 'selected_date', $value),
            'slot' => $this->setState($contact, 'slot_id', $value),
            default => null,
        };
    }

    private function seedFixedSettings(Contact $contact, Company $company): void
    {
        $settings = $this->getDataAsArray()['settings'] ?? [];
        $fixedSource = trim((string) ($settings['source_name'] ?? ''));

        if ($fixedSource !== '') {
            $this->setState($contact, 'source_name', $fixedSource);
        }

        if (! empty($settings['duration_minutes'])) {
            $this->setState($contact, 'duration_minutes', (string) (int) $settings['duration_minutes']);
        }
    }

    private function resolveSelectedSource(Company $company, Contact $contact): ?Source
    {
        $sourceName = $this->getState($contact, 'source_name');

        if ($sourceName === '') {
            return null;
        }

        return Source::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('is_bookable', true)
            ->where('name', $sourceName)
            ->first();
    }

    private function resolveDurationMinutes(Source $source, Contact $contact): ?int
    {
        $stored = $this->getState($contact, 'duration_minutes');

        if ($stored !== '') {
            return (int) $stored;
        }

        $options = $source->durationOptions();

        if (count($options) === 1) {
            return $options[0];
        }

        if (count($options) > 1) {
            return null;
        }

        return (int) ($source->default_duration_minutes ?: 30);
    }

    private function storeReservationVariables(Contact $contact, Reservation $reservation): void
    {
        $timezone = $reservation->source?->timezone ?: 'UTC';
        $start = $reservation->start_date?->timezone($timezone);

        $contact->setContactState($this->flow_id, 'booking_service', $reservation->source?->name ?? '');
        $contact->setContactState($this->flow_id, 'booking_date', $start?->toDateString() ?? '');
        $contact->setContactState($this->flow_id, 'booking_time', $start?->format('g:i A') ?? '');
        $contact->setContactState($this->flow_id, 'booking_reference', (string) ($reservation->id));
    }

    private function routeToHandle(Contact $contact, string $handle, $message, $data): void
    {
        $next = $this->getNextNodeId($handle) ?: $this->getNextNodeId('error');

        if ($next) {
            $next->process($message, $data);
        }
    }

    private function stateKey(string $suffix): string
    {
        return "ba_{$this->id}_{$suffix}";
    }

    private function getState(Contact $contact, string $suffix): string
    {
        return (string) $contact->getContactStateValue($this->flow_id, $this->stateKey($suffix));
    }

    private function setState(Contact $contact, string $suffix, string $value): void
    {
        $contact->setContactState($this->flow_id, $this->stateKey($suffix), $value);
    }

    private function clearWizardState(Contact $contact): void
    {
        foreach (['source_name', 'duration_minutes', 'selected_date', 'slot_id', 'payment_outcome'] as $suffix) {
            $contact->clearContactState($this->flow_id, $this->stateKey($suffix));
        }
    }

    private function listItemId(string $step, string $value): string
    {
        $encoded = rtrim(strtr(base64_encode($value), '+/', '-_'), '=');

        return 'ba-'.$step.'-'.$encoded.'_id'.$this->id.'_flow'.$this->flow_id;
    }

    /**
     * @return array{step: string, value: string}|null
     */
    private function parseListItemId(string $extraData): ?array
    {
        if (! preg_match('/^ba-(service|duration|date|slot)-([^_]+)_id'.preg_quote($this->id, '/').'_flow'.preg_quote((string) $this->flow_id, '/').'$/', $extraData, $matches)) {
            return null;
        }

        $decoded = base64_decode(strtr($matches[2], '-_', '+/'), true);

        if ($decoded === false) {
            return null;
        }

        return [
            'step' => $matches[1],
            'value' => $decoded,
        ];
    }

    protected function getNextNodeId($handleId = null)
    {
        foreach ($this->outgoingEdges as $edge) {
            if ($handleId === null || str_contains($edge->getSourceHandle(), (string) $handleId)) {
                return $edge->getTarget();
            }
        }

        return null;
    }
}
