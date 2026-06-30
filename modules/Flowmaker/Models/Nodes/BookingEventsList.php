<?php

namespace Modules\Flowmaker\Models\Nodes;

use App\Models\Company;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Flowmaker\Models\Contact;
use Modules\Reminders\Services\EventCatalogService;

class BookingEventsList extends Node
{
    public function listenForReply($message, $data)
    {
        $extraData = $data->extra ?? '';
        $elseNode = $this->getNextNodeId('else');
        $node = null;
        $itemMatched = false;

        if ($extraData !== null && $extraData !== '') {
            if (preg_match('/^occurrence-(\d+)_id'.$this->id.'_flow'.$this->flow_id.'$/', $extraData, $matches)) {
                $itemMatched = true;
                $contactId = is_object($data) ? $data->contact_id : $data['contact_id'];
                $contact = Contact::find($contactId);

                if ($contact) {
                    $contact->setContactState($this->flow_id, 'selected_occurrence_id', $matches[1]);
                }

                $node = $this->getNextNodeId('selected') ?: $elseNode;
            }
        }

        if ($extraData === null || $extraData === '') {
            return;
        }

        $contactId = is_object($data) ? $data->contact_id : $data['contact_id'];
        $contact = Contact::find($contactId);

        if ($contact) {
            $contact->clearContactState($this->flow_id, 'current_node');
        }

        if ($node) {
            $node->process($message, $data);
        } elseif ($elseNode) {
            $elseNode->process($message, $data);
        }
    }

    public function process($message, $data)
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
        $header = $contact->changeVariables($settings['header'] ?? 'Upcoming events');
        $body = $contact->changeVariables($settings['body'] ?? 'Choose an event session to register.');
        $footer = $contact->changeVariables($settings['footer'] ?? '');
        $buttonText = $contact->changeVariables($settings['buttonText'] ?? 'View events');
        $limit = (int) ($settings['limit'] ?? 10);

        $options = app(EventCatalogService::class)->upcomingOccurrencesAsFlowOptions($company, $limit);

        if ($options === []) {
            $contact->sendMessage(__('No upcoming events are open for registration right now.'), false, false, 'TEXT');
            $next = $this->getNextNodeId('empty') ?: $this->getNextNodeId('else');

            if ($next) {
                $next->process($message, $data);
            }

            return ['success' => true];
        }

        $rows = [];

        foreach ($options as $option) {
            $rows[] = [
                'id' => 'occurrence-'.$option['id'].'_id'.$this->id.'_flow'.$this->flow_id,
                'title' => (string) $option['title'],
                'description' => (string) ($option['description'] ?? ''),
            ];
        }

        return $this->sendList($contact, $header, $body, $footer, $buttonText, $rows, $message, $data);
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
        array $rows,
        $message,
        $data
    ): array {
        $company = Company::find($contact->company_id);
        $token = $company?->getConfig('plain_token', '') ?? '';

        $payload = [
            'token' => $token,
            'phone' => $contact->phone,
            'message' => (string) $body,
            'header' => (string) $header,
            'footer' => (string) $footer,
            'action' => [
                'button' => (string) $buttonText,
                'sections' => [[
                    'title' => __('Events'),
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
        } catch (\Exception $e) {
            Log::error('Booking events list send failed', ['error' => $e->getMessage()]);
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
