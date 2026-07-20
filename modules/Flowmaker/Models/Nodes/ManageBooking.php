<?php

namespace Modules\Flowmaker\Models\Nodes;

use App\Models\Company;
use App\Services\Flowmaker\FlowRunLogger;
use App\Services\WhatsApp\InteractiveListLimits;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Modules\Flowmaker\Models\Contact;
use Modules\Reminders\Models\Reservation;
use Modules\Reminders\Services\AvailabilityService;
use Modules\Reminders\Services\ReservationBookingService;

class ManageBooking extends Node
{
    private const LIST_LIMIT = 10;

    private const PAGINATED_LIST_SIZE = self::LIST_LIMIT - 1;

    public function listenForReply($message, $data): void
    {
        $contactId = is_object($data) ? $data->contact_id : $data['contact_id'];
        $contact = Contact::find($contactId);

        if (! $contact) {
            return;
        }

        $extraData = is_object($data) ? ($data->extra ?? '') : ($data['extra'] ?? '');

        if ($extraData === null || $extraData === '') {
            return;
        }

        $selection = $this->parseSelection((string) $extraData);

        if ($selection === null) {
            return;
        }

        if ($selection['action'] === 'select') {
            $this->pinReservation($contact, (int) $selection['value']);
            $reservation = $this->resolveReservation($contact);

            if (! $reservation) {
                $this->handleNotFound($contact, $message, $data);

                return;
            }

            $this->continueWithReservation($contact, $reservation, $message, $data);

            return;
        }

        if ($selection['action'] === 'confirm_cancel') {
            $reservation = $this->resolveReservation($contact);

            if (! $reservation) {
                $this->handleNotFound($contact, $message, $data);

                return;
            }

            $this->performCancel($contact, $reservation, $message, $data);

            return;
        }

        if ($selection['action'] === 'abort_cancel') {
            $contact->sendMessage(__('Okay — your booking was kept.'), false, false, 'TEXT');
            $contact->clearContactState($this->flow_id, 'current_node');
            $this->clearManageState($contact);

            return;
        }

        $reservation = $this->resolveReservation($contact);

        if (! $reservation) {
            $this->handleNotFound($contact, $message, $data);

            return;
        }

        if ($selection['action'] === 'cancel') {
            $this->pinReservation($contact, (int) $reservation->id);
            $this->sendCancelConfirmation($contact, $reservation);

            return;
        }

        if ($selection['action'] === 'reschedule') {
            $this->pinReservation($contact, (int) $reservation->id);
            $this->setState($contact, 'date_offset', '0');
            $this->setState($contact, 'slot_offset', '0');
            $this->setState($contact, 'reschedule_date', '');
            $this->promptRescheduleDates($contact, $reservation, $message, $data);

            return;
        }

        if ($selection['action'] === 'more_dates') {
            $offset = (int) $this->getState($contact, 'date_offset') + self::PAGINATED_LIST_SIZE;
            $this->setState($contact, 'date_offset', (string) $offset);
            $this->promptRescheduleDates($contact, $reservation, $message, $data);

            return;
        }

        if ($selection['action'] === 'more_slots') {
            $offset = (int) $this->getState($contact, 'slot_offset') + self::PAGINATED_LIST_SIZE;
            $this->setState($contact, 'slot_offset', (string) $offset);
            $this->promptRescheduleSlots($contact, $reservation, $message, $data);

            return;
        }

        if ($selection['action'] === 'reschedule_date') {
            $this->setState($contact, 'reschedule_date', $selection['value']);
            $this->pinReservation($contact, (int) $reservation->id);
            $this->setState($contact, 'slot_offset', '0');
            $this->promptRescheduleSlots($contact, $reservation, $message, $data);

            return;
        }

        if ($selection['action'] === 'reschedule_slot') {
            $this->finalizeReschedule($contact, $reservation, $selection['value'], $message, $data);
        }
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

        $this->clearPaginationState($contact);

        $upcoming = $this->upcomingReservations($contact);

        if ($upcoming->isEmpty()) {
            $this->handleNotFound($contact, $message, $data);

            return ['success' => false];
        }

        if ($upcoming->count() > 1 && $this->getPinnedReservationId($contact) === null) {
            $this->waitForReply($contact);
            $this->sendReservationPicker($contact, $upcoming);

            return ['success' => true];
        }

        $reservation = $this->resolveReservation($contact, $upcoming);

        if (! $reservation) {
            $this->handleNotFound($contact, $message, $data);

            return ['success' => false];
        }

        $this->pinReservation($contact, (int) $reservation->id);
        $this->continueWithReservation($contact, $reservation, $message, $data);

        return ['success' => true];
    }

