<?php

namespace Modules\Flowmaker\Models\Nodes;

use App\Services\Flowmaker\FlowRunLogger;
use Modules\Flowmaker\Models\Contact;
use Modules\Reminders\Models\EventRegistration;
use Modules\Reminders\Services\EventRegistrationService;

/**
 * Cancel an event registration by reference variable (mirrors ManageBooking cancel path).
 */
class ManageEventRegistration extends Node
{
    public function process($message, $data): array
    {
        if ($this->isStartNode) {
            return ['success' => true];
        }

        $contactId = is_object($data) ? $data->contact_id : $data['contact_id'];
        $contact = Contact::find($contactId);

        if (! $contact) {
            return ['success' => false];
        }

        $registration = $this->resolveRegistration($contact);

        if (! $registration) {
            $contact->sendMessage(__('No upcoming event registration was found.'), false, false, 'TEXT');
            $this->routeToHandle($contact, 'not_found', $message, $data);

            return ['success' => false];
        }

        $settings = $this->getDataAsArray()['settings'] ?? [];
        $action = (string) ($settings['default_action'] ?? 'cancel');

        if ($action !== 'cancel') {
            $contact->sendMessage(__('Unsupported manage action.'), false, false, 'TEXT');
            $this->routeToHandle($contact, 'error', $message, $data);

            return ['success' => false];
        }

        try {
            app(EventRegistrationService::class)->cancel($registration);
            FlowRunLogger::log($this->flow_id, $contact->id, 'booking_event_cancelled', $this->id, (string) $registration->id);
            $contact->sendMessage(
                $settings['success_message'] ?? __('Your event registration has been cancelled.'),
                false,
                false,
                'TEXT'
            );
            $this->routeToHandle($contact, 'cancelled', $message, $data);
        } catch (\Throwable $exception) {
            $contact->sendMessage($exception->getMessage(), false, false, 'TEXT');
            $this->routeToHandle($contact, 'error', $message, $data);

            return ['success' => false];
        }

        return ['success' => true];
    }

    private function resolveRegistration(Contact $contact): ?EventRegistration
    {
        $settings = $this->getDataAsArray()['settings'] ?? [];
        $referenceVar = trim((string) ($settings['reference_variable'] ?? 'booking_event_reference'));

        $reference = trim((string) $contact->getContactStateValue($this->flow_id, $referenceVar));

        if ($reference !== '' && ctype_digit($reference)) {
            $byId = EventRegistration::withoutGlobalScopes()
                ->where('company_id', $contact->company_id)
                ->where('id', (int) $reference)
                ->first();

            if ($byId) {
                return $byId;
            }
        }

        return EventRegistration::withoutGlobalScopes()
            ->where('company_id', $contact->company_id)
            ->where(function ($query) use ($contact) {
                $query->where('contact_id', $contact->id)
                    ->orWhere('phone', $contact->phone);
            })
            ->whereIn('status', [
                EventRegistration::STATUS_CONFIRMED,
                EventRegistration::STATUS_WAITLISTED,
            ])
            ->whereNull('cancelled_at')
            ->orderByDesc('id')
            ->first();
    }

    private function routeToHandle(Contact $contact, string $handle, $message, $data): void
    {
        $next = $this->getNextNodeId($handle) ?: $this->getNextNodeId('error');

        if ($next) {
            $next->process($message, $data);
        }
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
