<?php

namespace Modules\Flowmaker\Models\Nodes;

use App\Services\Flowmaker\FlowRunLogger;
use Illuminate\Support\Facades\Log;
use Modules\Flowmaker\Models\Contact;
use Modules\Journies\Models\JourneyStage;
use Modules\Journies\Services\JourneyContactService;

class OrderStatus extends Node
{
    public const STATUSES = [
        'confirmed',
        'preparing',
        'shipped',
        'delivered',
        'cancelled',
    ];

    public function process($message, $data)
    {
        try {
            $settings = $this->getDataAsArray()['settings'] ?? [];
            $status = $settings['status'] ?? 'confirmed';
            if (! in_array($status, self::STATUSES, true)) {
                $status = 'confirmed';
            }

            $template = $settings['message']
                ?? 'Your order status is now: {{order_status}}. Reference: {{order_reference}}';

            $contactId = is_object($data) ? $data->contact_id : $data['contact_id'];
            $contact = Contact::find($contactId);

            if (! $contact) {
                return ['success' => false];
            }

            $reference = $contact->getContactStateValue($this->flow_id, 'payment_invoice_id')
                ?: $contact->getContactStateValue($this->flow_id, 'catalog_order_reference')
                ?: $contact->getContactStateValue($this->flow_id, 'order_reference')
                ?: (string) $contact->id;

            $contact->setContactState($this->flow_id, 'order_status', $status);
            $contact->setContactState($this->flow_id, 'order_reference', (string) $reference);

            $body = $contact->changeVariables($template, $this->flow_id);
            $body = str_replace(
                ['{{order_status}}', '{{order_reference}}'],
                [$status, (string) $reference],
                $body
            );

            $contact->sendMessage($body, false, false, 'TEXT', null, null, null, true);

            $stageId = $settings['stageId'] ?? null;
            if (! empty($stageId) && $stageId !== 'none') {
                $stage = JourneyStage::query()
                    ->where('id', $stageId)
                    ->whereHas('journey', fn ($query) => $query->where('company_id', $contact->company_id))
                    ->first();

                if ($stage) {
                    app(JourneyContactService::class)->moveContactToStage(
                        $contact,
                        $stage,
                        'flow',
                        null,
                        true,
                        false,
                    );
                }
            }

            FlowRunLogger::log(
                (int) $this->flow_id,
                (int) $contact->id,
                'order_status_updated',
                (string) $this->getId(),
                $status
            );
        } catch (\Throwable $e) {
            Log::error('OrderStatus node failed', ['error' => $e->getMessage()]);

            return ['success' => false];
        }

        $nextNode = $this->getNextNodeId();
        if ($nextNode) {
            $nextNode->process($message, $data);
        }

        return ['success' => true];
    }

    protected function getNextNodeId($data = null)
    {
        if (! empty($this->outgoingEdges)) {
            return $this->outgoingEdges[0]->getTarget();
        }

        return null;
    }
}