    private function continueWithReservation(Contact $contact, Reservation $reservation, $message, $data): void
    {
        $settings = $this->getDataAsArray()['settings'] ?? [];
        $action = (string) ($settings['default_action'] ?? 'menu');

        if ($action === 'cancel') {
            $this->sendCancelConfirmation($contact, $reservation);

            return;
        }

        if ($action === 'reschedule') {
            $this->setState($contact, 'date_offset', '0');
            $this->setState($contact, 'slot_offset', '0');
            $this->setState($contact, 'reschedule_date', '');
            $this->promptRescheduleDates($contact, $reservation, $message, $data);

            return;
        }

        $this->sendActionMenu($contact, $reservation);
    }

    /**
     * @param  Collection<int, Reservation>|null  $upcoming
     */
    private function resolveReservation(Contact $contact, ?Collection $upcoming = null): ?Reservation
    {
        $pinnedId = $this->getPinnedReservationId($contact);

        if ($pinnedId !== null) {
            $pinned = Reservation::withoutGlobalScopes()
                ->with('source')
                ->where('company_id', $contact->company_id)
                ->where('contact_id', $contact->id)
                ->where('id', $pinnedId)
                ->whereNull('cancelled_at')
                ->where('status', 1)
                ->where('start_date', '>=', now())
                ->first();

            if ($pinned) {
                return $pinned;
            }
        }

        $settings = $this->getDataAsArray()['settings'] ?? [];
        $referenceVar = trim((string) ($settings['reference_variable'] ?? 'booking_reference'));
        $reference = trim((string) $contact->getContactStateValue($this->flow_id, $referenceVar));

        if ($reference !== '' && ctype_digit($reference)) {
            $byId = Reservation::withoutGlobalScopes()
                ->with('source')
                ->where('company_id', $contact->company_id)
                ->where('contact_id', $contact->id)
                ->where('id', (int) $reference)
                ->whereNull('cancelled_at')
                ->where('status', 1)
                ->where('start_date', '>=', now())
                ->first();

            if ($byId) {
                return $byId;
            }
        }

        $upcoming ??= $this->upcomingReservations($contact);

        return $upcoming->first();
    }

    /**
     * @return Collection<int, Reservation>
     */
    private function upcomingReservations(Contact $contact): Collection
    {
        return Reservation::withoutGlobalScopes()
            ->with('source')
            ->where('company_id', $contact->company_id)
            ->where('contact_id', $contact->id)
            ->whereNull('cancelled_at')
            ->where('status', 1)
            ->where('start_date', '>=', now())
            ->orderBy('start_date')
            ->get();
    }

    /**
     * @param  Collection<int, Reservation>  $reservations
     */
    private function sendReservationPicker(Contact $contact, Collection $reservations): void
    {
        $rows = $reservations
            ->take(self::LIST_LIMIT)
            ->map(function (Reservation $reservation) {
                $timezone = $reservation->source?->timezone ?: 'UTC';
                $when = $reservation->start_date?->timezone($timezone)->format('M j, g:i A') ?? '';

                return [
                    'id' => $this->listItemId('select', (string) $reservation->id),
                    'title' => mb_substr((string) ($reservation->source?->name ?? __('Appointment')), 0, 24),
                    'description' => $when,
                ];
            })
            ->values()
            ->all();

        $this->sendList(
            $contact,
            __('Your bookings'),
            __('Which appointment would you like to manage?'),
            '',
            __('Choose'),
            __('Upcoming'),
            $rows
        );
    }

