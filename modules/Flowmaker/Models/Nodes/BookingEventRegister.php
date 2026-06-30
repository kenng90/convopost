<?php

namespace Modules\Flowmaker\Models\Nodes;

use App\Models\Company;
use App\Services\Flowmaker\BookingWebhookService;
use App\Services\Flowmaker\FlowRunLogger;
use Modules\Flowmaker\Jobs\ResumeFlowFromMpesa;
use Modules\Flowmaker\Models\Contact;
use Modules\Reminders\Models\EventRegistration;
use Modules\Reminders\Services\BookingPaymentService;
use Modules\Reminders\Services\EventCatalogService;
use Modules\Reminders\Services\EventRegistrationService;
use Modules\Reminders\Support\BookingPaymentConfig;

class BookingEventRegister extends Node
{
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

        if (! app(EventCatalogService::class)->eventsEnabled($company)) {
            $contact->sendMessage(__('Events booking is not available.'), false, false, 'TEXT');
            $this->routeToHandle($contact, 'error', $message, $data);

            return ['success' => false];
        }

        $occurrenceId = $this->resolveOccurrenceId($contact);

        if (! $occurrenceId) {
            $contact->sendMessage(__('Please select an event first.'), false, false, 'TEXT');
            $this->routeToHandle($contact, 'error', $message, $data);

            return ['success' => false];
        }

        $settings = $this->getDataAsArray()['settings'] ?? [];
        $partySize = max(1, (int) ($settings['party_size'] ?? 1));

        $payload = [
            'occurrence_id' => $occurrenceId,
            'phone' => $contact->phone,
            'name' => $contact->name ?: $contact->phone,
            'party_size' => $partySize,
            'flow_id' => $this->flow_id,
            'flow_node_id' => $this->id,
        ];

        $occurrence = app(EventCatalogService::class)->findRegisterableOccurrence($company, $occurrenceId);

        if (! $occurrence) {
            $contact->sendMessage(__('This event session is no longer open for registration.'), false, false, 'TEXT');
            $this->routeToHandle($contact, 'error', $message, $data);

            return ['success' => false];
        }

        $paymentConfig = BookingPaymentConfig::fromEvent($occurrence->event);

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

                app(BookingPaymentService::class)->initiateEventPaymentForFlow($company, $payload);
                $contact->sendMessage(__('Check your phone and enter your M-Pesa PIN to complete payment and confirm your registration.'), false, false, 'TEXT');
                $contact->setContactState($this->flow_id, 'current_node', $this->id);

                return ['success' => true];
            }

            $registration = app(EventRegistrationService::class)->register($company, $payload);
            $registration->update(['payment_status' => BookingPaymentConfig::STATUS_NOT_REQUIRED]);
            $this->completeSuccess($contact, $registration, $message, $data);
        } catch (\Throwable $exception) {
            Log::warning('Flow event registration failed', [
                'contact_id' => $contact->id,
                'occurrence_id' => $occurrenceId,
                'error' => $exception->getMessage(),
            ]);

            $contact->sendMessage($exception->getMessage(), false, false, 'TEXT');
            $this->routeToHandle($contact, 'error', $message, $data);
        }

        return ['success' => true];
    }

    public function completeSuccess(Contact $contact, EventRegistration $registration, $message, $data): void
    {
        $registration->loadMissing(['event', 'occurrence']);
        $this->storeRegistrationVariables($contact, $registration);

        $settings = $this->getDataAsArray()['settings'] ?? [];
        $timezone = $registration->event?->timezone ?: 'UTC';
        $startsAt = $registration->occurrence?->starts_at?->timezone($timezone);

        $successMessage = $settings['success_message'] ?? __('You are registered for :event on :date at :time.', [
            'event' => $registration->event?->title ?? __('the event'),
            'date' => $startsAt?->format('M j, Y'),
            'time' => $startsAt?->format('g:i A'),
        ]);

        $contact->sendMessage($contact->changeVariables($successMessage, $this->flow_id), false, false, 'TEXT');
        FlowRunLogger::log($this->flow_id, $contact->id, 'booking_event_registered', $this->id, (string) $registration->id);

        $company = Company::find($contact->company_id);
        if ($company) {
            app(BookingWebhookService::class)->dispatchEventRegistrationConfirmed($company, $registration, $this->flow_id, $this->id, $settings);
        }

        $contact->clearContactState($this->flow_id, 'selected_occurrence_id');
        $contact->clearContactState($this->flow_id, 'current_node');
        $this->routeToHandle($contact, 'success', $message, $data);
    }

    public static function notifyPaymentOutcome(int $flowId, int $contactId, string $nodeId, string $outcome): void
    {
        $contact = Contact::find($contactId);

        if (! $contact) {
            return;
        }

        $contact->setContactState($flowId, "ber_{$nodeId}_payment_outcome", $outcome);
        $contact->setContactState($flowId, 'current_node', $nodeId);
        ResumeFlowFromMpesa::dispatch($flowId, $contactId)->onQueue('flows');
    }

    private function resolveOccurrenceId(Contact $contact): int
    {
        $fromState = (int) $contact->getContactStateValue($this->flow_id, 'selected_occurrence_id');

        if ($fromState > 0) {
            return $fromState;
        }

        $settings = $this->getDataAsArray()['settings'] ?? [];

        return (int) ($settings['occurrence_id'] ?? 0);
    }

    private function storeRegistrationVariables(Contact $contact, EventRegistration $registration): void
    {
        $timezone = $registration->event?->timezone ?: 'UTC';
        $startsAt = $registration->occurrence?->starts_at?->timezone($timezone);

        $contact->setContactState($this->flow_id, 'booking_event_title', $registration->event?->title ?? '');
        $contact->setContactState($this->flow_id, 'booking_event_date', $startsAt?->format('M j, Y') ?? '');
        $contact->setContactState($this->flow_id, 'booking_event_time', $startsAt?->format('g:i A') ?? '');
        $contact->setContactState($this->flow_id, 'booking_event_reference', (string) $registration->id);
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
        return "ber_{$this->id}_{$suffix}";
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
