<?php

namespace Modules\Flowmaker\Models\Nodes;

use App\Models\Company;
use Illuminate\Support\Facades\Log;
use Modules\Flowmaker\Models\Contact;
use Modules\Reminders\Services\EventCatalogService;
use Modules\Reminders\Services\EventRegistrationService;

class BookingEventRegister extends Node
{
    public function process($message, $data)
    {
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

            return ['success' => false];
        }

        $occurrenceId = (int) $contact->getContactStateValue($this->flow_id, 'selected_occurrence_id');

        if (! $occurrenceId) {
            $settings = $this->getDataAsArray()['settings'] ?? [];
            $occurrenceId = (int) ($settings['occurrence_id'] ?? 0);
        }

        if (! $occurrenceId) {
            $contact->sendMessage(__('Please select an event first.'), false, false, 'TEXT');
            $next = $this->getNextNodeId('error');

            if ($next) {
                $next->process($message, $data);
            }

            return ['success' => false];
        }

        try {
            $registration = app(EventRegistrationService::class)->register($company, [
                'occurrence_id' => $occurrenceId,
                'phone' => $contact->phone,
                'name' => $contact->name ?: $contact->phone,
                'party_size' => 1,
            ]);

            $settings = $this->getDataAsArray()['settings'] ?? [];
            $successMessage = $settings['success_message'] ?? __('You are registered for :event.', [
                'event' => $registration->event?->title ?? __('the event'),
            ]);

            $contact->sendMessage($contact->changeVariables($successMessage, $this->flow_id), false, false, 'TEXT');
            $contact->clearContactState($this->flow_id, 'selected_occurrence_id');

            $next = $this->getNextNodeId('success') ?: $this->getNextNodeId();

            if ($next) {
                $next->process($message, $data);
            }
        } catch (\Throwable $exception) {
            Log::warning('Flow event registration failed', [
                'contact_id' => $contact->id,
                'occurrence_id' => $occurrenceId,
                'error' => $exception->getMessage(),
            ]);

            $contact->sendMessage($exception->getMessage(), false, false, 'TEXT');
            $next = $this->getNextNodeId('error');

            if ($next) {
                $next->process($message, $data);
            }
        }

        return ['success' => true];
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