    private function sendActionMenu(Contact $contact, Reservation $reservation): void
    {
        $settings = $this->getDataAsArray()['settings'] ?? [];
        $timezone = $reservation->source?->timezone ?: 'UTC';
        $when = $reservation->start_date?->timezone($timezone)->format('M j, Y g:i A') ?? '';

        $rows = [
            ['id' => $this->listItemId('action', 'cancel'), 'title' => __('Cancel booking'), 'description' => $when],
        ];

        if (($settings['allow_reschedule'] ?? true) !== false) {
            $rows[] = [
                'id' => $this->listItemId('action', 'reschedule'),
                'title' => __('Reschedule'),
                'description' => __('Pick a new date and time'),
            ];
        }

        $this->waitForReply($contact);
        $this->sendList(
            $contact,
            (string) ($settings['header'] ?? __('Manage your booking')),
            (string) ($settings['body'] ?? __('What would you like to do?')),
            '',
            (string) ($settings['buttonText'] ?? __('Choose')),
            __('Actions'),
            $rows
        );
    }

    private function sendCancelConfirmation(Contact $contact, Reservation $reservation): void
    {
        $timezone = $reservation->source?->timezone ?: 'UTC';
        $when = $reservation->start_date?->timezone($timezone)->format('M j, Y g:i A') ?? '';

        $this->waitForReply($contact);
        $this->sendList(
            $contact,
            __('Confirm cancellation'),
            __('Cancel your appointment on :when?', ['when' => $when]),
            '',
            __('Confirm'),
            __('Confirm'),
            [
                [
                    'id' => $this->listItemId('action', 'confirm_cancel'),
                    'title' => __('Yes, cancel'),
                    'description' => __('This cannot be undone'),
                ],
                [
                    'id' => $this->listItemId('action', 'abort_cancel'),
                    'title' => __('Keep booking'),
                    'description' => __('Do not cancel'),
                ],
            ]
        );
    }

    private function performCancel(Contact $contact, Reservation $reservation, $message, $data): void
    {
        app(ReservationBookingService::class)->cancel($reservation, 'whatsapp_manage_booking');
        FlowRunLogger::log($this->flow_id, $contact->id, 'booking_cancelled', $this->id, (string) $reservation->id);
        $contact->sendMessage(__('Your booking has been cancelled.'), false, false, 'TEXT');
        $contact->clearContactState($this->flow_id, 'current_node');
        $this->clearManageState($contact);
        $this->routeToHandle($contact, 'cancelled', $message, $data);
    }

    private function promptRescheduleSlots(Contact $contact, Reservation $reservation, $message, $data): void
    {
        $source = $reservation->source;

        if (! $source) {
            $this->routeToHandle($contact, 'error', $message, $data);

            return;
        }

        $date = $this->getState($contact, 'reschedule_date');
        $duration = (int) ($reservation->duration_minutes ?: $source->default_duration_minutes ?: 30);
        $slots = app(AvailabilityService::class)->slotsForDate($source, $date, $duration);

        if ($slots === []) {
            $contact->sendMessage(__('No times available on that date. Please pick another day.'), false, false, 'TEXT');
            $this->setState($contact, 'date_offset', '0');
            $this->promptRescheduleDates($contact, $reservation, $message, $data);

            return;
        }

        $offset = (int) $this->getState($contact, 'slot_offset');
        $remaining = max(0, count($slots) - $offset);
        $pageSize = $remaining > self::LIST_LIMIT
            ? self::PAGINATED_LIST_SIZE
            : self::LIST_LIMIT;
        $page = array_slice($slots, $offset, $pageSize);
        $rows = collect($page)->map(fn (array $slot) => [
            'id' => $this->listItemId('reschedule_slot', $slot['id']),
            'title' => $slot['title'],
            'description' => '',
        ])->all();

        if ($offset + count($page) < count($slots)) {
            $rows[] = [
                'id' => $this->listItemId('more', 'slots'),
                'title' => __('More times…'),
                'description' => '',
            ];
        }

        $this->waitForReply($contact);
        $this->sendList(
            $contact,
            __('Select time'),
            __('Choose a new time for your appointment.'),
            '',
            __('Choose time'),
            __('Times'),
            $rows
        );
    }

