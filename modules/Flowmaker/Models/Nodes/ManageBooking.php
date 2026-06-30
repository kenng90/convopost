<?php

namespace Modules\Flowmaker\Models\Nodes;

use App\Models\Company;
use App\Services\Flowmaker\FlowRunLogger;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Modules\Flowmaker\Models\Contact;
use Modules\Reminders\Models\Reservation;
use Modules\Reminders\Services\AvailabilityService;
use Modules\Reminders\Services\ReservationBookingService;

class ManageBooking extends Node
{
    private const LIST_LIMIT = 10;

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

        $reservation = $this->resolveReservation($contact);

        if (! $reservation) {
            $contact->sendMessage(__('No upcoming booking was found for this contact.'), false, false, 'TEXT');
            $contact->clearContactState($this->flow_id, 'current_node');
            $this->routeToHandle($contact, 'not_found', $message, $data);

            return;
        }

        if ($selection['action'] === 'cancel') {
            app(ReservationBookingService::class)->cancel($reservation);
            FlowRunLogger::log($this->flow_id, $contact->id, 'booking_cancelled', $this->id, (string) $reservation->id);
            $contact->sendMessage(__('Your booking has been cancelled.'), false, false, 'TEXT');
            $contact->clearContactState($this->flow_id, 'current_node');
            $this->routeToHandle($contact, 'cancelled', $message, $data);

            return;
        }

        if ($selection['action'] === 'reschedule') {
            $this->setState($contact, 'reschedule_reservation_id', (string) $reservation->id);
            $contact->clearContactState($this->flow_id, 'current_node');
            $this->promptRescheduleDates($contact, $reservation, $message, $data);

            return;
        }

        if ($selection['action'] === 'more_dates') {
            $offset = (int) $this->getState($contact, 'date_offset') + self::LIST_LIMIT;
            $this->setState($contact, 'date_offset', (string) $offset);
            $contact->clearContactState($this->flow_id, 'current_node');
            $this->promptRescheduleDates($contact, $reservation, $message, $data);

            return;
        }

        if ($selection['action'] === 'more_slots') {
            $offset = (int) $this->getState($contact, 'slot_offset') + self::LIST_LIMIT;
            $this->setState($contact, 'slot_offset', (string) $offset);
            $contact->clearContactState($this->flow_id, 'current_node');
            $this->promptRescheduleSlots($contact, $reservation, $message, $data);

            return;
        }

        if ($selection['action'] === 'reschedule_date') {
            $this->setState($contact, 'reschedule_date', $selection['value']);
            $this->setState($contact, 'reschedule_reservation_id', (string) $reservation->id);
            $contact->clearContactState($this->flow_id, 'current_node');
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

        $reservation = $this->resolveReservation($contact);

        if (! $reservation) {
            $contact->sendMessage(__('No upcoming booking was found.'), false, false, 'TEXT');
            $this->routeToHandle($contact, 'not_found', $message, $data);

            return ['success' => false];
        }

        $settings = $this->getDataAsArray()['settings'] ?? [];
        $action = (string) ($settings['default_action'] ?? 'menu');

        if ($action === 'cancel') {
            app(ReservationBookingService::class)->cancel($reservation);
            FlowRunLogger::log($this->flow_id, $contact->id, 'booking_cancelled', $this->id, (string) $reservation->id);
            $contact->sendMessage(__('Your booking has been cancelled.'), false, false, 'TEXT');
            $this->routeToHandle($contact, 'cancelled', $message, $data);

            return ['success' => true];
        }

        $contact->setContactState($this->flow_id, 'current_node', $this->id);
        $this->sendActionMenu($contact, $reservation);

        return ['success' => true];
    }

    private function resolveReservation(Contact $contact): ?Reservation
    {
        $settings = $this->getDataAsArray()['settings'] ?? [];
        $referenceVar = trim((string) ($settings['reference_variable'] ?? 'booking_reference'));

        $reference = trim((string) $contact->getContactStateValue($this->flow_id, $referenceVar));

        if ($reference !== '' && ctype_digit($reference)) {
            $byId = Reservation::withoutGlobalScopes()
                ->where('company_id', $contact->company_id)
                ->where('contact_id', $contact->id)
                ->where('id', (int) $reference)
                ->whereNull('cancelled_at')
                ->where('status', 1)
                ->first();

            if ($byId) {
                return $byId->loadMissing('source');
            }
        }

        return Reservation::withoutGlobalScopes()
            ->with('source')
            ->where('company_id', $contact->company_id)
            ->where('contact_id', $contact->id)
            ->whereNull('cancelled_at')
            ->where('status', 1)
            ->where('start_date', '>=', now())
            ->orderBy('start_date')
            ->first();
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
            $this->promptRescheduleDates($contact, $reservation, $message, $data);

            return;
        }

        $offset = (int) $this->getState($contact, 'slot_offset');
        $page = array_slice($slots, $offset, self::LIST_LIMIT);
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

        $contact->setContactState($this->flow_id, 'current_node', $this->id);
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

        $offset = (int) $this->getState($contact, 'date_offset');
        $page = array_slice($dates, $offset, self::LIST_LIMIT);
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

        $contact->setContactState($this->flow_id, 'current_node', $this->id);
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
            $this->clearRescheduleState($contact);
            $this->routeToHandle($contact, 'rescheduled', $message, $data);
        } catch (\Throwable $exception) {
            Log::warning('Manage booking reschedule failed', ['error' => $exception->getMessage()]);
            $contact->sendMessage($exception->getMessage(), false, false, 'TEXT');
            $this->routeToHandle($contact, 'error', $message, $data);
        }
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
                    'rows' => $rows,
                ]],
            ],
        ];

        try {
            \Illuminate\Support\Facades\Http::post(config('app.url').'/api/wpbox/sendlistmessage', $payload);
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

    private function clearRescheduleState(Contact $contact): void
    {
        foreach (['reschedule_date', 'reschedule_reservation_id', 'date_offset', 'slot_offset'] as $suffix) {
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
