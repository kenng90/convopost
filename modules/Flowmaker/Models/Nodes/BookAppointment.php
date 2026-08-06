<?php

namespace Modules\Flowmaker\Models\Nodes;

use App\Models\Company;
use App\Services\Flowmaker\BookingWebhookService;
use App\Services\Flowmaker\FlowRunLogger;
use App\Services\WhatsApp\InteractiveListLimits;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Flowmaker\Jobs\ResumeFlowFromMpesa;
use Modules\Flowmaker\Models\Contact;
use Modules\Flowmaker\Traits\SendsAvailabilityWaitMessage;
use Modules\Reminders\Models\Reservation;
use Modules\Reminders\Models\Source;
use Modules\Reminders\Services\AvailabilityService;
use Modules\Reminders\Services\BookingCatalogService;
use Modules\Reminders\Services\BookingFormBridgeService;
use Modules\Reminders\Services\BookingPaymentService;
use Modules\Reminders\Services\ReservationBookingService;
use Modules\Reminders\Support\BookingPaymentConfig;

class BookAppointment extends Node
{
    use SendsAvailabilityWaitMessage;

    private const LIST_LIMIT = 10;

    private const PAGINATED_LIST_SIZE = self::LIST_LIMIT - 1;

    public function listenForReply($message, $data): void
    {
        $contactId = is_object($data) ? $data->contact_id : $data['contact_id'];
        $contact = Contact::find($contactId);

        if (! $contact) {
            return;
        }

        $paymentOutcome = $contact->getContactStateValue($this->flow_id, $this->stateKey('payment_outcome'));

        if ($paymentOutcome !== null && $paymentOutcome !== '') {
            $settings = $this->getDataAsArray()['settings'] ?? [];
            $retryCount = (int) $this->getState($contact, 'payment_retries');

            if ($paymentOutcome !== 'success'
                && ! empty($settings['allow_payment_retry'])
                && $retryCount < 2) {
                $this->setState($contact, 'payment_retries', (string) ($retryCount + 1));
                $contact->clearContactState($this->flow_id, $this->stateKey('payment_outcome'));
                FlowRunLogger::log($this->flow_id, $contact->id, 'booking_payment_retry', $this->id);
                $contact->sendMessage(__('Payment did not complete. We will send the M-Pesa prompt again — enter your PIN to confirm.'), false, false, 'TEXT');
                $contact->setContactState($this->flow_id, 'current_node', $this->id);
                $this->retryPayment($contact);

                return;
            }

            $contact->clearContactState($this->flow_id, $this->stateKey('payment_outcome'));
            $contact->clearContactState($this->flow_id, 'current_node');

            $handle = $paymentOutcome === 'success' ? 'success' : 'error';
            if ($paymentOutcome !== 'success') {
                FlowRunLogger::log($this->flow_id, $contact->id, 'booking_error', $this->id, 'payment_failed');
            }

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

        if ($selection['step'] === 'more') {
            $this->incrementOffset($contact, $selection['value']);
            $contact->clearContactState($this->flow_id, 'current_node');
            $this->advanceWizard($contact, $message, $data);

            return;
        }

        $this->storeSelection($contact, $selection['step'], $selection['value']);
        $this->logStepSelection($contact, $selection['step']);
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

        $settings = $this->getDataAsArray()['settings'] ?? [];
        $intakeMode = (string) ($settings['intake_mode'] ?? 'lists');

        $this->clearWizardState($contact);
        $this->seedFixedSettings($contact, $company);
        $contact->setContactState($this->flow_id, 'current_node', $this->id);

        $useForm = $intakeMode === 'form'
            || ($intakeMode === 'auto' && app(BookingFormBridgeService::class)->hasFormIntakeSignals($contact, $this->flow_id, $settings));

        if ($useForm) {
            FlowRunLogger::log($this->flow_id, $contact->id, 'booking_form_intake_started', $this->id);

            return $this->tryFormIntake($contact, $company, $message, $data, $settings);
        }

        FlowRunLogger::log($this->flow_id, $contact->id, 'booking_wizard_started', $this->id);

        return $this->advanceWizard($contact, $message, $data);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function tryFormIntake(Contact $contact, Company $company, $message, $data, array $settings): array
    {
        $this->notifyLookingUpAvailability($contact, 'availability');
        $result = app(BookingFormBridgeService::class)->resolve($contact, $company, $this->flow_id, $settings);

        if ($result['status'] === 'error') {
            $contact->sendMessage($result['message'] ?? __('Could not complete booking from the form.'), false, false, 'TEXT');
            FlowRunLogger::log($this->flow_id, $contact->id, 'booking_error', $this->id, $result['message'] ?? 'form_bridge_error');
            $this->routeToHandle($contact, 'error', $message, $data);

            return ['success' => false];
        }

        if ($result['status'] === 'unavailable') {
            $contact->sendMessage($result['message'] ?? __('No available appointment slots.'), false, false, 'TEXT');
            FlowRunLogger::log($this->flow_id, $contact->id, 'booking_unavailable', $this->id);
            $this->routeToHandle($contact, 'unavailable', $message, $data);

            return ['success' => false];
        }

        /** @var Source $source */
        $source = $result['source'];
        $this->setState($contact, 'source_name', $source->name);
        $this->setState($contact, 'duration_minutes', (string) ($result['duration_minutes'] ?? $source->default_duration_minutes ?: 30));
        $this->setState($contact, 'booking_source', 'whatsapp_form');

        if ($result['status'] === 'ready') {
            $this->setState($contact, 'slot_id', (string) $result['slot_id']);
            if (! empty($result['date'])) {
                $this->setState($contact, 'selected_date', (string) $result['date']);
            }

            return $this->finalizeBooking($contact, $company, $source, $message, $data);
        }

        // needs_slot_pick — one interactive list for the resolved day
        if (! empty($result['date'])) {
            $this->setState($contact, 'selected_date', (string) $result['date']);
        }

        $slots = $result['slots'] ?? [];
        if ($slots === []) {
            $contact->sendMessage(__('No available appointment slots.'), false, false, 'TEXT');
            $this->routeToHandle($contact, 'unavailable', $message, $data);

            return ['success' => false];
        }

        $body = $result['message']
            ?? ($settings['slot_body'] ?? __('Choose an available time slot.'));

        return $this->sendList(
            $contact,
            $settings['slot_header'] ?? __('Select time'),
            $body,
            '',
            $settings['buttonText'] ?? __('Choose time'),
            __('Times'),
            $this->paginatedRows(
                collect($slots)->map(fn (array $slot) => [
                    'id' => $this->listItemId('slot', $slot['id']),
                    'title' => $slot['title'],
                    'description' => '',
                ])->all(),
                0,
                'slot'
            )
        );
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
            return $this->promptServiceSelection($contact, $company, $message, $data);
        }

        if (! $this->getState($contact, 'duration_minutes')) {
            $duration = $this->resolveDurationMinutes($source, $contact);

            if ($duration === null) {
                return $this->promptDurationSelection($contact, $source);
            }

            $this->setState($contact, 'duration_minutes', (string) $duration);
        }

        if (! $this->getState($contact, 'selected_date')) {
            return $this->promptDateSelection($contact, $source, $message, $data);
        }

        if (! $this->getState($contact, 'slot_id')) {
            return $this->promptSlotSelection($contact, $source, $message, $data);
        }

        return $this->finalizeBooking($contact, $company, $source, $message, $data);
    }

    private function finalizeBooking(Contact $contact, Company $company, Source $source, $message, $data): array
    {
        $bookingSource = $this->getState($contact, 'booking_source');

        $payload = [
            'phone' => $contact->phone,
            'name' => $contact->name ?: $contact->phone,
            'source' => $source->name,
            'slot_id' => $this->getState($contact, 'slot_id'),
            'duration_minutes' => (int) $this->getState($contact, 'duration_minutes'),
            'flow_id' => $this->flow_id,
            'flow_node_id' => $this->id,
            'booking_source' => $bookingSource !== '' ? $bookingSource : 'whatsapp_list',
        ];

        $settings = $this->getDataAsArray()['settings'] ?? [];
        $paymentConfig = BookingPaymentConfig::fromSource($source);

        try {
            if ($paymentConfig['payment_required']) {
                if (! app(BookingPaymentService::class)->mpesaConfigured($company)) {
                    if (! empty($settings['allow_pay_at_venue'])) {
                        $reservation = app(ReservationBookingService::class)->book($company, $payload);
                        $reservation->update(['payment_status' => BookingPaymentConfig::STATUS_NOT_REQUIRED]);
                        $this->completeSuccess($contact, $reservation, $message, $data, __('Pay at your appointment.'));

                        return ['success' => true];
                    }

                    $contact->sendMessage(__('Online payment is not available right now. Please contact us for help.'), false, false, 'TEXT');
                    FlowRunLogger::log($this->flow_id, $contact->id, 'booking_error', $this->id, 'mpesa_not_configured');
                    $this->routeToHandle($contact, 'error', $message, $data);

                    return ['success' => false];
                }

                $payload['flow_context'] = [
                    'flow_id' => $this->flow_id,
                    'flow_node_id' => $this->id,
                    'contact_id' => $contact->id,
                ];

                app(BookingPaymentService::class)->initiateAppointmentPaymentForFlow($company, $payload);
                FlowRunLogger::log($this->flow_id, $contact->id, 'booking_payment_initiated', $this->id);
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

            FlowRunLogger::log($this->flow_id, $contact->id, 'booking_error', $this->id, $exception->getMessage());
            if (! $this->getNextNodeId('error')) {
                $contact->sendMessage(__('We could not complete your booking. Please try again or contact our team.'), false, false, 'TEXT');
            }
            $this->routeToHandle($contact, 'error', $message, $data);
        }

        return ['success' => true];
    }

    public function completeSuccess(Contact $contact, Reservation $reservation, $message, $data, ?string $extraNote = null): void
    {
        $reservation->loadMissing(['source', 'appointmentStaffMember']);
        $this->storeReservationVariables($contact, $reservation);

        $settings = $this->getDataAsArray()['settings'] ?? [];

        // Prefer the service confirmation WhatsApp template when configured.
        if (! $reservation->hasTemplateConfirmation()) {
            $successMessage = $settings['success_message'] ?? __('Your appointment for :service on :date at :time is confirmed.', [
                'service' => $reservation->source?->name ?? __('your service'),
                'date' => $reservation->start_date?->timezone($reservation->source?->timezone ?: 'UTC')->format('M j, Y'),
                'time' => $reservation->start_date?->timezone($reservation->source?->timezone ?: 'UTC')->format('g:i A'),
            ]);

            if ($extraNote) {
                $successMessage .= ' '.$extraNote;
            }

            $contact->sendMessage($contact->changeVariables($successMessage, $this->flow_id), false, false, 'TEXT');
        } elseif ($extraNote) {
            $contact->sendMessage($contact->changeVariables($extraNote, $this->flow_id), false, false, 'TEXT');
        }
        FlowRunLogger::log($this->flow_id, $contact->id, 'booking_confirmed', $this->id, (string) $reservation->id);

        $company = Company::find($contact->company_id);
        if ($company) {
            app(BookingWebhookService::class)->dispatchAppointmentConfirmed($company, $reservation, $this->flow_id, $this->id, $settings);
        }

        $this->applyOnCompleteActions($contact, $settings);

        $this->clearWizardState($contact);
        $contact->clearContactState($this->flow_id, 'current_node');
        $this->routeToHandle($contact, 'success', $message, $data);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function applyOnCompleteActions(Contact $contact, array $settings): void
    {
        $onComplete = $settings['onComplete'] ?? [];
        if (! is_array($onComplete)) {
            return;
        }

        $groupId = $onComplete['groupId'] ?? null;
        if (! empty($groupId) && $groupId !== 'none') {
            $group = \Modules\Contacts\Models\Group::query()
                ->where('id', $groupId)
                ->where('company_id', $contact->company_id)
                ->first();

            if ($group && ! $contact->groups()->where('group_id', $groupId)->exists()) {
                $contact->groups()->attach($groupId);
                if (class_exists(\Modules\Journies\Support\GroupRuleBridge::class)) {
                    \Modules\Journies\Support\GroupRuleBridge::contactAddedToGroups($contact, [$groupId]);
                }
            }
        }

        $stageId = $onComplete['stageId'] ?? null;
        if (! empty($stageId) && $stageId !== 'none' && class_exists(\Modules\Journies\Models\JourneyStage::class)) {
            $stage = \Modules\Journies\Models\JourneyStage::query()
                ->where('id', $stageId)
                ->whereHas('journey', fn ($query) => $query->where('company_id', $contact->company_id))
                ->first();

            if ($stage) {
                app(\Modules\Journies\Services\JourneyContactService::class)->moveContactToStage(
                    $contact,
                    $stage,
                    'flow',
                    null,
                    true,
                    false,
                );
            }
        }
    }

    private function promptServiceSelection(Contact $contact, Company $company, $message, $data): array
    {
        $services = app(BookingCatalogService::class)->bookableServicesForCompany($company);

        if ($services === []) {
            if (! $this->getNextNodeId('error')) {
                $contact->sendMessage(__('No bookable services are available right now.'), false, false, 'TEXT');
            }
            $this->routeToHandle($contact, 'error', $message, $data);

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
            $this->paginatedRows(
                collect($services)->map(fn (array $service) => [
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
                ])->all(),
                (int) $this->getState($contact, 'service_offset'),
                'service'
            )
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

    private function promptDateSelection(Contact $contact, Source $source, $message = '', $data = null): array
    {
        $this->notifyLookingUpAvailability($contact, 'dates');

        $duration = (int) $this->getState($contact, 'duration_minutes');
        $from = now($source->timezone ?: 'UTC')->startOfDay();
        $to = $from->copy()->addDays((int) $source->max_advance_days);
        $dates = app(AvailabilityService::class)->availableDates($source, $from, $to, $duration);

        if ($dates === []) {
            if (! $this->getNextNodeId('unavailable')) {
                $contact->sendMessage(__('No available dates right now. Please try again later.'), false, false, 'TEXT');
            }
            FlowRunLogger::log($this->flow_id, $contact->id, 'booking_unavailable', $this->id);
            $this->routeToHandle($contact, 'unavailable', $message, $data);

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
            $this->paginatedRows(
                collect($dates)->map(function (string $date) use ($timezone) {
                    $label = Carbon::parse($date, $timezone)->format('D, M j, Y');

                    return [
                        'id' => $this->listItemId('date', $date),
                        'title' => $label,
                        'description' => $date,
                    ];
                })->all(),
                (int) $this->getState($contact, 'date_offset'),
                'date'
            )
        );
    }

    private function promptSlotSelection(Contact $contact, Source $source, $message = '', $data = null): array
    {
        $this->notifyLookingUpAvailability($contact, 'times');

        $duration = (int) $this->getState($contact, 'duration_minutes');
        $date = $this->getState($contact, 'selected_date');
        $slots = app(AvailabilityService::class)->slotsForDate($source, $date, $duration);

        if ($slots === []) {
            $contact->sendMessage(__('No times are available for that date. Please choose another day.'), false, false, 'TEXT');
            $this->setState($contact, 'selected_date', '');
            $this->setState($contact, 'slot_id', '');

            return $this->promptDateSelection($contact, $source, $message, $data);
        }

        $settings = $this->getDataAsArray()['settings'] ?? [];

        return $this->sendList(
            $contact,
            $settings['slot_header'] ?? __('Select time'),
            $settings['slot_body'] ?? __('Choose an available time slot.'),
            '',
            $settings['buttonText'] ?? __('Choose time'),
            __('Times'),
            $this->paginatedRows(
                collect($slots)->map(fn (array $slot) => [
                    'id' => $this->listItemId('slot', $slot['id']),
                    'title' => $slot['title'],
                    'description' => '',
                ])->all(),
                (int) $this->getState($contact, 'slot_offset'),
                'slot'
            )
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

        $constrained = InteractiveListLimits::constrainListFields(
            (string) ($contact->changeVariables($header, $this->flow_id) ?? ''),
            (string) $contact->changeVariables($body, $this->flow_id),
            (string) ($contact->changeVariables($footer, $this->flow_id) ?? ''),
            (string) $contact->changeVariables($buttonText, $this->flow_id),
            $sectionTitle,
            collect($rows)->map(fn (array $row) => [
                'id' => $row['id'],
                'title' => (string) ($row['title'] ?? ''),
                'description' => (string) ($row['description'] ?? ''),
            ])->all()
        );

        $payload = [
            'token' => $token,
            'phone' => $contact->phone,
            'message' => $constrained['body'],
            'header' => $constrained['header'],
            'footer' => $constrained['footer'],
            'action' => [
                'button' => $constrained['button'],
                'sections' => [[
                    'title' => $constrained['section_title'],
                    'rows' => $constrained['rows'],
                ]],
            ],
        ];

        $contact->setContactState($this->flow_id, 'current_node', $this->id);

        try {
            $response = Http::post(config('app.url').'/api/wpbox/sendlistmessage', $payload);

            if ($response->failed()) {
                Log::error('Book appointment list message failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
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

        return Source::queryForCompany($company->id)
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
        foreach (['source_name', 'duration_minutes', 'selected_date', 'slot_id', 'payment_outcome', 'service_offset', 'date_offset', 'slot_offset', 'payment_retries', 'booking_source'] as $suffix) {
            $contact->clearContactState($this->flow_id, $this->stateKey($suffix));
        }
    }

    /**
     * @param  array<int, array{id: string, title: string, description: string}>  $rows
     * @return array<int, array{id: string, title: string, description: string}>
     */
    private function paginatedRows(array $rows, int $offset, string $type): array
    {
        $remaining = max(0, count($rows) - $offset);
        $pageSize = $remaining > self::LIST_LIMIT
            ? self::PAGINATED_LIST_SIZE
            : self::LIST_LIMIT;
        $page = array_slice($rows, $offset, $pageSize);

        if ($offset + count($page) < count($rows)) {
            $page[] = [
                'id' => $this->listItemId('more', $type),
                'title' => __('More…'),
                'description' => '',
            ];
        }

        return $page;
    }

    private function incrementOffset(Contact $contact, string $type): void
    {
        $key = match ($type) {
            'service' => 'service_offset',
            'date' => 'date_offset',
            'slot' => 'slot_offset',
            default => null,
        };

        if ($key === null) {
            return;
        }

        $current = (int) $this->getState($contact, $key);
        $this->setState($contact, $key, (string) ($current + self::PAGINATED_LIST_SIZE));
    }

    private function logStepSelection(Contact $contact, string $step): void
    {
        $event = match ($step) {
            'service' => 'booking_service_selected',
            'duration' => 'booking_duration_selected',
            'date' => 'booking_date_selected',
            'slot' => 'booking_slot_selected',
            default => null,
        };

        if ($event) {
            FlowRunLogger::log($this->flow_id, $contact->id, $event, $this->id);
        }
    }

    private function retryPayment(Contact $contact): void
    {
        $company = Company::find($contact->company_id);
        $source = $this->resolveSelectedSource($company, $contact);

        if (! $company || ! $source) {
            return;
        }

        $payload = [
            'phone' => $contact->phone,
            'name' => $contact->name ?: $contact->phone,
            'source' => $source->name,
            'slot_id' => $this->getState($contact, 'slot_id'),
            'duration_minutes' => (int) $this->getState($contact, 'duration_minutes'),
            'flow_context' => [
                'flow_id' => $this->flow_id,
                'flow_node_id' => $this->id,
                'contact_id' => $contact->id,
            ],
        ];

        try {
            app(BookingPaymentService::class)->initiateAppointmentPaymentForFlow($company, $payload);
        } catch (\Throwable $exception) {
            Log::warning('Book appointment payment retry failed', ['error' => $exception->getMessage()]);
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
        if (! preg_match('/^ba-(service|duration|date|slot|more)-([^_]+)_id'.preg_quote($this->id, '/').'_flow'.preg_quote((string) $this->flow_id, '/').'$/', $extraData, $matches)) {
            return null;
        }

        if ($matches[1] === 'more') {
            $decoded = base64_decode(strtr($matches[2], '-_', '+/'), true);

            return ['step' => 'more', 'value' => $decoded !== false ? $decoded : $matches[2]];
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