    private function promptRescheduleDates(Contact $contact, Reservation $reservation, $message, $data): void
    {
        $source = $reservation->source;

        if (! $source) {
            $this->routeToHandle($contact, 'error', $message, $data);

            return;
        }

        $duration = (int) ($reservation->duration_minutes ?: $source->default_duration_minutes ?: 30);
        $from = now($source->timezone ?: 'UTC')->startOfDay();
        $to = $from->copy()->addDays((int) $source->max_advance_days);
        $dates = app(AvailabilityService::class)->availableDates($source, $from, $to, $duration);

        if ($dates === []) {
            if (! $this->getNextNodeId('error')) {
                $contact->sendMessage(__('No alternative appointment dates are available right now.'), false, false, 'TEXT');
            }
            $contact->clearContactState($this->flow_id, 'current_node');
            $this->clearManageState($contact);
            $this->routeToHandle($contact, 'error', $message, $data);

            return;
        }

        $offset = (int) $this->getState($contact, 'date_offset');
        $remaining = max(0, count($dates) - $offset);
        $pageSize = $remaining > self::LIST_LIMIT
            ? self::PAGINATED_LIST_SIZE
            : self::LIST_LIMIT;
        $page = array_slice($dates, $offset, $pageSize);
        $timezone = $source->timezone ?: 'UTC';

        $rows = collect($page)->map(function (string $date) use ($timezone) {
            return [
                'id' => $this->listItemId('reschedule_date', $date),
                'title' => Carbon::parse($date, $timezone)->format('D, M j, Y'),
                'description' => $date,
            ];
        })->all();

        if ($offset + count($page) < count($dates)) {
            $rows[] = [
                'id' => $this->listItemId('more', 'dates'),
                'title' => __('More dates…'),
                'description' => '',
            ];
        }

        $this->waitForReply($contact);
        $this->sendList(
            $contact,
            __('Select date'),
            __('Pick a new day for your appointment.'),
            '',
            __('Choose date'),
            __('Dates'),
            $rows
        );
    }

    private function finalizeReschedule(Contact $contact, Reservation $reservation, string $slotId, $message, $data): void
    {
        try {
            $updated = app(ReservationBookingService::class)->reschedule($reservation, [
                'slot_id' => $slotId,
                'duration_minutes' => $reservation->duration_minutes,
            ]);

            FlowRunLogger::log($this->flow_id, $contact->id, 'booking_rescheduled', $this->id, (string) $updated->id);

            $timezone = $updated->source?->timezone ?: 'UTC';
            $when = $updated->start_date?->timezone($timezone)->format('M j, Y g:i A') ?? '';

            $contact->sendMessage(__('Your booking has been rescheduled to :when.', ['when' => $when]), false, false, 'TEXT');
            $contact->clearContactState($this->flow_id, 'current_node');
            $this->clearManageState($contact);
            $this->routeToHandle($contact, 'rescheduled', $message, $data);
        } catch (\Throwable $exception) {
            Log::warning('Manage booking reschedule failed', ['error' => $exception->getMessage()]);
            if (! $this->getNextNodeId('error')) {
                $contact->sendMessage(__('We could not reschedule your booking. Please try again or contact our team.'), false, false, 'TEXT');
            }
            $this->routeToHandle($contact, 'error', $message, $data);
        }
    }

    private function handleNotFound(Contact $contact, $message, $data): void
    {
        $contact->sendMessage(__('No upcoming booking was found for this contact.'), false, false, 'TEXT');
        $contact->clearContactState($this->flow_id, 'current_node');
        $this->clearManageState($contact);
        $this->routeToHandle($contact, 'not_found', $message, $data);
    }

    /**
     * @return array{action: string, value: string}|null
     */
    private function parseSelection(string $extraData): ?array
    {
        if (preg_match('/^mb-action-([^_]+)_id'.preg_quote($this->id, '/').'_flow'.preg_quote((string) $this->flow_id, '/').'$/', $extraData, $matches)) {
            $decoded = $this->decodeValue($matches[1]);

            return ['action' => $decoded, 'value' => $decoded];
        }

        if (preg_match('/^mb-select-([^_]+)_id'.preg_quote($this->id, '/').'_flow'.preg_quote((string) $this->flow_id, '/').'$/', $extraData, $matches)) {
            return ['action' => 'select', 'value' => $this->decodeValue($matches[1])];
        }

        if (preg_match('/^mb-reschedule_date-([^_]+)_id'.preg_quote($this->id, '/').'_flow'.preg_quote((string) $this->flow_id, '/').'$/', $extraData, $matches)) {
            return ['action' => 'reschedule_date', 'value' => $this->decodeValue($matches[1])];
        }

        if (preg_match('/^mb-reschedule_slot-([^_]+)_id'.preg_quote($this->id, '/').'_flow'.preg_quote((string) $this->flow_id, '/').'$/', $extraData, $matches)) {
            return ['action' => 'reschedule_slot', 'value' => $this->decodeValue($matches[1])];
        }

        if (preg_match('/^mb-more-([^_]+)_id'.preg_quote($this->id, '/').'_flow'.preg_quote((string) $this->flow_id, '/').'$/', $extraData, $matches)) {
            return ['action' => 'more_'.$this->decodeValue($matches[1]), 'value' => ''];
        }

        return null;
    }

    private function decodeValue(string $encoded): string
    {
        $decoded = base64_decode(strtr($encoded, '-_', '+/'), true);

        return $decoded === false ? $encoded : $decoded;
    }

    private function listItemId(string $step, string $value): string
    {
        $encoded = rtrim(strtr(base64_encode($value), '+/', '-_'), '=');

        return 'mb-'.$step.'-'.$encoded.'_id'.$this->id.'_flow'.$this->flow_id;
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
    ): void {
        $company = Company::find($contact->company_id);
        $token = $company?->getConfig('plain_token', '') ?? '';

        $constrained = InteractiveListLimits::constrainListFields(
            (string) ($contact->changeVariables($header, $this->flow_id) ?? ''),
            (string) $contact->changeVariables($body, $this->flow_id),
            (string) ($contact->changeVariables($footer, $this->flow_id) ?? ''),
            (string) $contact->changeVariables($buttonText, $this->flow_id),
            $sectionTitle,
            $rows
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

        try {
            $response = \Illuminate\Support\Facades\Http::post(config('app.url').'/api/wpbox/sendlistmessage', $payload);

            if ($response->failed()) {
                Log::error('Manage booking list message failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $exception) {
            Log::error('Manage booking list message failed', ['error' => $exception->getMessage()]);
        }
    }

    private function routeToHandle(Contact $contact, string $handle, $message, $data): void
    {
        $next = $this->getNextNodeId($handle);

        if ($next) {
            $next->process($message, $data);
        }
    }

    private function waitForReply(Contact $contact): void
    {
        $contact->setContactState($this->flow_id, 'current_node', $this->id);
    }

    private function stateKey(string $suffix): string
    {
        return "mb_{$this->id}_{$suffix}";
    }

    private function getState(Contact $contact, string $suffix): string
    {
        return (string) $contact->getContactStateValue($this->flow_id, $this->stateKey($suffix));
    }

    private function setState(Contact $contact, string $suffix, string $value): void
    {
        $contact->setContactState($this->flow_id, $this->stateKey($suffix), $value);
    }

    private function pinReservation(Contact $contact, int $reservationId): void
    {
        $this->setState($contact, 'selected_reservation_id', (string) $reservationId);
        $this->setState($contact, 'reschedule_reservation_id', (string) $reservationId);
    }

    private function getPinnedReservationId(Contact $contact): ?int
    {
        $pinned = $this->getState($contact, 'selected_reservation_id');

        if ($pinned === '' || ! ctype_digit($pinned)) {
            $pinned = $this->getState($contact, 'reschedule_reservation_id');
        }

        return ($pinned !== '' && ctype_digit($pinned)) ? (int) $pinned : null;
    }

    private function clearPaginationState(Contact $contact): void
    {
        $this->setState($contact, 'date_offset', '0');
        $this->setState($contact, 'slot_offset', '0');
        $this->setState($contact, 'reschedule_date', '');
    }

    private function clearManageState(Contact $contact): void
    {
        foreach ([
            'selected_reservation_id',
            'reschedule_reservation_id',
            'reschedule_date',
            'date_offset',
            'slot_offset',
        ] as $suffix) {
            $contact->clearContactState($this->flow_id, $this->stateKey($suffix));
        }
    }

    protected function getNextNodeId($handleId = null)
    {
        foreach ($this->outgoingEdges as $edge) {
            if ($handleId === null || $edge->getSourceHandle() === (string) $handleId) {
                return $edge->getTarget();
            }
        }

        return null;
    }
}
